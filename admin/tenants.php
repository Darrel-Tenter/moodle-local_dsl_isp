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
 * Tenant management page for ISP Manager.
 *
 * Allows site administrators to enable/disable ISP Manager per tenant.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_dsl_isp\feature_gate;

// Get parameters.
$action = optional_param('action', '', PARAM_ALPHA);
$tenantid = optional_param('tenantid', 0, PARAM_INT);

// Authentication and authorization.
require_login();
require_capability('local/dsl_isp:managetenants', context_system::instance());

// Page setup.
$pageurl = new moodle_url('/local/dsl_isp/admin/tenants.php');
admin_externalpage_setup('local_dsl_isp_tenants');

$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());

// Handle actions.
if (!empty($action) && $tenantid > 0) {
    require_sesskey();

    $tenantname = $DB->get_field('tool_tenant', 'name', ['id' => $tenantid]);

    if ($action === 'enable') {
        feature_gate::enable($tenantid, $USER->id);
        $message = get_string('ispmanagerenabled', 'local_dsl_isp', $tenantname);
        redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);

    } else if ($action === 'disable') {
        feature_gate::disable($tenantid);
        $message = get_string('ispmanagerdisabled', 'local_dsl_isp', $tenantname);
        redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Get tenant data.
$tenants = feature_gate::get_all_tenant_settings();

// Build the table.
$table = new html_table();
$table->head = [
    get_string('tenantname', 'local_dsl_isp'),
    get_string('ispapienabled', 'local_dsl_isp'),
    get_string('enabledby', 'local_dsl_isp'),
    get_string('enableddate', 'local_dsl_isp'),
    get_string('actions', 'local_dsl_isp'),
];
$table->attributes['class'] = 'admintable generaltable';
$table->data = [];

foreach ($tenants as $tenant) {
    $row = [];

    // Tenant name.
    $row[] = format_string($tenant->tenantname);

    // Enabled status.
    $isenabled = !empty($tenant->enabled);
    if ($isenabled) {
        $row[] = html_writer::tag('span', get_string('yes'), ['class' => 'badge badge-success']);
    } else {
        $row[] = html_writer::tag('span', get_string('no'), ['class' => 'badge badge-secondary']);
    }

    // Enabled by.
    if ($isenabled && !empty($tenant->enabledbyfirstname)) {
        $row[] = $tenant->enabledbyfirstname . ' ' . $tenant->enabledbylastname;
    } else {
        $row[] = '-';
    }

    // Enabled date.
    if ($isenabled && !empty($tenant->timeenabled)) {
        $row[] = userdate($tenant->timeenabled, get_string('strftimedatetimeshort', 'langconfig'));
    } else {
        $row[] = '-';
    }

    // Actions.
    $actions = [];
    if ($isenabled) {
        $disableurl = new moodle_url($pageurl, [
            'action' => 'disable',
            'tenantid' => $tenant->tenantid,
            'sesskey' => sesskey(),
        ]);
        $actions[] = html_writer::link(
            $disableurl,
            get_string('disableispmanager', 'local_dsl_isp'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    } else {
        $enableurl = new moodle_url($pageurl, [
            'action' => 'enable',
            'tenantid' => $tenant->tenantid,
            'sesskey' => sesskey(),
        ]);
        $actions[] = html_writer::link(
            $enableurl,
            get_string('enableispmanager', 'local_dsl_isp'),
            ['class' => 'btn btn-sm btn-outline-success']
        );
    }
    $row[] = implode(' ', $actions);

    $table->data[] = $row;
}

// Output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tenantmanagementheading', 'local_dsl_isp'));

// Show configuration status.
$templatecourseid = get_config('local_dsl_isp', 'template_course_id');
if (empty($templatecourseid)) {
    echo $OUTPUT->notification(
        get_string('error_templatecoursenotconfigured', 'local_dsl_isp'),
        \core\output\notification::NOTIFY_WARNING
    );
}

if (empty($tenants)) {
    echo $OUTPUT->notification(
        get_string('notenants', 'local_dsl_isp'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo html_writer::table($table);
}

// Link back to settings.
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_dsl_isp']);
echo html_writer::tag('p',
    html_writer::link($settingsurl, get_string('settings', 'local_dsl_isp'), ['class' => 'btn btn-secondary'])
);

echo $OUTPUT->footer();
