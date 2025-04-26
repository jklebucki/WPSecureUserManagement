jQuery(document).ready(function($) {
    // Obsługa zakładek
    $('.wpum-tabs button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Usuń klasę active ze wszystkich przycisków i zawartości
        $('.wpum-tabs button').removeClass('active');
        $('.wpum-tab-content').removeClass('active');
        
        // Dodaj klasę active do klikniętego przycisku i odpowiedniej zawartości
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
    });

    // Obsługa formularza zmiany hasła
    $('#wpum-password, #wpum-confirm-password').on('input', function() {
        var password = $('#wpum-password').val();
        var confirmPassword = $('#wpum-confirm-password').val();
        var submitButton = $('#wpum-change-password-button');
        
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
    $('#wpum-delete-account-button').on('click', function(e) {
        e.preventDefault();
        $('body').addClass('modal-open');
        $('#wpum-delete-account-modal').removeClass('hidden');
    });

    // Zamykanie modalu
    $('.wpum-close, .wpum-cancel').on('click', function() {
        $('body').removeClass('modal-open');
        $('#wpum-delete-account-modal').addClass('hidden');
    });

    // Zamykanie modalu po kliknięciu w tło
    $(document).on('click', '.wpum-modal', function(e) {
        if (e.target === this) {
            $('body').removeClass('modal-open');
            $(this).addClass('hidden');
        }
    });

    // Obsługa klawisza ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && !$('#wpum-delete-account-modal').hasClass('hidden')) {
            $('body').removeClass('modal-open');
            $('#wpum-delete-account-modal').addClass('hidden');
        }
    });

    // Zatrzymanie propagacji kliknięć wewnątrz modalu
    $('.wpum-modal-content').on('click', function(e) {
        e.stopPropagation();
    });

    // Obsługa formularza usuwania konta
    $('#wpum-delete-account-form').on('submit', function(e) {
        // Form będzie wysłany normalnie
    });
    
    // Obsługa formularza shooting credentials
    $('#wpum-shooting-credentials-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'wpum_save_credentials');
        formData.append('wpum_nonce', wpumAjax.nonce);
        
        $.ajax({
            url: wpumAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#wpum-messages')
                        .html(response.data.message)
                        .removeClass('error')
                        .addClass('success')
                        .show();
                    
                    if (response.data.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    $('#wpum-messages')
                        .html(response.data.message)
                        .removeClass('success')
                        .addClass('error')
                        .show();
                }
            },
            error: function() {
                $('#wpum-messages')
                    .html('An error occurred. Please try again.')
                    .removeClass('success')
                    .addClass('error')
                    .show();
            }
        });
    });
});
