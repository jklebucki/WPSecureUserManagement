jQuery(document).ready(function($) {
    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[\W_]/)) strength++;
        return strength;
    }

    function updateStrengthMeter(strength) {
        const meter = $('#password-strength-meter');
        meter.removeClass('weak medium strong');
        if (strength <= 2) {
            meter.addClass('weak').text('Weak');
        } else if (strength === 3) {
            meter.addClass('medium').text('Medium');
        } else {
            meter.addClass('strong').text('Strong');
        }
    }

    $('#wpum-password').on('input', function() {
        const password = $(this).val();
        const strength = checkPasswordStrength(password);
        updateStrengthMeter(strength);
    });
});
