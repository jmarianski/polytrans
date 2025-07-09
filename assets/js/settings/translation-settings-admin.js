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
        });

        $('input[name="allowed_sources[]"]').on('change', function () {
            // Trigger OpenAI language pair filtering if available
            if (window.OpenAIManager && window.OpenAIManager.updateLanguagePairVisibility) {
                window.OpenAIManager.updateLanguagePairVisibility();
            }
        });

        updateLangConfigVisibility();

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
