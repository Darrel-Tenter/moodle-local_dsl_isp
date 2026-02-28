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
 * DSP assignment management page for ISP Manager.
 *
 * Handles add, remove, and reset actions for DSP-client assignments.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_dsl_isp\feature_gate;
use local_dsl_isp\manager;
use local_dsl_isp\enrollment_manager;
use local_dsl_isp\completion_manager;
use local_dsl_isp\form\dsp_form;

// Get parameters.
$clientid = required_param('clientid', PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

// Authentication and authorization.
require_login();

$context = context_system::instance();
require_capability('local/dsl_isp:managedsps', $context);

// Get tenant ID and verify feature is enabled.
$tenantid = feature_gate::get_current_tenant_id();
if (empty($tenantid)) {
    throw new moodle_exception('error_tenantnotfound', 'local_dsl_isp');
}
feature_gate::require_enabled($tenantid);

// Get client.
$mgr = new manager($tenantid);
$client = $mgr->get_client($clientid);

$clientname = $client->firstname . ' ' . $client->lastname;

// URLs.
$pageurl = new moodle_url('/local/dsl_isp/manage_dsps.php', ['clientid' => $clientid, 'action' => $action]);
$clienturl = new moodle_url('/local/dsl_isp/client.php', ['id' => $clientid, 'action' => 'view']);

// Page setup.
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

// Handle actions.
switch ($action) {
    case 'add':
        local_dsl_isp_handle_dsp_add_action($client, $tenantid, $pageurl, $clienturl);
        break;

    case 'remove':
        local_dsl_isp_handle_dsp_remove_action($client, $userid, $clienturl);
        break;

    case 'reset':
        require_capability('local/dsl_isp:resetcompletion', $context);
        local_dsl_isp_handle_dsp_reset_action($client, $userid, $tenantid, $clienturl);
        break;

    case 'view':
    default:
        // Redirect to client detail page for viewing DSPs.
        redirect($clienturl);
        break;
}

/**
 * Handle the add DSP action.
 *
 * @param stdClass $client The client record.
 * @param int $tenantid The tenant ID.
 * @param moodle_url $pageurl The current page URL.
 * @param moodle_url $clienturl The client detail page URL.
 */
function local_dsl_isp_handle_dsp_add_action(stdClass $client, int $tenantid, moodle_url $pageurl, moodle_url $clienturl): void {
    global $PAGE, $OUTPUT, $USER;

    $clientname = $client->firstname . ' ' . $client->lastname;
    $PAGE->set_title(get_string('adddsp', 'local_dsl_isp') . ': ' . $clientname);
    $PAGE->set_heading(get_string('adddsp', 'local_dsl_isp') . ': ' . $clientname);

    $customdata = [
        'tenantid' => $tenantid,
        'clientid' => $client->id,
    ];

    $form = new dsp_form($pageurl, $customdata);

    if ($form->is_cancelled()) {
        redirect($clienturl);
    }

    if ($data = $form->get_data()) {
        $dspuserid = $form->get_userid();

        if ($dspuserid) {
            $enrollmentmanager = new enrollment_manager();

            try {
                $enrollmentmanager->assign_dsp($client->id, $dspuserid, $USER->id);

                // Get DSP name for message.
                $dsp = $GLOBALS['DB']->get_record('user', ['id' => $dspuserid], 'firstname, lastname');
                $dspname = fullname($dsp);

                $message = get_string('dspassigned', 'local_dsl_isp', [
                    'dspname' => $dspname,
                    'clientname' => $clientname,
                ]);
                redirect($clienturl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

            } catch (moodle_exception $e) {
                \core\notification::error($e->getMessage());
            }
        }
    }

    // Add navigation breadcrumb.
    $PAGE->navbar->add($clientname, $clienturl);
    $PAGE->navbar->add(get_string('adddsp', 'local_dsl_isp'));

    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
}

/**
 * Handle the remove DSP action.
 *
 * @param stdClass $client The client record.
 * @param int $userid The DSP user ID.
 * @param moodle_url $clienturl The client detail page URL.
 */
function local_dsl_isp_handle_dsp_remove_action(stdClass $client, int $userid, moodle_url $clienturl): void {
    global $USER, $DB;

    require_sesskey();

    if (empty($userid)) {
        throw new moodle_exception('error_dspnotfound', 'local_dsl_isp');
    }

    $clientname = $client->firstname . ' ' . $client->lastname;
    $enrollmentmanager = new enrollment_manager();

    try {
        $enrollmentmanager->remove_dsp($client->id, $userid, $USER->id);

        // Get DSP name for message.
        $dsp = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
        $dspname = fullname($dsp);

        $message = get_string('dspremoved', 'local_dsl_isp', [
            'dspname' => $dspname,
            'clientname' => $clientname,
        ]);
        redirect($clienturl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

    } catch (moodle_exception $e) {
        redirect($clienturl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

/**
 * Handle the reset completion action.
 *
 * @param stdClass $client The client record.
 * @param int $userid The DSP user ID.
 * @param int $tenantid The tenant ID.
 * @param moodle_url $clienturl The client detail page URL.
 */
function local_dsl_isp_handle_dsp_reset_action(stdClass $client, int $userid, int $tenantid, moodle_url $clienturl): void {
    global $USER, $DB;

    require_sesskey();

    if (empty($userid)) {
        throw new moodle_exception('error_dspnotfound', 'local_dsl_isp');
    }

    $clientname = $client->firstname . ' ' . $client->lastname;
    $completionmanager = new completion_manager();

    try {
        $completionmanager->manual_reset($client->id, $userid, $USER->id);

        // Get DSP name for message.
        $dsp = $DB->get_record('user', ['id' => $userid], 'firstname, lastname');
        $dspname = fullname($dsp);

        $message = get_string('completionreset', 'local_dsl_isp', [
            'dspname' => $dspname,
        ]);
        redirect($clienturl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

    } catch (moodle_exception $e) {
        redirect($clienturl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}
