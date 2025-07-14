jQuery(function ($) {
    'use strict';

    // Cache selectors
    var $mergedList = $('#polytrans-merged-list');
    var $controls = $('.polytrans-controls');
    var $scope = $('#polytrans-scope');
    var $translateBtn = $('#polytrans-translate-btn');
    var $targetLangsRow = $('#polytrans-target-langs-row');
    var $targetLangs = $('#polytrans-target-langs');
    var $needsReview = $('#polytrans-needs-review');
    var $translateStatus = $('#polytrans-translate-status');

    var postId = PolyTransScheduler.postId;
    var langNames = PolyTransScheduler.lang_names || {};

    // Helper: get last log message or fallback
    function getLastLog(info, fallback) {
        return (info.log && info.log.length ? info.log[info.log.length - 1] : fallback);
    }

    // Helper: show/hide translation status UI
    function renderStatusUI(status) {
        console.log('[PolyTrans] renderStatusUI called with:', status);
        var hasAny = false;
        $mergedList.find('li').each(function () {
            var lang = this.id.replace('polytrans-merged-', '');
            var info = status[lang];
            console.log('[PolyTrans] Processing language:', lang, 'info:', info);
            var $li = $(this);
            var $loader = $li.find('.polytrans-loader');
            var $check = $li.find('.polytrans-check');
            var $failed = $li.find('.polytrans-failed');
            var $editBtn = $li.find('.polytrans-edit-btn');
            var $clearBtn = $li.find('.polytrans-clear-translation');
            if (info && (info.status === 'started' || info.status === 'translating' || info.status === 'processing')) {
                console.log('[PolyTrans] Showing started status for:', lang);
                $li.show();
                $loader.show();
                $check.hide();
                $editBtn.hide();
                $clearBtn.show();
                hasAny = true;
            } else if (info && (info.status === 'finished' || info.status === 'completed') && info.post_id) {
                console.log('[PolyTrans] Showing finished status for:', lang, 'post_id:', info.post_id);
                $li.show();
                $loader.hide();
                $check.show();
                $editBtn.show().attr('href', PolyTransScheduler.edit_url.replace('__ID__', info.post_id));
                $clearBtn.show();
                hasAny = true;
            } else if (info && info.status === 'failed') {
                $li.show();
                $loader.hide();
                $check.hide();
                $failed.show();
                $editBtn.hide();
                $clearBtn.show();
                hasAny = true;
            } else {
                console.log('[PolyTrans] Hiding status for:', lang, 'info status:', info ? info.status : 'no info');
                $li.hide();
                $editBtn.hide();
                $clearBtn.hide();
            }
        });
        console.log('[PolyTrans] hasAny:', hasAny);
        if (hasAny) {
            $mergedList.show();
            $controls.hide();
        } else {
            $mergedList.hide();
            $controls.show();
            clearInterval(window.polytransPollInterval);
        }
    }

    // Helper: fetch status from server and update UI
    function fetchStatusAndRender() {
        $.post(PolyTransScheduler.ajax_url, {
            action: 'polytrans_get_translation_status',
            post_id: postId,
            _ajax_nonce: PolyTransScheduler.nonce
        }, function (resp) {
            console.log('[PolyTrans] Status response:', resp);
            if (resp && resp.success && resp.data && resp.data.status) {
                console.log('[PolyTrans] Rendering status:', resp.data.status);
                renderStatusUI(resp.data.status);
            } else {
                console.warn('[PolyTrans] Invalid status response:', resp);
            }
        }).fail(function (xhr, status, error) {
            console.error('[PolyTrans] Status fetch failed:', error, xhr.responseText);
        });
    }

    // Helper: start polling for status updates
    function startPolling() {
        if (window.polytransPollInterval) clearInterval(window.polytransPollInterval);
        window.polytransPollInterval = setInterval(fetchStatusAndRender, 5000);
    }

    // Initial fetch and polling
    fetchStatusAndRender();
    startPolling();

    // Monitor form changes to disable translation when dirty
    function checkFormDirty() {
        var isDirty = false;
        var dirtyReason = '';

        // Check TinyMCE editor if available
        if (window.tinymce && window.tinymce.get('content')) {
            var editor = window.tinymce.get('content');
            if (!editor.isHidden() && editor.isDirty()) {
                isDirty = true;
                dirtyReason = 'TinyMCE editor has changes';
            }
        }

        // Check WordPress autosave if available
        if (!isDirty && window.wp && window.wp.autosave) {
            if (wp.autosave.server.postChanged()) {
                isDirty = true;
                dirtyReason = 'WordPress autosave detected changes';
            }
        }

        // Check if any form fields have changed
        if (!isDirty) {
            $('#post input:not([name*="polytrans"], #active_post_lock), #post textarea:not([name*="polytrans"]), #post select:not([name*="polytrans"])').each(function () {
                var $field = $(this);
                var originalValue = $field.data('original-value');
                var currentValue = $field.val();

                // Skip if this is a hidden WordPress field that changes automatically
                if ($field.attr('name') && (
                    $field.attr('name').indexOf('_wp') === 0 ||
                    $field.attr('name').indexOf('_ajax') === 0 ||
                    $field.attr('name').indexOf('action') === 0 ||
                    $field.attr('name').indexOf('post_ID') === 0
                )) {
                    return true; // Continue to next field
                }

                if (originalValue === undefined) {
                    $field.data('original-value', currentValue);
                } else if (originalValue !== currentValue) {
                    isDirty = true;
                    dirtyReason = 'Form field changed: ' + ($field.attr('name') || $field.attr('id') || 'unknown');
                    return false; // Break out of loop
                }
            });
        }

        // Debug logging
        if (isDirty) {
            console.error('[PolyTrans] Translation disabled - ' + dirtyReason);
        }

        // Disable/enable translation controls based on dirty state
        if (isDirty) {
            $translateBtn.prop('disabled', true);
            $scope.prop('disabled', true);
            $targetLangs.prop('disabled', true);
            $needsReview.prop('disabled', true);

            // Show dirty warning if not already shown
            if (!$('#polytrans-dirty-warning').length) {
                $controls.prepend(
                    '<div id="polytrans-dirty-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 8px; margin-bottom: 10px; border-radius: 3px; color: #856404;">' +
                    '<strong>⚠ Unsaved Changes:</strong> Save the post before translating.' +
                    '</div>'
                );
            }
        } else {
            $scope.prop('disabled', false);
            $targetLangs.prop('disabled', false);
            $needsReview.prop('disabled', false);
            $('#polytrans-dirty-warning').remove();
            // Re-trigger scope change to set correct button state
            $scope.trigger('change');
        }
    }

    // Check immediately and set up monitoring
    // First, store initial values for all form fields
    $('#post input:not([name*="polytrans"]), #post textarea:not([name*="polytrans"]), #post select:not([name*="polytrans"])').each(function () {
        $(this).data('original-value', $(this).val());
    });

    checkFormDirty();

    // Monitor form changes
    $(document).on('input change', '#post input:not([name*="_polytrans_"]), #post textarea:not([name*="_polytrans_"]), #post select:not([name*="_polytrans_"])', function () {
        setTimeout(checkFormDirty, 100); // Small delay to allow other handlers to run
    });

    // Monitor TinyMCE changes if available
    if (window.tinymce) {
        $(document).on('tinymce-editor-init', function (event, editor) {
            if (editor.id === 'content') {
                editor.on('change keyup', function () {
                    setTimeout(checkFormDirty, 100);
                });
            }
        });
    }

    // Monitor autosave changes
    if (window.wp && window.wp.autosave) {
        $(document).on('heartbeat-tick', function () {
            setTimeout(checkFormDirty, 100);
        });
    }

    // Listen for post save events to re-enable translation
    $(document).on('click', '#publish, #save-post', function () {
        // Wait a bit for the save to complete, then recheck
        setTimeout(function () {
            // Reset original values after save
            $('#post input:not([name*="_polytrans_"]), #post textarea:not([name*="_polytrans_"]), #post select:not([name*="_polytrans_"])').each(function () {
                $(this).data('original-value', $(this).val());
            });
            checkFormDirty();
        }, 1000);
    });

    // Handle scope change
    $scope.on('change', function () {
        var scopeVal = $scope.val();
        if (scopeVal === 'local') {
            $('#polytrans-scheduler-options').hide();
            $translateBtn.prop('disabled', true);
        } else {
            $('#polytrans-scheduler-options').show();
            $translateBtn.prop('disabled', false);
            if (scopeVal === 'regional') {
                $targetLangsRow.show();
            } else {
                $targetLangsRow.hide();
            }
        }
    }).trigger('change');

    // Handle translation button click
    $translateBtn.on('click', function () {
        var scopeVal = $scope.val();
        var targets = $targetLangs.val() || [];
        var needsReviewVal = $needsReview.is(':checked') ? 1 : 0;
        var data = {
            action: 'polytrans_schedule_translation',
            post_id: postId,
            scope: scopeVal,
            targets: targets,
            needs_review: needsReviewVal,
            _ajax_nonce: PolyTransScheduler.nonce
        };
        var $btn = $translateBtn;
        $btn.prop('disabled', true);
        $.post(PolyTransScheduler.ajax_url, data, function (resp) {
            if (resp && resp.success && resp.data) {
                fetchStatusAndRender();
                startPolling();
            } else {
                $translateStatus.text(resp.data && resp.data.message ? resp.data.message : resp.data);
                startPolling();
                console.error('Translation scheduling failed:', resp);
            }
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            $translateStatus.text('Error: ' + (xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Unknown error'));
            $btn.prop('disabled', false);
        });
    });

    // Handle clear button click
    $mergedList.on('click', '.polytrans-clear-translation', function () {
        var $btn = $(this);
        var lang = $btn.data('lang');
        if (!lang) return;
        if (!confirm('Are you sure you want to clear this translation?')) return;
        $.post(PolyTransScheduler.ajax_url, {
            action: 'polytrans_clear_translation_status',
            post_id: postId,
            lang: lang,
            _ajax_nonce: PolyTransScheduler.nonce
        }, function (resp) {
            if (resp && resp.success) {
                // Always re-fetch status after clear
                fetchStatusAndRender();
            } else {
                console.error(resp);
                alert('Failed to clear translation: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
            }
        });
    });

    // Handle edit button click (delegated)
    $(document).on('click', '.polytrans-edit-btn', function (e) {
        e.preventDefault();
        var url = $(this).attr('href');
        if (url && url !== '#') {
            window.open(url, '_blank');
        }
    });
});