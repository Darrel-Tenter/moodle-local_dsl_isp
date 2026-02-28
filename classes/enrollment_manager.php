<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dsl_isp;

use stdClass;
use moodle_exception;
use context_system;
use core_external\external_api;

/**
 * Enrollment manager for ISP Manager.
 *
 * Handles DSP assignment to clients, course enrollment, and removal.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_manager {

    /**
     * Assign a DSP to a client.
     *
     * This creates the dsl_isp_dsp record and enrolls the user in the client's course.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @param int $assignedby The user ID of the admin making the assignment.
     * @return stdClass The created dsl_isp_dsp record.
     * @throws moodle_exception On failure.
     */
    public function assign_dsp(int $clientid, int $userid, int $assignedby): stdClass {
        global $DB;

        // Get the client.
        $client = $DB->get_record('dsl_isp_client', ['id' => $clientid], '*', MUST_EXIST);

        // Verify the user exists.
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new moodle_exception('error_dspnotfound', 'local_dsl_isp');
        }

        // Verify the user is in the same tenant.
        if (!feature_gate::user_in_tenant($userid, $client->tenantid)) {
            throw new moodle_exception('error_usernotintenant', 'local_dsl_isp');
        }

        // Check if already assigned (active assignment exists).
        if ($this->is_dsp_assigned($clientid, $userid)) {
            throw new moodle_exception('error_dspalreadyassigned', 'local_dsl_isp');
        }

        // Create the assignment record.
        $now = time();
        $dsp = new stdClass();
        $dsp->clientid = $clientid;
        $dsp->userid = $userid;
        $dsp->timeassigned = $now;
        $dsp->timeunassigned = null;
        $dsp->assignedby = $assignedby;
        $dsp->unassignedby = null;

        $dsp->id = $DB->insert_record('dsl_isp_dsp', $dsp);

        // Enroll in the course.
        $this->enrol_user_in_course($userid, $client->courseid);

        // Fire event.
        $event = \local_dsl_isp\event\dsp_assigned::create([
            'context' => context_system::instance(),
            'objectid' => $dsp->id,
            'relateduserid' => $userid,
            'other' => [
                'clientid' => $clientid,
                'assignedby' => $assignedby,
            ],
        ]);
        $event->trigger();

        return $dsp;
    }

    /**
     * Remove a DSP from a client.
     *
     * This soft-deletes the dsl_isp_dsp record (sets timeunassigned) and unenrolls the user.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @param int $unassignedby The user ID of the admin removing the assignment.
     * @return bool True on success.
     * @throws moodle_exception On failure.
     */
    public function remove_dsp(int $clientid, int $userid, int $unassignedby): bool {
        global $DB;

        // Get the client.
        $client = $DB->get_record('dsl_isp_client', ['id' => $clientid], '*', MUST_EXIST);

        // Get the active assignment.
        $dsp = $DB->get_record('dsl_isp_dsp', [
            'clientid' => $clientid,
            'userid' => $userid,
            'timeunassigned' => null,
        ]);

        if (!$dsp) {
            throw new moodle_exception('error_dspnotfound', 'local_dsl_isp');
        }

        // Soft-delete the assignment.
        $dsp->timeunassigned = time();
        $dsp->unassignedby = $unassignedby;
        $DB->update_record('dsl_isp_dsp', $dsp);

        // Unenroll from the course.
        $this->unenrol_user_from_course($userid, $client->courseid);

        // Fire event.
        $event = \local_dsl_isp\event\dsp_removed::create([
            'context' => context_system::instance(),
            'objectid' => $dsp->id,
            'relateduserid' => $userid,
            'other' => [
                'clientid' => $clientid,
                'unassignedby' => $unassignedby,
            ],
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Check if a DSP is currently assigned to a client.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @return bool True if assigned.
     */
    public function is_dsp_assigned(int $clientid, int $userid): bool {
        global $DB;

        return $DB->record_exists('dsl_isp_dsp', [
            'clientid' => $clientid,
            'userid' => $userid,
            'timeunassigned' => null,
        ]);
    }

    /**
     * Get all active DSPs for a client.
     *
     * @param int $clientid The client ID.
     * @return array Array of DSP records with user information.
     */
    public function get_client_dsps(int $clientid): array {
        global $DB;

        $sql = "SELECT d.*, u.firstname, u.lastname, u.email,
                       cc.timecompleted
                  FROM {dsl_isp_dsp} d
                  JOIN {user} u ON u.id = d.userid
                  JOIN {dsl_isp_client} c ON c.id = d.clientid
             LEFT JOIN {course_completions} cc ON cc.userid = d.userid AND cc.course = c.courseid
                 WHERE d.clientid = :clientid
                   AND d.timeunassigned IS NULL
                   AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, ['clientid' => $clientid]);
    }

    /**
     * Get all clients assigned to a DSP.
     *
     * @param int $userid The DSP user ID.
     * @param int $tenantid The tenant ID to scope results.
     * @return array Array of client records.
     */
    public function get_dsp_clients(int $userid, int $tenantid): array {
        global $DB;

        $sql = "SELECT c.*, d.timeassigned
                  FROM {dsl_isp_dsp} d
                  JOIN {dsl_isp_client} c ON c.id = d.clientid
                 WHERE d.userid = :userid
                   AND d.timeunassigned IS NULL
                   AND c.tenantid = :tenantid
                   AND c.status = 1
              ORDER BY c.lastname ASC, c.firstname ASC";

        return $DB->get_records_sql($sql, [
            'userid' => $userid,
            'tenantid' => $tenantid,
        ]);
    }

    /**
     * Get DSP assignment history for a client.
     *
     * @param int $clientid The client ID.
     * @param bool $includeactive Whether to include active assignments.
     * @return array Array of DSP assignment records.
     */
    public function get_assignment_history(int $clientid, bool $includeactive = true): array {
        global $DB;

        $params = ['clientid' => $clientid];
        $where = 'd.clientid = :clientid';

        if (!$includeactive) {
            $where .= ' AND d.timeunassigned IS NOT NULL';
        }

        $sql = "SELECT d.*, u.firstname, u.lastname, u.email,
                       ua.firstname AS assignedbyfirstname, ua.lastname AS assignedbylastname,
                       uu.firstname AS unassignedbyfirstname, uu.lastname AS unassignedbylastname
                  FROM {dsl_isp_dsp} d
                  JOIN {user} u ON u.id = d.userid
             LEFT JOIN {user} ua ON ua.id = d.assignedby
             LEFT JOIN {user} uu ON uu.id = d.unassignedby
                 WHERE {$where}
              ORDER BY d.timeassigned DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Search for users within a tenant that can be assigned as DSPs.
     *
     * @param int $tenantid The tenant ID.
     * @param string $search Search string.
     * @param int $clientid The client ID (to exclude already-assigned DSPs).
     * @param int $limit Maximum results to return.
     * @return array Array of user records.
     */
    public function search_tenant_users(int $tenantid, string $search, int $clientid = 0, int $limit = 20): array {
        global $DB;

        $params = ['tenantid' => $tenantid];
        $where = ['tu.tenantid = :tenantid', 'u.deleted = 0', 'u.suspended = 0'];

        if (!empty($search)) {
            $where[] = $DB->sql_like("CONCAT(u.firstname, ' ', u.lastname)", ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        // Exclude already-assigned DSPs if a client is specified.
        if ($clientid > 0) {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM {dsl_isp_dsp} d
                 WHERE d.clientid = :clientid
                   AND d.userid = u.id
                   AND d.timeunassigned IS NULL
            )";
            $params['clientid'] = $clientid;
        }

        $whereclause = implode(' AND ', $where);

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                  FROM {user} u
                  JOIN {tool_tenant_user} tu ON tu.userid = u.id
                 WHERE {$whereclause}
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Enroll a user in a course with the student role.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @throws moodle_exception On failure.
     */
    protected function enrol_user_in_course(int $userid, int $courseid): void {
        $roleid = $this->get_student_role_id();

        $enrolment = [
            'roleid' => $roleid,
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        $result = external_api::call_external_function(
            'enrol_manual_enrol_users',
            ['enrolments' => [$enrolment]],
            false
        );

        if (!empty($result['error'])) {
            throw new moodle_exception('error_enrollmentfailed', 'local_dsl_isp', '', null, $result['exception']->message ?? '');
        }
    }

    /**
     * Unenroll a user from a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @throws moodle_exception On failure.
     */
    protected function unenrol_user_from_course(int $userid, int $courseid): void {
        $enrolment = [
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        $result = external_api::call_external_function(
            'enrol_manual_unenrol_users',
            ['enrolments' => [$enrolment]],
            false
        );

        if (!empty($result['error'])) {
            throw new moodle_exception('error_unenrollmentfailed', 'local_dsl_isp', '', null, $result['exception']->message ?? '');
        }
    }

    /**
     * Get the student role ID.
     *
     * @return int The role ID.
     */
    protected function get_student_role_id(): int {
        $configid = get_config('local_dsl_isp', 'student_role_id');

        if (!empty($configid)) {
            return (int) $configid;
        }

        // Fall back to standard student role.
        global $DB;
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');

        return $studentrole ? (int) $studentrole->id : 5; // 5 is typical default.
    }

    /**
     * Get count of active DSPs for a client.
     *
     * @param int $clientid The client ID.
     * @return int The count.
     */
    public function get_dsp_count(int $clientid): int {
        global $DB;

        return $DB->count_records('dsl_isp_dsp', [
            'clientid' => $clientid,
            'timeunassigned' => null,
        ]);
    }

    /**
     * Get count of DSPs who have completed the ISP course for a client.
     *
     * @param int $clientid The client ID.
     * @return int The count.
     */
    public function get_completed_dsp_count(int $clientid): int {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT d.userid)
                  FROM {dsl_isp_dsp} d
                  JOIN {dsl_isp_client} c ON c.id = d.clientid
                  JOIN {course_completions} cc ON cc.userid = d.userid AND cc.course = c.courseid
                 WHERE d.clientid = :clientid
                   AND d.timeunassigned IS NULL
                   AND cc.timecompleted IS NOT NULL";

        return (int) $DB->count_records_sql($sql, ['clientid' => $clientid]);
    }
}
