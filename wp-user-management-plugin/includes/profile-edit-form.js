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

    logoutBtn.onclick = function() {
        if (confirm('Are you sure you want to log out?')) {
            fetch('/wp-json/wp/v2/users/logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': sum_logout_nonce // Use the nonce in the request
                },
                body: JSON.stringify({})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/';
                } else {
                    alert('Logout failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    };
});
