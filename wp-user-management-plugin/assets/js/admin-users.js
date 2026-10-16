/**
 * WP User Management - admin user list: selection, bulk delete confirmation dialog.
 */
(function ($) {
    'use strict';

    var i18n = (window.wpumUsers && window.wpumUsers.i18n) || {};

    var $all = $('#wpum-select-all');
    var $count = $('#wpum-selected-count');
    var $action = $('#wpum-bulk-action');
    var $modal = $('#wpum-bulk-modal');
    var $list = $('#wpum-bulk-list');
    var $summary = $('#wpum-bulk-summary');
    var $reassign = $('#wpum-reassign-to');
    var lastFocus = null;

    function enabledBoxes() { return $('.wpum-user-cb:not(:disabled)'); }
    function checkedBoxes() { return $('.wpum-user-cb:checked'); }

    function refresh() {
        var total = enabledBoxes().length;
        var selected = checkedBoxes().length;
        $count.text(selected ? String(i18n.selected || '%d').replace('%d', selected) : '');
        $all.prop('checked', total > 0 && selected === total).prop('indeterminate', selected > 0 && selected < total);
    }

    $all.on('change', function () {
        enabledBoxes().prop('checked', this.checked);
        refresh();
    });
    $(document).on('change', '.wpum-user-cb', refresh);

    function syncReassign() {
        var mode = $modal.find('input[name="wpum_content"]:checked').val();
        $reassign.prop('disabled', mode !== 'reassign');
    }

    function openModal() {
        var $selected = checkedBoxes();
        var ids = $selected.map(function () { return String(this.value); }).get();

        $summary.text(ids.length === 1
            ? (i18n.summaryOne || '')
            : String(i18n.summaryMany || '%d').replace('%d', ids.length));

        $list.empty();
        $selected.slice(0, 10).each(function () {
            $('<li>').text($(this).data('login')).appendTo($list);
        });
        if (ids.length > 10) {
            $('<li>').text(String(i18n.more || '%d').replace('%d', ids.length - 10)).appendTo($list);
        }

        // The account receiving the content cannot be one that is being deleted.
        $reassign.find('option').each(function () {
            $(this).prop('disabled', ids.indexOf(String(this.value)) !== -1);
        });
        if ($reassign.find('option:selected').prop('disabled')) {
            $reassign.val($reassign.find('option:not(:disabled)').first().val());
        }
        syncReassign();

        lastFocus = document.activeElement;
        $modal.prop('hidden', false);
        $('body').addClass('wpum-modal-open');
        $('#wpum-bulk-cancel').trigger('focus');
    }

    function closeModal() {
        $modal.prop('hidden', true);
        $('body').removeClass('wpum-modal-open');
        $('#wpum-bulk-confirm').val('');
        if (lastFocus) { $(lastFocus).trigger('focus'); }
    }

    $('#wpum-bulk-apply').on('click', function () {
        if ($action.val() !== 'delete') {
            $action.trigger('focus');
            return;
        }
        if (!checkedBoxes().length) {
            $count.text(i18n.none || '');
            return;
        }
        openModal();
    });

    $modal.on('change', 'input[name="wpum_content"]', syncReassign);
    $('#wpum-bulk-cancel').on('click', closeModal);

    // Only the dialog's confirm button marks the request as confirmed (the server rejects it otherwise).
    $('#wpum-bulk-submit').on('click', function () {
        $('#wpum-bulk-confirm').val('1');
    });

    $modal.on('mousedown', function (event) {
        if (event.target === this) { closeModal(); }
    });
    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && !$modal.prop('hidden')) { closeModal(); }
    });

    refresh();
})(jQuery);
