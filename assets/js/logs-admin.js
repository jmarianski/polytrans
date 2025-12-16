/**
 * PolyTrans Logs Admin JavaScript
 * 
 * Handles logs page functionality: pagination, auto-refresh, context toggling
 * 
 * @package PolyTrans
 * @since 1.7.0
 */

(function($) {
    'use strict';

    const PolyTransLogs = {
        autoRefreshInterval: null,
        isPaused: false,
        currentInterval: parseInt(localStorage.getItem('polytrans_logs_refresh_interval')) || 10,
        totalPages: 1,

        /**
         * Initialize
         */
        init: function(totalPages) {
            this.totalPages = totalPages || 1;
            
            // Context toggle functionality (delegated to work with AJAX-loaded content)
            $(document).on('click', '.toggle-context', function() {
                const contextId = $(this).data('context-id');
                $('#' + contextId).toggle();

                const buttonText = $(this).text() === PolyTransLogsAdmin.strings.showContext ?
                    PolyTransLogsAdmin.strings.hideContext :
                    PolyTransLogsAdmin.strings.showContext;

                $(this).text(buttonText);
            });

            // Handle pagination link clicks
            $(document).on('click', '.pagination-links a', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                const urlParams = new URLSearchParams(url.split('?')[1]);
                const page = urlParams.get('paged') || 1;

                // Update the form's paged input or add it if it doesn't exist
                let $pagedInput = $('#logs-filter-form input[name="paged"]');
                if ($pagedInput.length) {
                    $pagedInput.val(page);
                } else {
                    $('#logs-filter-form').append('<input type="hidden" name="paged" value="' + page + '">');
                }

                // Update the URL without reloading
                if (window.history && window.history.pushState) {
                    window.history.pushState(null, '', url);
                }

                // Update the page counter (will be updated again after refresh with correct total)
                PolyTransLogs.updatePageCounter(parseInt(page), PolyTransLogs.totalPages);

                // Refresh the logs table
                PolyTransLogs.refreshLogsTable();
            });

            // Handle interval change
            $('#autorefresh-interval').on('change', function() {
                PolyTransLogs.currentInterval = parseInt($(this).val());
                localStorage.setItem('polytrans_logs_refresh_interval', PolyTransLogs.currentInterval);
                if (PolyTransLogs.currentInterval === 0) {
                    PolyTransLogs.isPaused = false;
                    $('#pause-autorefresh').text(PolyTransLogsAdmin.strings.pause);
                    if (PolyTransLogs.autoRefreshInterval) {
                        clearInterval(PolyTransLogs.autoRefreshInterval);
                    }
                } else {
                    if (PolyTransLogs.isPaused) {
                        PolyTransLogs.isPaused = false;
                        $('#pause-autorefresh').text(PolyTransLogsAdmin.strings.pause);
                    }
                    PolyTransLogs.startAutoRefresh();
                }
                PolyTransLogs.updateStatus();
            });

            // Handle pause/resume button
            $('#pause-autorefresh').on('click', function() {
                if (PolyTransLogs.currentInterval === 0) {
                    return; // Do nothing if auto-refresh is disabled
                }

                PolyTransLogs.isPaused = !PolyTransLogs.isPaused;
                $(this).text(PolyTransLogs.isPaused ? PolyTransLogsAdmin.strings.resume : PolyTransLogsAdmin.strings.pause);

                if (PolyTransLogs.isPaused) {
                    if (PolyTransLogs.autoRefreshInterval) {
                        clearInterval(PolyTransLogs.autoRefreshInterval);
                    }
                } else {
                    PolyTransLogs.startAutoRefresh();
                }
                PolyTransLogs.updateStatus();
            });

            // Handle manual refresh button
            $('#manual-refresh').on('click', function() {
                PolyTransLogs.refreshLogsTable();
            });

            // Handle browser back/forward buttons
            window.addEventListener('popstate', function(event) {
                // Parse the current URL to get the page number
                const urlParams = new URLSearchParams(window.location.search);
                const page = urlParams.get('paged') || 1;

                // Update the form's paged input
                const $pagedInput = $('#logs-filter-form input[name="paged"]');
                if ($pagedInput.length) {
                    $pagedInput.val(page);
                }

                // Refresh the logs table
                PolyTransLogs.refreshLogsTable();
            });

            // Initialize
            $('#autorefresh-interval').val(PolyTransLogs.currentInterval);
            PolyTransLogs.startAutoRefresh();
            PolyTransLogs.updateStatus();
        },

        /**
         * Update page counter in the UI
         */
        updatePageCounter: function(page, totalPages) {
            totalPages = totalPages || this.totalPages;
            const pageText = page + ' of ' + totalPages;
            
            // Update pagination counter text (WordPress uses .tablenav-paging-text)
            $('.tablenav-pages .tablenav-paging-text').each(function() {
                $(this).text(pageText);
            });
            
            // Also update pagination input field if it exists (WordPress admin style)
            $('.tablenav-pages input[name="paged"]').val(page);
            
            // Update the active page link number if it exists
            $('.pagination-links .current').text(page);
        },

        /**
         * Refresh logs table via AJAX
         */
        refreshLogsTable: function() {
            // Get current form data to maintain filters and pagination
            const formData = $('#logs-filter-form').serialize();

            // Show loading indicator
            $('#autorefresh-status').text(PolyTransLogsAdmin.strings.refreshing);

            $.ajax({
                url: PolyTransLogsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_refresh_logs',
                    nonce: PolyTransLogsAdmin.nonce,
                    filters: formData
                },
                success: function(response) {
                    if (response.success) {
                        $('#logs-table-container').html(response.data.html);

                        // Update the page counter after table refresh
                        const currentPage = response.data.current_page || 
                            $('#logs-filter-form input[name="paged"]').val() || 
                            (new URLSearchParams(window.location.search)).get('paged') || 
                            1;
                        const totalPages = response.data.total_pages || PolyTransLogs.totalPages;
                        
                        // Update total pages for future use
                        PolyTransLogs.totalPages = totalPages;
                        
                        // Update page counter with correct total pages
                        PolyTransLogs.updatePageCounter(parseInt(currentPage), parseInt(totalPages));
                        
                        // Update the top pagination HTML (outside logs-table-container)
                        if (response.data.top_pagination) {
                            $('#logs-table-container').siblings('.tablenav.bottom').find('.tablenav-pages').html(response.data.top_pagination);
                        }
                    }
                    // Restore status text
                    PolyTransLogs.updateStatus();
                },
                error: function() {
                    console.log('Failed to refresh logs');
                    // Restore status text
                    PolyTransLogs.updateStatus();
                }
            });
        },

        /**
         * Update auto-refresh status text
         */
        updateStatus: function() {
            const statusElement = $('#autorefresh-status');
            if (this.currentInterval === 0) {
                statusElement.text(PolyTransLogsAdmin.strings.autoRefreshDisabled);
            } else if (this.isPaused) {
                statusElement.text(PolyTransLogsAdmin.strings.paused);
            } else {
                statusElement.text(PolyTransLogsAdmin.strings.autoRefreshingEvery + ' ' + this.currentInterval + ' ' + PolyTransLogsAdmin.strings.seconds);
            }
        },

        /**
         * Start auto-refresh interval
         */
        startAutoRefresh: function() {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
            }

            if (this.currentInterval > 0 && !this.isPaused) {
                this.autoRefreshInterval = setInterval(function() {
                    PolyTransLogs.refreshLogsTable();
                }, this.currentInterval * 1000);
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Get total pages from data attribute or default
        const totalPages = $('#logs-filter-form').data('total-pages') || 1;
        PolyTransLogs.init(totalPages);
    });

})(jQuery);

