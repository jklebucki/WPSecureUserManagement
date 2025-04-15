document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.sum-tabs button');
    const tabContents = document.querySelectorAll('.sum-tab-content');
    const modal = document.getElementById('sum-delete-account-modal');
    const btn = document.getElementById('sum-delete-account-button');
    const span = document.getElementsByClassName('sum-close')[0];
    const cancelBtn = document.querySelector('.sum-cancel');
    const logoutBtn = document.getElementById('sum-logout-button');
    const sum_logout_nonce = document.getElementById('sum-logout-nonce').value; // Ensure nonce is retrieved correctly

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
        });
    });

    btn.onclick = function () {
        modal.style.display = 'block';
        modal.setAttribute('tabindex', '-1'); // Ensure modal can gain focus
        modal.focus(); // Focus on the modal when displayed
        modal.classList.remove('hidden'); // Remove any hidden class that might be applied
    };

    span.onclick = function () {
        modal.style.display = 'none';
    };

    cancelBtn.onclick = function () {
        modal.style.display = 'none';
    };

    window.onclick = function (event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };

    const logoutForm = document.createElement('form');
    logoutForm.method = 'POST';
    logoutForm.style.display = 'none';
    const logoutNonceInput = document.createElement('input');
    logoutNonceInput.type = 'hidden';
    logoutNonceInput.name = 'sum_logout_nonce';
    logoutNonceInput.value = document.getElementById('sum-logout-nonce').value;
    logoutForm.appendChild(logoutNonceInput);
    document.body.appendChild(logoutForm);

    const logoutModal = document.createElement('div');
    logoutModal.id = 'sum-logout-modal';
    logoutModal.classList.add('modal');
    logoutModal.innerHTML = `
        <div class="modal-content">
            <span class="sum-close">&times;</span>
            <p>Are you sure you want to log out?</p>
            <button id="sum-confirm-logout">Yes</button>
            <button id="sum-cancel-logout">No</button>
        </div>
    `;
    
    document.body.appendChild(logoutModal);

    const logoutModalClose = logoutModal.querySelector('.sum-close');
    const confirmLogoutBtn = document.getElementById('sum-confirm-logout');
    const cancelLogoutBtn = document.getElementById('sum-cancel-logout');

    logoutBtn.onclick = function () {
        logoutModal.style.display = 'block';
    };

    logoutModalClose.onclick = function () {
        logoutModal.style.display = 'none';
    };

    cancelLogoutBtn.onclick = function () {
        logoutModal.style.display = 'none';
    };

    confirmLogoutBtn.onclick = function () {
        logoutForm.action = '/wp-admin/admin-post.php?action=sum_logout';
        logoutForm.submit();
    };

    window.onclick = function (event) {
        if (event.target == logoutModal) {
            logoutModal.style.display = 'none';
        }
    };

    const formGroups = document.querySelectorAll('.sum-form-group');
    formGroups.forEach(group => {
        group.style.display = 'flex';
        group.style.flexDirection = 'column';
    });

    // Ensure modal is appended to the body to avoid conflicts with other styles
    if (modal && !document.body.contains(modal)) {
        document.body.appendChild(modal);
    }

    const passwordInput = document.getElementById('sum-password');
    const confirmPasswordInput = document.getElementById('sum-confirm-password');
    const changePasswordButton = document.getElementById('sum-change-password-button');
    const passwordMatchMessage = document.getElementById('password-match-message');

    confirmPasswordInput.addEventListener('input', function () {
        if (passwordInput.value === confirmPasswordInput.value) {
            passwordMatchMessage.textContent = 'Passwords match';
            passwordMatchMessage.classList.remove('no-match');
            passwordMatchMessage.classList.add('match');
            changePasswordButton.disabled = false;
        } else {
            passwordMatchMessage.textContent = 'Passwords do not match';
            passwordMatchMessage.classList.remove('match');
            passwordMatchMessage.classList.add('no-match');
            changePasswordButton.disabled = true;
        }
    });
});

jQuery(document).ready(function($) {
    // Obsługa przycisku logout
    $('#sum-logout-button').on('click', function(e) {
        e.preventDefault();
        $('#sum-logout-modal').addClass('show');
    });

    // Zamykanie modala
    $('.sum-close, .sum-cancel, .sum-modal').on('click', function(e) {
        if (e.target === this) {
            $('#sum-logout-modal').removeClass('show');
        }
    });

    // Zatrzymaj propagację kliknięć wewnątrz modala
    $('.sum-modal-content').on('click', function(e) {
        e.stopPropagation();
    });

    // Obsługa klawisza ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#sum-logout-modal').removeClass('show');
        }
    });

    // Obsługa formularza wylogowania
    $('#sum-logout-form').on('submit', function(e) {
        // Form będzie wysłany normalnie do admin-post.php
    });
});
