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
use local_dsl_isp\manager;
use local_dsl_isp\course_builder;

/**
 * Form for adding or editing an ISP client.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_form extends moodleform {

    /** @var int Maximum file size in bytes (calculated from MB setting). */
    protected int $maxbytes;

    /** @var int The tenant ID for this form. */
    protected int $tenantid;

    /** @var int|null The client ID if editing. */
    protected ?int $clientid;

    /** @var bool Whether this is an edit operation. */
    protected bool $isedit;

    /**
     * Form definition.
     */
    protected function definition(): void {
        global $CFG;

        $mform = $this->_form;

        // Get custom data.
        $this->tenantid = $this->_customdata['tenantid'] ?? 0;
        $this->clientid = $this->_customdata['clientid'] ?? null;
        $this->isedit = !empty($this->clientid);

        // Calculate max file size.
        $maxsizemb = get_config('local_dsl_isp', 'max_file_size_mb') ?: 10;
        $this->maxbytes = $maxsizemb * 1024 * 1024;

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'tenantid', $this->tenantid);
        $mform->setType('tenantid', PARAM_INT);

        // Section 1: Client Information.
        $this->add_client_info_section();

        // Section 2: ISP Documents (only for new clients or via separate update form).
        if (!$this->isedit) {
            $this->add_documents_section();
        }

        // Section 3: Assign DSPs (only for new clients).
        if (!$this->isedit) {
            $this->add_dsps_section();
        }

        // Action buttons.
        $this->add_action_buttons(true, $this->isedit ?
            get_string('save', 'local_dsl_isp') :
            get_string('addnewclient', 'local_dsl_isp'));
    }

    /**
     * Add the client information section.
     */
    protected function add_client_info_section(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'clientinfoheader', get_string('clientinformation', 'local_dsl_isp'));

        // First name.
        $mform->addElement('text', 'firstname', get_string('firstname', 'local_dsl_isp'), ['size' => 50]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', get_string('error_firstnamerequired', 'local_dsl_isp'), 'required');
        $mform->addRule('firstname', get_string('error_firstnamerequired', 'local_dsl_isp'), 'maxlength', 100);

        // Last name.
        $mform->addElement('text', 'lastname', get_string('lastname', 'local_dsl_isp'), ['size' => 50]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', get_string('error_lastnamerequired', 'local_dsl_isp'), 'required');
        $mform->addRule('lastname', get_string('error_lastnamerequired', 'local_dsl_isp'), 'maxlength', 100);

        // Service type.
        $servicetypes = manager::get_service_type_options();
        $mform->addElement('select', 'servicetype', get_string('servicetype', 'local_dsl_isp'), $servicetypes);
        $mform->setType('servicetype', PARAM_ALPHANUMEXT);
        $mform->addRule('servicetype', get_string('error_servicetyperequired', 'local_dsl_isp'), 'required');

        // Anniversary date.
        $mform->addElement(
            'date_selector',
            'anniversarydate',
            get_string('anniversarydatefield', 'local_dsl_isp'),
            ['optional' => false]
        );
        $mform->addHelpButton('anniversarydate', 'anniversarydatefield', 'local_dsl_isp');
        $mform->addRule('anniversarydate', get_string('error_anniversarydaterequired', 'local_dsl_isp'), 'required');
    }

    /**
     * Add the documents section.
     */
    protected function add_documents_section(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'documentsheader', get_string('ispdocuments', 'local_dsl_isp'));

        // Help text about accepted file types and size.
        $mform->addElement('static', 'documenthelp', '',
            get_string('acceptedfiletypes', 'local_dsl_isp') . '. ' .
            get_string('maxfilesize', 'local_dsl_isp', ['a' => get_config('local_dsl_isp', 'max_file_size_mb') ?: 10])
        );

        $slots = course_builder::get_document_slots();

        foreach ($slots as $index => $slot) {
            $this->add_document_slot($index, $slot);
        }
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

        // Add required/optional indicator.
        if ($slot['required']) {
            $label .= ' ' . get_string('docslot_required', 'local_dsl_isp');
        } else {
            $label .= ' ' . get_string('docslot_optional', 'local_dsl_isp');
        }

        // For optional slots, add a checkbox to include.
        if (!$slot['required']) {
            $mform->addElement('checkbox', "{$slotname}_include", '', get_string('includedocument', 'local_dsl_isp'));
            $mform->setDefault("{$slotname}_include", 0);
        }

        // File upload.
        $fileoptions = [
            'maxbytes' => $this->maxbytes,
            'accepted_types' => ['.pdf'],
            'maxfiles' => 1,
        ];

        $mform->addElement('filepicker', "{$slotname}_file", $label, null, $fileoptions);
        $mform->setType("{$slotname}_file", PARAM_FILE);

        if ($slot['required']) {
            $mform->addRule("{$slotname}_file", get_string('error_documentrequired', 'local_dsl_isp'), 'required');
        } else {
            $mform->hideIf("{$slotname}_file", "{$slotname}_include", 'notchecked');
        }

        // Document name field.
        $mform->addElement('text', "{$slotname}_name", get_string('documentname', 'local_dsl_isp'), ['size' => 50]);
        $mform->setType("{$slotname}_name", PARAM_TEXT);
        $mform->setDefault("{$slotname}_name", $slot['name']);

        if ($slot['required']) {
            $mform->addRule("{$slotname}_name", get_string('error_documentnamerequired', 'local_dsl_isp'), 'required');
        } else {
            $mform->hideIf("{$slotname}_name", "{$slotname}_include", 'notchecked');
        }

        // Document date field.
        $mform->addElement('text', "{$slotname}_date", get_string('documentdate', 'local_dsl_isp'), ['size' => 20]);
        $mform->setType("{$slotname}_date", PARAM_TEXT);

        if ($slot['required']) {
            $mform->addRule("{$slotname}_date", get_string('error_documentdaterequired', 'local_dsl_isp'), 'required');
        } else {
            $mform->hideIf("{$slotname}_date", "{$slotname}_include", 'notchecked');
        }

        // Add a note about the date being read from the document.
        $mform->addElement('static', "{$slotname}_datehelp", '',
            '<small class="text-muted">' . get_string('datereadfromdoc', 'local_dsl_isp') . '</small>'
        );

        if (!$slot['required']) {
            $mform->hideIf("{$slotname}_datehelp", "{$slotname}_include", 'notchecked');
        }
    }

    /**
     * Add the DSPs section.
     */
    protected function add_dsps_section(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'dspsheader', get_string('assigndsps', 'local_dsl_isp'));

        // Help text.
        $mform->addElement('static', 'dsphelp', '', get_string('dspscanbeaddedlater', 'local_dsl_isp'));

        // User autocomplete for DSP selection.
        $options = [
            'multiple' => true,
            'ajax' => 'local_dsl_isp/dsp_selector',
            'valuehtmlcallback' => function($userid) {
                global $DB;
                $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');
                if ($user) {
                    return fullname($user) . ' (' . $user->email . ')';
                }
                return '';
            },
        ];

        $mform->addElement(
            'autocomplete',
            'dsps',
            get_string('searchdsps', 'local_dsl_isp'),
            [],
            $options
        );
        $mform->setType('dsps', PARAM_INT);
    }

    /**
     * Validate the form data.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @return array Array of errors.
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        // Validate anniversary date is not in the future.
        if (!empty($data['anniversarydate']) && $data['anniversarydate'] > time()) {
            $errors['anniversarydate'] = get_string('error_anniversarydatefuture', 'local_dsl_isp');
        }

        // Validate unique client name within tenant.
        if (!empty($data['firstname']) && !empty($data['lastname'])) {
            $params = [
                'tenantid' => $this->tenantid,
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
            ];

            $sql = "SELECT id FROM {dsl_isp_client}
                     WHERE tenantid = :tenantid
                       AND " . $DB->sql_compare_text('firstname') . " = " . $DB->sql_compare_text(':firstname') . "
                       AND " . $DB->sql_compare_text('lastname') . " = " . $DB->sql_compare_text(':lastname');

            if ($this->isedit) {
                $sql .= " AND id != :clientid";
                $params['clientid'] = $this->clientid;
            }

            if ($DB->record_exists_sql($sql, $params)) {
                $errors['firstname'] = get_string('error_clientnameexists', 'local_dsl_isp');
            }
        }

        // Validate required documents have files.
        if (!$this->isedit) {
            $slots = course_builder::get_document_slots();
            foreach ($slots as $index => $slot) {
                if ($slot['required']) {
                    $filekey = "doc_{$index}_file";
                    $draftitemid = $data[$filekey] ?? 0;

                    if (empty($draftitemid) || !$this->has_draft_files($draftitemid)) {
                        $errors[$filekey] = get_string('error_documentrequired', 'local_dsl_isp');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if a draft area has files.
     *
     * @param int $draftitemid The draft item ID.
     * @return bool True if files exist.
     */
    protected function has_draft_files(int $draftitemid): bool {
        global $USER;

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, '', false);

        return !empty($files);
    }

    /**
     * Get the uploaded document files from the form.
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
            $filekey = "doc_{$index}_file";
            $namekey = "doc_{$index}_name";
            $datekey = "doc_{$index}_date";
            $includekey = "doc_{$index}_include";

            // Check if this slot should be included.
            if (!$slot['required'] && empty($data->$includekey)) {
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

    /**
     * Get the selected DSP user IDs from the form.
     *
     * @return array Array of user IDs.
     */
    public function get_dsp_userids(): array {
        $data = $this->get_data();

        if (!$data || empty($data->dsps)) {
            return [];
        }

        // Ensure we return an array of integers.
        if (is_array($data->dsps)) {
            return array_map('intval', $data->dsps);
        }

        return [(int) $data->dsps];
    }
}
