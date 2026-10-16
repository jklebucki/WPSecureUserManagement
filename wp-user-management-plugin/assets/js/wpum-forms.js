/**
 * WP User Management - shared front-end behaviour: password strength meter,
 * password match check, double-submit protection.
 */
(function ($) {
    'use strict';

    var cfg = window.wpumFront || {};
    var i18n = cfg.i18n || {};
    var minLength = parseInt(cfg.minPasswordLength, 10) || 8;

    function passwordScore(password) {
        var score = 0;
        if (password.length >= minLength) { score++; }
        if (/[A-Z]/.test(password)) { score++; }
        if (/[a-z]/.test(password)) { score++; }
        if (/[0-9]/.test(password)) { score++; }
        if (/[^A-Za-z0-9]/.test(password)) { score++; }
        return score;
    }

    function updateStrength($meter, password) {
        var score = password ? passwordScore(password) : 0;
        var level = 0;
        var key = '';

        if (score > 0 && score <= 2) { level = 1; key = 'weak'; }
        else if (score === 3) { level = 2; key = 'medium'; }
        else if (score === 4) { level = 3; key = 'strong'; }
        else if (score >= 5) { level = 4; key = 'strong'; }

        $meter
            .attr('data-level', level)
            .removeClass('weak medium strong')
            .addClass(key)
            .find('.wpum-strength__label')
            .text(key ? (i18n[key] || key) : '');
    }

    function updateMatch($form) {
        var password = $form.find('#wpum-password').val() || '';
        var confirm = $form.find('#wpum-confirm-password').val() || '';
        var $message = $form.find('.wpum-match');
        var ok = password !== '' && password === confirm;

        $form.find('[data-wpum-match]').prop('disabled', !ok);

        if (password && confirm) {
            $message
                .text(ok ? (i18n.passwordMatch || '') : (i18n.passwordDiff || ''))
                .toggleClass('is-ok', ok)
                .toggleClass('is-bad', !ok);
        } else {
            $message.text('').removeClass('is-ok is-bad');
        }
    }

    $(document).on('input', '.wpum-ui #wpum-password', function () {
        updateStrength($(this).closest('form').find('.wpum-strength'), $(this).val());
    });

    $(document).on('input', '.wpum-ui #wpum-password, .wpum-ui #wpum-confirm-password', function () {
        updateMatch($(this).closest('form'));
    });

    // Prevent double submits (the AJAX credentials form manages its own button).
    $(document).on('submit', '.wpum-ui form', function () {
        var $form = $(this);
        if ($form.is('#wpum-shooting-credentials-form')) {
            return;
        }
        var $buttons = $form.find('button[type="submit"]');
        window.setTimeout(function () {
            $buttons.prop('disabled', true).addClass('is-busy');
        }, 0);
    });

    // Back/forward cache restores the page with disabled buttons - undo that.
    $(window).on('pageshow', function (event) {
        if (event.originalEvent && event.originalEvent.persisted) {
            $('.wpum-ui form button[type="submit"].is-busy').prop('disabled', false).removeClass('is-busy');
        }
    });
})(jQuery);
