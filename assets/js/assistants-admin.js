/**
 * AI Assistants Admin Interface
 * 
 * Handles the admin UI for managing AI assistants.
 * Part of Phase 1: AI Assistants Management System.
 */

(function($) {
    'use strict';

    const AssistantsAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initEditor();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Delete assistant
            $(document).on('click', '.assistant-delete', this.handleDelete.bind(this));

            // Save assistant form
            $('#assistant-editor-form').on('submit', this.handleSave.bind(this));

            // Test assistant
            $('#test-assistant-btn').on('click', this.handleTest.bind(this));

            // Provider change - update model suggestions
            $('#assistant-provider').on('change', this.handleProviderChange.bind(this));

            // Migrate workflows
            $('#migrate-workflows-btn').on('click', this.handleMigration.bind(this));
        },

        /**
         * Initialize editor (if on editor page)
         */
        initEditor: function() {
            if (!window.polytransAssistantData) {
                return;
            }

            // Initialize system prompt editor
            if (typeof PolyTransPromptEditor !== 'undefined') {
                const systemContainer = document.getElementById('system-prompt-editor-container');
                if (systemContainer) {
                    this.systemPromptEditor = PolyTransPromptEditor.create(systemContainer, {
                        initialValue: window.polytransAssistantData.system_prompt || '',
                        placeholder: 'Enter system instructions here...\n\nExample: You are a content quality expert. Analyze posts for grammar, SEO, and readability.',
                        rows: 8
                    });
                    this.systemPromptEditor.init();
                }

                // Initialize user message template editor
                const userContainer = document.getElementById('user-message-editor-container');
                if (userContainer) {
                    this.userMessageEditor = PolyTransPromptEditor.create(userContainer, {
                        initialValue: window.polytransAssistantData.user_message_template || '',
                        placeholder: 'Enter user message template here...\n\nYou can use Twig variables like {{ title }}, {{ content }}, etc.\n\nExample:\nTitle: {{ title }}\nContent: {{ content }}\n\nPlease analyze this content.',
                        rows: 10
                    });
                    this.userMessageEditor.init();
                }
            }
        },

        /**
         * Handle provider change
         */
        handleProviderChange: function(e) {
            const provider = $(e.target).val();
            const $modelField = $('#assistant-model');

            // Get model suggestions from localized data
            if (polytransAssistants.providers[provider]) {
                const models = polytransAssistants.providers[provider].models;
                if (models && models.length > 0) {
                    // Set first model as default if field is empty
                    if (!$modelField.val()) {
                        $modelField.val(models[0]);
                    }
                }
            }
        },

        /**
         * Handle delete assistant
         */
        handleDelete: function(e) {
            e.preventDefault();

            if (!confirm(polytransAssistants.strings.confirmDelete)) {
                return;
            }

            const $button = $(e.currentTarget);
            const assistantId = $button.data('assistant-id');

            $button.prop('disabled', true).text(polytransAssistants.strings.loading);

            $.ajax({
                url: polytransAssistants.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_delete_assistant',
                    nonce: polytransAssistants.nonce,
                    assistant_id: assistantId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove row from table
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                        AssistantsAdmin.showNotice(response.data.message, 'success');
                    } else {
                        AssistantsAdmin.showNotice(response.data.message, 'error');
                        $button.prop('disabled', false).text(polytransAssistants.strings.delete);
                    }
                },
                error: function() {
                    AssistantsAdmin.showNotice(polytransAssistants.strings.deleteError, 'error');
                    $button.prop('disabled', false).text(polytransAssistants.strings.delete);
                }
            });
        },

        /**
         * Handle save assistant
         */
        handleSave: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('button[type="submit"]');

            // Get system prompt from editor
            let systemPrompt = '';
            if (this.systemPromptEditor && this.systemPromptEditor.getValue) {
                systemPrompt = this.systemPromptEditor.getValue();
            }

            // Get user message template from editor
            let userMessage = '';
            if (this.userMessageEditor && this.userMessageEditor.getValue) {
                userMessage = this.userMessageEditor.getValue();
            }

            // Validate required fields
            const name = $('#assistant-name').val().trim();
            const provider = $('#assistant-provider').val();
            
            if (!name || !provider || !systemPrompt) {
                this.showNotice(polytransAssistants.strings.requiredField, 'error');
                return;
            }

            $submitBtn.prop('disabled', true).text(polytransAssistants.strings.loading);

            // Prepare form data
            const formData = {
                action: 'polytrans_save_assistant',
                nonce: polytransAssistants.nonce,
                assistant_id: $form.find('input[name="assistant_id"]').val(),
                name: name,
                provider: provider,
                model: $('#assistant-model').val(),
                system_prompt: systemPrompt,
                user_message_template: userMessage,
                response_format: $('#assistant-response-format').val(),
                config: {
                    temperature: parseFloat($('#assistant-temperature').val()) || 0.7,
                    max_tokens: parseInt($('#assistant-max-tokens').val()) || 2000
                }
            };

            $.ajax({
                url: polytransAssistants.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        AssistantsAdmin.showNotice(response.data.message, 'success');
                        
                        // Redirect to list page after short delay
                        setTimeout(function() {
                            window.location.href = 'admin.php?page=polytrans-assistants';
                        }, 1500);
                    } else {
                        AssistantsAdmin.showNotice(response.data.message, 'error');
                        $submitBtn.prop('disabled', false).text(polytransAssistants.strings.save);
                    }
                },
                error: function() {
                    AssistantsAdmin.showNotice(polytransAssistants.strings.saveError, 'error');
                    $submitBtn.prop('disabled', false).text(polytransAssistants.strings.save);
                }
            });
        },

        /**
         * Handle test assistant
         */
        handleTest: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const assistantId = $('input[name="assistant_id"]').val();

            // For testing, we'll use some sample variables
            const testVariables = {
                title: 'Sample Article Title',
                content: 'This is sample content for testing the assistant.',
                excerpt: 'Sample excerpt',
                language: 'en'
            };

            $button.prop('disabled', true).text(polytransAssistants.strings.loading);
            $('#test-results-container').hide();

            $.ajax({
                url: polytransAssistants.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_test_assistant',
                    nonce: polytransAssistants.nonce,
                    assistant_id: assistantId,
                    test_variables: testVariables
                },
                success: function(response) {
                    $button.prop('disabled', false).text(polytransAssistants.strings.test);

                    if (response.success) {
                        AssistantsAdmin.showTestResults(response.data.result);
                    } else {
                        AssistantsAdmin.showNotice(response.data.message + ': ' + (response.data.error || ''), 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(polytransAssistants.strings.test);
                    AssistantsAdmin.showNotice(polytransAssistants.strings.testError, 'error');
                }
            });
        },

        /**
         * Show test results
         */
        showTestResults: function(result) {
            const $container = $('#test-results-container');
            const $content = $('#test-results-content');

            let html = '<div class="notice notice-success"><p>' + polytransAssistants.strings.testSuccess + '</p></div>';
            
            html += '<div class="test-result-data">';
            html += '<h3>Response:</h3>';
            html += '<pre>' + this.escapeHtml(JSON.stringify(result.data, null, 2)) + '</pre>';
            
            if (result.metadata) {
                html += '<h3>Metadata:</h3>';
                html += '<ul>';
                html += '<li><strong>Provider:</strong> ' + this.escapeHtml(result.metadata.provider) + '</li>';
                html += '<li><strong>Model:</strong> ' + this.escapeHtml(result.metadata.model) + '</li>';
                if (result.metadata.tokens_used) {
                    html += '<li><strong>Tokens Used:</strong> ' + result.metadata.tokens_used + '</li>';
                }
                html += '</ul>';
            }
            html += '</div>';

            $content.html(html);
            $container.show();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Handle workflow migration
         */
        handleMigration: function(e) {
            e.preventDefault();

            if (!confirm('This will migrate all legacy workflow steps to managed assistants. This action cannot be undone. Continue?')) {
                return;
            }

            const $button = $(e.currentTarget);
            const $spinner = $button.next('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: polytransAssistants.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_migrate_workflows',
                    nonce: polytransAssistants.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');

                    if (response.success) {
                        AssistantsAdmin.showNotice(response.data.message, 'success');
                        
                        // Reload page after short delay to show updated list
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        AssistantsAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    AssistantsAdmin.showNotice('Migration failed. Please check logs.', 'error');
                }
            });
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AssistantsAdmin.init();
    });

})(jQuery);

