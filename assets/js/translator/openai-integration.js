/**
 * OpenAI Integration JavaScript for Translation Settings
 * Handles API key validation and model management
 */
(function ($) {
    'use strict';

    var OpenAIManager = {
        initialized: false,
        modelsLoaded: false,

        init: function () {
            this.bindEvents();
            this.checkInitialApiKey();
            this.loadModels(); // Load models on init
            this.initialized = true;
        }, bindEvents: function () {
            // API Key validation - use event delegation since elements might not exist yet
            $(document).on('click', '#validate-openai-key', this.validateApiKey.bind(this));
            $(document).on('click', '#toggle-openai-key-visibility', this.toggleApiKeyVisibility.bind(this));
            $(document).on('click', '#refresh-openai-models', this.refreshModels.bind(this));

            // Section toggle buttons
            $(document).on('click', '#toggle-openai-section', this.toggleSection.bind(this, '#openai-config-section'));
            $(document).on('click', '#toggle-basic-settings', this.toggleSection.bind(this, '#basic-settings-section'));
            $(document).on('click', '#toggle-email-settings', this.toggleSection.bind(this, '#email-settings-section'));
        }, checkInitialApiKey: function () {
            var apiKey = $('#openai-api-key').val();
            // API key validation happens on demand via validate button
        },

        validateApiKey: function (e) {
            e.preventDefault();

            var apiKey = $('#openai-api-key').val().trim();
            var $button = $('#validate-openai-key');
            var $message = $('#openai-validation-message');

            if (!apiKey) {
                this.showMessage($message, 'error', 'Please enter an API key');
                return;
            }

            $button.prop('disabled', true).text('Validating...');
            $message.empty();

            var ajaxUrl = (typeof polytrans_openai !== 'undefined' && polytrans_openai.ajax_url) ?
                polytrans_openai.ajax_url :
                (typeof ajaxurl !== 'undefined' ? ajaxurl : null);

            var nonce = (typeof polytrans_openai !== 'undefined' && polytrans_openai.nonce) ?
                polytrans_openai.nonce :
                $('input[name="_wpnonce"]').val();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_validate_openai_key',
                    api_key: apiKey,
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        // response.data is the message string directly, not an object with .message property
                        this.showMessage($message, 'success', response.data);
                    } else {
                        // Handle error - response.data should be the error message string
                        this.showMessage($message, 'error', response.data);
                    }
                }.bind(this),
                error: function () {
                    this.showMessage($message, 'error', 'Failed to validate API key. Please try again.');
                }.bind(this),
                complete: function () {
                    $button.prop('disabled', false).text('Validate');
                }
            });
        },

        toggleApiKeyVisibility: function (e) {
            e.preventDefault();

            var $input = $('#openai-api-key');
            var $button = $('#toggle-openai-key-visibility');

            if ($input.length === 0) {
                console.error('API key input field not found!');
                return;
            }

            var currentType = $input.attr('type');

            if (currentType === 'password') {
                $input.attr('type', 'text');
                $button.text('🔒'); // Lock icon for hidden state
            } else {
                $input.attr('type', 'password');
                $button.text('👁'); // Eye icon for visible state
            }
        },

        toggleSection: function (sectionSelector, e) {
            e.preventDefault();
            var $section = $(sectionSelector);
            var isVisible = $section.is(':visible');

            $section.toggle();

            // Track manual toggle state for OpenAI section
            if (sectionSelector === '#openai-config-section') {
                $section.data('manually-hidden', isVisible);
            }
        },

        showMessage: function ($container, type, message) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var dismissText = (typeof polytrans_openai !== 'undefined' && polytrans_openai.strings && polytrans_openai.strings.dismiss_notice) ?
                polytrans_openai.strings.dismiss_notice : 'Dismiss this notice';
            var html = '<div class="notice ' + className + ' is-dismissible inline"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' +
                dismissText + '</span></button></div>';
            $container.html(html);

            // Initialize dismiss functionality
            $container.find('.notice-dismiss').on('click', function (e) {
                e.preventDefault();
                $(this).closest('.notice').fadeOut(function () {
                    $(this).remove();
                });
            });
        },

        /**
         * Load models from API
         */
        loadModels: function () {
            var $select = $('#openai-model');
            if ($select.length === 0) {
                return; // Model select not on this page
            }

            var selectedModel = $select.data('selected-model') || (typeof polytrans_openai !== 'undefined' ? polytrans_openai.selected_model : '');
            var apiKey = $('#openai-api-key').val() || '';

            // Use cached models if available and no API key (for fallback)
            if (!apiKey && typeof polytrans_openai !== 'undefined' && polytrans_openai.models) {
                this.updateModelSelect($select, polytrans_openai.models, selectedModel);
                this.modelsLoaded = true;
                return;
            }

            // Fetch fresh models from API
            this.fetchModels(apiKey, selectedModel, function (models) {
                this.updateModelSelect($select, models, selectedModel);
                this.modelsLoaded = true;
            }.bind(this));
        },

        /**
         * Fetch models from API via AJAX (using universal endpoint)
         */
        fetchModels: function (apiKey, selectedModel, callback) {
            var ajaxUrl = (typeof polytrans_openai !== 'undefined' && polytrans_openai.ajax_url) ?
                polytrans_openai.ajax_url :
                (typeof ajaxurl !== 'undefined' ? ajaxurl : null);

            // Use universal endpoint - get nonce from AssistantsMenu (same as provider models)
            var nonce = (typeof polytransAssistants !== 'undefined' && polytransAssistants.nonce) ?
                polytransAssistants.nonce :
                ((typeof polytrans_openai !== 'undefined' && polytrans_openai.nonce) ?
                    polytrans_openai.nonce :
                    $('input[name="_wpnonce"]').val());

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_get_provider_models',
                    provider_id: 'openai', // Explicitly specify OpenAI provider
                    selected_model: selectedModel || '',
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success && response.data && response.data.models) {
                        callback(response.data.models);
                    } else {
                        // Fallback to cached models
                        if (typeof polytrans_openai !== 'undefined' && polytrans_openai.models) {
                            callback(polytrans_openai.models);
                        }
                    }
                }.bind(this),
                error: function () {
                    // Fallback to cached models on error
                    if (typeof polytrans_openai !== 'undefined' && polytrans_openai.models) {
                        callback(polytrans_openai.models);
                    }
                }.bind(this)
            });
        },

        /**
         * Update model select dropdown with new models
         */
        updateModelSelect: function ($select, groupedModels, selectedModel) {
            if (!$select.length) {
                return;
            }

            $select.empty();

            // Add models grouped by category
            for (var groupName in groupedModels) {
                if (!groupedModels.hasOwnProperty(groupName)) {
                    continue;
                }

                var $optgroup = $('<optgroup></optgroup>').attr('label', groupName);
                var models = groupedModels[groupName];

                for (var modelId in models) {
                    if (!models.hasOwnProperty(modelId)) {
                        continue;
                    }

                    var modelLabel = models[modelId];
                    var $option = $('<option></option>')
                        .attr('value', modelId)
                        .text(modelLabel);

                    if (selectedModel && modelId === selectedModel) {
                        $option.prop('selected', true);
                    }

                    $optgroup.append($option);
                }

                $select.append($optgroup);
            }
        },

        /**
         * Refresh models from API
         */
        refreshModels: function (e) {
            e.preventDefault();

            var $button = $('#refresh-openai-models');
            var $select = $('#openai-model');
            var selectedModel = $select.val() || $select.data('selected-model') || '';
            var apiKey = $('#openai-api-key').val() || '';

            if (!$select.length) {
                return;
            }

            var originalText = $button.text();
            $button.prop('disabled', true).text(
                (typeof polytrans_openai !== 'undefined' && polytrans_openai.strings && polytrans_openai.strings.refreshing_models) ?
                    polytrans_openai.strings.refreshing_models :
                    'Refreshing...'
            );

            this.fetchModels(apiKey, selectedModel, function (models) {
                this.updateModelSelect($select, models, selectedModel);
                $button.prop('disabled', false).text(originalText);

                // Show success message in dedicated message container
                var $messageContainer = $('#openai-model-message');
                if ($messageContainer.length) {
                    var message = (typeof polytrans_openai !== 'undefined' && polytrans_openai.strings && polytrans_openai.strings.models_refreshed) ?
                        polytrans_openai.strings.models_refreshed :
                        'Models refreshed';
                    this.showMessage($messageContainer, 'success', message);
                }
            }.bind(this));
        }
    };

    // Make OpenAIManager globally accessible
    window.OpenAIManager = OpenAIManager;

    // Translation provider switching
    var TranslationProviderManager = {
        initialized: false,

        init: function () {
            if (this.initialized) {
                console.log('Translation Provider Manager already initialized, skipping...');
                return;
            }

            this.bindEvents();
            this.updateProviderSection();
            this.initialized = true;
        },

        bindEvents: function () {
            $('input[name="enabled_translation_providers[]"]').on('change', this.updateProviderSection.bind(this));
        },

        updateProviderSection: function () {
            // Get first checked provider for backward compatibility
            var provider = $('input[name="enabled_translation_providers[]"]:checked').first().val() || 'google';

            // Always show OpenAI settings since they're used in workflows regardless of main translation provider
            var $toggleButton = $('#toggle-openai-section');
            $toggleButton.show();

            // Initialize OpenAI manager if not already done
            if (!OpenAIManager.initialized) {
                OpenAIManager.init();
            }
        }
    };

    // Initialize managers on document ready
    $(function () {
        TranslationProviderManager.init();

        // Always show OpenAI toggle since OpenAI settings are used in workflows
        $('#toggle-openai-section').show();
    });

})(jQuery);
