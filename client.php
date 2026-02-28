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
 * Client management page for ISP Manager.
 *
 * Handles view, add, edit, documents, archive, and unarchive actions.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_dsl_isp\feature_gate;
use local_dsl_isp\manager;
use local_dsl_isp\course_builder;
use local_dsl_isp\form\client_form;
use local_dsl_isp\form\document_update_form;
use local_dsl_isp\output\client_detail;

// Get parameters.
$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA);

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

// Initialize manager.
$mgr = new manager($tenantid);

// Determine required capability based on action.
$requiresmanage = in_array($action, ['add', 'edit', 'documents', 'archive', 'unarchive']);
if ($requiresmanage) {
    require_capability('local/dsl_isp:manageclients', $context);
}

// Load client if ID provided.
$client = null;
if ($id > 0) {
    // For archive viewing, allow inactive clients.
    $activeonly = ($action !== 'unarchive');
    $client = $mgr->get_client($id, $activeonly);
}

// Page URL.
$pageurl = new moodle_url('/local/dsl_isp/client.php', ['id' => $id, 'action' => $action]);
$indexurl = new moodle_url('/local/dsl_isp/index.php');

// Page setup.
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');

// Handle actions.
switch ($action) {
    case 'add':
        local_dsl_isp_handle_add_action($mgr, $tenantid, $pageurl, $indexurl);
        break;

    case 'edit':
        if (!$client) {
            throw new moodle_exception('error_clientnotfound', 'local_dsl_isp');
        }
        local_dsl_isp_handle_edit_action($mgr, $client, $tenantid, $pageurl, $indexurl);
        break;

    case 'documents':
        if (!$client) {
            throw new moodle_exception('error_clientnotfound', 'local_dsl_isp');
        }
        local_dsl_isp_handle_documents_action($mgr, $client, $tenantid, $pageurl, $indexurl);
        break;

    case 'archive':
        if (!$client) {
            throw new moodle_exception('error_clientnotfound', 'local_dsl_isp');
        }
        local_dsl_isp_handle_archive_action($mgr, $client, $indexurl);
        break;

    case 'unarchive':
        if (!$client) {
            throw new moodle_exception('error_clientnotfound', 'local_dsl_isp');
        }
        local_dsl_isp_handle_unarchive_action($mgr, $client, $indexurl);
        break;

    case 'view':
    default:
        if (!$client) {
            throw new moodle_exception('error_clientnotfound', 'local_dsl_isp');
        }
        local_dsl_isp_handle_view_action($client, $tenantid, $context);
        break;
}

/**
 * Handle the add client action.
 *
 * @param manager $mgr The manager instance.
 * @param int $tenantid The tenant ID.
 * @param moodle_url $pageurl The current page URL.
 * @param moodle_url $indexurl The index page URL.
 */
