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

/**
 * Renderable for the client list page.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_list implements renderable, templatable {

    /** @var array The list of clients. */
    protected array $clients;

    /** @var int Total number of clients (for pagination). */
    protected int $total;

    /** @var int Current page number. */
    protected int $page;

    /** @var int Items per page. */
    protected int $perpage;

    /** @var string Current search string. */
    protected string $search;

    /** @var string Current service type filter. */
    protected string $servicetype;

    /** @var string Current completion status filter. */
    protected string $completionstatus;

    /** @var int The tenant ID. */
    protected int $tenantid;

    /** @var manager The manager instance. */
    protected manager $manager;

    /** @var bool Whether the user can manage clients. */
    protected bool $canmanage;

    /**
     * Constructor.
     *
     * @param array $clients The list of clients.
     * @param int $total Total number of clients.
     * @param int $page Current page number.
     * @param int $perpage Items per page.
     * @param string $search Current search string.
     * @param string $servicetype Current service type filter.
     * @param string $completionstatus Current completion status filter.
     * @param int $tenantid The tenant ID.
     * @param bool $canmanage Whether the user can manage clients.
     */
    public function __construct(
        array $clients,
        int $total,
        int $page,
        int $perpage,
        string $search,
        string $servicetype,
        string $completionstatus,
        int $tenantid,
        bool $canmanage
    ) {
        $this->clients = $clients;
        $this->total = $total;
        $this->page = $page;
        $this->perpage = $perpage;
        $this->search = $search;
        $this->servicetype = $servicetype;
        $this->completionstatus = $completionstatus;
        $this->tenantid = $tenantid;
        $this->canmanage = $canmanage;
        $this->manager = new manager($tenantid);
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template data.
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE;

        $data = [
            'hasclients' => !empty($this->clients),
            'clients' => [],
            'total' => $this->total,
            'clientcounttext' => get_string('clientcount', 'local_dsl_isp', ['count' => $this->total]),
            'canmanage' => $this->canmanage,
            'addclienturl' => (new moodle_url('/local/dsl_isp/client.php', ['action' => 'add']))->out(false),
            'search' => $this->search,
            'servicetypeoptions' => $this->get_service_type_options(),
            'statusoptions' => $this->get_status_options(),
            'formaction' => (new moodle_url('/local/dsl_isp/index.php'))->out(false),
        ];

        // Process each client.
        foreach ($this->clients as $client) {
            $data['clients'][] = $this->export_client($client);
        }

        // Pagination.
        if ($this->total > $this->perpage) {
            $baseurl = new moodle_url('/local/dsl_isp/index.php', [
                'search' => $this->search,
                'servicetype' => $this->servicetype,
                'completionstatus' => $this->completionstatus,
            ]);

            $pagingbar = new \paging_bar($this->total, $this->page, $this->perpage, $baseurl);
            $data['pagination'] = $output->render($pagingbar);
            $data['haspagination'] = true;
        } else {
            $data['haspagination'] = false;
        }

        // No results message.
        if (empty($this->clients)) {
            if (!empty($this->search) || !empty($this->servicetype) || !empty($this->completionstatus)) {
                $data['noresultsmessage'] = get_string('noclientsmatch', 'local_dsl_isp');
            } else {
                $data['noresultsmessage'] = get_string('noclients', 'local_dsl_isp');
            }
        }

        return $data;
    }

    /**
     * Export a single client for the template.
     *
     * @param stdClass $client The client record.
     * @return array Client data for template.
     */
    protected function export_client(stdClass $client): array {
        // Calculate plan year boundaries.
        $boundaries = $this->manager->get_plan_year_boundaries($client->anniversarydate);

        // Determine completion status.
        $status = $this->manager->calculate_completion_status($client);

        $dspcount = $client->dsp_count ?? 0;
        $completedcount = $client->completed_count ?? 0;

        return [
            'id' => $client->id,
            'firstname' => $client->firstname,
            'lastname' => $client->lastname,
            'fullname' => $client->firstname . ' ' . $client->lastname,
            'servicetype' => $client->servicetype,
            'servicetypelabel' => get_string('servicetype_' . $client->servicetype, 'local_dsl_isp'),
            'anniversarydate' => userdate($client->anniversarydate, get_string('strftimedate', 'langconfig')),
            'planyearstart' => userdate($boundaries['start'], get_string('strftimedate', 'langconfig')),
            'planyearend' => userdate($boundaries['end'], get_string('strftimedate', 'langconfig')),
            'planyeartext' => get_string('planyear', 'local_dsl_isp', [
                'start' => userdate($boundaries['start'], '%b %d, %Y'),
                'end' => userdate($boundaries['end'], '%b %d, %Y'),
            ]),
            'dspcount' => $dspcount,
            'completedcount' => $completedcount,
            'dspcounttext' => get_string('dspcount', 'local_dsl_isp', [
                'completed' => $completedcount,
                'total' => $dspcount,
            ]),
            'progresspercent' => $dspcount > 0 ? round(($completedcount / $dspcount) * 100) : 0,
            'status' => $status,
            'statuslabel' => $this->get_status_label($status),
            'statusclass' => $this->get_status_class($status),
            'iscomplete' => $status === 'complete',
            'isinprogress' => $status === 'inprogress',
            'isoverdue' => $status === 'overdue',
            'isnotstarted' => $status === 'notstarted',
            'viewurl' => (new moodle_url('/local/dsl_isp/client.php', [
                'id' => $client->id,
                'action' => 'view',
            ]))->out(false),
            'managedspsurl' => (new moodle_url('/local/dsl_isp/manage_dsps.php', [
                'clientid' => $client->id,
            ]))->out(false),
            'updatedocsurl' => (new moodle_url('/local/dsl_isp/client.php', [
                'id' => $client->id,
                'action' => 'documents',
            ]))->out(false),
            'canmanage' => $this->canmanage,
        ];
    }

    /**
     * Get service type filter options.
     *
     * @return array Options for template.
     */
    protected function get_service_type_options(): array {
        $options = [
            [
                'value' => '',
                'label' => get_string('allservicetypes', 'local_dsl_isp'),
                'selected' => empty($this->servicetype),
            ],
        ];

        foreach (manager::SERVICE_TYPES as $type) {
            $options[] = [
                'value' => $type,
                'label' => get_string('servicetype_' . $type, 'local_dsl_isp'),
                'selected' => $this->servicetype === $type,
            ];
        }

        return $options;
    }

    /**
     * Get completion status filter options.
     *
     * @return array Options for template.
     */
    protected function get_status_options(): array {
        return [
            [
                'value' => '',
                'label' => get_string('allstatuses', 'local_dsl_isp'),
                'selected' => empty($this->completionstatus),
            ],
            [
                'value' => 'complete',
                'label' => get_string('statuscomplete', 'local_dsl_isp'),
                'selected' => $this->completionstatus === 'complete',
            ],
            [
                'value' => 'inprogress',
                'label' => get_string('statusinprogress', 'local_dsl_isp'),
                'selected' => $this->completionstatus === 'inprogress',
            ],
            [
                'value' => 'overdue',
                'label' => get_string('statusoverdue', 'local_dsl_isp'),
                'selected' => $this->completionstatus === 'overdue',
            ],
        ];
    }

    /**
     * Get human-readable status label.
     *
     * @param string $status The status code.
     * @return string The label.
     */
    protected function get_status_label(string $status): string {
        $key = 'status' . $status;
        return get_string($key, 'local_dsl_isp');
    }

    /**
     * Get Bootstrap badge class for status.
     *
     * @param string $status The status code.
     * @return string The CSS class.
     */
    protected function get_status_class(string $status): string {
        $classes = [
            'complete' => 'badge-success',
            'inprogress' => 'badge-warning',
            'overdue' => 'badge-danger',
            'notstarted' => 'badge-secondary',
        ];

        return $classes[$status] ?? 'badge-secondary';
    }
}
