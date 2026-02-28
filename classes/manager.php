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

/**
 * Core manager class for ISP Manager business logic.
 *
 * Orchestrates client creation, updates, archival, and data retrieval.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var int Status value for active clients. */
    public const STATUS_ACTIVE = 1;

    /** @var int Status value for archived clients. */
    public const STATUS_ARCHIVED = 0;

    /** @var array Valid service types. */
    public const SERVICE_TYPES = [
        'residential',
        'attendant_care',
        'dsa',
        'foster_care',
        'other',
    ];

    /** @var int The tenant ID this manager instance operates within. */
    protected int $tenantid;

    /**
     * Constructor.
     *
     * @param int $tenantid The tenant ID to operate within.
     */
    public function __construct(int $tenantid) {
        $this->tenantid = $tenantid;
    }

    /**
     * Create a new client with associated ISP course and DSP assignments.
     *
     * This method executes the full "Add New Client" workflow:
     * 1. Validate inputs
     * 2. Duplicate template course
     * 3. Update course settings
     * 4. Replace document placeholders
     * 5. Insert client record
     * 6. Assign DSPs
     *
     * @param stdClass $data Form data containing client information.
     * @param array $documents Array of document data with files.
     * @param array $dspuserids Array of user IDs to assign as DSPs.
     * @return stdClass The created client record.
     * @throws moodle_exception On validation or creation failure.
     */
    public function create_client(stdClass $data, array $documents, array $dspuserids = []): stdClass {
        global $DB, $USER;

        // Validate service type.
        if (!in_array($data->servicetype, self::SERVICE_TYPES, true)) {
            throw new moodle_exception('error_servicetyperequired', 'local_dsl_isp');
        }

        // Validate anniversary date is not in the future.
        if ($data->anniversarydate > time()) {
            throw new moodle_exception('error_anniversarydatefuture', 'local_dsl_isp');
        }

        // Check for duplicate client name within tenant.
        if ($this->client_name_exists($data->firstname, $data->lastname)) {
            throw new moodle_exception('error_clientnameexists', 'local_dsl_isp');
        }

        // Get the course builder.
        $coursebuilder = new course_builder();

        // Start transaction.
        $transaction = $DB->start_delegated_transaction();

        try {
            // Create the ISP course from template.
            $coursename = $this->build_course_name($data->firstname, $data->lastname, $data->servicetype);
            $courseid = $coursebuilder->create_course_from_template(
                $coursename,
                $data->anniversarydate
            );

            // Replace document placeholders.
            foreach ($documents as $slotindex => $docdata) {
                if (!empty($docdata['file'])) {
                    $coursebuilder->replace_document(
                        $courseid,
                        $slotindex,
                        $docdata['file'],
                        $docdata['name'],
                        $docdata['date']
                    );
                }
            }

            // Insert client record.
            $now = time();
            $client = new stdClass();
            $client->tenantid = $this->tenantid;
            $client->courseid = $courseid;
            $client->firstname = $data->firstname;
            $client->lastname = $data->lastname;
            $client->servicetype = $data->servicetype;
            $client->anniversarydate = $data->anniversarydate;
            $client->status = self::STATUS_ACTIVE;
            $client->timecreated = $now;
            $client->timemodified = $now;
            $client->usermodified = $USER->id;

            $client->id = $DB->insert_record('dsl_isp_client', $client);

            // Assign DSPs.
            $enrollmentmanager = new enrollment_manager();
            foreach ($dspuserids as $dspuserid) {
                $enrollmentmanager->assign_dsp($client->id, $dspuserid, $USER->id);
            }

            // Fire event.
            $event = \local_dsl_isp\event\client_created::create([
                'context' => context_system::instance(),
                'objectid' => $client->id,
                'other' => [
                    'tenantid' => $this->tenantid,
                    'courseid' => $courseid,
                    'clientname' => $data->firstname . ' ' . $data->lastname,
                ],
            ]);
            $event->trigger();

            $transaction->allow_commit();

            return $client;

        } catch (\Exception $e) {
            $transaction->rollback($e);

            // Attempt to clean up partially created course.
            if (!empty($courseid)) {
                $coursebuilder->delete_course($courseid);
            }

            throw $e;
        }
    }

    /**
     * Update an existing client's information.
     *
     * @param int $clientid The client ID to update.
     * @param stdClass $data Updated client data.
     * @return stdClass The updated client record.
     * @throws moodle_exception On validation or update failure.
     */
    public function update_client(int $clientid, stdClass $data): stdClass {
        global $DB, $USER;

        $client = $this->get_client($clientid);

        // Validate service type if provided.
        if (!empty($data->servicetype) && !in_array($data->servicetype, self::SERVICE_TYPES, true)) {
            throw new moodle_exception('error_servicetyperequired', 'local_dsl_isp');
        }

        // Check for duplicate name if name is changing.
        $namechanged = (
            (!empty($data->firstname) && $data->firstname !== $client->firstname) ||
            (!empty($data->lastname) && $data->lastname !== $client->lastname)
        );

        if ($namechanged) {
            $newfirst = $data->firstname ?? $client->firstname;
            $newlast = $data->lastname ?? $client->lastname;
            if ($this->client_name_exists($newfirst, $newlast, $clientid)) {
                throw new moodle_exception('error_clientnameexists', 'local_dsl_isp');
            }
        }

        // Track changed fields for event.
        $changedfields = [];

        // Update allowed fields.
        $allowedfields = ['firstname', 'lastname', 'servicetype', 'anniversarydate'];
        foreach ($allowedfields as $field) {
            if (isset($data->$field) && $data->$field !== $client->$field) {
                $changedfields[$field] = [
                    'old' => $client->$field,
                    'new' => $data->$field,
                ];
                $client->$field = $data->$field;
            }
        }

        if (empty($changedfields)) {
            // Nothing changed.
            return $client;
        }

        $client->timemodified = time();
        $client->usermodified = $USER->id;

        $DB->update_record('dsl_isp_client', $client);

        // Update course name if client name changed.
        if (isset($changedfields['firstname']) || isset($changedfields['lastname']) ||
            isset($changedfields['servicetype'])) {
            $coursebuilder = new course_builder();
            $newname = $this->build_course_name($client->firstname, $client->lastname, $client->servicetype);
            $coursebuilder->update_course_name($client->courseid, $newname);
        }

        // Fire event.
        $event = \local_dsl_isp\event\client_updated::create([
            'context' => context_system::instance(),
            'objectid' => $client->id,
            'other' => [
                'tenantid' => $this->tenantid,
                'changedfields' => $changedfields,
            ],
        ]);
        $event->trigger();

        return $client;
    }

    /**
     * Archive a client.
     *
     * @param int $clientid The client ID to archive.
     * @return bool True on success.
     * @throws moodle_exception If client not found.
     */
    public function archive_client(int $clientid): bool {
        global $DB, $USER;

        $client = $this->get_client($clientid);

        $client->status = self::STATUS_ARCHIVED;
        $client->timemodified = time();
        $client->usermodified = $USER->id;

        $DB->update_record('dsl_isp_client', $client);

        // Fire event.
        $event = \local_dsl_isp\event\client_archived::create([
            'context' => context_system::instance(),
            'objectid' => $client->id,
            'other' => [
                'tenantid' => $this->tenantid,
                'clientname' => $client->firstname . ' ' . $client->lastname,
            ],
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Unarchive a client.
     *
     * @param int $clientid The client ID to unarchive.
     * @return bool True on success.
     * @throws moodle_exception If client not found.
     */
    public function unarchive_client(int $clientid): bool {
        global $DB, $USER;

        $client = $this->get_client($clientid, false);

        $client->status = self::STATUS_ACTIVE;
        $client->timemodified = time();
        $client->usermodified = $USER->id;

        $DB->update_record('dsl_isp_client', $client);

        return true;
    }

    /**
     * Get a single client by ID.
     *
     * @param int $clientid The client ID.
     * @param bool $activeonly If true, only return active clients.
     * @return stdClass The client record.
     * @throws moodle_exception If client not found or not in tenant.
     */
    public function get_client(int $clientid, bool $activeonly = true): stdClass {
        global $DB;

        $params = [
            'id' => $clientid,
            'tenantid' => $this->tenantid,
        ];

        if ($activeonly) {
            $params['status'] = self::STATUS_ACTIVE;
        }

        $client = $DB->get_record('dsl_isp_client', $params);

        if (!$client) {
            throw new moodle_exception('error_clientnotfound', 'local_dsl_isp');
        }

        return $client;
    }

    /**
     * Get a client by course ID.
     *
     * @param int $courseid The course ID.
     * @return stdClass|null The client record or null if not found.
     */
    public function get_client_by_course(int $courseid): ?stdClass {
        global $DB;

        $client = $DB->get_record('dsl_isp_client', [
            'courseid' => $courseid,
            'tenantid' => $this->tenantid,
        ]);

        return $client ?: null;
    }

    /**
     * Get all clients for the tenant with optional filtering.
     *
     * @param int $status Status filter (1=active, 0=archived, -1=all).
     * @param string $search Search string for name matching.
     * @param string $servicetype Service type filter.
     * @param string $completionstatus Completion status filter (complete/inprogress/overdue/all).
     * @param int $page Page number (0-indexed).
     * @param int $perpage Items per page.
     * @return array Array with 'clients' and 'total' keys.
     */
    public function get_clients(
        int $status = self::STATUS_ACTIVE,
        string $search = '',
        string $servicetype = '',
        string $completionstatus = '',
        int $page = 0,
        int $perpage = 50
    ): array {
        global $DB;

        $params = ['tenantid' => $this->tenantid];
        $where = ['c.tenantid = :tenantid'];

        if ($status >= 0) {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }

        if (!empty($search)) {
            $where[] = $DB->sql_like('CONCAT(c.firstname, \' \', c.lastname)', ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        if (!empty($servicetype) && in_array($servicetype, self::SERVICE_TYPES, true)) {
            $where[] = 'c.servicetype = :servicetype';
            $params['servicetype'] = $servicetype;
        }

        $whereclause = implode(' AND ', $where);

        // Get total count.
        $countsql = "SELECT COUNT(c.id) FROM {dsl_isp_client} c WHERE {$whereclause}";
        $total = $DB->count_records_sql($countsql, $params);

        // Get paginated results with DSP completion stats.
        $sql = "SELECT c.*,
                       (SELECT COUNT(d.id)
                          FROM {dsl_isp_dsp} d
                         WHERE d.clientid = c.id AND d.timeunassigned IS NULL) AS dsp_count,
                       (SELECT COUNT(DISTINCT cc.userid)
                          FROM {dsl_isp_dsp} d
                          JOIN {course_completions} cc ON cc.userid = d.userid AND cc.course = c.courseid
                         WHERE d.clientid = c.id
                           AND d.timeunassigned IS NULL
                           AND cc.timecompleted IS NOT NULL) AS completed_count
                  FROM {dsl_isp_client} c
                 WHERE {$whereclause}
              ORDER BY c.lastname ASC, c.firstname ASC";

        $clients = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        // Apply completion status filter in PHP (more complex to do in SQL efficiently).
        if (!empty($completionstatus) && $completionstatus !== 'all') {
            $clients = array_filter($clients, function($client) use ($completionstatus) {
                $status = $this->calculate_completion_status($client);
                return $status === $completionstatus;
            });
        }

        return [
            'clients' => array_values($clients),
            'total' => $total,
        ];
    }

    /**
     * Calculate the completion status for a client.
     *
     * @param stdClass $client Client record with dsp_count and completed_count.
     * @return string Status string: 'complete', 'inprogress', 'overdue', 'notstarted'.
     */
    public function calculate_completion_status(stdClass $client): string {
        if (!isset($client->dsp_count) || $client->dsp_count == 0) {
            return 'notstarted';
        }

        $completedcount = $client->completed_count ?? 0;

        if ($completedcount >= $client->dsp_count) {
            return 'complete';
        }

        // Check if overdue (past anniversary date in current year without full completion).
        $currentyearanniversary = $this->get_current_year_anniversary($client->anniversarydate);
        if ($currentyearanniversary < time() && $completedcount < $client->dsp_count) {
            return 'overdue';
        }

        return 'inprogress';
    }

    /**
     * Get the current plan year boundaries for a client.
     *
     * @param int $anniversarydate The original anniversary timestamp.
     * @return array Array with 'start' and 'end' timestamps.
     */
    public function get_plan_year_boundaries(int $anniversarydate): array {
        $currentyearanniversary = $this->get_current_year_anniversary($anniversarydate);

        // If today is before this year's anniversary, the current plan year started last year.
        if ($currentyearanniversary > time()) {
            $start = strtotime('-1 year', $currentyearanniversary);
            $end = $currentyearanniversary;
        } else {
            $start = $currentyearanniversary;
            $end = strtotime('+1 year', $currentyearanniversary);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Get the anniversary date adjusted to the current year.
     *
     * @param int $anniversarydate The original anniversary timestamp.
     * @return int The anniversary timestamp in the current year.
     */
    protected function get_current_year_anniversary(int $anniversarydate): int {
        $month = (int) date('n', $anniversarydate);
        $day = (int) date('j', $anniversarydate);
        $currentyear = (int) date('Y');

        return mktime(0, 0, 0, $month, $day, $currentyear);
    }

    /**
     * Check if a client name already exists within the tenant.
     *
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @param int|null $excludeclientid Client ID to exclude (for updates).
     * @return bool True if name exists.
     */
    protected function client_name_exists(string $firstname, string $lastname, ?int $excludeclientid = null): bool {
        global $DB;

        $params = [
            'tenantid' => $this->tenantid,
            'firstname' => $firstname,
            'lastname' => $lastname,
        ];

        $sql = "SELECT id FROM {dsl_isp_client}
                 WHERE tenantid = :tenantid
                   AND " . $DB->sql_compare_text('firstname') . " = " . $DB->sql_compare_text(':firstname') . "
                   AND " . $DB->sql_compare_text('lastname') . " = " . $DB->sql_compare_text(':lastname');

        if ($excludeclientid) {
            $sql .= " AND id != :excludeid";
            $params['excludeid'] = $excludeclientid;
        }

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Build the standard course name for a client.
     *
     * @param string $firstname Client first name.
     * @param string $lastname Client last name.
     * @param string $servicetype Service type.
     * @return string The course name.
     */
    protected function build_course_name(string $firstname, string $lastname, string $servicetype): string {
        $servicelabel = get_string('servicetype_' . $servicetype, 'local_dsl_isp');
        return "{$firstname} {$lastname} ISP ({$servicelabel})";
    }

    /**
     * Get service type options for form select.
     *
     * @return array Associative array of service type => label.
     */
    public static function get_service_type_options(): array {
        $options = [];
        foreach (self::SERVICE_TYPES as $type) {
            $options[$type] = get_string('servicetype_' . $type, 'local_dsl_isp');
        }
        return $options;
    }
}
