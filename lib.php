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
 * Library functions for ISP Manager.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation with ISP Manager link.
 *
 * @param global_navigation $navigation The global navigation object.
 */
function local_dsl_isp_extend_navigation(global_navigation $navigation): void {
    global $USER;

    // Only add navigation if user is logged in.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Check if user has view capability.
    $context = context_system::instance();
    if (!has_capability('local/dsl_isp:view', $context)) {
        return;
    }

    // Check if feature is enabled for user's tenant.
    $tenantid = \local_dsl_isp\feature_gate::get_current_tenant_id();
    if (empty($tenantid) || !\local_dsl_isp\feature_gate::is_enabled($tenantid)) {
        return;
    }

    // Add navigation node.
    $url = new moodle_url('/local/dsl_isp/index.php');
    $node = navigation_node::create(
        get_string('ispmanager', 'local_dsl_isp'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_dsl_isp',
        new pix_icon('i/folder', '')
    );

    $node->showinflatnavigation = true;

    // Add to the navigation.
    $navigation->add_node($node);
}

/**
 * Extend navigation settings with ISP Manager links.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The context.
 */
function local_dsl_isp_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    // This function is called for course and other contexts.
    // We only need system-level navigation which is handled in extend_navigation.
}

/**
 * Fragment output for AJAX operations.
 *
 * @param array $args The fragment arguments.
 * @return string The HTML fragment.
 */
function local_dsl_isp_output_fragment_dsp_search(array $args): string {
    global $DB, $OUTPUT;

    $tenantid = $args['tenantid'] ?? 0;
    $clientid = $args['clientid'] ?? 0;
    $search = $args['search'] ?? '';

    if (empty($tenantid) || empty($search)) {
        return '';
    }

    $enrollmentmanager = new \local_dsl_isp\enrollment_manager();
    $users = $enrollmentmanager->search_tenant_users($tenantid, $search, $clientid, 10);

    if (empty($users)) {
        return html_writer::tag('div',
            get_string('nousersmatching', 'local_dsl_isp', s($search)),
            ['class' => 'text-muted p-2']
        );
    }

    $items = [];
    foreach ($users as $user) {
        $items[] = html_writer::tag('a',
            html_writer::tag('span', fullname($user), ['class' => 'd-block']) .
            html_writer::tag('small', $user->email, ['class' => 'text-muted']),
            [
                'href' => '#',
                'class' => 'list-group-item list-group-item-action',
                'data-userid' => $user->id,
                'data-username' => fullname($user),
                'data-email' => $user->email,
            ]
        );
    }

    return html_writer::tag('div', implode('', $items), ['class' => 'list-group']);
}

/**
 * Serve plugin files.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The file arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if file not found.
 */
function local_dsl_isp_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    // This plugin doesn't serve files directly.
    // Documents are stored in course file areas managed by mod_resource.
    return false;
}

/**
 * Get the list of services provided by this plugin.
 *
 * @return array The services.
 */
function local_dsl_isp_get_services(): array {
    return [
        'ISP Manager Web Services' => [
            'functions' => [
                'local_dsl_isp_get_clients',
                'local_dsl_isp_get_completion_log',
            ],
            'requiredcapability' => 'local/dsl_isp:view',
            'restrictedusers' => 0,
            'enabled' => 1,
        ],
    ];
}

/**
 * Callback for cache invalidation when settings change.
 *
 * @param string $name The setting name.
 * @param mixed $value The new value.
 */
function local_dsl_isp_config_updated(string $name, $value): void {
    // Invalidate config cache when template course ID changes.
    if ($name === 'local_dsl_isp/template_course_id') {
        $cache = cache::make('local_dsl_isp', 'config');
        $cache->delete('template_course_id');
    }
}

/**
 * Add ISP Manager to user profile navigation.
 *
 * @param core_user\output\myprofile\tree $tree The profile tree.
 * @param stdClass $user The user object.
 * @param bool $iscurrentuser Whether viewing own profile.
 * @param stdClass|null $course The course object.
 */
function local_dsl_isp_myprofile_navigation(
    core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course
): void {
    // Don't add to profile navigation - ISP Manager is for tenant admins only.
}

/**
 * Check if the plugin is ready for use.
 *
 * @return bool True if configured and ready.
 */
function local_dsl_isp_is_configured(): bool {
    $templateid = get_config('local_dsl_isp', 'template_course_id');
    return !empty($templateid);
}

/**
 * Get the count of active clients for a tenant.
 *
 * @param int $tenantid The tenant ID.
 * @return int The count.
 */
function local_dsl_isp_get_client_count(int $tenantid): int {
    global $DB;

    return $DB->count_records('dsl_isp_client', [
        'tenantid' => $tenantid,
        'status' => 1,
    ]);
}

/**
 * Hook callback for user deletion.
 *
 * When a user is deleted, we need to handle their DSP assignments.
 * We don't delete the records (for audit purposes) but we should
 * ensure they're properly marked as unassigned.
 *
 * @param stdClass $user The user being deleted.
 */
function local_dsl_isp_pre_user_delete(stdClass $user): void {
    global $DB;

    // Mark all active DSP assignments for this user as unassigned.
    $now = time();
    $DB->set_field_select(
        'dsl_isp_dsp',
        'timeunassigned',
        $now,
        'userid = :userid AND timeunassigned IS NULL',
        ['userid' => $user->id]
    );
}
