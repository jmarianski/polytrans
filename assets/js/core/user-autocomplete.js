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
                minLength: 1,
                source: function (request, response) {
                    $.getJSON('/wp-json/wp/v2/users', {
                        search: request.term,
                        per_page: 20
                    }, function (data) {
                        response(data.map(function (u) {
                            return {
                                label: u.name + ' (' + u.user_email + ')',
                                value: u.name + ' (' + u.user_email + ')',
                                id: u.id
                            };
                        }));
                    });
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
