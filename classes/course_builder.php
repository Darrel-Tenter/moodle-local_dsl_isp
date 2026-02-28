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

use stdClass;
use moodle_exception;
use stored_file;
use context_course;
use context_module;
use core_external\external_api;

/**
 * Course builder for ISP Manager.
 *
 * Handles course creation from template, document replacement, and custom field management.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_builder {

    /** @var array Document slot definitions matching Oregon ISP requirements. */
    public const DOCUMENT_SLOTS = [
        1 => [
            'name' => 'One Page Profile',
            'shortname' => 'onepageprofile',
            'required' => true,
            'fieldname' => 'ispdoc1',
        ],
        2 => [
            'name' => 'Individual Support Plan',
            'shortname' => 'individualsupportplan',
            'required' => true,
            'fieldname' => 'ispdoc2',
        ],
        3 => [
            'name' => 'Person Centered Information',
            'shortname' => 'personcenteredinfo',
            'required' => true,
            'fieldname' => 'ispdoc3',
        ],
        4 => [
            'name' => 'Safety Plan / Risk Management Plan',
            'shortname' => 'safetyplan',
            'required' => true,
            'fieldname' => 'ispdoc4',
        ],
        5 => [
            'name' => 'Provider Risk Management Strategies',
            'shortname' => 'providerrms',
            'required' => true,
            'fieldname' => 'ispdoc5',
        ],
        6 => [
            'name' => 'Risk Identification Tool',
            'shortname' => 'rit',
            'required' => false, // Conditional.
            'fieldname' => 'ispdoc6',
        ],
        7 => [
            'name' => 'Action Plan',
            'shortname' => 'actionplan',
            'required' => false,
            'fieldname' => 'ispdoc7',
            'repeatable' => true,
            'maxrepeat' => 3,
        ],
        8 => [
            'name' => 'Support Document / Protocol',
            'shortname' => 'supportdoc',
            'required' => false,
            'fieldname' => 'ispdoc8',
            'repeatable' => true,
            'maxrepeat' => 5,
        ],
    ];

    /** @var int|null Cached template course ID. */
    protected ?int $templatecourseid = null;

    /**
     * Get the configured template course ID.
     *
     * @return int The template course ID.
     * @throws moodle_exception If template course is not configured.
     */
    public function get_template_course_id(): int {
        if ($this->templatecourseid !== null) {
            return $this->templatecourseid;
        }

        // Try cache first.
        $cache = \cache::make('local_dsl_isp', 'config');
        $cachedid = $cache->get('template_course_id');

        if ($cachedid !== false) {
            $this->templatecourseid = (int) $cachedid;
            return $this->templatecourseid;
        }

        // Get from config.
        $configid = get_config('local_dsl_isp', 'template_course_id');

        if (empty($configid)) {
            throw new moodle_exception('error_templatecoursenotconfigured', 'local_dsl_isp');
        }

        $this->templatecourseid = (int) $configid;
        $cache->set('template_course_id', $this->templatecourseid);

        return $this->templatecourseid;
    }

    /**
     * Create a new ISP course from the template.
     *
     * @param string $coursename The name for the new course.
     * @param int $startdate The course start date (anniversary date).
     * @return int The new course ID.
     * @throws moodle_exception On failure.
     */
    public function create_course_from_template(string $coursename, int $startdate): int {
        global $DB;

        $templateid = $this->get_template_course_id();

        // Generate unique shortname.
        $shortname = $this->generate_unique_shortname($coursename);

        // Use core web service to duplicate course.
        $params = [
            'courseid' => $templateid,
            'fullname' => $coursename,
            'shortname' => $shortname,
            'categoryid' => $this->get_template_category_id($templateid),
            'visible' => 1,
            'options' => [
                ['name' => 'users', 'value' => 0],
                ['name' => 'activities', 'value' => 1],
                ['name' => 'blocks', 'value' => 1],
                ['name' => 'filters', 'value' => 1],
                ['name' => 'questionbank', 'value' => 0],
                ['name' => 'competencies', 'value' => 0],
            ],
        ];

        $result = external_api::call_external_function(
            'core_course_duplicate_course',
            $params,
            false
        );

        if (!empty($result['error'])) {
            throw new moodle_exception('error_coursecreationfailed', 'local_dsl_isp', '', null, $result['exception']->message ?? '');
        }

        $newcourseid = $result['data']['id'];

        // Update course start date.
        $this->update_course_settings($newcourseid, [
            'startdate' => $startdate,
        ]);

        return $newcourseid;
    }

    /**
     * Update course settings.
     *
     * @param int $courseid The course ID.
     * @param array $settings Settings to update.
     */
    public function update_course_settings(int $courseid, array $settings): void {
        $courseupdate = [
            'id' => $courseid,
        ];

        foreach ($settings as $key => $value) {
            $courseupdate[$key] = $value;
        }

        $result = external_api::call_external_function(
            'core_course_update_courses',
            ['courses' => [$courseupdate]],
            false
        );

        if (!empty($result['error'])) {
            debugging('Failed to update course settings: ' . ($result['exception']->message ?? 'Unknown error'), DEBUG_DEVELOPER);
        }
    }

    /**
     * Update course name.
     *
     * @param int $courseid The course ID.
     * @param string $fullname The new course name.
     */
    public function update_course_name(int $courseid, string $fullname): void {
        $this->update_course_settings($courseid, ['fullname' => $fullname]);
    }

    /**
     * Replace a document in a course's file resource.
     *
     * @param int $courseid The course ID.
     * @param int $slotindex The document slot index (1-8).
     * @param stored_file $newfile The new file to use.
     * @param string $documentname The document name for the custom field.
     * @param string $documentdate The document date for the custom field.
     * @return bool True on success.
     */
    public function replace_document(
        int $courseid,
        int $slotindex,
        stored_file $newfile,
        string $documentname,
        string $documentdate
    ): bool {
        global $DB;

        if (!isset(self::DOCUMENT_SLOTS[$slotindex])) {
            throw new moodle_exception('error_documentreplacefailed', 'local_dsl_isp');
        }

        $slot = self::DOCUMENT_SLOTS[$slotindex];

        // Find the resource activity for this slot.
        $cm = $this->find_resource_by_slot($courseid, $slotindex);

        if (!$cm) {
            throw new moodle_exception('error_documentreplacefailed', 'local_dsl_isp');
        }

        // Replace the file in the resource.
        $this->replace_resource_file($cm, $newfile);

        // Update the custom course field.
        $fieldvalue = trim($documentname . ' ' . $documentdate);
        $this->update_course_custom_field($courseid, $slot['fieldname'], $fieldvalue);

        return true;
    }

    /**
     * Find the resource activity module for a document slot.
     *
     * @param int $courseid The course ID.
     * @param int $slotindex The slot index.
     * @return stdClass|null The course module record or null.
     */
    protected function find_resource_by_slot(int $courseid, int $slotindex): ?stdClass {
        global $DB;

        $slot = self::DOCUMENT_SLOTS[$slotindex];

        // Resources are identified by their idnumber matching the slot shortname.
        $sql = "SELECT cm.*
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {resource} r ON r.id = cm.instance
                 WHERE cm.course = :courseid
                   AND m.name = 'resource'
                   AND cm.idnumber = :idnumber";

        $cm = $DB->get_record_sql($sql, [
            'courseid' => $courseid,
            'idnumber' => $slot['shortname'],
        ]);

        return $cm ?: null;
    }

    /**
     * Replace the file in a resource activity.
     *
     * @param stdClass $cm The course module record.
     * @param stored_file $newfile The new file.
     */
    protected function replace_resource_file(stdClass $cm, stored_file $newfile): void {
        $fs = get_file_storage();
        $context = context_module::instance($cm->id);

        // Delete existing files in the resource file area.
        $fs->delete_area_files($context->id, 'mod_resource', 'content');

        // Create the new file record.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $newfile->get_filename(),
        ];

        // Copy the file to the resource area.
        $fs->create_file_from_storedfile($filerecord, $newfile);
    }

    /**
     * Update a custom course field value.
     *
     * @param int $courseid The course ID.
     * @param string $fieldshortname The field shortname.
     * @param string $value The value to set.
     */
    protected function update_course_custom_field(int $courseid, string $fieldshortname, string $value): void {
        global $DB;

        // Get the field definition.
        $field = $DB->get_record('customfield_field', ['shortname' => $fieldshortname]);

        if (!$field) {
            debugging("Custom field '{$fieldshortname}' not found", DEBUG_DEVELOPER);
            return;
        }

        // Get or create the data record.
        $data = $DB->get_record('customfield_data', [
            'fieldid' => $field->id,
            'instanceid' => $courseid,
        ]);

        if ($data) {
            $data->value = $value;
            $data->valueformat = FORMAT_PLAIN;
            $data->timemodified = time();
            $DB->update_record('customfield_data', $data);
        } else {
            $data = new stdClass();
            $data->fieldid = $field->id;
            $data->instanceid = $courseid;
            $data->contextid = context_course::instance($courseid)->id;
            $data->value = $value;
            $data->valueformat = FORMAT_PLAIN;
            $data->timecreated = time();
            $data->timemodified = time();
            $DB->insert_record('customfield_data', $data);
        }
    }

    /**
     * Get the current value of a custom course field.
     *
     * @param int $courseid The course ID.
     * @param string $fieldshortname The field shortname.
     * @return string|null The field value or null.
     */
    public function get_course_custom_field(int $courseid, string $fieldshortname): ?string {
        global $DB;

        $sql = "SELECT cd.value
                  FROM {customfield_data} cd
                  JOIN {customfield_field} cf ON cf.id = cd.fieldid
                 WHERE cd.instanceid = :courseid
                   AND cf.shortname = :shortname";

        $value = $DB->get_field_sql($sql, [
            'courseid' => $courseid,
            'shortname' => $fieldshortname,
        ]);

        return $value ?: null;
    }

    /**
     * Parse PDF ModDate from file content.
     *
     * Uses lightweight byte-level parsing to extract the /ModDate value.
     *
     * @param stored_file $file The PDF file.
     * @return int|null Unix timestamp or null if parsing fails.
     */
    public function parse_pdf_mod_date(stored_file $file): ?int {
        $content = $file->get_content();

        // Search for /ModDate pattern in PDF.
        // Format: /ModDate (D:YYYYMMDDHHmmSS+HH'mm') or similar variations.
        if (preg_match('/\/ModDate\s*\(D:(\d{4})(\d{2})(\d{2})/', $content, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            // Sanity checks.
            if ($year < 1990 || $year > 2100 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return null;
            }

            // Check for suspicious dates.
            $timestamp = mktime(0, 0, 0, $month, $day, $year);

            // Reject dates in 1970 (Unix epoch issues) or future dates.
            if ($year === 1970 || $timestamp > time()) {
                return null;
            }

            return $timestamp;
        }

        return null;
    }

    /**
     * Get document information for a course's document slots.
     *
     * @param int $courseid The course ID.
     * @return array Array of document slot data.
     */
    public function get_course_documents(int $courseid): array {
        global $DB;

        $documents = [];

        foreach (self::DOCUMENT_SLOTS as $index => $slot) {
            $cm = $this->find_resource_by_slot($courseid, $index);

            $docinfo = [
                'slot' => $index,
                'name' => $slot['name'],
                'shortname' => $slot['shortname'],
                'required' => $slot['required'],
                'hasfile' => false,
                'filename' => null,
                'filesize' => null,
                'timemodified' => null,
                'fieldvalue' => null,
            ];

            if ($cm) {
                $context = context_module::instance($cm->id);
                $fs = get_file_storage();
                $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder', false);

                if (!empty($files)) {
                    $file = reset($files);
                    $docinfo['hasfile'] = true;
                    $docinfo['filename'] = $file->get_filename();
                    $docinfo['filesize'] = $file->get_filesize();
                    $docinfo['timemodified'] = $file->get_timemodified();
                }
            }

            // Get custom field value.
            $docinfo['fieldvalue'] = $this->get_course_custom_field($courseid, $slot['fieldname']);

            $documents[$index] = $docinfo;
        }

        return $documents;
    }

    /**
     * Delete a course.
     *
     * @param int $courseid The course ID.
     * @return bool True on success.
     */
    public function delete_course(int $courseid): bool {
        try {
            $result = external_api::call_external_function(
                'core_course_delete_courses',
                ['courseids' => [$courseid]],
                false
            );

            return empty($result['error']);
        } catch (\Exception $e) {
            debugging('Failed to delete course: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Generate a unique course shortname.
     *
     * @param string $basename Base name to use.
     * @return string Unique shortname.
     */
    protected function generate_unique_shortname(string $basename): string {
        global $DB;

        // Clean the base name.
        $shortname = clean_param(substr($basename, 0, 200), PARAM_ALPHANUMEXT);
        $shortname = str_replace(' ', '_', $shortname);

        // Add timestamp for uniqueness.
        $shortname .= '_' . time();

        // Ensure it doesn't exist.
        $counter = 0;
        $candidate = $shortname;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $counter++;
            $candidate = $shortname . '_' . $counter;
        }

        return $candidate;
    }

    /**
     * Get the category ID for the template course.
     *
     * @param int $templateid The template course ID.
     * @return int The category ID.
     */
    protected function get_template_category_id(int $templateid): int {
        global $DB;
        return (int) $DB->get_field('course', 'category', ['id' => $templateid]);
    }

    /**
     * Get the document slot definitions.
     *
     * @return array The document slot definitions.
     */
    public static function get_document_slots(): array {
        return self::DOCUMENT_SLOTS;
    }
}
