(function ($) {
    $(function () {
        // Generic user autocomplete for any .user-autocomplete-input
        $('.user-autocomplete-input').each(function () {
            var $input = $(this);
            var hiddenSelector = $input.data('user-autocomplete-for');
            var $hidden = hiddenSelector ? $(hiddenSelector) : $input.siblings('input[type="hidden"]');
            var clearSelector = $input.data('user-autocomplete-clear');
            var $clear = clearSelector ? $(clearSelector) : $input.siblings('.user-autocomplete-clear');
            $input.autocomplete({
                minLength: 2,
                source: function (request, response) {
                    if (typeof PolyTransUserAutocomplete !== 'undefined' && PolyTransUserAutocomplete.ajaxUrl) {
                        // Use custom AJAX endpoint
                        $.post(PolyTransUserAutocomplete.ajaxUrl, {
                            action: 'polytrans_search_users',
                            search: request.term,
                            nonce: PolyTransUserAutocomplete.nonce
                        }, function (data) {
                            if (data.success) {
                                response(data.data.users.map(function (u) {
                                    return {
                                        label: u.label,
                                        value: u.label,
                                        id: u.id
                                    };
                                }));
                            } else {
                                response([]);
                            }
                        });
                    } else {
                        // Fallback to WP REST API
                        $.getJSON('/wp-json/wp/v2/users', {
                            search: request.term,
                            per_page: 20
                        }, function (data) {
                            response(data.map(function (u) {
                                return {
                                    label: u.name + (u.user_email ? ' (' + u.user_email + ')' : ''),
                                    value: u.name + (u.user_email ? ' (' + u.user_email + ')' : ''),
                                    id: u.id
                                };
                            }));
                        });
                    }
                },
                select: function (event, ui) {
                    $input.val(ui.item.label);
                    $hidden.val(ui.item.id);
                    $clear.show();
                    return false;
                },
                change: function (event, ui) {
                    if (!ui.item) {
                        $input.val('');
                        $hidden.val('none');
                        $clear.hide();
                    }
                }
            });
            $clear.on('click', function () {
                $input.val('');
                $hidden.val('none');
                $clear.hide();
            });
        });
    });
})(jQuery);
