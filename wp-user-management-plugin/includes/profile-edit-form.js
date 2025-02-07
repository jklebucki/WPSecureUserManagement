document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.sum-tabs button');
    const tabContents = document.querySelectorAll('.sum-tab-content');
    const modal = document.getElementById('sum-delete-account-modal');
    const btn = document.getElementById('sum-delete-account-button');
    const span = document.getElementsByClassName('sum-close')[0];
    const cancelBtn = document.querySelector('.sum-cancel');
    const logoutBtn = document.getElementById('sum-logout-button');

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
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        const nonce = document.createElement('input');
        nonce.type = 'hidden';
        nonce.name = 'sum_logout_nonce';
        nonce.value = sum_logout_nonce;
        form.appendChild(nonce);
        document.body.appendChild(form);
        form.submit();
    }
});
