(function ($) {
    $(function () {
        // Tab functionality
        function initTabs() {
            $('.nav-tab').on('click', function (e) {
                e.preventDefault();

                // Remove active class from all tabs and content
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active').hide();

                // Add active class to clicked tab
                $(this).addClass('nav-tab-active');

                // Show corresponding content
                var targetId = $(this).attr('href');
                $(targetId).addClass('active').show();

                // Trigger Language Pairs filtering when switching to Language Pairs tab
                if (targetId === '#language-pairs-settings') {
                    if (window.PolyTransLanguagePairs && window.PolyTransLanguagePairs.updateLanguagePairVisibility) {
                        window.PolyTransLanguagePairs.updateLanguagePairVisibility();
                    }
                }

                // Store active tab in localStorage
                try {
                    localStorage.setItem('polytrans-active-tab', targetId);
                } catch (e) { }
            });

            // Restore active tab from localStorage
            try {
                var activeTab = localStorage.getItem('polytrans-active-tab');
                if (activeTab && $(activeTab).length) {
                    $('.nav-tab[href="' + activeTab + '"]').trigger('click');
                }
            } catch (e) { }
        }

        function updateLangConfigVisibility() {
            var allowed = [];
            $('input[name="allowed_targets[]"]:checked').each(function () {
                allowed.push($(this).val());
            });
            $('#translation-settings-table tbody tr').each(function () {
                var lang = $(this).data('lang');
                if (allowed.indexOf(lang) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        // Add data-lang to each row for easy lookup (use value from input[name^='status'] for robustness)
        $('#translation-settings-table tbody tr').each(function () {
            var lang = $(this).find('select[name^="status["]').attr('name');
            if (lang) {
                lang = lang.match(/status\[(.+)\]/);
                if (lang && lang[1]) {
                    $(this).attr('data-lang', lang[1]);
                }
            }
        });

        $('input[name="allowed_targets[]"]').on('change', function () {
            updateLangConfigVisibility();
            // Also trigger OpenAI language pair filtering if available
            if (window.OpenAIManager && window.OpenAIManager.updateLanguagePairVisibility) {
                window.OpenAIManager.updateLanguagePairVisibility();
            }
            // Trigger Language Pairs filtering
            if (window.PolyTransLanguagePairs && window.PolyTransLanguagePairs.updateLanguagePairVisibility) {
                window.PolyTransLanguagePairs.updateLanguagePairVisibility();
            }
        });

        $('input[name="allowed_sources[]"]').on('change', function () {
            // Trigger OpenAI language pair filtering if available
            if (window.OpenAIManager && window.OpenAIManager.updateLanguagePairVisibility) {
                window.OpenAIManager.updateLanguagePairVisibility();
            }
            // Trigger Language Pairs filtering
            if (window.PolyTransLanguagePairs && window.PolyTransLanguagePairs.updateLanguagePairVisibility) {
                window.PolyTransLanguagePairs.updateLanguagePairVisibility();
            }
        });

        updateLangConfigVisibility();

        // Language Pairs filtering based on path rules
        window.PolyTransLanguagePairs = {
            updateLanguagePairVisibility: function () {
                // Only run if we're on Language Pairs tab
                if (!$('#language-pairs-settings').is(':visible')) {
                    return;
                }

                // 1. Get allowed source/target languages
                var allowedSources = [];
                var allowedTargets = [];
                $('input[name="allowed_sources[]"]:checked').each(function () {
                    allowedSources.push($(this).val());
                });
                $('input[name="allowed_targets[]"]:checked').each(function () {
                    allowedTargets.push($(this).val());
                });

                // If no allowed sources/targets, show all pairs (fallback)
                if (allowedSources.length === 0 || allowedTargets.length === 0) {
                    $('.language-pair-row').show();
                    $('.no-pairs-message').remove();
                    return;
                }

                // 2. Get all rules from the DOM
                var rules = [];
                $('#path-rules-container .openai-path-rule, #path-rules-table .openai-path-rule').each(function (i) {
                    var $rule = $(this);
                    var source = $rule.find('.openai-path-source').val();
                    var target = $rule.find('.openai-path-target').val();
                    var intermediate = $rule.find('.openai-path-intermediate').val() || '';
                    if (source && target) {
                        rules.push({ 
                            source: source, 
                            target: target, 
                            intermediate: intermediate === '' ? 'none' : intermediate, 
                            index: i 
                        });
                    }
                });

                // 3. If no rules, show all pairs (backward compatibility)
                if (rules.length === 0) {
                    $('.language-pair-row').show();
                    $('.no-pairs-message').remove();
                    return;
                }

                // 4. Expand all rules into all possible pairs
                var pairToRules = {};
                rules.forEach(function (rule) {
                    var sources = (rule.source === 'all') ? allowedSources : [rule.source];
                    var targets = (rule.target === 'all') ? allowedTargets : [rule.target];
                    
                    // Debug log
                    console.log('[PolyTrans Language Pairs] Processing rule:', rule, 'sources:', sources, 'targets:', targets);
                    
                    sources.forEach(function (src) {
                        targets.forEach(function (tgt) {
                            if (src === tgt) return;
                            if (rule.intermediate === 'none' || rule.intermediate === '') {
                                // Direct pair
                                var key = src + '_to_' + tgt;
                                if (!pairToRules[key]) pairToRules[key] = [];
                                pairToRules[key].push({
                                    type: 'direct',
                                    rule: rule
                                });
                                console.log('[PolyTrans Language Pairs] Added direct pair:', key);
                            } else {
                                // Via intermediate: src->inter and inter->tgt
                                // Only create pairs if source/target are different from intermediate
                                if (src !== rule.intermediate) {
                                    var key1 = src + '_to_' + rule.intermediate;
                                    if (!pairToRules[key1]) pairToRules[key1] = [];
                                    pairToRules[key1].push({
                                        type: 'via',
                                        rule: rule,
                                        inter: rule.intermediate
                                    });
                                    console.log('[PolyTrans Language Pairs] Added via pair:', key1, 'from rule', rule.source, '->', rule.target, 'via', rule.intermediate);
                                }
                                if (rule.intermediate !== tgt) {
                                    var key2 = rule.intermediate + '_to_' + tgt;
                                    if (!pairToRules[key2]) pairToRules[key2] = [];
                                    pairToRules[key2].push({
                                        type: 'via',
                                        rule: rule,
                                        inter: rule.intermediate
                                    });
                                    console.log('[PolyTrans Language Pairs] Added via pair:', key2, 'from rule', rule.source, '->', rule.target, 'via', rule.intermediate);
                                }
                            }
                        });
                    });
                });
                
                console.log('[PolyTrans Language Pairs] All pairs:', pairToRules);

                // 5. Show/hide language pair rows
                var visiblePairs = 0;
                $('.language-pair-row').each(function () {
                    var $row = $(this);
                    var pairKey = $row.data('pair');
                    var matchingRules = pairToRules[pairKey] || [];
                    
                    if (matchingRules.length > 0) {
                        $row.show();
                        visiblePairs++;
                    } else {
                        $row.hide();
                    }
                });

                // Show message if no pairs visible
                var $table = $('.language-pair-row').closest('table');
                var $noPairsMsg = $table.find('.no-pairs-message');
                if (visiblePairs === 0 && $('.language-pair-row').length > 0) {
                    if ($noPairsMsg.length === 0) {
                        $table.find('tbody').prepend(
                            '<tr class="no-pairs-message"><td colspan="2" style="text-align: center; padding: 20px; color: #666;"><em>' +
                            'No language pairs match the current path rules. Adjust your rules or add a rule that covers the pairs you need.' +
                            '</em></td></tr>'
                        );
                    }
                } else {
                    $noPairsMsg.remove();
                }
            }
        };

        // Initialize tabs
        initTabs();

        // Secret generator button logic
        var btn = document.getElementById('generate-translation-secret');
        var input = document.getElementById('translation-receiver-secret');
        var initialSecret = input ? input.getAttribute('data-initial') : '';
        var confirmOverwrite = true;
        if (input && (!initialSecret || initialSecret.trim() === '')) {
            confirmOverwrite = false; // No need to confirm if no initial value
        }
        if (btn && input) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                // Only ask once, and only if there was a value set before entering the form
                if (confirmOverwrite && input.value && input.value.trim() !== '') {
                    if (!window.confirm('A secret was already set before opening this page. Do you want to overwrite it with a new one?')) {
                        return;
                    }
                    confirmOverwrite = false; // Only ask once
                }
                // Generate a secure random 32-char string (base64url)
                function randomSecret(length) {
                    var array = new Uint8Array(length);
                    if (window.crypto && window.crypto.getRandomValues) {
                        window.crypto.getRandomValues(array);
                    } else {
                        for (var i = 0; i < length; i++) array[i] = Math.floor(Math.random() * 256);
                    }
                    return btoa(String.fromCharCode.apply(null, array)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '').substring(0, length);
                }
                input.value = randomSecret(32);
                input.focus();
            });
        }
    });
})(jQuery);
