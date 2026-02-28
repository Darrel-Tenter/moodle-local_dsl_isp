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

namespace local_dsl_isp\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use local_dsl_isp\manager;
use local_dsl_isp\course_builder;
use local_dsl_isp\enrollment_manager;
use local_dsl_isp\completion_manager;

/**
 * Renderable for the client detail page.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_detail implements renderable, templatable {

    /** @var stdClass The client record. */
    protected stdClass $client;

    /** @var int The tenant ID. */
    protected int $tenantid;

    /** @var bool Whether user can manage clients. */
    protected bool $canmanageclients;

    /** @var bool Whether user can manage DSPs. */
    protected bool $canmanagedsps;

    /** @var bool Whether user can reset completion. */
    protected bool $canresetcompletion;

    /** @var bool Whether user can view history. */
    protected bool $canviewhistory;

    /**
     * Constructor.
     *
     * @param stdClass $client The client record.
     * @param int $tenantid The tenant ID.
     * @param bool $canmanageclients Whether user can manage clients.
     * @param bool $canmanagedsps Whether user can manage DSPs.
     * @param bool $canresetcompletion Whether user can reset completion.
     * @param bool $canviewhistory Whether user can view history.
     */
    public function __construct(
        stdClass $client,
        int $tenantid,
        bool $canmanageclients,
        bool $canmanagedsps,
        bool $canresetcompletion,
        bool $canviewhistory
    ) {
        $this->client = $client;
        $this->tenantid = $tenantid;
        $this->canmanageclients = $canmanageclients;
        $this->canmanagedsps = $canmanagedsps;
        $this->canresetcompletion = $canresetcompletion;
        $this->canviewhistory = $canviewhistory;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template data.
     */
    public function export_for_template(renderer_base $output): array {
        $manager = new manager($this->tenantid);
        $coursebuilder = new course_builder();
        $enrollmentmanager = new enrollment_manager();
        $completionmanager = new completion_manager();

        // Get plan year boundaries.
        $boundaries = $manager->get_plan_year_boundaries($this->client->anniversarydate);

        // Get documents.
        $documents = $coursebuilder->get_course_documents($this->client->courseid);

        // Get DSPs.
        $dsps = $enrollmentmanager->get_client_dsps($this->client->id);

        // Get completion history.
        $history = [];
        if ($this->canviewhistory) {
            $history = $completionmanager->get_completion_log($this->client->id);
        }

        // Calculate completion stats.
        $dspcount = count($dsps);
        $completedcount = 0;
        foreach ($dsps as $dsp) {
            if (!empty($dsp->timecompleted)) {
                $completedcount++;
            }
        }

        $data = [
            // Client info.
            'id' => $this->client->id,
            'firstname' => $this->client->firstname,
            'lastname' => $this->client->lastname,
            'fullname' => $this->client->firstname . ' ' . $this->client->lastname,
            'servicetype' => $this->client->servicetype,
            'servicetypelabel' => get_string('servicetype_' . $this->client->servicetype, 'local_dsl_isp'),
            'anniversarydate' => userdate($this->client->anniversarydate, get_string('strftimedate', 'langconfig')),
            'planyearstart' => userdate($boundaries['start'], '%b %d, %Y'),
            'planyearend' => userdate($boundaries['end'], '%b %d, %Y'),
            'status' => $this->client->status,
            'isactive' => $this->client->status == 1,
            'isarchived' => $this->client->status == 0,

            // Completion summary.
            'dspcount' => $dspcount,
            'completedcount' => $completedcount,
            'progresspercent' => $dspcount > 0 ? round(($completedcount / $dspcount) * 100) : 0,

            // Documents section.
            'hasdocuments' => !empty($documents),
            'documents' => $this->export_documents($documents),

            // DSPs section.
            'hasdsps' => !empty($dsps),
            'dsps' => $this->export_dsps($dsps),

            // History section.
            'hashistory' => !empty($history),
            'history' => $this->export_history($history),

            // Capabilities.
            'canmanageclients' => $this->canmanageclients,
            'canmanagedsps' => $this->canmanagedsps,
            'canresetcompletion' => $this->canresetcompletion,
            'canviewhistory' => $this->canviewhistory,

            // URLs.
            'backurl' => (new moodle_url('/local/dsl_isp/index.php'))->out(false),
            'editurl' => (new moodle_url('/local/dsl_isp/client.php', [
                'id' => $this->client->id,
                'action' => 'edit',
            ]))->out(false),
            'documentsurl' => (new moodle_url('/local/dsl_isp/client.php', [
                'id' => $this->client->id,
                'action' => 'documents',
            ]))->out(false),
            'archiveurl' => (new moodle_url('/local/dsl_isp/client.php', [
                'id' => $this->client->id,
                'action' => 'archive',
                'sesskey' => sesskey(),
            ]))->out(false),
            'unarchiveurl' => (new moodle_url('/local/dsl_isp/client.php', [
                'id' => $this->client->id,
                'action' => 'unarchive',
                'sesskey' => sesskey(),
            ]))->out(false),
            'adddspurl' => (new moodle_url('/local/dsl_isp/manage_dsps.php', [
                'clientid' => $this->client->id,
                'action' => 'add',
            ]))->out(false),
            'courseurl' => (new moodle_url('/course/view.php', [
                'id' => $this->client->courseid,
            ]))->out(false),
            'sesskey' => sesskey(),
        ];

        return $data;
    }

    /**
     * Export documents for template.
     *
     * @param array $documents The documents data.
     * @return array Formatted documents.
     */
    protected function export_documents(array $documents): array {
        $result = [];

        foreach ($documents as $index => $doc) {
            $result[] = [
                'slot' => $index,
                'name' => get_string('docslot_' . $doc['shortname'], 'local_dsl_isp'),
                'required' => $doc['required'],
                'hasfile' => $doc['hasfile'],
                'filename' => $doc['filename'] ?? '',
                'filesize' => $doc['filesize'] ? display_size($doc['filesize']) : '',
                'lastupdated' => $doc['timemodified'] ?
                    userdate($doc['timemodified'], get_string('strftimedatetimeshort', 'langconfig')) : '',
                'fieldvalue' => $doc['fieldvalue'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Export DSPs for template.
     *
     * @param array $dsps The DSP records.
     * @return array Formatted DSPs.
     */
    protected function export_dsps(array $dsps): array {
        $result = [];

        foreach ($dsps as $dsp) {
            $iscompleted = !empty($dsp->timecompleted);

            $result[] = [
                'id' => $dsp->id,
                'userid' => $dsp->userid,
                'firstname' => $dsp->firstname,
                'lastname' => $dsp->lastname,
                'fullname' => $dsp->firstname . ' ' . $dsp->lastname,
                'email' => $dsp->email,
                'dateassigned' => userdate($dsp->timeassigned, get_string('strftimedate', 'langconfig')),
                'iscompleted' => $iscompleted,
                'completiondate' => $iscompleted ?
                    userdate($dsp->timecompleted, get_string('strftimedatetimeshort', 'langconfig')) : '',
                'completionstatustext' => $iscompleted ?
                    get_string('completed', 'local_dsl_isp', userdate($dsp->timecompleted, '%b %d, %Y')) :
                    get_string('inprogress', 'local_dsl_isp'),
                'completionstatusclass' => $iscompleted ? 'text-success' : 'text-warning',
                'removeurl' => (new moodle_url('/local/dsl_isp/manage_dsps.php', [
                    'clientid' => $this->client->id,
                    'action' => 'remove',
                    'userid' => $dsp->userid,
                    'sesskey' => sesskey(),
                ]))->out(false),
                'reseturl' => (new moodle_url('/local/dsl_isp/manage_dsps.php', [
                    'clientid' => $this->client->id,
                    'action' => 'reset',
                    'userid' => $dsp->userid,
                    'sesskey' => sesskey(),
                ]))->out(false),
                'canmanagedsps' => $this->canmanagedsps,
                'canresetcompletion' => $this->canresetcompletion,
            ];
        }

        return $result;
    }

    /**
     * Export completion history for template.
     *
     * @param array $history The history records.
     * @return array Formatted history.
     */
    protected function export_history(array $history): array {
        $result = [];

        foreach ($history as $record) {
            $iscompleted = !empty($record->timecompleted);
            $isgap = !$iscompleted;

            $result[] = [
                'id' => $record->id,
                'userid' => $record->userid,
                'dspname' => $record->firstname . ' ' . $record->lastname,
                'planyearstart' => userdate($record->planyearstart, '%b %d, %Y'),
                'planyearend' => userdate($record->planyearend, '%b %d, %Y'),
                'planyear' => userdate($record->planyearstart, '%b %Y') . ' – ' .
                              userdate($record->planyearend, '%b %Y'),
                'iscompleted' => $iscompleted,
                'isgap' => $isgap,
                'completeddate' => $iscompleted ?
                    userdate($record->timecompleted, get_string('strftimedatetimeshort', 'langconfig')) : '',
                'completedtext' => $iscompleted ?
                    userdate($record->timecompleted, '%b %d, %Y') :
                    get_string('gap', 'local_dsl_isp'),
                'archiveddate' => userdate($record->timearchived, get_string('strftimedatetimeshort', 'langconfig')),
                'notes' => $record->notes ?? '',
                'hasmanualreset' => $record->notes === 'manual_reset',
            ];
        }

        return $result;
    }
}
