// JavaScript for Tag Translation Admin Page
jQuery(document).ready(function ($) {
    // Save tag list
    $('#save-tag-list').on('click', function () {
        var tagList = $('#tag-list-textarea').val();
        $.post(ajaxurl, {
            action: 'polytrans_save_tag_list',
            tag_list: tagList,
            nonce: PolyTransTagTranslation.nonce
        }, function (resp) {
            if (resp.success) {
                $('#tag-list-saved').show().delay(1500).fadeOut();
                location.reload();
            } else {
                alert('Failed to save tag list');
            }
        });
    });
    $(".tag-translation-input").on("change", function () {
        var tag = $(this).data("tag");
        var lang = $(this).data("lang");
        var value = $(this).val();
        var translation_id = $(this).data("translation-id") || "";
        $.post(ajaxurl, {
            action: "polytrans_save_tag_translation",
            tag_id: tag,
            lang: lang,
            value: value,
            translation_id: translation_id,
            nonce: PolyTransTagTranslation.nonce
        }, function (resp) {
            if (resp.success) {
                if (typeof wp !== 'undefined' && wp.toast && wp.toast.success) {
                    wp.toast.success('Translation saved!');
                } else {
                    $("<div class='notice notice-success is-dismissible'><p>Translation saved!</p></div>")
                        .prependTo('.wrap').delay(2000).fadeOut();
                }
            }
        });
    });
    // Export CSV
    $('#export-tag-csv').on('click', function () {
        window.location = ajaxurl + '?action=polytrans_export_tag_csv&nonce=' + PolyTransTagTranslation.nonce;
    });
    // Import CSV UI
    $('#show-import-csv').on('click', function () {
        $('#import-csv-area').show();
        $(this).hide();
    });
    $('#import-csv-cancel').on('click', function () {
        $('#import-csv-area').hide();
        $('#show-import-csv').show();
    });
    // Import CSV submit (file)
    $('#import-csv-submit').on('click', function () {
        var fileInput = document.getElementById('import-csv-file');
        if (!fileInput.files.length) {
            alert('Please select a CSV file.');
            return;
        }
        var file = fileInput.files[0];
        var reader = new FileReader();
        reader.onload = function (e) {
            var csv = e.target.result;
            $.post(ajaxurl, {
                action: 'polytrans_import_tag_csv',
                csv: csv,
                nonce: PolyTransTagTranslation.nonce
            }, function (resp) {
                if (resp.success) {
                    location.reload();
                } else {
                    alert('Import failed: ' + (resp.data || 'Unknown error'));
                }
            });
        };
        reader.readAsText(file);
    });
    $('#toggle-tag-list').on('click', function () {
        $('#tag-list-area').toggle();
    });
});
