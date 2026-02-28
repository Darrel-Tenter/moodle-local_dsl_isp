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

/**
 * Web service definitions for local_dsl_isp.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // Get clients for a tenant.
    'local_dsl_isp_get_clients' => [
        'classname' => 'local_dsl_isp\external\get_clients',
        'methodname' => 'execute',
        'description' => 'Get all ISP clients for a tenant with completion summary.',
        'type' => 'read',
        'capabilities' => 'local/dsl_isp:view',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Get completion log for a client.
    'local_dsl_isp_get_completion_log' => [
        'classname' => 'local_dsl_isp\external\get_completion_log',
        'methodname' => 'execute',
        'description' => 'Get historical completion records for a client.',
        'type' => 'read',
        'capabilities' => 'local/dsl_isp:viewhistory',
        'ajax' => false,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Search users for DSP assignment.
    'local_dsl_isp_search_users' => [
        'classname' => 'local_dsl_isp\external\search_users',
        'methodname' => 'execute',
        'description' => 'Search for users within a tenant for DSP assignment.',
        'type' => 'read',
        'capabilities' => 'local/dsl_isp:managedsps',
        'ajax' => true,
        'services' => [],
    ],
];

$services = [
    'ISP Manager Services' => [
        'functions' => [
            'local_dsl_isp_get_clients',
            'local_dsl_isp_get_completion_log',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_dsl_isp',
    ],
];
