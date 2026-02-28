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
use local_dsl_isp\feature_gate;
use local_dsl_isp\enrollment_manager;

/**
 * Form for assigning a DSP to an existing client.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dsp_form extends moodleform {

    /** @var int The tenant ID for this form. */
    protected int $tenantid;

    /** @var int The client ID. */
    protected int $clientid;

    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        // Get custom data.
        $this->tenantid = $this->_customdata['tenantid'] ?? 0;
        $this->clientid = $this->_customdata['clientid'] ?? 0;

        // Hidden fields.
        $mform->addElement('hidden', 'clientid', $this->clientid);
        $mform->setType('clientid', PARAM_INT);

        $mform->addElement('hidden', 'tenantid', $this->tenantid);
        $mform->setType('tenantid', PARAM_INT);

        // User autocomplete for DSP selection.
        $options = [
            'multiple' => false,
            'ajax' => 'local_dsl_isp/dsp_selector',
            'valuehtmlcallback' => function($userid) {
                global $DB;
                $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');
                if ($user) {
                    return fullname($user) . ' (' . $user->email . ')';
                }
                return '';
            },
            'noselectionstring' => get_string('searchusers', 'local_dsl_isp'),
        ];

        $mform->addElement(
            'autocomplete',
            'userid',
            get_string('selectdsp', 'local_dsl_isp'),
            [],
            $options
        );
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', get_string('error_dspnotfound', 'local_dsl_isp'), 'required');

        // Action buttons.
        $this->add_action_buttons(true, get_string('assigndsp', 'local_dsl_isp'));
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

        if (empty($data['userid'])) {
            $errors['userid'] = get_string('error_dspnotfound', 'local_dsl_isp');
            return $errors;
        }

        $userid = (int) $data['userid'];

        // Verify user exists and is not deleted.
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            $errors['userid'] = get_string('error_dspnotfound', 'local_dsl_isp');
            return $errors;
        }

        // Verify user is in the tenant.
        if (!feature_gate::user_in_tenant($userid, $this->tenantid)) {
            $errors['userid'] = get_string('error_usernotintenant', 'local_dsl_isp');
            return $errors;
        }

        // Check if already assigned.
        $enrollmentmanager = new enrollment_manager();
        if ($enrollmentmanager->is_dsp_assigned($this->clientid, $userid)) {
            $errors['userid'] = get_string('error_dspalreadyassigned', 'local_dsl_isp');
            return $errors;
        }

        return $errors;
    }

    /**
     * Get the selected user ID from the form.
     *
     * @return int|null The user ID or null.
     */
    public function get_userid(): ?int {
        $data = $this->get_data();

        if (!$data || empty($data->userid)) {
            return null;
        }

        return (int) $data->userid;
    }
}
