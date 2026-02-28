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

namespace local_dsl_isp\event;

use core\event\base;

/**
 * Event fired when a DSP is assigned to a client.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dsp_assigned extends base {

    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'dsl_isp_dsp';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventdspassigned', 'local_dsl_isp');
    }

    /**
     * Get the event description.
     *
     * @return string
     */
    public function get_description(): string {
        $clientid = $this->other['clientid'] ?? 'Unknown';
        return "The user with id '{$this->userid}' assigned DSP with id '{$this->relateduserid}' " .
               "to ISP client with id '{$clientid}'.";
    }

    /**
     * Get the URL related to this event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/dsl_isp/client.php', [
            'id' => $this->other['clientid'],
            'action' => 'view',
        ]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (!isset($this->other['clientid'])) {
            throw new \coding_exception('The \'clientid\' value must be set in other.');
        }
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' value must be set.');
        }
    }
}
