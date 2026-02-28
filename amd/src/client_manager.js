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
 * Client manager AMD module for ISP Manager.
 *
 * Handles client list interactions including confirmation dialogs for archive actions.
 *
 * @module     local_dsl_isp/client_manager
 * @copyright  2026 Direct Support Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification', 'core/str'], function(Notification, Str) {

    /**
     * Initialize the client manager module.
     *
     * @param {Object} config Configuration object.
     * @param {string} config.sesskey The session key.
     */
    var init = function(config) {
        // Store config.
        this.sesskey = config.sesskey || '';

        // Initialize event listeners.
        initEventListeners();
    };

    /**
     * Initialize event listeners for the client list page.
     */
    var initEventListeners = function() {
        // Archive confirmation.
        document.addEventListener('click', function(e) {
            var archiveLink = e.target.closest('[data-action="archive"]');
            if (archiveLink) {
                e.preventDefault();
                handleArchiveClick(archiveLink);
            }
        });

        // Filter form auto-submit on select change (optional enhancement).
        var filterForm = document.querySelector('.local-dsl-isp-client-list form');
        if (filterForm) {
            var selects = filterForm.querySelectorAll('select');
            selects.forEach(function(select) {
                select.addEventListener('change', function() {
                    // Optional: auto-submit on filter change.
                    // filterForm.submit();
                });
            });
        }

        // Search input debounce (optional enhancement).
        var searchInput = document.querySelector('#search');
        if (searchInput) {
            var debounceTimer = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                // Optional: auto-submit after delay.
                // debounceTimer = setTimeout(function() {
                //     filterForm.submit();
                // }, 500);
            });
        }
    };

    /**
     * Handle archive button click with confirmation.
     *
     * @param {HTMLElement} link The archive link element.
     */
    var handleArchiveClick = function(link) {
        var clientId = link.dataset.clientid;
        var href = link.href;

        // Get confirmation strings.
        var stringRequests = [
            {key: 'confirm_archiveclient', component: 'local_dsl_isp'},
            {key: 'confirm_archiveclient_desc', component: 'local_dsl_isp'},
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
                    // Confirmed - navigate to archive URL.
                    window.location.href = href;
                }
            );
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    /**
     * Refresh the client list via AJAX.
     *
     * @param {Object} filters The filter parameters.
     * @return {Promise} Promise resolving when refresh completes.
     */
    var refreshClientList = function(filters) {
        // This could be used for AJAX-based filtering in the future.
        // For now, the page uses standard form submission.
        var url = new URL(window.location.href);

        if (filters.search !== undefined) {
            url.searchParams.set('search', filters.search);
        }
        if (filters.servicetype !== undefined) {
            url.searchParams.set('servicetype', filters.servicetype);
        }
        if (filters.completionstatus !== undefined) {
            url.searchParams.set('completionstatus', filters.completionstatus);
        }
        url.searchParams.set('page', '0'); // Reset to first page.

        window.location.href = url.toString();

        return Promise.resolve();
    };

    /**
     * Update the URL without page reload (for bookmarking).
     *
     * @param {Object} params The parameters to update.
     */
    var updateUrl = function(params) {
        var url = new URL(window.location.href);

        Object.keys(params).forEach(function(key) {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });

        window.history.replaceState({}, '', url.toString());
    };

    // Export public methods.
    return {
        init: init,
        refreshClientList: refreshClientList,
        updateUrl: updateUrl
    };
});
