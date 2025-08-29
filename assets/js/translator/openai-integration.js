/**
 * OpenAI Integration JavaScript for Translation Settings
 * Handles API key validation, assistant loading, and autocomplete functionality
 */
(function ($) {
    'use strict';

    var OpenAIManager = {
        assistants: [],
        assistantsLoaded: false,
        initialized: false,

        init: function () {
            this.bindEvents();
            this.checkInitialApiKey();
            this.initialized = true;
        }, bindEvents: function () {
            // API Key validation - use event delegation since elements might not exist yet
            $(document).on('click', '#validate-openai-key', this.validateApiKey.bind(this));
            $(document).on('click', '#toggle-openai-key-visibility', this.toggleApiKeyVisibility.bind(this));

            // Source language change
            $(document).on('change', '#openai-source-language', this.updateLanguagePairVisibility.bind(this));

            // Allowed source/target language changes
            $(document).on('change', 'input[name="allowed_sources[]"], input[name="allowed_targets[]"]', this.updateLanguagePairVisibility.bind(this));

            // Section toggle buttons
            $(document).on('click', '#toggle-openai-section', this.toggleSection.bind(this, '#openai-config-section'));
            $(document).on('click', '#toggle-basic-settings', this.toggleSection.bind(this, '#basic-settings-section'));
            $(document).on('click', '#toggle-email-settings', this.toggleSection.bind(this, '#email-settings-section'));
        }, checkInitialApiKey: function () {
            var apiKey = $('#openai-api-key').val();

            // Show/hide assistant mapping section based on API key (temporary testing override)
            this.updateAssistantMappingVisibility(apiKey);

            if (apiKey && apiKey.trim() !== '') {
                this.loadAssistants();
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
        }, loadAssistants: function () {
            var $loading = $('#assistants-loading');
            var $error = $('#assistants-error');

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
                    if (response.success) {
                        this.assistants = response.data;
                        this.assistantsLoaded = true;
                        this.initializeAssistantAutocomplete();
                        this.updateAssistantLabels();
                    } else {
                        console.error('Assistant loading failed:', response.data);
                        $error.show().find('p').text(response.data || 'Unknown error');

                        // Update selects to show error state instead of "Loading..."
                        this.handleAssistantLoadingError(response.data || 'Failed to load assistants');
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

                    // Update selects to show error state instead of "Loading..."
                    this.handleAssistantLoadingError('Network error: ' + error);
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
            console.log('populateAssistantSelects called with', assistants.length, 'assistants');

            // Use a slight delay to ensure DOM is ready
            setTimeout(function () {
                // Target both sets of assistant select elements
                var $selects = $('.openai-assistant-select, .assistant-select');
                console.log('Found', $selects.length, 'select elements');

                if ($selects.length === 0) {
                    console.log('No select elements found. Available elements:', $('.openai-assistants-section').length ? 'Section found' : 'Section not found');
                    return;
                }

                $selects.each(function () {
                    var $select = $(this);
                    var pairKey = $select.data('pair');
                    var currentValue = '';
                    var $hiddenInput;

                    // Handle both UI systems
                    if ($select.hasClass('openai-assistant-select')) {
                        // New UI system (class-openai-settings-ui.php)
                        $hiddenInput = $('.openai-assistant-hidden[data-pair="' + pairKey + '"]');
                        currentValue = $hiddenInput.val() || '';
                    } else if ($select.hasClass('assistant-select')) {
                        // Legacy UI system (class-openai-settings-provider.php)
                        var hiddenInputId = $select.data('hidden-input');
                        $hiddenInput = $('#' + hiddenInputId);
                        currentValue = $hiddenInput.val() || $select.data('selected') || '';
                    }

                    console.log('Processing pair:', pairKey, 'currentValue:', currentValue);

                    // Clear existing options
                    $select.empty();

                    // Add default "not selected" option
                    $select.append($('<option></option>')
                        .attr('value', '')
                        .text('No assistant selected'));

                    // Add assistant options
                    assistants.forEach(function (assistant) {
                        var option = $('<option></option>')
                            .attr('value', assistant.id)
                            .text(assistant.name + ' (' + assistant.model + ')');
                        $select.append(option);
                    });

                    // Set the selected value to match the hidden input
                    $select.val(currentValue);

                    // Handle selection changes - update the hidden input
                    $select.off('change.openai').on('change.openai', function () {
                        var newValue = $(this).val();
                        $hiddenInput.val(newValue);
                    });
                });

                // Show success message when assistants are loaded
                if (assistants.length > 0) {
                    var $section = $('#openai-assistants-section');
                    var $loadingMsg = $section.find('.assistants-loading-message');
                    if ($loadingMsg.length === 0) {
                        $section.prepend('<div class="assistants-loading-message" style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:8px 12px;border-radius:4px;margin-bottom:15px;font-size:13px;">✓ Loaded ' + assistants.length + ' assistants from your OpenAI account</div>');
                    }
                    setTimeout(function () {
                        $section.find('.assistants-loading-message').fadeOut();
                    }, 3000);
                }
            }.bind(this), 100);
        },

        updateAssistantLabels: function () {
            // No longer needed with select fields - values are already correct
        },

        clearAssistantSelection: function (e) {
            // No longer needed with select fields - users can select "Not selected" option
        },

        updateAssistantMappingVisibility: function (apiKey) {
            var $section = $('#openai-assistants-section');

            // Temporary override for testing - remove "|| true" when ready for production
            if ((apiKey && apiKey.trim() !== '') || true) {
                $section.show();

                // If assistants are already loaded, populate the selects
                if (this.assistantsLoaded) {
                    this.populateAssistantSelects();
                }
            } else {
                $section.hide();
            }
        },

        updateLanguagePairVisibility: function () {
            // 1. Get allowed source/target languages
            var allowedSources = [];
            var allowedTargets = [];
            $('input[name="allowed_sources[]"]:checked').each(function () {
                allowedSources.push($(this).val());
            });
            $('input[name="allowed_targets[]"]:checked').each(function () {
                allowedTargets.push($(this).val());
            });

            // 2. Get all rules from the DOM
            var rules = [];
            $('#openai-path-rules-list .openai-path-rule').each(function (i) {
                var $rule = $(this);
                var source = $rule.find('.openai-path-source').val();
                var target = $rule.find('.openai-path-target').val();
                var intermediate = $rule.find('.openai-path-intermediate').val();
                rules.push({ source: source, target: target, intermediate: intermediate, index: i });
            });
            console.log('[Polytrans] Path rules:', rules);

            // 3. Expand all rules into all possible pairs, collecting all rules for each pair
            var pairToRules = {};
            rules.forEach(function (rule) {
                var sources = (rule.source === 'all') ? allowedSources : [rule.source];
                var targets = (rule.target === 'all') ? allowedTargets : [rule.target];
                sources.forEach(function (src) {
                    targets.forEach(function (tgt) {
                        if (src === tgt) return;
                        if (rule.intermediate === 'none') {
                            // Direct pair
                            var key = src + '_to_' + tgt;
                            if (!pairToRules[key]) pairToRules[key] = [];
                            pairToRules[key].push({
                                type: 'direct',
                                rule: rule,
                                srcSet: sources,
                                tgtSet: targets
                            });
                        } else {
                            // Via intermediate: src->inter and inter->tgt
                            if (src !== rule.intermediate) {
                                var key1 = src + '_to_' + rule.intermediate;
                                if (!pairToRules[key1]) pairToRules[key1] = [];
                                pairToRules[key1].push({
                                    type: 'via',
                                    rule: rule,
                                    srcSet: sources,
                                    inter: rule.intermediate,
                                    tgtSet: targets
                                });
                            }
                            if (rule.intermediate !== tgt) {
                                var key2 = rule.intermediate + '_to_' + tgt;
                                if (!pairToRules[key2]) pairToRules[key2] = [];
                                pairToRules[key2].push({
                                    type: 'via',
                                    rule: rule,
                                    srcSet: sources,
                                    inter: rule.intermediate,
                                    tgtSet: targets
                                });
                            }
                        }
                    });
                });
            });
            console.log('[Polytrans] All pairs and their rules:', pairToRules);

            // 4. Show/hide mapping table rows
            var visiblePairs = 0;
            $('.language-pair-row').each(function () {
                var $row = $(this);
                var src = $row.data('source');
                var tgt = $row.data('target');
                var pairKey = $row.data('pair');
                var matchingRules = pairToRules[pairKey] || [];
                if (matchingRules.length > 0) {
                    $row.show();
                    visiblePairs++;
                    // Build summary string for translation path column
                    var pathSummaries = matchingRules.map(function (mr) {
                        if (mr.type === 'direct') {
                            var srcSet = '(' + mr.srcSet.map(function (code) { return code.toUpperCase(); }).join(',') + ')';
                            var tgtSet = '(' + mr.tgtSet.map(function (code) { return code.toUpperCase(); }).join(',') + ')';
                            return srcSet + '→' + tgtSet;
                        } else if (mr.type === 'via') {
                            var srcSet = '(' + mr.srcSet.map(function (code) { return code.toUpperCase(); }).join(',') + ')';
                            var inter = mr.inter.toUpperCase();
                            var tgtSet = '(' + mr.tgtSet.map(function (code) { return code.toUpperCase(); }).join(',') + ')';
                            return srcSet + '→' + inter + '→' + tgtSet;
                        }
                        return '';
                    });
                    // Remove duplicates
                    var uniqueSummaries = Array.from(new Set(pathSummaries));
                    $row.find('.translation-path-details').html(uniqueSummaries.join('<br>'));
                    $row.find('.translation-path-direct').text(matchingRules.length === 1 && matchingRules[0].type === 'direct' ? 'Direct' : 'Rule(s)');
                    console.log('[Polytrans] Showing pair:', pairKey, 'with rules:', pathSummaries);
                } else {
                    $row.hide();
                    console.log('[Polytrans] Hiding pair:', pairKey);
                }
            });

            // 5. Show/hide "no relevant pairs" message
            var $noRelevantMessage = $('.no-relevant-pairs-message');
            if (visiblePairs === 0) {
                $noRelevantMessage.show();
                console.log('[Polytrans] No relevant pairs visible.');
            } else {
                $noRelevantMessage.hide();
                console.log('[Polytrans] Visible pairs:', visiblePairs);
            }
        },

        showMessage: function ($container, type, message) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var html = '<div class="notice ' + className + ' inline"><p>' + message + '</p></div>';
            $container.html(html);
        },

        handleAssistantLoadingError: function (errorMessage) {
            // When assistants fail to load, update selects to show error but preserve hidden values
            $('.openai-assistant-select, .assistant-select').each(function () {
                var $select = $(this);
                var pairKey = $select.data('pair');
                var currentValue = '';
                var $hiddenInput;

                // Handle both UI systems
                if ($select.hasClass('openai-assistant-select')) {
                    // New UI system (class-openai-settings-ui.php)
                    $hiddenInput = $('.openai-assistant-hidden[data-pair="' + pairKey + '"]');
                    currentValue = $hiddenInput.val() || '';
                } else if ($select.hasClass('assistant-select')) {
                    // Legacy UI system (class-openai-settings-provider.php)
                    var hiddenInputId = $select.data('hidden-input');
                    $hiddenInput = $('#' + hiddenInputId);
                    currentValue = $hiddenInput.val() || $select.data('selected') || '';
                }

                // Clear and show error option
                $select.empty();
                $select.append($('<option></option>')
                    .attr('value', '')
                    .text('⚠ Failed to load assistants'));

                // If there was a current value, add it as an option so it's preserved
                if (currentValue) {
                    $select.append($('<option></option>')
                        .attr('value', currentValue)
                        .text('Current: ' + currentValue)
                        .prop('selected', true));
                }
            });
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
            console.trace();

            this.bindEvents();
            this.updateProviderSection();
            this.initialized = true;
        },

        bindEvents: function () {
            $('input[name="translation_provider"]').on('change', this.updateProviderSection.bind(this));
        },

        updateProviderSection: function () {
            var provider = $('input[name="translation_provider"]:checked').val();

            // Always show OpenAI settings since they're used in workflows regardless of main translation provider
            var $toggleButton = $('#toggle-openai-section');
            $toggleButton.show();

            // Initialize OpenAI manager if not already done
            if (!OpenAIManager.initialized) {
                OpenAIManager.init();
            }
        }
    };

    // --- Translation Path Rules Management ---
    var PathRulesManager = {
        init: function () {
            this.bindEvents();
            this.makeSortable();
        },
        bindEvents: function () {
            $(document).on('click', '#openai-path-add-rule', this.addRule.bind(this));
            $(document).on('click', '.openai-path-remove', this.removeRule.bind(this));
            // Reload translation pairs when any select in the rules list changes
            $(document).on('change', '#openai-path-rules-list select', function () {
                if (window.OpenAIManager && typeof window.OpenAIManager.updateLanguagePairVisibility === 'function') {
                    window.OpenAIManager.updateLanguagePairVisibility();
                }
            });
        },
        addRule: function (e) {
            e.preventDefault();
            var $list = $('#openai-path-rules-list');
            var index = $list.children('.openai-path-rule').length;
            var $template = this.getRuleTemplate(index);
            $list.append($template);
            this.updateIndices();
            if (window.OpenAIManager && typeof window.OpenAIManager.updateLanguagePairVisibility === 'function') {
                window.OpenAIManager.updateLanguagePairVisibility();
            }
        },
        removeRule: function (e) {
            e.preventDefault();
            var $rule = $(e.target).closest('.openai-path-rule');
            $rule.remove();
            this.updateIndices();
            if (window.OpenAIManager && typeof window.OpenAIManager.updateLanguagePairVisibility === 'function') {
                window.OpenAIManager.updateLanguagePairVisibility();
            }
        },
        makeSortable: function () {
            if (typeof $ === 'undefined' || typeof $.fn.sortable === 'undefined') {
                // jQuery UI not loaded
                return;
            }
            $('#openai-path-rules-list').sortable({
                handle: '.drag-handle',
                update: this.updateIndices.bind(this)
            });
        },
        updateIndices: function () {
            $('#openai-path-rules-list .openai-path-rule').each(function (i, el) {
                var $el = $(el);
                $el.attr('data-index', i);
                $el.find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (name) {
                        var newName = name.replace(/openai_path_rules\[[0-9]+\]/, 'openai_path_rules[' + i + ']');
                        $(this).attr('name', newName);
                    }
                });
            });
        },
        getRuleTemplate: function (index) {
            // Always fetch the latest languages at the time of adding a rule
            var langs = (typeof window.POLYTRANS_LANGS !== 'undefined' && Array.isArray(window.POLYTRANS_LANGS)) ? window.POLYTRANS_LANGS : [];
            var langNames = (typeof window.POLYTRANS_LANG_NAMES !== 'undefined' && typeof window.POLYTRANS_LANG_NAMES === 'object') ? window.POLYTRANS_LANG_NAMES : {};
            var html = '';
            html += '<div class="openai-path-rule" data-index="' + index + '">';
            html += '<span class="drag-handle" title="Drag to reorder">☰</span>';
            html += '<select name="openai_path_rules[' + index + '][source]" class="openai-path-source">';
            html += '<option value="all">All</option>';
            if (langs.length > 0) {
                langs.forEach(function (lang) {
                    html += '<option value="' + lang + '">' + (langNames[lang] || lang.toUpperCase()) + '</option>';
                });
            }
            html += '</select> → ';
            html += '<select name="openai_path_rules[' + index + '][target]" class="openai-path-target">';
            html += '<option value="all">All</option>';
            if (langs.length > 0) {
                langs.forEach(function (lang) {
                    html += '<option value="' + lang + '">' + (langNames[lang] || lang.toUpperCase()) + '</option>';
                });
            }
            html += '</select> via ';
            html += '<select name="openai_path_rules[' + index + '][intermediate]" class="openai-path-intermediate">';
            html += '<option value="none">None (Direct)</option>';
            if (langs.length > 0) {
                langs.forEach(function (lang) {
                    html += '<option value="' + lang + '">' + (langNames[lang] || lang.toUpperCase()) + '</option>';
                });
            }
            html += '</select>';
            html += '<button type="button" class="button openai-path-remove" title="Remove">✕</button>';
            html += '</div>';
            return html;
        }
    };

    // Expose for debugging
    window.PathRulesManager = PathRulesManager;

    // Initialize managers on document ready
    $(function () {
        TranslationProviderManager.init();

        // Always show OpenAI toggle since OpenAI settings are used in workflows
        $('#toggle-openai-section').show();

        PathRulesManager.init();
    });

})(jQuery);
