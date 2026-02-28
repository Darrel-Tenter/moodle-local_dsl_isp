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

namespace local_dsl_isp\task;

use core\task\scheduled_task;
use local_dsl_isp\completion_manager;
use local_dsl_isp\enrollment_manager;
use local_dsl_isp\feature_gate;
use context_system;
use stdClass;

/**
 * Scheduled task for annual ISP completion renewal.
 *
 * Runs daily and processes all active clients whose ISP anniversary date
 * falls on the current date. For each client, archives completion data
 * for all assigned DSPs and resets their course completion.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class annual_renewal extends scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_annualrenewal', 'local_dsl_isp');
    }

    /**
     * Execute the task.
     *
     * Finds all active clients whose anniversary month/day matches today,
     * archives their DSPs' completion records, and resets completion.
     */
    public function execute(): void {
        global $DB;

        mtrace('Starting ISP annual renewal task...');

        // Get current date components.
        $todaymonth = (int) date('n'); // Month without leading zeros (1-12).
        $todayday = (int) date('j');   // Day without leading zeros (1-31).
        $todayyear = (int) date('Y');

        mtrace("Looking for clients with anniversary on month={$todaymonth}, day={$todayday}");

        // Find all active clients whose anniversary falls today.
        // Using MySQL date functions to extract month and day from Unix timestamp.
        $sql = "SELECT c.*
                  FROM {dsl_isp_client} c
                  JOIN {dsl_isp_tenant_settings} ts ON ts.tenantid = c.tenantid
                 WHERE c.status = :status
                   AND ts.enabled = 1
                   AND MONTH(FROM_UNIXTIME(c.anniversarydate)) = :month
                   AND DAYOFMONTH(FROM_UNIXTIME(c.anniversarydate)) = :day";

        $params = [
            'status' => 1, // Active clients only.
            'month' => $todaymonth,
            'day' => $todayday,
        ];

        // Use recordset for potentially large result sets.
        $clients = $DB->get_recordset_sql($sql, $params);

        $completionmanager = new completion_manager();
        $enrollmentmanager = new enrollment_manager();

        $processedcount = 0;
        $errorcount = 0;
        $errors = [];

        // Group clients by tenant for notification batching.
        $tenantclients = [];

        foreach ($clients as $client) {
            mtrace("Processing client {$client->id}: {$client->firstname} {$client->lastname}");

            try {
                $dspsprocessed = $this->process_client_renewal(
                    $client,
                    $completionmanager,
                    $enrollmentmanager,
                    $todayyear
                );

                $processedcount++;

                // Track for notifications.
                if (!isset($tenantclients[$client->tenantid])) {
                    $tenantclients[$client->tenantid] = [];
                }
                $tenantclients[$client->tenantid][] = [
                    'client' => $client,
                    'dspcount' => $dspsprocessed,
                ];

                mtrace("  Processed {$dspsprocessed} DSP(s) for client {$client->id}");

            } catch (\Exception $e) {
                $errorcount++;
                $errors[] = [
                    'clientid' => $client->id,
                    'error' => $e->getMessage(),
                ];
                mtrace("  ERROR processing client {$client->id}: " . $e->getMessage());
            }
        }

        $clients->close();

        // Send notifications to tenant admins.
        foreach ($tenantclients as $tenantid => $clientlist) {
            $this->send_tenant_notification($tenantid, $clientlist);
        }

        // Summary.
        if ($processedcount > 0) {
            mtrace(get_string('renewalprocessed', 'local_dsl_isp', ['count' => $processedcount]));
        } else {
            mtrace(get_string('renewalskipped', 'local_dsl_isp'));
        }

        if ($errorcount > 0) {
            mtrace("Completed with {$errorcount} error(s).");
            foreach ($errors as $error) {
                mtrace("  - Client {$error['clientid']}: {$error['error']}");
            }
        }

        mtrace('ISP annual renewal task completed.');
    }

    /**
     * Process renewal for a single client.
     *
     * @param stdClass $client The client record.
     * @param completion_manager $completionmanager The completion manager instance.
     * @param enrollment_manager $enrollmentmanager The enrollment manager instance.
     * @param int $currentyear The current year.
     * @return int Number of DSPs processed.
     */
    protected function process_client_renewal(
        stdClass $client,
        completion_manager $completionmanager,
        enrollment_manager $enrollmentmanager,
        int $currentyear
    ): int {
        global $DB;

        // Calculate plan year boundaries.
        // The plan year that just ended started one year ago on this anniversary date.
        $anniversarymonth = (int) date('n', $client->anniversarydate);
        $anniversaryday = (int) date('j', $client->anniversarydate);

        // Plan year end is today (the anniversary).
        $planyearend = mktime(0, 0, 0, $anniversarymonth, $anniversaryday, $currentyear);

        // Plan year start was one year ago.
        $planyearstart = mktime(0, 0, 0, $anniversarymonth, $anniversaryday, $currentyear - 1);

        // Get all active DSPs for this client.
        $dsps = $enrollmentmanager->get_client_dsps($client->id);

        $processedcount = 0;

        foreach ($dsps as $dsp) {
            // Check idempotency - skip if already processed for this plan year.
            if ($completionmanager->log_entry_exists($client->id, $dsp->userid, $planyearstart)) {
                mtrace("    Skipping DSP {$dsp->userid} (already processed for this plan year)");
                continue;
            }

            // Archive and reset.
            $completionmanager->archive_and_reset(
                $client->id,
                $dsp->userid,
                $planyearstart,
                $planyearend,
                null // No notes for scheduled renewal.
            );

            $processedcount++;
        }

        // Update client timemodified.
        $client->timemodified = time();
        $DB->update_record('dsl_isp_client', $client);

        // Fire the client renewed event.
        $event = \local_dsl_isp\event\client_renewed::create([
            'context' => context_system::instance(),
            'objectid' => $client->id,
            'other' => [
                'tenantid' => $client->tenantid,
                'planyearstart' => $planyearstart,
                'planyearend' => $planyearend,
                'dspcount' => $processedcount,
            ],
        ]);
        $event->trigger();

        return $processedcount;
    }

    /**
     * Send notification to tenant admins about processed renewals.
     *
     * @param int $tenantid The tenant ID.
     * @param array $clientlist Array of processed clients with DSP counts.
     */
    protected function send_tenant_notification(int $tenantid, array $clientlist): void {
        global $DB;

        if (empty($clientlist)) {
            return;
        }

        // Build the client list text.
        $clientlines = [];
        foreach ($clientlist as $item) {
            $clientlines[] = get_string('notification_renewal_client', 'local_dsl_isp', [
                'firstname' => $item['client']->firstname,
                'lastname' => $item['client']->lastname,
                'dspcount' => $item['dspcount'],
            ]);
        }
        $clientlisttext = implode("\n", $clientlines);

        // Find tenant admins (users with the view capability in this tenant).
        $tenantadmins = $this->get_tenant_admins($tenantid);

        if (empty($tenantadmins)) {
            mtrace("  No tenant admins found for tenant {$tenantid}, skipping notification");
            return;
        }

        // Prepare the message.
        $subject = get_string('notification_renewal_subject', 'local_dsl_isp');
        $body = get_string('notification_renewal_body', 'local_dsl_isp', [
            'clientlist' => $clientlisttext,
        ]);

        // Send to each admin.
        foreach ($tenantadmins as $admin) {
            $this->send_notification_message($admin, $subject, $body);
        }

        // Also send to configured CC email if set.
        $ccemail = get_config('local_dsl_isp', 'renewal_notify_email');
        if (!empty($ccemail)) {
            $this->send_email_notification($ccemail, $subject, $body);
        }

        mtrace("  Sent renewal notification to " . count($tenantadmins) . " tenant admin(s)");
    }

    /**
     * Get users with tenant admin capabilities for a tenant.
     *
     * @param int $tenantid The tenant ID.
     * @return array Array of user records.
     */
    protected function get_tenant_admins(int $tenantid): array {
        global $DB;

        // Get users in this tenant who have the view capability.
        // This is a simplified approach - in production, you might want to
        // check for a specific "tenant admin" role or capability more precisely.
        $sql = "SELECT DISTINCT u.*
                  FROM {user} u
                  JOIN {tool_tenant_user} tu ON tu.userid = u.id
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                 WHERE tu.tenantid = :tenantid
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND rc.capability = :capability
                   AND rc.permission = 1
                   AND ra.contextid = :contextid";

        $systemcontext = context_system::instance();

        return $DB->get_records_sql($sql, [
            'tenantid' => $tenantid,
            'capability' => 'local/dsl_isp:view',
            'contextid' => $systemcontext->id,
        ]);
    }

    /**
     * Send a notification message to a user.
     *
     * @param stdClass $user The recipient user.
     * @param string $subject The message subject.
     * @param string $body The message body.
     */
    protected function send_notification_message(stdClass $user, string $subject, string $body): void {
        $message = new \core\message\message();
        $message->component = 'local_dsl_isp';
        $message->name = 'renewal_notification';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            mtrace("    Warning: Failed to send notification to user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Send an email notification to an address.
     *
     * @param string $email The recipient email address.
     * @param string $subject The email subject.
     * @param string $body The email body.
     */
    protected function send_email_notification(string $email, string $subject, string $body): void {
        // Create a fake user object for the email recipient.
        $recipient = new stdClass();
        $recipient->id = -1;
        $recipient->email = $email;
        $recipient->firstname = 'ISP';
        $recipient->lastname = 'Notification';
        $recipient->maildisplay = 1;
        $recipient->mailformat = 1;
        $recipient->auth = 'manual';
        $recipient->deleted = 0;
        $recipient->suspended = 0;

        $noreply = \core_user::get_noreply_user();

        try {
            email_to_user($recipient, $noreply, $subject, $body, nl2br(s($body)));
        } catch (\Exception $e) {
            mtrace("    Warning: Failed to send email to {$email}: " . $e->getMessage());
        }
    }
}
