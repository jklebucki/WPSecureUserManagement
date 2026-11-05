/**
 * WP User Management - Turnstile on AJAX forms of other plugins: a token works once, so a refused attempt needs a new one.
 */
(function ($) {
    'use strict';

    $(document).on('wpformsAjaxSubmitFailed wpformsAjaxSubmitError', 'form.wpforms-form', function () {
        if (!window.turnstile) {
            return;
        }
        $(this).find('.wpum-turnstile').each(function () {
            window.turnstile.reset(this);
        });
    });
})(jQuery);
