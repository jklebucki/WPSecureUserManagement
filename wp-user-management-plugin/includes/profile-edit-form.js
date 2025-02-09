document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.sum-tabs button');
    const tabContents = document.querySelectorAll('.sum-tab-content');
    const modal = document.getElementById('sum-delete-account-modal');
    const btn = document.getElementById('sum-delete-account-button');
    const span = document.getElementsByClassName('sum-close')[0];
    const cancelBtn = document.querySelector('.sum-cancel');
    const logoutBtn = document.getElementById('sum-logout-button');
    const sum_logout_nonce = document.getElementById('sum-logout-nonce').value; // Ensure nonce is retrieved correctly

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
        });
    });

    btn.onclick = function() {
        modal.style.display = 'block';
        modal.setAttribute('tabindex', '-1'); // Ensure modal can gain focus
        modal.focus(); // Focus on the modal when displayed
        modal.classList.remove('hidden'); // Remove any hidden class that might be applied
    }

    span.onclick = function() {
        modal.style.display = 'none';
    }

    cancelBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    const logoutForm = document.createElement('form');
    logoutForm.method = 'POST';
    logoutForm.style.display = 'none';
    const logoutNonceInput = document.createElement('input');
    logoutNonceInput.type = 'hidden';
    logoutNonceInput.name = 'sum_logout_nonce';
    logoutNonceInput.value = document.getElementById('sum-logout-nonce').value;
    logoutForm.appendChild(logoutNonceInput);
    document.body.appendChild(logoutForm);

    logoutBtn.onclick = function() {
        if (confirm('Are you sure you want to log out?')) {
            logoutForm.submit();
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
});
