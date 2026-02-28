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
 * Completion manager for ISP Manager.
 *
 * Handles completion archival, reset operations, and historical log management.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_manager {

    /**
     * Archive current completion and reset for a single DSP on a client.
     *
     * This is the core operation used by both annual renewal and manual reset.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @param int $planyearstart The plan year start timestamp.
     * @param int $planyearend The plan year end timestamp.
     * @param string|null $notes Optional notes (e.g., 'manual_reset').
     * @return bool True on success.
     * @throws moodle_exception On failure.
     */
    public function archive_and_reset(
        int $clientid,
        int $userid,
        int $planyearstart,
        int $planyearend,
        ?string $notes = null
    ): bool {
        global $DB;

        // Get the client.
        $client = $DB->get_record('dsl_isp_client', ['id' => $clientid], '*', MUST_EXIST);

        // Check for idempotency - don't create duplicate log entries.
        $existing = $DB->get_record('dsl_isp_completion_log', [
            'clientid' => $clientid,
            'userid' => $userid,
            'planyearstart' => $planyearstart,
        ]);

        if ($existing) {
            // Already processed for this plan year.
            return true;
        }

        // Get current completion status.
        $completion = $DB->get_record('course_completions', [
            'course' => $client->courseid,
            'userid' => $userid,
        ]);

        // Create the archive record.
        $log = new stdClass();
        $log->clientid = $clientid;
        $log->userid = $userid;
        $log->planyearstart = $planyearstart;
        $log->planyearend = $planyearend;
        $log->timecompleted = $completion ? $completion->timecompleted : null;
        $log->timearchived = time();
        $log->notes = $notes;

        $DB->insert_record('dsl_isp_completion_log', $log);

        // Reset completion using local_recompletion.
        $this->reset_course_completion($client->courseid, $userid);

        return true;
    }

    /**
     * Manually reset completion for a DSP.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @param int $resetby The user ID of the admin performing the reset.
     * @return bool True on success.
     * @throws moodle_exception On failure.
     */
    public function manual_reset(int $clientid, int $userid, int $resetby): bool {
        global $DB, $USER;

        // Get the client.
        $client = $DB->get_record('dsl_isp_client', ['id' => $clientid], '*', MUST_EXIST);

        // Calculate current plan year boundaries.
        $manager = new manager($client->tenantid);
        $boundaries = $manager->get_plan_year_boundaries($client->anniversarydate);

        // Archive and reset with manual_reset note.
        $this->archive_and_reset(
            $clientid,
            $userid,
            $boundaries['start'],
            time(), // End is now for manual resets.
            'manual_reset'
        );

        // Fire event.
        $event = \local_dsl_isp\event\completion_manually_reset::create([
            'context' => context_system::instance(),
            'objectid' => $clientid,
            'relateduserid' => $userid,
            'other' => [
                'clientid' => $clientid,
                'resetby' => $resetby,
            ],
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Get completion log entries for a client.
     *
     * @param int $clientid The client ID.
     * @param int|null $userid Optional DSP user ID filter.
     * @param int|null $planyearstart Optional plan year start filter.
     * @return array Array of log records with user information.
     */
    public function get_completion_log(int $clientid, ?int $userid = null, ?int $planyearstart = null): array {
        global $DB;

        $params = ['clientid' => $clientid];
        $where = ['cl.clientid = :clientid'];

        if ($userid !== null) {
            $where[] = 'cl.userid = :userid';
            $params['userid'] = $userid;
        }

        if ($planyearstart !== null) {
            $where[] = 'cl.planyearstart = :planyearstart';
            $params['planyearstart'] = $planyearstart;
        }

        $whereclause = implode(' AND ', $where);

        $sql = "SELECT cl.*, u.firstname, u.lastname, u.email
                  FROM {dsl_isp_completion_log} cl
                  JOIN {user} u ON u.id = cl.userid
                 WHERE {$whereclause}
              ORDER BY cl.planyearstart DESC, u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get completion log for a specific DSP across all clients.
     *
     * @param int $userid The DSP user ID.
     * @param int $tenantid The tenant ID to scope results.
     * @return array Array of log records with client information.
     */
    public function get_dsp_completion_history(int $userid, int $tenantid): array {
        global $DB;

        $sql = "SELECT cl.*, c.firstname AS clientfirstname, c.lastname AS clientlastname,
                       c.servicetype
                  FROM {dsl_isp_completion_log} cl
                  JOIN {dsl_isp_client} c ON c.id = cl.clientid
                 WHERE cl.userid = :userid
                   AND c.tenantid = :tenantid
              ORDER BY cl.planyearstart DESC, c.lastname ASC";

        return $DB->get_records_sql($sql, [
            'userid' => $userid,
            'tenantid' => $tenantid,
        ]);
    }

    /**
     * Get current completion status for a DSP on a client.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @return stdClass|null Completion record or null.
     */
    public function get_current_completion(int $clientid, int $userid): ?stdClass {
        global $DB;

        $sql = "SELECT cc.*
                  FROM {course_completions} cc
                  JOIN {dsl_isp_client} c ON c.courseid = cc.course
                 WHERE c.id = :clientid
                   AND cc.userid = :userid";

        $completion = $DB->get_record_sql($sql, [
            'clientid' => $clientid,
            'userid' => $userid,
        ]);

        return $completion ?: null;
    }

    /**
     * Get completion gaps for a client (DSPs who didn't complete in a plan year).
     *
     * @param int $clientid The client ID.
     * @return array Array of log records where timecompleted is null.
     */
    public function get_completion_gaps(int $clientid): array {
        global $DB;

        $sql = "SELECT cl.*, u.firstname, u.lastname, u.email
                  FROM {dsl_isp_completion_log} cl
                  JOIN {user} u ON u.id = cl.userid
                 WHERE cl.clientid = :clientid
                   AND cl.timecompleted IS NULL
              ORDER BY cl.planyearstart DESC, u.lastname ASC";

        return $DB->get_records_sql($sql, ['clientid' => $clientid]);
    }

    /**
     * Reset course completion using local_recompletion.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @throws moodle_exception On failure.
     */
    protected function reset_course_completion(int $courseid, int $userid): void {
        // Try using the web service first.
        try {
            $result = external_api::call_external_function(
                'local_recompletion_reset_course',
                [
                    'courseid' => $courseid,
                    'userid' => $userid,
                ],
                false
            );

            if (empty($result['error'])) {
                return;
            }
        } catch (\Exception $e) {
            // Web service not available, try direct function call.
        }

        // Fall back to direct function call if available.
        if (function_exists('local_recompletion_reset_course')) {
            local_recompletion_reset_course($courseid, $userid);
            return;
        }

        // If neither method works, throw an exception.
        throw new moodle_exception('error_completionresetfailed', 'local_dsl_isp');
    }

    /**
     * Check if a log entry already exists for a client/user/plan year combination.
     *
     * @param int $clientid The client ID.
     * @param int $userid The DSP user ID.
     * @param int $planyearstart The plan year start timestamp.
     * @return bool True if exists.
     */
    public function log_entry_exists(int $clientid, int $userid, int $planyearstart): bool {
        global $DB;

        return $DB->record_exists('dsl_isp_completion_log', [
            'clientid' => $clientid,
            'userid' => $userid,
            'planyearstart' => $planyearstart,
        ]);
    }

    /**
     * Get summary statistics for a client's completion history.
     *
     * @param int $clientid The client ID.
     * @return stdClass Object with total, completed, and gap counts.
     */
    public function get_completion_stats(int $clientid): stdClass {
        global $DB;

        $stats = new stdClass();

        $stats->total = $DB->count_records('dsl_isp_completion_log', ['clientid' => $clientid]);

        $stats->completed = $DB->count_records_select(
            'dsl_isp_completion_log',
            'clientid = :clientid AND timecompleted IS NOT NULL',
            ['clientid' => $clientid]
        );

        $stats->gaps = $DB->count_records_select(
            'dsl_isp_completion_log',
            'clientid = :clientid AND timecompleted IS NULL',
            ['clientid' => $clientid]
        );

        return $stats;
    }

    /**
     * Get all plan years for a client.
     *
     * @param int $clientid The client ID.
     * @return array Array of unique plan year start timestamps.
     */
    public function get_client_plan_years(int $clientid): array {
        global $DB;

        $sql = "SELECT DISTINCT planyearstart
                  FROM {dsl_isp_completion_log}
                 WHERE clientid = :clientid
              ORDER BY planyearstart DESC";

        return $DB->get_fieldset_sql($sql, ['clientid' => $clientid]);
    }
}
