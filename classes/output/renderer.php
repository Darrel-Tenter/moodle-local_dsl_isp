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

use plugin_renderer_base;
use renderable;

/**
 * Renderer for the ISP Manager plugin.
 *
 * @package    local_dsl_isp
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the client list page.
     *
     * @param client_list $clientlist The client list renderable.
     * @return string The rendered HTML.
     */
    protected function render_client_list(client_list $clientlist): string {
        $data = $clientlist->export_for_template($this);
        return $this->render_from_template('local_dsl_isp/client_list', $data);
    }

    /**
     * Render the client detail page.
     *
     * @param client_detail $clientdetail The client detail renderable.
     * @return string The rendered HTML.
     */
    protected function render_client_detail(client_detail $clientdetail): string {
        $data = $clientdetail->export_for_template($this);
        return $this->render_from_template('local_dsl_isp/client_detail', $data);
    }

    /**
     * Render a single client card.
     *
     * @param array $clientdata The client data array.
     * @return string The rendered HTML.
     */
    public function render_client_card(array $clientdata): string {
        return $this->render_from_template('local_dsl_isp/client_card', $clientdata);
    }

    /**
     * Render a completion status indicator.
     *
     * @param array $statusdata The status data array.
     * @return string The rendered HTML.
     */
    public function render_completion_status(array $statusdata): string {
        return $this->render_from_template('local_dsl_isp/completion_status', $statusdata);
    }
}
