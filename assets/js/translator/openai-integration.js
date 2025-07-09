/**
 * OpenAI Integration JavaScript for Translation Settings
 * Handles API key validation, assistant loading, and autocomplete functionality
 */
(function ($) {
    'use strict';

    var OpenAIManager = {
        assistants: [],
        assistantsLoaded: false,

        init: function () {
            this.bindEvents();
            this.checkInitialApiKey();
        }, bindEvents: function () {
            // API Key validation
            $('#validate-openai-key').on('click', this.validateApiKey.bind(this));
            $('#toggle-openai-key-visibility').on('click', this.toggleApiKeyVisibility.bind(this));

            // Source language change
            $('#openai-source-language').on('change', this.updateLanguagePairVisibility.bind(this));

            // Allowed source/target language changes
            $('input[name="allowed_sources[]"], input[name="allowed_targets[]"]').on('change', this.updateLanguagePairVisibility.bind(this));

            // Section toggle buttons
            $('#toggle-openai-section').on('click', this.toggleSection.bind(this, '#openai-config-section'));
            $('#toggle-basic-settings').on('click', this.toggleSection.bind(this, '#basic-settings-section'));
            $('#toggle-email-settings').on('click', this.toggleSection.bind(this, '#email-settings-section'));

            // Assistant autocomplete clear buttons
            // Note: Clear buttons removed in favor of select dropdown "Not selected" option
        }, checkInitialApiKey: function () {
            var apiKey = $('#openai-api-key').val();
            console.log('Checking initial API key:', apiKey ? 'Found key: ' + apiKey.substring(0, 7) + '...' : 'No key found');

            // Show/hide assistant mapping section based on API key (temporary testing override)
            this.updateAssistantMappingVisibility(apiKey);

            if (apiKey && apiKey.trim() !== '') {
                console.log('API key detected, loading assistants...');
                this.loadAssistants();
            } else {
                console.log('No API key, skipping assistant loading');
            }

            // Trigger initial language pair filtering
            this.updateLanguagePairVisibility();
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
                    console.log('Validate API key response:', response);
                    if (response.success) {
                        // response.data is the message string directly, not an object with .message property
                        this.showMessage($message, 'success', response.data);
                        this.updateAssistantMappingVisibility(apiKey);
                        this.loadAssistants();
                    } else {
                        // Handle error - response.data should be the error message string
                        this.showMessage($message, 'error', response.data);
                        this.updateAssistantMappingVisibility('');
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
        }, loadAssistants: function () {
            var $loading = $('#assistants-loading');
            var $error = $('#assistants-error');

            console.log('Loading assistants...');
            $loading.show();
            $error.hide();

            var apiKey = $('#openai-api-key').val(); // Fixed ID selector - use hyphen not underscore
            if (!apiKey) {
                console.error('No API key provided');
                $loading.hide();
                $error.show().find('p').text('Please enter an API key first');
                return;
            }

            var ajaxData = {
                action: 'polytrans_load_openai_assistants',
                api_key: apiKey,
                nonce: (typeof polytrans_openai !== 'undefined' && polytrans_openai.nonce) ?
                    polytrans_openai.nonce :
                    $('input[name="_wpnonce"]').val() // Fixed nonce fallback
            };

            var ajaxUrl = (typeof polytrans_openai !== 'undefined' && polytrans_openai.ajax_url) ?
                polytrans_openai.ajax_url :
                (typeof ajaxurl !== 'undefined' ? ajaxurl : null);

            console.log('AJAX data:', ajaxData);
            console.log('ajaxurl:', ajaxUrl);

            if (!ajaxUrl) {
                console.error('ajaxurl is not available');
                $error.show().find('p').text('AJAX URL not available');
                $loading.hide();
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function (response) {
                    console.log('Assistant loading response:', response);
                    if (response.success) {
                        this.assistants = response.data;
                        this.assistantsLoaded = true;
                        console.log('Loaded assistants:', this.assistants);
                        this.initializeAssistantAutocomplete();
                        this.updateAssistantLabels();
                    } else {
                        console.error('Assistant loading failed:', response.data);
                        $error.show().find('p').text(response.data || 'Unknown error');
                    }
                }.bind(this),
                error: function (xhr, status, error) {
                    console.error('AJAX error loading assistants:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    $error.show().find('p').text('Network error: ' + error);
                }.bind(this),
                complete: function () {
                    $loading.hide();
                }
            });
        },

        initializeAssistantAutocomplete: function () {
            if (!this.assistantsLoaded) return;

            this.populateAssistantSelects();
        },

        populateAssistantSelects: function () {
            var assistants = this.assistants || [];
            console.log('Populating assistant selects with', assistants.length, 'assistants');

            $('.assistant-select').each(function () {
                var $select = $(this);
                var selectedValue = $select.data('selected') || $select.val();

                // Clear existing options (except the first "Not selected" option)
                $select.find('option:not(:first)').remove();

                // Add assistant options
                assistants.forEach(function (assistant) {
                    var option = $('<option></option>')
                        .attr('value', assistant.id)
                        .text(assistant.name + ' (' + assistant.model + ')');
                    $select.append(option);
                });

                // Set the selected value if it exists
                if (selectedValue) {
                    $select.val(selectedValue);
                    if ($select.val() !== selectedValue) {
                        // Value doesn't exist in the list, reset to "Not selected"
                        console.log('Previously selected assistant', selectedValue, 'not found in current list');
                        $select.val('');
                    }
                }

                console.log('Populated select', $select.attr('id'), 'with', assistants.length, 'options, selected:', $select.val(), selectedValue);
            });
        },

        updateAssistantLabels: function () {
            // No longer needed with select fields - values are already correct
            console.log('Assistant labels updated (select fields handle this automatically)');
        },

        clearAssistantSelection: function (e) {
            // No longer needed with select fields - users can select "Not selected" option
            console.log('Clear function called (no longer needed with select fields)');
        },

        updateAssistantMappingVisibility: function (apiKey) {
            var $section = $('#openai-assistants-section');
            console.log('Updating assistant mapping visibility. API key present:', !!apiKey);
            console.log('Assistant section found:', $section.length > 0);

            // Temporary override for testing - remove "|| true" when ready for production
            if ((apiKey && apiKey.trim() !== '') || true) {
                $section.show();
                console.log('Showing assistant section');
            } else {
                $section.hide();
                console.log('Hiding assistant section');
            }
        },

        updateLanguagePairVisibility: function () {
            var openaiSourceLang = $('#openai-source-language').val();
            var allowedSources = [];
            var allowedTargets = [];

            // Get allowed source languages
            $('input[name="allowed_sources[]"]:checked').each(function () {
                allowedSources.push($(this).val());
            });

            // Get allowed target languages
            $('input[name="allowed_targets[]"]:checked').each(function () {
                allowedTargets.push($(this).val());
            });

            var visiblePairs = 0;

            // Filter and show/hide language pairs
            $('.language-pair-row').each(function () {
                var $row = $(this);
                var sourceLang = $row.data('source');
                var targetLang = $row.data('target');
                var $toOpenaiBadge = $row.find('.pair-type-to-openai');
                var $fromOpenaiBadge = $row.find('.pair-type-from-openai');

                var isSourceToOpenai = allowedSources.indexOf(sourceLang) !== -1 &&
                    targetLang === openaiSourceLang &&
                    sourceLang !== openaiSourceLang;

                var isOpenaiToTarget = sourceLang === openaiSourceLang &&
                    allowedTargets.indexOf(targetLang) !== -1 &&
                    targetLang !== openaiSourceLang;

                // Show row if it matches either pattern
                if (isSourceToOpenai || isOpenaiToTarget) {
                    $row.show();
                    visiblePairs++;

                    // Show appropriate badge based on the match
                    if (isSourceToOpenai) {
                        $toOpenaiBadge.show();
                        $fromOpenaiBadge.hide();
                    } else if (isOpenaiToTarget) {
                        $toOpenaiBadge.hide();
                        $fromOpenaiBadge.show();
                    }
                } else {
                    $row.hide();
                    $toOpenaiBadge.hide();
                    $fromOpenaiBadge.hide();
                }
            });

            // Show/hide "no relevant pairs" message
            var $noRelevantMessage = $('.no-relevant-pairs-message');
            if (visiblePairs === 0) {
                $noRelevantMessage.show();
            } else {
                $noRelevantMessage.hide();
            }
        },

        showMessage: function ($container, type, message) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var html = '<div class="notice ' + className + ' inline"><p>' + message + '</p></div>';
            $container.html(html);
        }
    };

    // Make OpenAIManager globally accessible
    window.OpenAIManager = OpenAIManager;

    // Translation provider switching
    var TranslationProviderManager = {
        init: function () {
            this.bindEvents();
            this.updateProviderSection();
        },

        bindEvents: function () {
            $('input[name="translation_provider"]').on('change', this.updateProviderSection.bind(this));
        },

        updateProviderSection: function () {
            var provider = $('input[name="translation_provider"]:checked').val();

            if (provider === 'openai') {
                // Show the OpenAI config section if OpenAI is selected
                // But respect the toggle button state
                var $toggleButton = $('#toggle-openai-section');

                $toggleButton.show();

                // Initialize OpenAI manager if not already done
                if (!OpenAIManager.assistantsLoaded) {
                    OpenAIManager.init();
                }
            } else {
                $('#openai-config-section').hide();
                $('#toggle-openai-section').hide();
            }
        }
    };

    // Initialize everything when document is ready
    $(function () {
        console.log('OpenAI Integration script loaded');
        console.log('polytrans_openai available:', typeof polytrans_openai !== 'undefined');
        console.log('ajaxurl available:', typeof ajaxurl !== 'undefined');
        console.log('jQuery UI available:', typeof $.ui !== 'undefined');

        TranslationProviderManager.init();

        // Initialize section toggles
        var provider = $('input[name="translation_provider"]:checked').val();
        if (provider !== 'openai') {
            $('#toggle-openai-section').hide();
        }
    });

})(jQuery);
