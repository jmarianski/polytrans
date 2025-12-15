/**
 * Universal Provider Settings JavaScript
 * Works with all providers using data attributes
 * 
 * Usage:
 * - API Key field: <input data-provider="provider_id" data-field="api-key" />
 * - Validate button: <button data-provider="provider_id" data-action="validate-key" />
 * - Model select: <select data-provider="provider_id" data-field="model" />
 * - Refresh button: <button data-provider="provider_id" data-action="refresh-models" />
 * - Toggle visibility: <button data-provider="provider_id" data-action="toggle-visibility" />
 */
(function ($) {
    'use strict';

    var UniversalProviderManager = {
        initialized: false,
        providers: {},

        init: function () {
            if (this.initialized) {
                return;
            }

            this.bindEvents();
            this.initializeProviders();
            this.initialized = true;
        },

        /**
         * Bind event handlers using event delegation
         */
        bindEvents: function () {
            // Validate API key
            $(document).on('click', '[data-provider][data-action="validate-key"]', this.validateApiKey.bind(this));
            
            // Toggle API key visibility
            $(document).on('click', '[data-provider][data-action="toggle-visibility"]', this.toggleApiKeyVisibility.bind(this));
            
            // Refresh models
            $(document).on('click', '[data-provider][data-action="refresh-models"]', this.refreshModels.bind(this));
            
            // Load models when provider tab is shown
            $(document).on('click', '.provider-settings-tab', this.onProviderTabClick.bind(this));
        },

        /**
         * Initialize all providers found on the page
         */
        initializeProviders: function () {
            var self = this;
            
            // Find all provider sections
            $('[data-provider-id]').each(function () {
                var $section = $(this);
                var providerId = $section.data('provider-id');
                
                if (!providerId) {
                    return;
                }
                
                // Initialize provider
                self.providers[providerId] = {
                    initialized: false,
                    modelsLoaded: false
                };
                
                // Load models if model select exists
                var $modelSelect = $section.find('[data-provider="' + providerId + '"][data-field="model"]');
                if ($modelSelect.length > 0) {
                    self.loadModels(providerId);
                }
            });
        },

        /**
         * Handle provider tab click - load models if not already loaded
         */
        onProviderTabClick: function (e) {
            var $tab = $(e.currentTarget);
            var providerId = $tab.attr('id').replace('-tab', '');
            
            // Load models if not already loaded
            if (this.providers[providerId] && !this.providers[providerId].modelsLoaded) {
                this.loadModels(providerId);
            }
        },

        /**
         * Validate API key for a provider
         */
        validateApiKey: function (e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var providerId = $button.data('provider');
            
            if (!providerId) {
                console.error('Provider ID not found');
                return;
            }
            
            var $input = $('[data-provider="' + providerId + '"][data-field="api-key"]');
            var $message = $('[data-provider="' + providerId + '"][data-field="validation-message"]');
            
            if ($input.length === 0) {
                console.error('API key input not found for provider: ' + providerId);
                return;
            }
            
            var apiKey = $input.val().trim();
            
            if (!apiKey) {
                this.showMessage($message, 'error', this.i18n('please_enter_api_key', 'Please enter an API key'));
                return;
            }
            
            var originalText = $button.text();
            $button.prop('disabled', true).text(this.i18n('validating', 'Validating...'));
            $message.empty();
            
            var ajaxUrl = this.getAjaxUrl();
            var nonce = this.getNonce();
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_validate_provider_key',
                    provider_id: providerId,
                    api_key: apiKey,
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success) {
                        this.showMessage($message, 'success', response.data || this.i18n('api_key_valid', 'API key is valid!'));
                    } else {
                        this.showMessage($message, 'error', response.data || this.i18n('api_key_invalid', 'Invalid API key'));
                    }
                }.bind(this),
                error: function () {
                    this.showMessage($message, 'error', this.i18n('validation_failed', 'Failed to validate API key. Please try again.'));
                }.bind(this),
                complete: function () {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Toggle API key visibility
         */
        toggleApiKeyVisibility: function (e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var providerId = $button.data('provider');
            
            if (!providerId) {
                console.error('Provider ID not found');
                return;
            }
            
            var $input = $('[data-provider="' + providerId + '"][data-field="api-key"]');
            
            if ($input.length === 0) {
                console.error('API key input not found for provider: ' + providerId);
                return;
            }
            
            var currentType = $input.attr('type');
            
            if (currentType === 'password') {
                $input.attr('type', 'text');
                $button.text('🔒');
            } else {
                $input.attr('type', 'password');
                $button.text('👁');
            }
        },

        /**
         * Load models for a provider
         */
        loadModels: function (providerId) {
            var $select = $('[data-provider="' + providerId + '"][data-field="model"]');
            
            if ($select.length === 0) {
                return; // No model select for this provider
            }
            
            // Get selected model
            var selectedModel = $select.val() || $select.data('selected-model') || '';
            
            // Get API key
            var $apiKeyInput = $('[data-provider="' + providerId + '"][data-field="api-key"]');
            var apiKey = $apiKeyInput.length > 0 ? $apiKeyInput.val() : '';
            
            // Fetch models
            this.fetchModels(providerId, apiKey, selectedModel, function (models) {
                this.updateModelSelect($select, models, selectedModel);
                if (this.providers[providerId]) {
                    this.providers[providerId].modelsLoaded = true;
                }
            }.bind(this));
        },

        /**
         * Fetch models from API
         */
        fetchModels: function (providerId, apiKey, selectedModel, callback) {
            var ajaxUrl = this.getAjaxUrl();
            var nonce = this.getNonce();
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'polytrans_get_provider_models',
                    provider_id: providerId,
                    selected_model: selectedModel || '',
                    nonce: nonce
                },
                success: function (response) {
                    if (response.success && response.data && response.data.models) {
                        callback(response.data.models);
                    } else {
                        // Fallback to empty or default models
                        callback({});
                    }
                }.bind(this),
                error: function () {
                    // Fallback to empty models on error
                    callback({});
                }.bind(this)
            });
        },

        /**
         * Update model select dropdown
         */
        updateModelSelect: function ($select, groupedModels, selectedModel) {
            if (!$select.length) {
                return;
            }
            
            // Clear existing options except "Loading models..."
            var $loadingOption = $select.find('option[value=""]');
            $select.empty();
            if ($loadingOption.length > 0) {
                $select.append($loadingOption);
            }
            
            // Check if we have models
            if (!groupedModels || Object.keys(groupedModels).length === 0) {
                // No models - add default option
                $select.append($('<option></option>').attr('value', '').text(this.i18n('no_models', 'No models available')));
                return;
            }
            
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
         * Refresh models for a provider
         */
        refreshModels: function (e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var providerId = $button.data('provider');
            
            if (!providerId) {
                console.error('Provider ID not found');
                return;
            }
            
            var $select = $('[data-provider="' + providerId + '"][data-field="model"]');
            var $message = $('[data-provider="' + providerId + '"][data-field="model-message"]');
            
            if ($select.length === 0) {
                return;
            }
            
            var selectedModel = $select.val() || '';
            var $apiKeyInput = $('[data-provider="' + providerId + '"][data-field="api-key"]');
            var apiKey = $apiKeyInput.length > 0 ? $apiKeyInput.val() : '';
            
            var originalText = $button.text();
            $button.prop('disabled', true).text(this.i18n('refreshing', 'Refreshing...'));
            
            this.fetchModels(providerId, apiKey, selectedModel, function (models) {
                this.updateModelSelect($select, models, selectedModel);
                $button.prop('disabled', false).text(originalText);
                
                // Show success message
                if ($message.length > 0) {
                    this.showMessage($message, 'success', this.i18n('models_refreshed', 'Models refreshed'));
                }
                
                if (this.providers[providerId]) {
                    this.providers[providerId].modelsLoaded = true;
                }
            }.bind(this));
        },

        /**
         * Show message in container
         */
        showMessage: function ($container, type, message) {
            if (!$container || !$container.length) {
                return;
            }
            
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var dismissText = this.i18n('dismiss_notice', 'Dismiss this notice');
            var html = '<div class="notice ' + className + ' is-dismissible inline"><p>' + 
                       this.escapeHtml(message) + 
                       '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' +
                       dismissText + 
                       '</span></button></div>';
            
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
         * Get AJAX URL
         */
        getAjaxUrl: function () {
            if (typeof PolyTransAjax !== 'undefined' && PolyTransAjax.ajaxurl) {
                return PolyTransAjax.ajaxurl;
            }
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }
            return null;
        },

        /**
         * Get nonce
         */
        getNonce: function () {
            // Try settings nonce first
            if (typeof PolyTransAjax !== 'undefined' && PolyTransAjax.nonce) {
                return PolyTransAjax.nonce;
            }
            // Try OpenAI nonce (for backward compatibility)
            if (typeof PolyTransAjax !== 'undefined' && PolyTransAjax.openai_nonce) {
                return PolyTransAjax.openai_nonce;
            }
            // Fallback to form nonce
            var $nonceInput = $('input[name="_wpnonce"]');
            if ($nonceInput.length > 0) {
                return $nonceInput.val();
            }
            return '';
        },

        /**
         * Internationalization helper
         */
        i18n: function (key, defaultValue) {
            if (typeof PolyTransAjax !== 'undefined' && 
                PolyTransAjax.i18n && 
                PolyTransAjax.i18n[key]) {
                return PolyTransAjax.i18n[key];
            }
            return defaultValue;
        },

        /**
         * Escape HTML
         */
        escapeHtml: function (text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function (m) { return map[m]; });
        }
    };

    // Make globally accessible
    window.UniversalProviderManager = UniversalProviderManager;

    // Initialize on document ready
    $(function () {
        UniversalProviderManager.init();
    });

})(jQuery);

