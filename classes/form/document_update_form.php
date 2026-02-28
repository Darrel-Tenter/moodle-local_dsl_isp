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

namespace local_dsl_isp\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;
use local_dsl_isp\course_builder;

/**
 * Form for updating documents on an existing client.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_update_form extends moodleform {

    /** @var int Maximum file size in bytes. */
    protected int $maxbytes;

    /** @var int The client ID. */
    protected int $clientid;

    /** @var array Current document data for the client. */
    protected array $currentdocs;

    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        // Get custom data.
        $this->clientid = $this->_customdata['clientid'] ?? 0;
        $this->currentdocs = $this->_customdata['currentdocs'] ?? [];

        // Calculate max file size.
        $maxsizemb = get_config('local_dsl_isp', 'max_file_size_mb') ?: 10;
        $this->maxbytes = $maxsizemb * 1024 * 1024;

        // Hidden fields.
        $mform->addElement('hidden', 'clientid', $this->clientid);
        $mform->setType('clientid', PARAM_INT);

        // Header.
        $mform->addElement('header', 'documentsheader', get_string('updatedocuments', 'local_dsl_isp'));

        // Help text.
        $mform->addElement('static', 'documenthelp', '',
            get_string('acceptedfiletypes', 'local_dsl_isp') . '. ' .
            get_string('maxfilesize', 'local_dsl_isp', ['a' => $maxsizemb])
        );

        // Add document slots.
        $slots = course_builder::get_document_slots();

        foreach ($slots as $index => $slot) {
            $this->add_document_slot($index, $slot);
        }

        // Action buttons.
        $this->add_action_buttons(true, get_string('save', 'local_dsl_isp'));
    }

    /**
     * Add a single document slot to the form.
     *
     * @param int $index The slot index.
     * @param array $slot The slot definition.
     */
    protected function add_document_slot(int $index, array $slot): void {
        $mform = $this->_form;

        $slotname = "doc_{$index}";
        $labelkey = 'docslot_' . $slot['shortname'];
        $label = get_string($labelkey, 'local_dsl_isp');

        // Get current document info for this slot.
        $currentdoc = $this->currentdocs[$index] ?? null;

        // Show current file info if exists.
        if ($currentdoc && $currentdoc['hasfile']) {
            $currentinfo = html_writer::tag('div',
                html_writer::tag('strong', get_string('filename', 'local_dsl_isp') . ': ') .
                s($currentdoc['filename']) .
                html_writer::tag('br') .
                html_writer::tag('strong', get_string('lastupdated', 'local_dsl_isp') . ': ') .
                userdate($currentdoc['timemodified']),
                ['class' => 'alert alert-info']
            );
            $mform->addElement('static', "{$slotname}_current", $label, $currentinfo);
        } else {
            $mform->addElement('static', "{$slotname}_current", $label,
                html_writer::tag('div', get_string('nodocuments', 'local_dsl_isp'), ['class' => 'text-muted'])
            );
        }

        // Checkbox to replace the document.
        $mform->addElement('checkbox', "{$slotname}_replace", '', get_string('includedocument', 'local_dsl_isp'));
        $mform->setDefault("{$slotname}_replace", 0);

        // File upload.
        $fileoptions = [
            'maxbytes' => $this->maxbytes,
            'accepted_types' => ['.pdf'],
            'maxfiles' => 1,
        ];

        $mform->addElement('filepicker', "{$slotname}_file", '', null, $fileoptions);
        $mform->setType("{$slotname}_file", PARAM_FILE);
        $mform->hideIf("{$slotname}_file", "{$slotname}_replace", 'notchecked');

        // Document name field.
        $mform->addElement('text', "{$slotname}_name", get_string('documentname', 'local_dsl_isp'), ['size' => 50]);
        $mform->setType("{$slotname}_name", PARAM_TEXT);

        // Pre-fill with current value or default.
        if ($currentdoc && !empty($currentdoc['fieldvalue'])) {
            // Try to extract name from fieldvalue (format: "Name Date").
            $parts = $this->parse_field_value($currentdoc['fieldvalue']);
            $mform->setDefault("{$slotname}_name", $parts['name']);
        } else {
            $mform->setDefault("{$slotname}_name", $slot['name']);
        }
        $mform->hideIf("{$slotname}_name", "{$slotname}_replace", 'notchecked');

        // Document date field.
        $mform->addElement('text', "{$slotname}_date", get_string('documentdate', 'local_dsl_isp'), ['size' => 20]);
        $mform->setType("{$slotname}_date", PARAM_TEXT);

        // Pre-fill with current value if available.
        if ($currentdoc && !empty($currentdoc['fieldvalue'])) {
            $parts = $this->parse_field_value($currentdoc['fieldvalue']);
            $mform->setDefault("{$slotname}_date", $parts['date']);
        }
        $mform->hideIf("{$slotname}_date", "{$slotname}_replace", 'notchecked');

        // Date help text.
        $mform->addElement('static', "{$slotname}_datehelp", '',
            '<small class="text-muted">' . get_string('datereadfromdoc', 'local_dsl_isp') . '</small>'
        );
        $mform->hideIf("{$slotname}_datehelp", "{$slotname}_replace", 'notchecked');
    }

    /**
     * Parse a field value into name and date components.
     *
     * Field values are stored as "Name Date" where Date is typically in M.D.YY format.
     *
     * @param string $fieldvalue The field value.
     * @return array Array with 'name' and 'date' keys.
     */
    protected function parse_field_value(string $fieldvalue): array {
        // Try to match a date pattern at the end (e.g., 7.1.25 or 07/01/2025).
        if (preg_match('/^(.+?)\s+(\d{1,2}[\.\/-]\d{1,2}[\.\/-]\d{2,4})$/', trim($fieldvalue), $matches)) {
            return [
                'name' => trim($matches[1]),
                'date' => trim($matches[2]),
            ];
        }

        // No date found, return the whole thing as name.
        return [
            'name' => trim($fieldvalue),
            'date' => '',
        ];
    }

    /**
     * Validate the form data.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @return array Array of errors.
     */
    public function validation($data, $files): array {
        global $USER;

        $errors = parent::validation($data, $files);

        $slots = course_builder::get_document_slots();
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);

        foreach ($slots as $index => $slot) {
            $replacekey = "doc_{$index}_replace";
            $filekey = "doc_{$index}_file";
            $namekey = "doc_{$index}_name";
            $datekey = "doc_{$index}_date";

            // If replace is checked, file, name, and date are required.
            if (!empty($data[$replacekey])) {
                $draftitemid = $data[$filekey] ?? 0;

                if (empty($draftitemid)) {
                    $errors[$filekey] = get_string('error_documentrequired', 'local_dsl_isp');
                } else {
                    $draftfiles = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, '', false);
                    if (empty($draftfiles)) {
                        $errors[$filekey] = get_string('error_documentrequired', 'local_dsl_isp');
                    }
                }

                if (empty($data[$namekey])) {
                    $errors[$namekey] = get_string('error_documentnamerequired', 'local_dsl_isp');
                }

                if (empty($data[$datekey])) {
                    $errors[$datekey] = get_string('error_documentdaterequired', 'local_dsl_isp');
                }
            }
        }

        return $errors;
    }

    /**
     * Get the document data to update.
     *
     * @return array Array of document data indexed by slot number.
     */
    public function get_document_data(): array {
        global $USER;

        $data = $this->get_data();
        if (!$data) {
            return [];
        }

        $documents = [];
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $slots = course_builder::get_document_slots();

        foreach ($slots as $index => $slot) {
            $replacekey = "doc_{$index}_replace";
            $filekey = "doc_{$index}_file";
            $namekey = "doc_{$index}_name";
            $datekey = "doc_{$index}_date";

            // Only process if replace is checked.
            if (empty($data->$replacekey)) {
                continue;
            }

            $draftitemid = $data->$filekey ?? 0;

            if (empty($draftitemid)) {
                continue;
            }

            // Get the file from draft area.
            $files = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, '', false);

            if (empty($files)) {
                continue;
            }

            $file = reset($files);

            $documents[$index] = [
                'file' => $file,
                'name' => $data->$namekey ?? $slot['name'],
                'date' => $data->$datekey ?? '',
            ];
        }

        return $documents;
    }
}
