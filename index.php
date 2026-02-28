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
 * Client list page for ISP Manager.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_dsl_isp\feature_gate;
use local_dsl_isp\manager;
use local_dsl_isp\output\client_list;

// Get parameters.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$servicetype = optional_param('servicetype', '', PARAM_ALPHANUMEXT);
$completionstatus = optional_param('completionstatus', '', PARAM_ALPHA);

// Authentication and authorization.
require_login();

$context = context_system::instance();
require_capability('local/dsl_isp:view', $context);

// Get tenant ID and verify feature is enabled.
$tenantid = feature_gate::get_current_tenant_id();
if (empty($tenantid)) {
    throw new moodle_exception('error_tenantnotfound', 'local_dsl_isp');
}
feature_gate::require_enabled($tenantid);

// Page setup.
$pageurl = new moodle_url('/local/dsl_isp/index.php', [
    'search' => $search,
    'servicetype' => $servicetype,
    'completionstatus' => $completionstatus,
    'page' => $page,
]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('clientlisttitle', 'local_dsl_isp'));
$PAGE->set_heading(get_string('clientlistheading', 'local_dsl_isp'));

// Get client data.
$mgr = new manager($tenantid);
$result = $mgr->get_clients(
    manager::STATUS_ACTIVE,
    $search,
    $servicetype,
    $completionstatus,
    $page,
    $perpage
);

// Check capabilities.
$canmanage = has_capability('local/dsl_isp:manageclients', $context);

// Create renderable.
$clientlist = new client_list(
    $result['clients'],
    $result['total'],
    $page,
    $perpage,
    $search,
    $servicetype,
    $completionstatus,
    $tenantid,
    $canmanage
);

// Initialize AMD module for client list interactions.
$PAGE->requires->js_call_amd('local_dsl_isp/client_manager', 'init', [[
    'sesskey' => sesskey(),
]]);

// Output.
$output = $PAGE->get_renderer('local_dsl_isp');

echo $OUTPUT->header();
echo $output->render($clientlist);
echo $OUTPUT->footer();
