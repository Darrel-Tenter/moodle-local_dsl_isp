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
use context_system;

/**
 * Web service to get clients for a tenant.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_clients extends external_api {

    /**
     * Describes the parameters for get_clients.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tenantid' => new external_value(PARAM_INT, 'The tenant ID'),
            'status' => new external_value(PARAM_INT, 'Status filter (1=active, 0=archived, -1=all)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Get clients for a tenant.
     *
     * @param int $tenantid The tenant ID.
     * @param int $status Status filter.
     * @return array The clients data.
     */
    public static function execute(int $tenantid, int $status = 1): array {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'tenantid' => $tenantid,
            'status' => $status,
        ]);

        // Check context and capability.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dsl_isp:view', $context);

        // Verify user is in the requested tenant.
        if (!feature_gate::user_in_tenant($USER->id, $params['tenantid'])) {
            throw new \moodle_exception('error_permissiondenied', 'local_dsl_isp');
        }

        // Verify feature is enabled.
        feature_gate::require_enabled($params['tenantid']);

        // Get clients.
        $mgr = new manager($params['tenantid']);
        $result = $mgr->get_clients($params['status'], '', '', '', 0, 1000);

        // Format for return.
        $clients = [];
        foreach ($result['clients'] as $client) {
            $boundaries = $mgr->get_plan_year_boundaries($client->anniversarydate);

            $clients[] = [
                'id' => (int) $client->id,
                'firstname' => $client->firstname,
                'lastname' => $client->lastname,
                'servicetype' => $client->servicetype,
                'anniversarydate' => (int) $client->anniversarydate,
                'courseid' => (int) $client->courseid,
                'status' => (int) $client->status,
                'dsp_count' => (int) ($client->dsp_count ?? 0),
                'completed_count' => (int) ($client->completed_count ?? 0),
                'planyearstart' => (int) $boundaries['start'],
                'planyearend' => (int) $boundaries['end'],
            ];
        }

        return [
            'clients' => $clients,
            'total' => $result['total'],
        ];
    }

    /**
     * Describes the return value for get_clients.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'clients' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Client ID'),
                    'firstname' => new external_value(PARAM_TEXT, 'Client first name'),
                    'lastname' => new external_value(PARAM_TEXT, 'Client last name'),
                    'servicetype' => new external_value(PARAM_ALPHANUMEXT, 'Service type'),
                    'anniversarydate' => new external_value(PARAM_INT, 'Anniversary date timestamp'),
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'status' => new external_value(PARAM_INT, 'Status (1=active, 0=archived)'),
                    'dsp_count' => new external_value(PARAM_INT, 'Total DSP count'),
                    'completed_count' => new external_value(PARAM_INT, 'Completed DSP count'),
                    'planyearstart' => new external_value(PARAM_INT, 'Current plan year start timestamp'),
                    'planyearend' => new external_value(PARAM_INT, 'Current plan year end timestamp'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total count of clients'),
        ]);
    }
}
