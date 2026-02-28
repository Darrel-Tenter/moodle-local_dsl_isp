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

namespace local_dsl_isp\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy API provider for local_dsl_isp.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the types of data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        // DSP assignments table.
        $collection->add_database_table(
            'dsl_isp_dsp',
            [
                'userid' => 'privacy:metadata:dsl_isp_dsp:userid',
                'clientid' => 'privacy:metadata:dsl_isp_dsp:clientid',
                'timeassigned' => 'privacy:metadata:dsl_isp_dsp:timeassigned',
                'timeunassigned' => 'privacy:metadata:dsl_isp_dsp:timeunassigned',
                'assignedby' => 'privacy:metadata:dsl_isp_dsp:assignedby',
                'unassignedby' => 'privacy:metadata:dsl_isp_dsp:unassignedby',
            ],
            'privacy:metadata:dsl_isp_dsp'
        );

        // Completion log table.
        $collection->add_database_table(
            'dsl_isp_completion_log',
            [
                'userid' => 'privacy:metadata:dsl_isp_completion_log:userid',
                'clientid' => 'privacy:metadata:dsl_isp_completion_log:clientid',
                'planyearstart' => 'privacy:metadata:dsl_isp_completion_log:planyearstart',
                'planyearend' => 'privacy:metadata:dsl_isp_completion_log:planyearend',
                'timecompleted' => 'privacy:metadata:dsl_isp_completion_log:timecompleted',
                'timearchived' => 'privacy:metadata:dsl_isp_completion_log:timearchived',
            ],
            'privacy:metadata:dsl_isp_completion_log'
        );

        // Client table stores IDD client data (not Moodle users, but still PII).
        $collection->add_database_table(
            'dsl_isp_client',
            [
                'firstname' => 'privacy:metadata:dsl_isp_client:firstname',
                'lastname' => 'privacy:metadata:dsl_isp_client:lastname',
                'servicetype' => 'privacy:metadata:dsl_isp_client:servicetype',
                'usermodified' => 'privacy:metadata:dsl_isp_client:usermodified',
            ],
            'privacy:metadata:dsl_isp_client'
        );

        // Tenant settings table.
        $collection->add_database_table(
            'dsl_isp_tenant_settings',
            [
                'enabledby' => 'privacy:metadata:dsl_isp_tenant_settings:enabledby',
            ],
            'privacy:metadata:dsl_isp_tenant_settings'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * @param int $userid The user ID.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Check if user has DSP assignments.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {dsl_isp_dsp} d ON d.userid = :userid1
                 WHERE ctx.contextlevel = :contextlevel1
                 
                 UNION
                 
                SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {dsl_isp_dsp} d ON d.assignedby = :userid2 OR d.unassignedby = :userid3
                 WHERE ctx.contextlevel = :contextlevel2
                 
                 UNION
                 
                SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {dsl_isp_completion_log} cl ON cl.userid = :userid4
                 WHERE ctx.contextlevel = :contextlevel3
                 
                 UNION
                 
                SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {dsl_isp_client} c ON c.usermodified = :userid5
                 WHERE ctx.contextlevel = :contextlevel4
                 
                 UNION
                 
                SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {dsl_isp_tenant_settings} ts ON ts.enabledby = :userid6
                 WHERE ctx.contextlevel = :contextlevel5";

        $params = [
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
            'contextlevel1' => CONTEXT_SYSTEM,
            'contextlevel2' => CONTEXT_SYSTEM,
            'contextlevel3' => CONTEXT_SYSTEM,
            'contextlevel4' => CONTEXT_SYSTEM,
            'contextlevel5' => CONTEXT_SYSTEM,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Users who are DSPs.
        $sql = "SELECT DISTINCT userid FROM {dsl_isp_dsp}";
        $userlist->add_from_sql('userid', $sql, []);

        // Users who assigned/unassigned DSPs.
        $sql = "SELECT DISTINCT assignedby FROM {dsl_isp_dsp} WHERE assignedby IS NOT NULL";
        $userlist->add_from_sql('assignedby', $sql, []);

        $sql = "SELECT DISTINCT unassignedby FROM {dsl_isp_dsp} WHERE unassignedby IS NOT NULL";
        $userlist->add_from_sql('unassignedby', $sql, []);

        // Users in completion log.
        $sql = "SELECT DISTINCT userid FROM {dsl_isp_completion_log}";
        $userlist->add_from_sql('userid', $sql, []);

        // Users who modified clients.
        $sql = "SELECT DISTINCT usermodified FROM {dsl_isp_client}";
        $userlist->add_from_sql('usermodified', $sql, []);

        // Users who enabled tenants.
        $sql = "SELECT DISTINCT enabledby FROM {dsl_isp_tenant_settings} WHERE enabledby IS NOT NULL";
        $userlist->add_from_sql('enabledby', $sql, []);
    }

    /**
     * Export personal data for the given approved contextlist.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }

            // Export DSP assignments where user is the DSP.
            $assignments = $DB->get_records('dsl_isp_dsp', ['userid' => $userid]);
            if (!empty($assignments)) {
                $data = [];
                foreach ($assignments as $assignment) {
                    $client = $DB->get_record('dsl_isp_client', ['id' => $assignment->clientid]);
                    $data[] = [
                        'client' => $client ? ($client->firstname . ' ' . $client->lastname) : 'Unknown',
                        'timeassigned' => transform::datetime($assignment->timeassigned),
                        'timeunassigned' => $assignment->timeunassigned ?
                            transform::datetime($assignment->timeunassigned) : null,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_dsl_isp'), 'DSP Assignments'],
                    (object) ['assignments' => $data]
                );
            }

            // Export completion history where user is the DSP.
            $completions = $DB->get_records('dsl_isp_completion_log', ['userid' => $userid]);
            if (!empty($completions)) {
                $data = [];
                foreach ($completions as $completion) {
                    $client = $DB->get_record('dsl_isp_client', ['id' => $completion->clientid]);
                    $data[] = [
                        'client' => $client ? ($client->firstname . ' ' . $client->lastname) : 'Unknown',
                        'planyearstart' => transform::datetime($completion->planyearstart),
                        'planyearend' => transform::datetime($completion->planyearend),
                        'timecompleted' => $completion->timecompleted ?
                            transform::datetime($completion->timecompleted) : null,
                        'timearchived' => transform::datetime($completion->timearchived),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_dsl_isp'), 'Completion History'],
                    (object) ['completions' => $data]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        // Note: We do NOT delete ISP client data or completion logs as these are
        // compliance records required for Oregon state audits (7-10 year retention).
        // DSP assignment records are also audit-critical.
        //
        // Per GDPR Article 17(3)(b), the right to erasure does not apply where
        // processing is necessary for compliance with a legal obligation.
        //
        // Instead, we anonymize user references where possible.
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }

            // Anonymize assignedby/unassignedby references.
            $DB->set_field('dsl_isp_dsp', 'assignedby', 0, ['assignedby' => $userid]);
            $DB->set_field('dsl_isp_dsp', 'unassignedby', 0, ['unassignedby' => $userid]);

            // Anonymize usermodified in client records.
            $DB->set_field('dsl_isp_client', 'usermodified', 0, ['usermodified' => $userid]);

            // Anonymize enabledby in tenant settings.
            $DB->set_field('dsl_isp_tenant_settings', 'enabledby', null, ['enabledby' => $userid]);

            // Note: We do NOT delete DSP assignment records (dsl_isp_dsp.userid) or
            // completion logs (dsl_isp_completion_log.userid) as these are compliance
            // records required for Oregon state audits. The userid remains for audit
            // trail purposes per legal retention requirements.
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Anonymize assignedby/unassignedby references.
        $DB->execute(
            "UPDATE {dsl_isp_dsp} SET assignedby = 0 WHERE assignedby $insql",
            $params
        );
        $DB->execute(
            "UPDATE {dsl_isp_dsp} SET unassignedby = 0 WHERE unassignedby $insql",
            $params
        );

        // Anonymize usermodified in client records.
        $DB->execute(
            "UPDATE {dsl_isp_client} SET usermodified = 0 WHERE usermodified $insql",
            $params
        );

        // Anonymize enabledby in tenant settings.
        $DB->execute(
            "UPDATE {dsl_isp_tenant_settings} SET enabledby = NULL WHERE enabledby $insql",
            $params
        );
    }
}
