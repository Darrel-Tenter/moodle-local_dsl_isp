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

/**
 * DSP assignment AMD module for ISP Manager.
 *
 * Handles DSP assignment interactions including confirmation dialogs
 * for remove and reset actions.
 *
 * @module     local_dsl_isp/dsp_assignment
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification', 'core/str', 'core/ajax'], function(Notification, Str, Ajax) {

    /**
     * Module configuration.
     */
    var config = {
        clientId: 0,
        clientName: '',
        sesskey: ''
    };

    /**
     * Initialize the DSP assignment module.
     *
     * @param {Object} options Configuration options.
     * @param {number} options.clientId The client ID.
     * @param {string} options.clientName The client name.
     * @param {string} options.sesskey The session key.
     */
    var init = function(options) {
        config.clientId = options.clientId || 0;
        config.clientName = options.clientName || '';
        config.sesskey = options.sesskey || '';

        initEventListeners();
    };

    /**
     * Initialize event listeners.
     */
    var initEventListeners = function() {
        var container = document.querySelector('.local-dsl-isp-client-detail');
        if (!container) {
            return;
        }

        // Remove DSP confirmation.
        container.addEventListener('click', function(e) {
            var removeLink = e.target.closest('[data-action="remove"]');
            if (removeLink) {
                e.preventDefault();
                handleRemoveClick(removeLink);
                return;
            }

            var resetLink = e.target.closest('[data-action="reset"]');
            if (resetLink) {
                e.preventDefault();
                handleResetClick(resetLink);
                return;
            }
        });
    };

    /**
     * Handle remove DSP button click.
     *
     * @param {HTMLElement} link The remove link element.
     */
    var handleRemoveClick = function(link) {
        var userId = link.dataset.userid;
        var userName = link.dataset.username;
        var href = link.href;

        // Get confirmation strings with placeholders.
        var stringRequests = [
            {key: 'confirm_removedsp', component: 'local_dsl_isp'},
            {key: 'confirm_removedsp_desc', component: 'local_dsl_isp', param: {
                dspname: userName,
                clientname: config.clientName
            }},
            {key: 'yes', component: 'local_dsl_isp'},
            {key: 'no', component: 'local_dsl_isp'}
        ];

        Str.get_strings(stringRequests).then(function(strings) {
            return Notification.confirm(
                strings[0], // Title.
                strings[1], // Message.
                strings[2], // Yes label.
                strings[3], // No label.
                function() {
                    // Confirmed - navigate to remove URL.
                    window.location.href = href;
                }
            );
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    /**
     * Handle reset completion button click.
     *
     * @param {HTMLElement} link The reset link element.
     */
    var handleResetClick = function(link) {
        var userId = link.dataset.userid;
        var userName = link.dataset.username;
        var href = link.href;

        // Get confirmation strings with placeholders.
        var stringRequests = [
            {key: 'confirm_resetcompletion', component: 'local_dsl_isp'},
            {key: 'confirm_resetcompletion_desc', component: 'local_dsl_isp', param: {
                dspname: userName,
                clientname: config.clientName
            }},
            {key: 'yes', component: 'local_dsl_isp'},
            {key: 'no', component: 'local_dsl_isp'}
        ];

        Str.get_strings(stringRequests).then(function(strings) {
            return Notification.confirm(
                strings[0], // Title.
                strings[1], // Message.
                strings[2], // Yes label.
                strings[3], // No label.
                function() {
                    // Confirmed - navigate to reset URL.
                    window.location.href = href;
                }
            );
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    /**
     * Show loading indicator on a row.
     *
     * @param {number} userId The user ID.
     */
    var showRowLoading = function(userId) {
        var row = document.querySelector('tr[data-userid="' + userId + '"]');
        if (row) {
            row.classList.add('loading');
            var actions = row.querySelector('td:last-child');
            if (actions) {
                actions.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            }
        }
    };

    /**
     * Remove loading indicator from a row.
     *
     * @param {number} userId The user ID.
     */
    var hideRowLoading = function(userId) {
        var row = document.querySelector('tr[data-userid="' + userId + '"]');
        if (row) {
            row.classList.remove('loading');
        }
    };

    /**
     * Remove a DSP row from the table.
     *
     * @param {number} userId The user ID.
     */
    var removeDspRow = function(userId) {
        var row = document.querySelector('tr[data-userid="' + userId + '"]');
        if (row) {
            row.remove();
        }

        // Update count if displayed.
        updateDspCount();
    };

    /**
     * Update the DSP count display.
     */
    var updateDspCount = function() {
        var table = document.querySelector('.local-dsl-isp-client-detail table');
        if (!table) {
            return;
        }

        var rows = table.querySelectorAll('tbody tr');
        var count = rows.length;

        // If no rows left, show the "no DSPs" message.
        if (count === 0) {
            var tbody = table.querySelector('tbody');
            if (tbody) {
                // Reload the page to show the empty state properly.
                window.location.reload();
            }
        }
    };

    // Export public methods.
    return {
        init: init
    };
});
