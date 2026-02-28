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
use local_dsl_isp\enrollment_manager;
use context_system;

/**
 * Web service to search users within a tenant for DSP assignment.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_users extends external_api {

    /**
     * Describes the parameters for search_users.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tenantid' => new external_value(PARAM_INT, 'The tenant ID'),
            'clientid' => new external_value(PARAM_INT, 'The client ID (to exclude already assigned DSPs)', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Search string'),
            'limit' => new external_value(PARAM_INT, 'Maximum number of results', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * Search for users within a tenant.
     *
     * @param int $tenantid The tenant ID.
     * @param int $clientid The client ID (to exclude already assigned DSPs).
     * @param string $search Search string.
     * @param int $limit Maximum number of results.
     * @return array The search results.
     */
    public static function execute(int $tenantid, int $clientid, string $search, int $limit = 20): array {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'tenantid' => $tenantid,
            'clientid' => $clientid,
            'search' => $search,
            'limit' => $limit,
        ]);

        // Check context and capability.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/dsl_isp:managedsps', $context);

        // Verify user is in the requested tenant.
        if (!feature_gate::user_in_tenant($USER->id, $params['tenantid'])) {
            throw new \moodle_exception('error_permissiondenied', 'local_dsl_isp');
        }

        // Verify feature is enabled.
        feature_gate::require_enabled($params['tenantid']);

        // Search users.
        $enrollmentmgr = new enrollment_manager();
        $users = $enrollmentmgr->search_tenant_users(
            $params['tenantid'],
            $params['search'],
            $params['clientid'],
            min($params['limit'], 50) // Cap at 50.
        );

        // Format for return.
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => (int) $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'fullname' => fullname($user),
                'email' => $user->email,
            ];
        }

        return [
            'users' => $results,
            'total' => count($results),
        ];
    }

    /**
     * Describes the return value for search_users.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'email' => new external_value(PARAM_EMAIL, 'Email address'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total count of results'),
        ]);
    }
}
