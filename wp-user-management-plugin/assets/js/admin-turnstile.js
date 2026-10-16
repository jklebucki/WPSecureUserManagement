/**
 * WP User Management - Turnstile settings page: key test + widget preview.
 */
(function ($) {
    'use strict';

    var cfg = window.wpumTurnstileAdmin || {};
    var i18n = cfg.i18n || {};
    var $result = $('#wpum-ts-result');
    var widgetId = null;

    function showResult(text, ok) {
        $result.removeClass('is-ok is-bad').addClass(ok ? 'is-ok' : 'is-bad').text(text);
    }

    $('#wpum-ts-test').on('click', function () {
        $result.removeClass('is-ok is-bad').text(i18n.testing || '');

        $.post(cfg.ajaxurl, {
            action: 'wpum_turnstile_test',
            nonce: cfg.nonce,
            site_key: $('#wpum-ts-site').val(),
            secret_key: $('#wpum-ts-secret').val()
        }).done(function (response) {
            var message = response && response.data && response.data.message ? response.data.message : i18n.error;
            showResult(message, !!(response && response.success));
        }).fail(function () {
            showResult(i18n.error, false);
        });
    });

    $('#wpum-ts-preview').on('click', function () {
        var key = $.trim($('#wpum-ts-site').val()) || cfg.siteKey || '';

        if (!key) {
            showResult(i18n.noSiteKey, false);
            return;
        }
        if (!window.turnstile) {
            showResult(i18n.previewError, false);
            return;
        }

        $result.removeClass('is-ok is-bad').text('');
        if (widgetId !== null) {
            window.turnstile.remove(widgetId);
            widgetId = null;
        }

        try {
            widgetId = window.turnstile.render('#wpum-ts-widget', {
                sitekey: key,
                'error-callback': function () { showResult(i18n.previewError, false); }
            });
        } catch (error) {
            showResult(i18n.previewError, false);
        }
    });
})(jQuery);
