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

namespace local_dsl_isp\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_dsl_isp\feature_gate;
use local_dsl_isp\manager;
use local_dsl_isp\completion_manager;
use context_system;

/**
 * Web service to get completion log for a client.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_completion_log extends external_api {

    /**
     * Describes the parameters for get_completion_log.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'clientid' => new external_value(PARAM_INT, 'The client ID'),
            'userid' => new external_value(PARAM_INT, 'Optional DSP user ID filter', VALUE_DEFAULT, 0),
            'planyearstart' => new external_value(PARAM_INT, 'Optional plan year start filter', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get completion log for a client.
     *
     * @param int $clientid The client ID.
     * @param int $userid Optional DSP user ID filter.
     * @param int $planyearstart Optional plan year start filter.
     * @return array The completion log data.
     */
    public static function execute(int $clientid, int $userid = 0, int $planyearstart = 0): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'clientid' => $clientid,
            'userid' => $userid,
            'planyearstart' => $planyearstart,
        ]);

        // Check context and capability.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dsl_isp:viewhistory', $context);

        // Get client and verify tenant access.
        $client = $DB->get_record('dsl_isp_client', ['id' => $params['clientid']], '*', MUST_EXIST);

        if (!feature_gate::user_in_tenant($USER->id, $client->tenantid)) {
            throw new \moodle_exception('error_permissiondenied', 'local_dsl_isp');
        }

        // Verify feature is enabled.
        feature_gate::require_enabled($client->tenantid);

        // Get completion log.
        $completionmgr = new completion_manager();
        $log = $completionmgr->get_completion_log(
            $params['clientid'],
            $params['userid'] ?: null,
            $params['planyearstart'] ?: null
        );

        // Format for return.
        $records = [];
        foreach ($log as $entry) {
            $records[] = [
                'id' => (int) $entry->id,
                'clientid' => (int) $entry->clientid,
                'userid' => (int) $entry->userid,
                'userfullname' => $entry->firstname . ' ' . $entry->lastname,
                'useremail' => $entry->email,
                'planyearstart' => (int) $entry->planyearstart,
                'planyearend' => (int) $entry->planyearend,
                'timecompleted' => $entry->timecompleted ? (int) $entry->timecompleted : null,
                'timearchived' => (int) $entry->timearchived,
                'notes' => $entry->notes ?? '',
                'iscompleted' => !empty($entry->timecompleted),
            ];
        }

        return [
            'records' => $records,
            'total' => count($records),
        ];
    }

    /**
     * Describes the return value for get_completion_log.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'records' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Log entry ID'),
                    'clientid' => new external_value(PARAM_INT, 'Client ID'),
                    'userid' => new external_value(PARAM_INT, 'DSP user ID'),
                    'userfullname' => new external_value(PARAM_TEXT, 'DSP full name'),
                    'useremail' => new external_value(PARAM_EMAIL, 'DSP email'),
                    'planyearstart' => new external_value(PARAM_INT, 'Plan year start timestamp'),
                    'planyearend' => new external_value(PARAM_INT, 'Plan year end timestamp'),
                    'timecompleted' => new external_value(PARAM_INT, 'Completion timestamp or null', VALUE_OPTIONAL),
                    'timearchived' => new external_value(PARAM_INT, 'Archive timestamp'),
                    'notes' => new external_value(PARAM_TEXT, 'Notes'),
                    'iscompleted' => new external_value(PARAM_BOOL, 'Whether DSP completed this cycle'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total count of records'),
        ]);
    }
}
