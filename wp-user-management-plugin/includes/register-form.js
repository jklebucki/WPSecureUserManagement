jQuery(document).ready(function($) {
    let captchaVerified = false;
    
    // Funkcja do odświeżania CAPTCHA
    function refreshCaptcha() {
        $.ajax({
            url: wpumAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpum_refresh_captcha',
                nonce: wpumAjax.nonce
            },
            beforeSend: function() {
                $('#wpum-captcha-display').css('opacity', '0.5');
                $('#wpum-captcha-refresh').addClass('rotating');
            },
            success: function(response) {
                if (response.success) {
                    $('#wpum-captcha-display').text(response.data.code);
                    $('#wpum-captcha-token').val(response.data.token);
                    $('#wpum-captcha').val(''); // Wyczyść pole input
                    captchaVerified = false; // Resetuj status weryfikacji
                    removeCaptchaError(); // Usuń komunikat błędu
                }
            },
            complete: function() {
                $('#wpum-captcha-display').css('opacity', '1');
                $('#wpum-captcha-refresh').removeClass('rotating');
            },
            error: function(xhr, status, error) {
                console.error('CAPTCHA refresh error:', error);
            }
        });
    }

    // Funkcja do weryfikacji CAPTCHA
    function verifyCaptcha(callback) {
        const captchaCode = $('#wpum-captcha').val().trim();
        const captchaToken = $('#wpum-captcha-token').val();
        
        if (!captchaCode) {
            showCaptchaError('Proszę wprowadzić kod');
            callback(false);
            return;
        }
        
        $.ajax({
            url: wpumAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpum_verify_captcha',
                nonce: wpumAjax.nonce,
                token: captchaToken,
                code: captchaCode
            },
            success: function(response) {
                if (response.success) {
                    captchaVerified = true;
                    showCaptchaSuccess();
                    callback(true);
                } else {
                    captchaVerified = false;
                    showCaptchaError(response.data.message || 'Nieprawidłowy kod');
                    callback(false);
                }
            },
            error: function(xhr, status, error) {
                console.error('CAPTCHA verification error:', error);
                showCaptchaError('Błąd weryfikacji. Spróbuj ponownie.');
                callback(false);
            }
        });
    }

    // Funkcja do wyświetlania błędu CAPTCHA
    function showCaptchaError(message) {
        removeCaptchaError();
        removeCaptchaSuccess();
        
        $('#wpum-captcha').addClass('wpum-input-error');
        
        const errorHtml = '<div class="wpum-captcha-error">' + message + '</div>';
        $('.wpum-captcha-container').append(errorHtml);
    }

    // Funkcja do wyświetlania sukcesu CAPTCHA
    function showCaptchaSuccess() {
        removeCaptchaError();
        removeCaptchaSuccess();
        
        $('#wpum-captcha').removeClass('wpum-input-error').addClass('wpum-input-success');
        
        const successHtml = '<div class="wpum-captcha-success">✓ Kod poprawny</div>';
        $('.wpum-captcha-container').append(successHtml);
    }

    // Funkcja do usuwania komunikatów błędu
    function removeCaptchaError() {
        $('#wpum-captcha').removeClass('wpum-input-error');
        $('.wpum-captcha-error').remove();
    }

    // Funkcja do usuwania komunikatów sukcesu
    function removeCaptchaSuccess() {
        $('#wpum-captcha').removeClass('wpum-input-success');
        $('.wpum-captcha-success').remove();
    }

    // Automatyczne odświeżenie CAPTCHA przy załadowaniu strony
    if ($('#wpum-registration-form').length) {
        refreshCaptcha();
    }

    // Odświeżenie CAPTCHA po kliknięciu przycisku
    $('#wpum-captcha-refresh').on('click', function(e) {
        e.preventDefault();
        refreshCaptcha();
    });

    // Resetuj status weryfikacji gdy użytkownik zmienia kod
    $('#wpum-captcha').on('input', function() {
        captchaVerified = false;
        removeCaptchaError();
        removeCaptchaSuccess();
        $(this).removeClass('wpum-input-error wpum-input-success');
    });

    // Weryfikacja CAPTCHA przy opuszczeniu pola
    $('#wpum-captcha').on('blur', function() {
        const captchaCode = $(this).val().trim();
        if (captchaCode && !captchaVerified) {
            verifyCaptcha(function(isValid) {
                // Callback - nic nie robimy, tylko pokazujemy rezultat
            });
        }
    });

    // Przechwycenie wysłania formularza
    $('#wpum-registration-form').on('submit', function(e) {
        e.preventDefault(); // Zatrzymaj domyślne wysyłanie
        
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        // Sprawdź czy CAPTCHA została już zweryfikowana
        if (captchaVerified) {
            // CAPTCHA poprawna, wyślij formularz
            submitButton.prop('disabled', true).text('Rejestrowanie...');
            form.off('submit').submit(); // Wyłącz handler i wyślij
        } else {
            // Weryfikuj CAPTCHA przed wysłaniem
            submitButton.prop('disabled', true).text('Sprawdzanie...');
            
            verifyCaptcha(function(isValid) {
                if (isValid) {
                    // CAPTCHA poprawna, wyślij formularz
                    submitButton.text('Rejestrowanie...');
                    form.off('submit').submit(); // Wyłącz handler i wyślij
                } else {
                    // CAPTCHA niepoprawna, przywróć przycisk
                    submitButton.prop('disabled', false).text('Zarejestruj się');
                    // Przewiń do pola CAPTCHA
                    $('html, body').animate({
                        scrollTop: $('#wpum-captcha').offset().top - 100
                    }, 500);
                    $('#wpum-captcha').focus();
                }
            });
        }
        
        return false; // Zapobiegaj wysłaniu formularza
    });
});
