/**
 * WP User Management - profile page: tabs, delete-account dialog, AJAX credentials form.
 */
(function ($) {
    'use strict';

    var cfg = window.wpumFront || {};
    var i18n = cfg.i18n || {};

    /* Tabs ---------------------------------------------------------- */
    var $tabs = $('.wpum-tab');

    function activateTab(id) {
        $tabs.each(function () {
            var active = $(this).data('tab') === id;
            $(this).toggleClass('is-active', active).attr('aria-selected', active ? 'true' : 'false');
        });
        $('.wpum-panel').each(function () {
            $(this).toggleClass('is-active', this.id === id);
        });
    }

    $tabs.on('click', function () {
        activateTab($(this).data('tab'));
    });

    $tabs.on('keydown', function (event) {
        var index = $tabs.index(this);
        var next = null;
        if (event.key === 'ArrowRight') { next = $tabs.eq((index + 1) % $tabs.length); }
        if (event.key === 'ArrowLeft') { next = $tabs.eq((index - 1 + $tabs.length) % $tabs.length); }
        if (next) {
            event.preventDefault();
            next.trigger('focus').trigger('click');
        }
    });

    /* Delete-account dialog ----------------------------------------- */
    var $modal = $('#wpum-delete-account-modal');
    var lastFocus = null;

    // Move to <body> so no theme container can clip or restyle it.
    $modal.appendTo('body');

    function openModal() {
        lastFocus = document.activeElement;
        $modal.prop('hidden', false);
        $('body').addClass('wpum-modal-open');
        $modal.find('input[type="password"]').first().trigger('focus');
    }

    function closeModal() {
        $modal.prop('hidden', true);
        $('body').removeClass('wpum-modal-open');
        if (lastFocus) { $(lastFocus).trigger('focus'); }
    }

    $('#wpum-delete-account-button').on('click', function (event) {
        event.preventDefault();
        openModal();
    });

    $modal.on('click', '.wpum-modal__close, .wpum-cancel', closeModal);

    $modal.on('mousedown', function (event) {
        if (event.target === this) { closeModal(); }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && !$modal.prop('hidden')) { closeModal(); }
    });

    /* Credentials (AJAX) -------------------------------------------- */
    function showMessage(text, type) {
        var $box = $('#wpum-messages');
        $box.removeClass('wpum-notice--success wpum-notice--error').addClass('wpum-notice--' + type).empty();
        $.each(String(text).split('\n'), function (index, line) {
            if (index) { $box.append('<br>'); }
            $box.append(document.createTextNode(line)); // text node: never interpret server text as HTML
        });
        $box.prop('hidden', false);
    }

    $('#wpum-shooting-credentials-form').on('submit', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]').prop('disabled', true).addClass('is-busy');
        var formData = new FormData(this);
        formData.append('action', 'wpum_save_credentials');

        $.ajax({
            url: cfg.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function (response) {
            if (response && response.success) {
                showMessage(response.data.message, 'success');
                if (response.data.reload) {
                    window.setTimeout(function () { window.location.reload(); }, 1500);
                }
            } else {
                showMessage((response && response.data && response.data.message) || i18n.saveError, 'error');
            }
        }).fail(function () {
            showMessage(i18n.saveError, 'error');
        }).always(function () {
            $button.prop('disabled', false).removeClass('is-busy');
        });
    });
})(jQuery);