function local_dsl_isp_handle_add_action(manager $mgr, int $tenantid, moodle_url $pageurl, moodle_url $indexurl): void {
    global $PAGE, $OUTPUT;

    $PAGE->set_title(get_string('addnewclient', 'local_dsl_isp'));
    $PAGE->set_heading(get_string('addnewclient', 'local_dsl_isp'));

    $customdata = [
        'tenantid' => $tenantid,
        'clientid' => null,
    ];

    $form = new client_form($pageurl, $customdata);

    if ($form->is_cancelled()) {
        redirect($indexurl);
    }

    if ($data = $form->get_data()) {
        // Get documents and DSPs from form.
        $documents = $form->get_document_data();
        $dspuserids = $form->get_dsp_userids();

        try {
            $client = $mgr->create_client($data, $documents, $dspuserids);

            $clientname = $data->firstname . ' ' . $data->lastname;
            $message = get_string('clientcreated', 'local_dsl_isp', $clientname);
            redirect($indexurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

        } catch (moodle_exception $e) {
            \core\notification::error($e->getMessage());
        }
    }

    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
}

/**
 * Handle the edit client action.
 *
 * @param manager $mgr The manager instance.
 * @param stdClass $client The client record.
 * @param int $tenantid The tenant ID.
 * @param moodle_url $pageurl The current page URL.
 * @param moodle_url $indexurl The index page URL.
 */
function local_dsl_isp_handle_edit_action(manager $mgr, stdClass $client, int $tenantid, moodle_url $pageurl, moodle_url $indexurl): void {
    global $PAGE, $OUTPUT;

    $clientname = $client->firstname . ' ' . $client->lastname;
    $PAGE->set_title(get_string('editclient', 'local_dsl_isp') . ': ' . $clientname);
    $PAGE->set_heading(get_string('editclient', 'local_dsl_isp') . ': ' . $clientname);

    $customdata = [
        'tenantid' => $tenantid,
        'clientid' => $client->id,
    ];

    $form = new client_form($pageurl, $customdata);

    // Set existing data.
    $formdata = clone $client;
    $formdata->id = $client->id;
    $form->set_data($formdata);

    if ($form->is_cancelled()) {
        $viewurl = new moodle_url('/local/dsl_isp/client.php', ['id' => $client->id, 'action' => 'view']);
        redirect($viewurl);
    }

    if ($data = $form->get_data()) {
        try {
            $mgr->update_client($client->id, $data);

            $newname = $data->firstname . ' ' . $data->lastname;
            $message = get_string('clientupdated', 'local_dsl_isp', $newname);
            $viewurl = new moodle_url('/local/dsl_isp/client.php', ['id' => $client->id, 'action' => 'view']);
            redirect($viewurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

        } catch (moodle_exception $e) {
            \core\notification::error($e->getMessage());
        }
    }

    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
}

/**
 * Handle the documents update action.
 *
 * @param manager $mgr The manager instance.
 * @param stdClass $client The client record.
 * @param int $tenantid The tenant ID.
 * @param moodle_url $pageurl The current page URL.
 * @param moodle_url $indexurl The index page URL.
 */
function local_dsl_isp_handle_documents_action(manager $mgr, stdClass $client, int $tenantid, moodle_url $pageurl, moodle_url $indexurl): void {
    global $PAGE, $OUTPUT;

    $clientname = $client->firstname . ' ' . $client->lastname;
    $PAGE->set_title(get_string('updatedocuments', 'local_dsl_isp') . ': ' . $clientname);
    $PAGE->set_heading(get_string('updatedocuments', 'local_dsl_isp') . ': ' . $clientname);

    // Get current documents.
    $coursebuilder = new course_builder();
    $currentdocs = $coursebuilder->get_course_documents($client->courseid);

    $customdata = [
        'clientid' => $client->id,
        'currentdocs' => $currentdocs,
    ];

    $form = new document_update_form($pageurl, $customdata);

    $viewurl = new moodle_url('/local/dsl_isp/client.php', ['id' => $client->id, 'action' => 'view']);

    if ($form->is_cancelled()) {
        redirect($viewurl);
    }

    if ($data = $form->get_data()) {
        $documents = $form->get_document_data();

        if (!empty($documents)) {
            try {
                foreach ($documents as $slotindex => $docdata) {
                    $coursebuilder->replace_document(
                        $client->courseid,
                        $slotindex,
                        $docdata['file'],
                        $docdata['name'],
                        $docdata['date']
                    );
                }

                // Fire event.
                $event = \local_dsl_isp\event\documents_updated::create([
                    'context' => context_system::instance(),
                    'objectid' => $client->id,
                    'other' => [
                        'tenantid' => $tenantid,
                        'slots' => array_keys($documents),
                    ],
                ]);
                $event->trigger();

                $message = get_string('documentsupdated', 'local_dsl_isp', $clientname);
                redirect($viewurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

            } catch (moodle_exception $e) {
                \core\notification::error($e->getMessage());
            }
        } else {
            // No documents selected for update.
            redirect($viewurl);
        }
    }

    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
}

/**
 * Handle the archive client action.
 *
 * @param manager $mgr The manager instance.
 * @param stdClass $client The client record.
 * @param moodle_url $indexurl The index page URL.
 */
function local_dsl_isp_handle_archive_action(manager $mgr, stdClass $client, moodle_url $indexurl): void {
    require_sesskey();

    $clientname = $client->firstname . ' ' . $client->lastname;

    try {
        $mgr->archive_client($client->id);
        $message = get_string('clientarchived', 'local_dsl_isp', $clientname);
        redirect($indexurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

    } catch (moodle_exception $e) {
        redirect($indexurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

/**
 * Handle the unarchive client action.
 *
 * @param manager $mgr The manager instance.
 * @param stdClass $client The client record.
 * @param moodle_url $indexurl The index page URL.
 */
function local_dsl_isp_handle_unarchive_action(manager $mgr, stdClass $client, moodle_url $indexurl): void {
    require_sesskey();

    $clientname = $client->firstname . ' ' . $client->lastname;

    try {
        $mgr->unarchive_client($client->id);
        $message = get_string('clientunarchived', 'local_dsl_isp', $clientname);
        redirect($indexurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

    } catch (moodle_exception $e) {
        redirect($indexurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

/**
 * Handle the view client action.
 *
 * @param stdClass $client The client record.
 * @param int $tenantid The tenant ID.
 * @param context $context The context.
 */
function local_dsl_isp_handle_view_action(stdClass $client, int $tenantid, context $context): void {
    global $PAGE, $OUTPUT;

    $clientname = $client->firstname . ' ' . $client->lastname;
    $PAGE->set_title($clientname);
    $PAGE->set_heading($clientname);

    // Check capabilities.
    $canmanageclients = has_capability('local/dsl_isp:manageclients', $context);
    $canmanagedsps = has_capability('local/dsl_isp:managedsps', $context);
    $canresetcompletion = has_capability('local/dsl_isp:resetcompletion', $context);
    $canviewhistory = has_capability('local/dsl_isp:viewhistory', $context);

    // Initialize AMD module for DSP assignment interactions.
    if ($canmanagedsps) {
        $PAGE->requires->js_call_amd('local_dsl_isp/dsp_assignment', 'init', [[
            'clientId' => $client->id,
            'clientName' => $clientname,
            'sesskey' => sesskey(),
        ]]);
    }

    // Create renderable.
    $clientdetail = new client_detail(
        $client,
        $tenantid,
        $canmanageclients,
        $canmanagedsps,
        $canresetcompletion,
        $canviewhistory
    );

    // Output.
    $output = $PAGE->get_renderer('local_dsl_isp');

    echo $OUTPUT->header();
    echo $output->render($clientdetail);
    echo $OUTPUT->footer();
}
