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

            // Provider change - load models dynamically
            $('#assistant-provider').on('change', this.handleProviderChange.bind(this));
            
            // Refresh models button
            $('#refresh-models').on('click', this.handleRefreshModels.bind(this));

            // Response format change - show/hide schema field
            $('#assistant-response-format').on('change', this.handleResponseFormatChange.bind(this));

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

            // Initialize schema field visibility based on response format
            this.handleResponseFormatChange();

            // Initialize system prompt visibility based on current provider
            const currentProvider = window.polytransAssistantData?.provider || $('#assistant-provider').val() || 'openai';
            this.updateSystemPromptVisibility(currentProvider);

            // Create system prompt textarea with variable sidebar
            const systemContainer = document.getElementById('system-prompt-editor-container');
            if (systemContainer) {
                // Check if provider supports system prompt
                const providerManifests = polytransAssistants.providerManifests || {};
                const manifest = providerManifests[currentProvider];
                const supportsSystemPrompt = manifest ? (manifest.supports_system_prompt !== false) : true; // Default to true for backward compatibility
                
                const systemTextarea = $('<textarea>')
                    .attr('id', 'assistant-system-prompt')
                    .attr('name', 'system_prompt')
                    .attr('rows', 8)
                    .attr('required', supportsSystemPrompt) // Only required if provider supports it
                    .addClass('large-text code prompt-editor-textarea')
                    .css('width', '100%')
                    .val(window.polytransAssistantData.system_prompt || '');
                
                const wrapper = $('<div>').addClass('field-wrapper');
                wrapper.append(systemTextarea);
                wrapper.append(this.renderVariableSidebar());
                $(systemContainer).append(wrapper);
                
                this.systemPromptEditor = systemTextarea[0];
            }

            // Create user message template textarea with variable sidebar
            const userContainer = document.getElementById('user-message-editor-container');
            if (userContainer) {
                const userTextarea = $('<textarea>')
                    .attr('id', 'assistant-user-message')
                    .attr('name', 'user_message_template')
                    .attr('rows', 10)
                    .addClass('large-text code prompt-editor-textarea')
                    .css('width', '100%')
                    .val(window.polytransAssistantData.user_message_template || '');
                
                const wrapper = $('<div>').addClass('field-wrapper');
                wrapper.append(userTextarea);
                wrapper.append(this.renderVariableSidebar());
                $(userContainer).append(wrapper);
                
                this.userMessageEditor = userTextarea[0];
            }
            
            // Initialize variable pill click handlers
            this.initVariablePills();
        },

        /**
         * Render variable sidebar
         */
        renderVariableSidebar: function() {
            // Use variables from PolyTransPromptEditor module
            const variables = typeof PolyTransPromptEditor !== 'undefined' 
                ? PolyTransPromptEditor.variables 
                : [];

            const pills = variables.map(v => 
                `<span class="var-pill" data-variable="{{ ${v.name} }}" title="${v.desc}">${v.name}</span>`
            ).join('');

            return $('<div>').addClass('variable-sidebar').html(pills);
        },

        /**
         * Initialize variable pill click handlers
         */
        initVariablePills: function() {
            let lastFocusedTextarea = null;

            // Track last focused textarea
            $(document).on('focus', '.prompt-editor-textarea', function() {
                lastFocusedTextarea = this;
            });

            // Handle variable pill clicks
            $(document).on('click', '.var-pill', function() {
                const variable = $(this).data('variable');
                const textarea = lastFocusedTextarea || $(this).closest('.field-wrapper').find('textarea')[0];

                if (textarea) {
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const text = textarea.value;
                    const before = text.substring(0, start);
                    const after = text.substring(end, text.length);

                    textarea.value = before + variable + after;
                    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
                    textarea.focus();
                }
            });
        },

        /**
         * Handle provider change - load models dynamically and update system prompt visibility
         */
        handleProviderChange: function(e) {
            const provider = $(e.target).val();
            const $modelField = $('#assistant-model');
            const currentModel = $modelField.data('selected-model') || $modelField.val();

            // Update system prompt visibility based on provider support
            this.updateSystemPromptVisibility(provider);

            // Show loading state
            $modelField.prop('disabled', true);
            $modelField.html('<option value="">Loading models...</option>');

            // Load models via AJAX
            $.ajax({
                url: polytransAssistants.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_get_provider_models',
                    provider_id: provider,
                    selected_model: currentModel,
                    nonce: polytransAssistants.nonce
                },
                success: (response) => {
                    if (response.success && response.data && response.data.models) {
                        this.populateModelSelect($modelField, response.data.models, currentModel);
                    } else {
                        // Fallback to empty select
                        $modelField.html('<option value="">Use Global Setting</option>');
                        console.error('Failed to load models:', response);
                    }
                    $modelField.prop('disabled', false);
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error loading models:', error);
                    $modelField.html('<option value="">Use Global Setting</option>');
                    $modelField.prop('disabled', false);
                }
            });
        },

        /**
         * Update system prompt field visibility based on provider support
         */
        updateSystemPromptVisibility: function(provider) {
            const $systemPromptRow = $('#system-prompt-row');
            const $systemPromptField = $('#assistant-system-prompt');
            const $requiredStar = $('.system-prompt-required');
            
            // Check if provider supports system prompt
            const providerManifests = polytransAssistants.providerManifests || {};
            const manifest = providerManifests[provider];
            const supportsSystemPrompt = manifest ? (manifest.supports_system_prompt !== false) : true; // Default to true for backward compatibility
            
            if (supportsSystemPrompt) {
                // Show system prompt field
                $systemPromptRow.show();
                if ($systemPromptField.length) {
                    $systemPromptField.prop('required', true);
                }
                $requiredStar.show();
            } else {
                // Hide system prompt field (provider doesn't support it)
                $systemPromptRow.hide();
                if ($systemPromptField.length) {
                    $systemPromptField.prop('required', false);
                    $systemPromptField.val(''); // Clear value since it won't be used
                }
                $requiredStar.hide();
            }
        },

        /**
         * Populate model select dropdown
         */
        populateModelSelect: function($select, groupedModels, selectedModel) {
            $select.empty();
            
            // Add "Use Global Setting" option
            const globalSelected = (!selectedModel || selectedModel === '') ? 'selected' : '';
            $select.append($('<option></option>')
                .attr('value', '')
                .prop('selected', globalSelected)
                .text('Use Global Setting'));

            // Add grouped models
            for (const [groupName, models] of Object.entries(groupedModels)) {
                const $optgroup = $('<optgroup></optgroup>').attr('label', groupName);
                
                for (const [modelValue, modelLabel] of Object.entries(models)) {
                    const isSelected = (selectedModel === modelValue) ? 'selected' : '';
                    $optgroup.append($('<option></option>')
                        .attr('value', modelValue)
                        .prop('selected', isSelected)
                        .text(modelLabel));
                }
                
                $select.append($optgroup);
            }
        },

        /**
         * Handle refresh models button click
         */
        handleRefreshModels: function(e) {
            e.preventDefault();
            const provider = $('#assistant-provider').val();
            const $modelField = $('#assistant-model');
            const currentModel = $modelField.val();

            if (!provider) {
                alert('Please select a provider first.');
                return;
            }

            // Trigger provider change handler to reload models
            $('#assistant-provider').trigger('change');
        },

        /**
         * Handle response format change - show/hide schema field
         */
        handleResponseFormatChange: function() {
            const format = $('#assistant-response-format').val();
            const $schemaRow = $('#expected-output-schema-row');
            
            if (format === 'json') {
                $schemaRow.show();
            } else {
                $schemaRow.hide();
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

            // Get system prompt from textarea
            let systemPrompt = '';
            if (this.systemPromptEditor) {
                systemPrompt = $(this.systemPromptEditor).val();
            }

            // Get user message template from textarea
            let userMessage = '';
            if (this.userMessageEditor) {
                userMessage = $(this.userMessageEditor).val();
            }

            // Validate required fields
            const name = $('#assistant-name').val().trim();
            const provider = $('#assistant-provider').val();
            
            if (!name || !provider || !systemPrompt) {
                this.showNotice(polytransAssistants.strings.requiredField, 'error');
                return;
            }

            $submitBtn.prop('disabled', true).text(polytransAssistants.strings.loading);

            // Get expected output schema if format is JSON
            let expectedOutputSchema = null;
            const responseFormat = $('#assistant-response-format').val();
            if (responseFormat === 'json') {
                const schemaText = $('#assistant-expected-output-schema').val().trim();
                if (schemaText) {
                    try {
                        expectedOutputSchema = JSON.parse(schemaText);
                    } catch (e) {
                        this.showNotice('Invalid JSON in Expected Output Schema: ' + e.message, 'error');
                        $submitBtn.prop('disabled', false).text(polytransAssistants.strings.save);
                        return;
                    }
                }
            }

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
                response_format: responseFormat,
                expected_output_schema: expectedOutputSchema ? JSON.stringify(expectedOutputSchema) : null,
                config: {
                    temperature: parseFloat($('#assistant-temperature').val()) || 0.7
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

