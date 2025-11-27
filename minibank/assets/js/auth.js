document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const action = this.getAttribute('action');

            fetch(action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // If server requested a delayed redirect (e.g., after registration), show a modal then redirect
                            if (data.delay && data.redirect) {
                                // Prefer a dedicated modal in the page (e.g., on register.php)
                                const pageModal = document.getElementById('registerSuccessModal');
                                if (pageModal) {
                                    const msgEl = document.getElementById('register-success-message');
                                    if (msgEl) msgEl.textContent = data.message || 'Berhasil.';
                                    pageModal.style.display = 'flex';
                                    const closeBtn = document.getElementById('registerSuccessClose');
                                    const okBtn = document.getElementById('register-success-ok');
                                    const doRedirect = () => { window.location.href = data.redirect; };
                                    if (closeBtn) closeBtn.onclick = doRedirect;
                                    if (okBtn) okBtn.onclick = doRedirect;
                                    setTimeout(doRedirect, Number(data.delay) || 2000);
                                } else {
                                    const modal = document.createElement('div');
                                    modal.className = 'modal';
                                    modal.style.display = 'flex';
                                    modal.innerHTML = `\
                                        <div class="modal-content">\
                                            <h2>Sukses</h2>\
                                            <p>${data.message || 'Berhasil.'}</p>\
                                        </div>`;
                                    document.body.appendChild(modal);
                                    setTimeout(() => { window.location.href = data.redirect; }, Number(data.delay) || 2000);
                                }
                            } else if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                    } else {
                        let errorDiv = document.querySelector('.alert-danger');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger';
                            this.prepend(errorDiv);
                        }
                        errorDiv.textContent = data.message;
                    }
                } catch (e) {
                    console.error('Non-JSON response from', action);
                    console.error(text);
                    let errorDiv = document.querySelector('.alert-danger');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger';
                        this.prepend(errorDiv);
                    }
                    errorDiv.textContent = 'Server error. Check console for details.';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                let errorDiv = document.querySelector('.alert-danger');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    this.prepend(errorDiv);
                }
                errorDiv.textContent = 'Network error. Check console for details.';
            });
        });
    }
});
