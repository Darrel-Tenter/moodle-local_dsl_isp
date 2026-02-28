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
 * Admin settings for ISP Manager.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page.
    $settings = new admin_settingpage('local_dsl_isp', get_string('pluginname', 'local_dsl_isp'));

    // Add to local plugins category.
    $ADMIN->add('localplugins', $settings);

    // Template course ID.
    $settings->add(new admin_setting_configtext(
        'local_dsl_isp/template_course_id',
        get_string('templatecourseid', 'local_dsl_isp'),
        get_string('templatecourseid_desc', 'local_dsl_isp'),
        '',
        PARAM_INT
    ));

    // Student role ID.
    $settings->add(new admin_setting_configtext(
        'local_dsl_isp/student_role_id',
        get_string('studentroleid', 'local_dsl_isp'),
        get_string('studentroleid_desc', 'local_dsl_isp'),
        '5',
        PARAM_INT
    ));

    // Maximum file size (MB).
    $settings->add(new admin_setting_configtext(
        'local_dsl_isp/max_file_size_mb',
        get_string('maxfilesizembsetting', 'local_dsl_isp'),
        get_string('maxfilesizembsetting_desc', 'local_dsl_isp'),
        '10',
        PARAM_INT
    ));

    // Renewal notification email.
    $settings->add(new admin_setting_configtext(
        'local_dsl_isp/renewal_notify_email',
        get_string('renewalnotifyemail', 'local_dsl_isp'),
        get_string('renewalnotifyemail_desc', 'local_dsl_isp'),
        '',
        PARAM_EMAIL
    ));

    // Add link to tenant management page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_dsl_isp_tenants',
        get_string('tenantmanagement', 'local_dsl_isp'),
        new moodle_url('/local/dsl_isp/admin/tenants.php'),
        'local/dsl_isp:managetenants'
    ));
}
