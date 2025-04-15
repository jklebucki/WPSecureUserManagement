jQuery(document).ready(function($) {
    // Obsługa zakładek
    $('.sum-tabs button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Usuń klasę active ze wszystkich przycisków i zawartości
        $('.sum-tabs button').removeClass('active');
        $('.sum-tab-content').removeClass('active');
        
        // Dodaj klasę active do klikniętego przycisku i odpowiedniej zawartości
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
    });

    // Obsługa formularza zmiany hasła
    $('#sum-password, #sum-confirm-password').on('input', function() {
        var password = $('#sum-password').val();
        var confirmPassword = $('#sum-confirm-password').val();
        var submitButton = $('#sum-change-password-button');
        
        if (password && confirmPassword && password === confirmPassword) {
            submitButton.prop('disabled', false);
        } else {
            submitButton.prop('disabled', true);
        }
        
        // Pokaż komunikat o zgodności haseł
        if (password && confirmPassword) {
            if (password === confirmPassword) {
                $('#password-match-message').html('Hasła są zgodne').css('color', 'green');
            } else {
                $('#password-match-message').html('Hasła nie są zgodne').css('color', 'red');
            }
        } else {
            $('#password-match-message').html('');
        }
    });

    // Obsługa przycisku usuwania konta
    $('#sum-delete-account-button').on('click', function(e) {
        e.preventDefault();
        $('#sum-delete-account-modal').removeClass('hidden');
    });

    // Zamykanie modalu
    $('.sum-close, .sum-cancel').on('click', function() {
        $(this).closest('.sum-modal').addClass('hidden');
    });

    // Zamykanie modalu po kliknięciu poza nim
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('sum-modal')) {
            $('.sum-modal').addClass('hidden');
        }
    });

    // Obsługa formularza usuwania konta
    $('#sum-delete-account-form').on('submit', function(e) {
        // Form będzie wysłany normalnie
    });
});
