// This file contains JavaScript functionality for the plugin, such as form validation and dynamic interactions.

document.addEventListener('DOMContentLoaded', function () {
    const registrationForm = document.getElementById('sum-registration-form');
    const loginForm = document.getElementById('sum-login-form');
    const profileEditForm = document.getElementById('sum-profile-edit-form');

    if (registrationForm) {
        registrationForm.addEventListener('submit', function (event) {
            if (!validateRegistrationForm()) {
                event.preventDefault();
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            if (!validateLoginForm()) {
                event.preventDefault();
            }
        });
    }

    if (profileEditForm) {
        profileEditForm.addEventListener('submit', function (event) {
            if (!validateProfileEditForm()) {
                event.preventDefault();
            }
        });
    }

    function validateRegistrationForm() {
        const username = document.getElementById('sum-username').value;
        const email = document.getElementById('sum-email').value;
        const password = document.getElementById('sum-password').value;
        const confirmPassword = document.getElementById('sum-confirm-password').value;

        if (username.length < 3) {
            alert('Username must be at least 3 characters long.');
            return false;
        }

        if (!validateEmail(email)) {
            alert('Please enter a valid email address.');
            return false;
        }

        if (password !== confirmPassword) {
            alert('Passwords do not match.');
            return false;
        }

        return true;
    }

    function validateLoginForm() {
        const usernameOrEmail = document.getElementById('sum-username-or-email').value;
        const password = document.getElementById('sum-login-password').value;

        if (!usernameOrEmail || !password) {
            alert('Both fields are required.');
            return false;
        }

        return true;
    }

    function validateProfileEditForm() {
        const email = document.getElementById('sum-profile-email').value;

        if (!validateEmail(email)) {
            alert('Please enter a valid email address.');
            return false;
        }

        return true;
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
});