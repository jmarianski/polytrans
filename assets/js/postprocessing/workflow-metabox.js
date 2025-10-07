/**
 * Workflow Meta Box JavaScript
 * Handles quick workflow execution from post editor
 */

(function($) {
    'use strict';

    const WorkflowMetabox = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkExecutionStatus();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Quick execute button
            $(document).on('click', '.workflow-quick-execute', this.handleQuickExecute.bind(this));
        },

        /**
         * Handle quick execute
         */
        handleQuickExecute: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const workflowId = $button.data('workflow-id');
            const workflowName = $button.data('workflow-name');
            const postId = $button.data('post-id');

            // Confirm
            if (!confirm(polytransMetabox.strings.confirmExecute + '\n\n' + workflowName)) {
                return;
            }

            // Disable button
            $button.prop('disabled', true).text(polytransMetabox.strings.executing);

            // Execute
            $.ajax({
                url: polytransMetabox.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_metabox_quick_execute',
                    nonce: polytransMetabox.nonce,
                    workflow_id: workflowId,
                    post_id: postId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess($button, workflowName);
                        
                        // Start polling for status
                        if (response.data.execution_id) {
                            this.pollExecutionStatus(response.data.execution_id, $button);
                        }
                    } else {
                        this.showError($button, response.data.message || 'Execution failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.showError($button, error);
                }
            });
        },

        /**
         * Poll execution status
         */
        pollExecutionStatus: function(executionId, $button) {
            const interval = setInterval(() => {
                $.ajax({
                    url: polytransMetabox.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'polytrans_check_execution_status',
                        nonce: polytransMetabox.nonce,
                        execution_id: executionId
                    },
                    success: (response) => {
                        if (response.success && response.data) {
                            const status = response.data.status;
                            
                            if (status === 'completed') {
                                clearInterval(interval);
                                this.showComplete($button);
                            } else if (status === 'error' || status === 'failed') {
                                clearInterval(interval);
                                this.showError($button, response.data.message || 'Execution failed');
                            }
                            // Keep polling if still running
                        }
                    }
                });
            }, 2000); // Poll every 2 seconds

            // Stop polling after 5 minutes
            setTimeout(() => {
                clearInterval(interval);
            }, 5 * 60 * 1000);
        },

        /**
         * Show success state
         */
        showSuccess: function($button, workflowName) {
            const $item = $button.closest('.workflow-item');
            $item.css({
                'background': '#e7f7ed',
                'border-color': '#46b450'
            });
            
            $button.text('✓ Started').css('color', '#46b450');
        },

        /**
         * Show complete state
         */
        showComplete: function($button) {
            $button
                .prop('disabled', false)
                .text('✓ Completed')
                .css('color', '#46b450');
            
            // Show notice
            this.showNotice('success', 'Workflow completed successfully!');
        },

        /**
         * Show error state
         */
        showError: function($button, message) {
            $button
                .prop('disabled', false)
                .text(polytransMetabox.strings.execute)
                .css('color', '');
            
            const $item = $button.closest('.workflow-item');
            $item.css({
                'background': '#fff',
                'border-color': '#ddd'
            });
            
            // Show notice
            this.showNotice('error', message);
        },

        /**
         * Show notice
         */
        showNotice: function(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.polytrans-workflows-metabox').prepend($notice);
            
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        /**
         * Check for running execution on page load
         */
        checkExecutionStatus: function() {
            const $statusDiv = $('.polytrans-execution-status');
            if ($statusDiv.length === 0) {
                return;
            }

            const executionId = $statusDiv.data('execution-id');
            if (!executionId) {
                return;
            }

            // Poll for status
            const interval = setInterval(() => {
                $.ajax({
                    url: polytransMetabox.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'polytrans_check_execution_status',
                        nonce: polytransMetabox.nonce,
                        execution_id: executionId
                    },
                    success: (response) => {
                        if (response.success && response.data) {
                            const status = response.data.status;
                            
                            if (status === 'completed') {
                                clearInterval(interval);
                                $statusDiv.html('<div class="notice notice-success inline"><p>✓ Workflow completed successfully!</p></div>');
                            } else if (status === 'error' || status === 'failed') {
                                clearInterval(interval);
                                $statusDiv.html('<div class="notice notice-error inline"><p>✗ Workflow failed</p></div>');
                            }
                        }
                    }
                });
            }, 2000);

            // Stop after 5 minutes
            setTimeout(() => {
                clearInterval(interval);
            }, 5 * 60 * 1000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WorkflowMetabox.init();
    });

})(jQuery);
