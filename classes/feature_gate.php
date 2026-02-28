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

namespace local_dsl_isp;

use moodle_exception;

/**
 * Feature gate for ISP Manager tenant-level feature access control.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feature_gate {

    /**
     * Check if ISP Manager is enabled for a specific tenant.
     *
     * @param int $tenantid The tenant ID to check.
     * @return bool True if enabled, false otherwise.
     */
    public static function is_enabled(int $tenantid): bool {
        global $DB;

        if ($tenantid <= 0) {
            return false;
        }

        $record = $DB->get_record('dsl_isp_tenant_settings', ['tenantid' => $tenantid], 'enabled');

        if (!$record) {
            return false;
        }

        return (bool) $record->enabled;
    }

    /**
     * Require ISP Manager to be enabled for a tenant, throwing an exception if not.
     *
     * This should be called after require_login() and require_capability() in page scripts.
     *
     * @param int $tenantid The tenant ID to check.
     * @throws moodle_exception If the feature is not enabled.
     */
    public static function require_enabled(int $tenantid): void {
        if (!self::is_enabled($tenantid)) {
            throw new moodle_exception('featurenotenabled', 'local_dsl_isp');
        }
    }

    /**
     * Enable ISP Manager for a tenant.
     *
     * @param int $tenantid The tenant ID to enable.
     * @param int $enabledby The user ID of the admin enabling the feature.
     * @return bool True on success.
     */
    public static function enable(int $tenantid, int $enabledby): bool {
        global $DB;

        $now = time();
        $existing = $DB->get_record('dsl_isp_tenant_settings', ['tenantid' => $tenantid]);

        if ($existing) {
            $existing->enabled = 1;
            $existing->enabledby = $enabledby;
            $existing->timeenabled = $now;
            $existing->timemodified = $now;
            $DB->update_record('dsl_isp_tenant_settings', $existing);
        } else {
            $record = new \stdClass();
            $record->tenantid = $tenantid;
            $record->enabled = 1;
            $record->enabledby = $enabledby;
            $record->timeenabled = $now;
            $record->timemodified = $now;
            $DB->insert_record('dsl_isp_tenant_settings', $record);
        }

        return true;
    }

    /**
     * Disable ISP Manager for a tenant.
     *
     * @param int $tenantid The tenant ID to disable.
     * @return bool True on success.
     */
    public static function disable(int $tenantid): bool {
        global $DB;

        $existing = $DB->get_record('dsl_isp_tenant_settings', ['tenantid' => $tenantid]);

        if ($existing) {
            $existing->enabled = 0;
            $existing->timemodified = time();
            $DB->update_record('dsl_isp_tenant_settings', $existing);
        }
        // If no record exists, the feature is already disabled (default state).

        return true;
    }

    /**
     * Get the current user's tenant ID.
     *
     * @return int The tenant ID, or 0 if no tenant is assigned.
     */
    public static function get_current_tenant_id(): int {
        // Use Workplace's tenancy API to get the current user's tenant.
        if (class_exists('\tool_tenant\tenancy')) {
            return \tool_tenant\tenancy::get_tenant_id();
        }

        return 0;
    }

    /**
     * Verify a user belongs to a specific tenant.
     *
     * @param int $userid The user ID to check.
     * @param int $tenantid The tenant ID to verify against.
     * @return bool True if the user belongs to the tenant.
     */
    public static function user_in_tenant(int $userid, int $tenantid): bool {
        global $DB;

        return $DB->record_exists('tool_tenant_user', [
            'userid' => $userid,
            'tenantid' => $tenantid,
        ]);
    }

    /**
     * Get all tenant settings records with tenant information.
     *
     * @return array Array of tenant settings records with tenant names.
     */
    public static function get_all_tenant_settings(): array {
        global $DB;

        $sql = "SELECT ts.id, ts.tenantid, ts.enabled, ts.enabledby, ts.timeenabled, ts.timemodified,
                       t.name AS tenantname,
                       u.firstname AS enabledbyfirstname, u.lastname AS enabledbylastname
                  FROM {tool_tenant} t
             LEFT JOIN {dsl_isp_tenant_settings} ts ON ts.tenantid = t.id
             LEFT JOIN {user} u ON u.id = ts.enabledby
              ORDER BY t.name ASC";

        return $DB->get_records_sql($sql);
    }
}
