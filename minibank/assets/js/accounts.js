document.addEventListener('DOMContentLoaded', function() {
    // Provide a global toast helper if not already defined
    if (!window.showToast) {
        window.showToast = function(msg, duration = 3000) {
            const toast = document.getElementById('appToast');
            const toastMsg = document.getElementById('appToastMessage');
            const live = document.getElementById('toastLive');
            if (!toast || !toastMsg) {
                if (live) live.textContent = msg; else alert(msg);
                return;
            }
            toastMsg.textContent = msg;
            if (live) live.textContent = msg;
            toast.classList.add('show');
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.classList.remove('show');
                toast.style.display = 'none';
                if (live) live.textContent = '';
            }, duration);
        };
    }
    // Reusable in-app confirmation modal (returns Promise<boolean>)
    window.showConfirm = function(opts) {
        opts = opts || {};
        const title = opts.title || 'Konfirmasi';
        const message = opts.message || 'Apakah Anda yakin?';
        const confirmText = opts.confirmText || 'Ya';
        const cancelText = opts.cancelText || 'Batal';

        let modal = document.getElementById('confirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'confirmModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">${title}</div>
                        <button class="modal-close" aria-label="Tutup">&times;</button>
                    </div>
                    <div class="modal-body"><p class="confirm-message"></p></div>
                    <div class="modal-footer">
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" id="confirmCancel">${cancelText}</button>
                            <button type="button" class="btn btn-danger" id="confirmOk">${confirmText}</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modal);
        }

        return new Promise((resolve) => {
            const titleNode = modal.querySelector('.modal-title');
            const msgNode = modal.querySelector('.confirm-message');
            const closeBtn = modal.querySelector('.modal-close');
            const okBtn = modal.querySelector('#confirmOk');
            const cancelBtn = modal.querySelector('#confirmCancel');

            titleNode.textContent = title;
            msgNode.textContent = message;
            okBtn.textContent = confirmText;
            cancelBtn.textContent = cancelText;

            function cleanup() {
                modal.style.display = 'none';
                closeBtn.removeEventListener('click', onCancel);
                cancelBtn.removeEventListener('click', onCancel);
                okBtn.removeEventListener('click', onOk);
                modal.removeEventListener('click', onOverlay);
            }

            function onOk(e) { e.preventDefault(); cleanup(); resolve(true); }
            function onCancel(e) { e.preventDefault(); cleanup(); resolve(false); }
            function onOverlay(e) { if (e.target === modal) { onCancel(e); } }

            closeBtn.addEventListener('click', onCancel);
            cancelBtn.addEventListener('click', onCancel);
            okBtn.addEventListener('click', onOk);
            modal.addEventListener('click', onOverlay);

            // show modal
            modal.style.display = 'flex';
            // focus the confirm button for quicker keyboard action
            setTimeout(() => { try { okBtn.focus(); } catch (e) {} }, 80);
        });
    };
    const modal = document.getElementById('updateUserModal');
    const modalClose = modal.querySelector('.modal-close');
    const updateUserForm = document.getElementById('updateUserForm');

    // delete UI removed: delete modal and buttons are no longer present

    const createModal = document.getElementById('createUserModal');
    const createModalClose = createModal.querySelector('.modal-close');
    const createUserBtn = document.getElementById('createUserBtn');
    const createUserForm = document.getElementById('createUserForm');
    const createRoleSelect = document.getElementById('create-role');
    const createCodeField = document.getElementById('create-code-field');
    
    const detailModal = document.getElementById('userDetailModal');
    const detailModalClose = detailModal.querySelector('.modal-close');
    const detailContent = document.getElementById('userDetailContent');

    let userIdToDelete = null;
    
    // Close modal events
    modalClose.onclick = () => modal.style.display = 'none';
    detailModalClose.onclick = () => detailModal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
        // delete modal removed; nothing to handle here
        if (event.target == createModal) {
            createModal.style.display = 'none';
        }
        if (event.target == detailModal) {
            detailModal.style.display = 'none';
        }
    };

    // Open detail modal event (buttons now use data-action="detail")
    document.querySelectorAll('button[data-action="detail"]').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid') || this.getAttribute('data-userid');
            detailContent.innerHTML = '<p>Memuat data...</p>';
            detailModal.style.display = 'flex';

            fetch(`../api/user/get_detail.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                        if (data.success) {
                            const details = data.details;
                            const user = details.user;

                            // build transactions grid
                            let transactionsHtml = '<h4>5 Transaksi Terakhir:</h4>';
                            if (details.transactions.length > 0) {
                                transactionsHtml += '<div class="detail-transactions-grid">';
                                details.transactions.forEach(t => {
                                    const amt = parseFloat(t.amount).toLocaleString('id-ID');
                                    transactionsHtml += `
                                        <div class="dt-item">
                                            <div class="dt-date">${t.createdat}</div>
                                            <div class="dt-desc">${t.type} - Rp ${amt}</div>
                                        </div>`;
                                });
                                transactionsHtml += '</div>';
                            } else {
                                transactionsHtml += '<p>Tidak ada transaksi ditemukan.</p>';
                            }

                            // Present admin vs user fields correctly
                            let rekeningHtml = '';
                            if (user.role === 'admin') {
                                rekeningHtml = `<p><strong>Admin UID:</strong> ${user.admin_uid ?? 'N/A'}</p>`;
                            } else {
                                const rekeningDisplay = user.rekening ? user.rekening : 'N/A';
                                rekeningHtml = `<p><strong>No. Rekening:</strong> ${rekeningDisplay}</p>`;
                            }

                            // delete button removed from kelola_akun detail modal per request
                            let deleteBtnHtml = '';

                            detailContent.innerHTML = `
                                <p><strong>Username:</strong> ${user.username}</p>
                                ${rekeningHtml}
                                <p><strong>Role:</strong> ${user.role}</p>
                                <p><strong>Terdaftar:</strong> ${new Date(user.created_at).toLocaleString('id-ID')}</p>
                                <hr>
                                <h3>Saldo: Rp ${details.balance.toLocaleString('id-ID')}</h3>
                                <hr>
                                ${transactionsHtml}
                                ${deleteBtnHtml}
                            `;

                            // delete handler removed — backend endpoint remains but UI no longer exposes deletion here
                        } else {
                            detailContent.innerHTML = `<p class="alert alert-danger">${data.message || 'Gagal memuat detail.'}</p>`;
                        }
                    });
        });
    });

    // Open create modal event
    createUserBtn.onclick = () => {
        createUserForm.reset();
        createCodeField.style.display = 'none';
        createModal.style.display = 'flex';
    };

    // Open update user modal (buttons now use data-action="update")
    document.querySelectorAll('button[data-action="update"]').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid') || this.getAttribute('data-userid');
            
            // Fetch user data and populate form
            fetch(`../api/user/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        updateUserForm.innerHTML = `
                            <input type="hidden" name="id" value="${user.id}">
                            <input type="hidden" name="csrf" value="${data.csrf_token}">
                            <div class="form-group">
                                <label for="update-username">Username</label>
                                <input type="text" id="update-username" name="username" class="form-control" value="${user.username}" required>
                            </div>
                            <div class="form-group">
                                <label for="update-password">Password (Kosongkan jika tidak diubah)</label>
                                <input type="password" id="update-password" name="password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="update-role">Role</label>
                                <select id="update-role" name="role" class="form-control" required>
                                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                                    <option value="guru" ${user.role === 'guru' ? 'selected' : ''}>Guru</option>
                                    <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                                </select>
                            </div>
                            <div class="modal-actions">
                                <button type="button" id="deleteUserBtn" class="btn btn-danger" data-userid="${user.id}">Hapus Akun</button>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        `;
                        modal.style.display = 'flex';
                        // attach delete handler for update modal (kelola_akun)
                        (function(){
                            const del = document.getElementById('deleteUserBtn');
                            if (!del) return;
                            try {
                                const currentId = window.CURRENT_USER_ID || null;
                                if (String(currentId) === String(user.id)) { del.style.display = 'none'; return; }
                            } catch(e){}
                            del.addEventListener('click', async function(){
                                if (!(await window.showConfirm({
                                    title: 'Hapus Akun',
                                    message: 'Yakin ingin menghapus akun ini? Tindakan ini tidak dapat dibatalkan.',
                                    confirmText: 'Hapus',
                                    cancelText: 'Batal'
                                }))) return;
                                const btn = this;
                                const targetId = btn.getAttribute('data-userid');
                                btn.disabled = true; btn.classList.add('loading');
                                await new Promise(r => requestAnimationFrame(r));
                                const fd = new FormData(); fd.append('id', targetId); fd.append('csrf', window.CSRF_TOKEN || '');
                                let deleteUrl = '../api/user/hapus_user.php';
                                try { if (typeof BASE_URL !== 'undefined' && BASE_URL) { const base = BASE_URL.endsWith('/') ? BASE_URL : (BASE_URL + '/'); deleteUrl = base + 'api/user/hapus_user.php'; } } catch(e){}
                                const controller = new AbortController(); const timeoutId = setTimeout(()=>controller.abort(), 12000);
                                try {
                                    const resp = await fetch(deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin', signal: controller.signal });
                                    const text = await resp.text(); let res = null; try { res = JSON.parse(text); } catch(e) { res = { success: false, message: text || 'Invalid response' }; }
                                    if (res && res.success) {
                                        modal.style.display = 'none'; window.showToast(res.message || 'Akun dihapus.');
                                        try { submitGlobalFilterForm(); } catch(e){ location.reload(); }
                                    } else { window.showToast(res && res.message ? res.message : 'Gagal menghapus akun.'); }
                                } catch(err) { console.error('Delete failed', err); window.showToast('Gagal menghubungi server.'); }
                                finally { clearTimeout(timeoutId); try{ btn.disabled = false; btn.classList.remove('loading'); } catch(e){} }
                            });
                        })();
                    } else {
                        window.showToast('Gagal memuat data user.');
                    }
                });
        });
    });

    // Delete action removed from UI

    // Handle update user form submission
    updateUserForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('../api/user/update_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modal.style.display = 'none';
                location.reload(); 
            } else {
                window.showToast(data.message || 'Terjadi kesalahan saat mengupdate.');
            }
        });
    });

    // Handle form submission for create
    createUserForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('../api/user/create_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createModal.style.display = 'none';
                location.reload();
            } else {
                window.showToast(data.message || 'Gagal membuat pengguna baru.');
            }
        });
    });

    // Delete confirmation handler removed

    // Close modal events
    createModalClose.onclick = () => createModal.style.display = 'none';

    // Show/hide code field on role change
    createRoleSelect.addEventListener('change', function() {
        if (this.value === 'admin' || this.value === 'guru') {
            createCodeField.style.display = 'block';
        } else {
            createCodeField.style.display = 'none';
        }
    });

    // --- Admin View Switcher Logic ---
    // --- Compact filter controls for small screens (JS fallback for :has()) ---
    function applyCompactFilters() {
        const breakpoint = 768;
        const filterForms = document.querySelectorAll('.filter-form');
        filterForms.forEach(form => {
            // mark compact mode on form for CSS purposes
            if (window.innerWidth <= breakpoint) form.classList.add('compact-mode'); else form.classList.remove('compact-mode');
            const groups = form.querySelectorAll('.form-group');
            groups.forEach(g => {
                // detect search input (keep it large)
                if (g.querySelector('input[name="search"]') || g.classList.contains('search-group')) {
                    g.classList.remove('compact', 'date', 'role', 'default');
                    return;
                }

                if (window.innerWidth <= breakpoint) {
                    // make compact
                    g.classList.add('compact');
                    // add semantic helper classes for icons
                    if (g.querySelector('input[type="date"]')) {
                        g.classList.add('date'); g.classList.remove('role', 'default');
                    } else if (g.querySelector('select[name="filter_role"]')) {
                        g.classList.add('role'); g.classList.remove('date', 'default');
                    } else {
                        g.classList.add('default'); g.classList.remove('role', 'date');
                    }

                    // ensure the control is focusable: move focus to inner control when clicked on group
                    if (!g._compactBound) {
                        g.addEventListener('click', function(e) {
                            const inner = g.querySelector('.form-control, select, input');
                            if (inner) inner.focus();
                        });
                        // keyboard accessibility: Enter or Space opens/focuses inner control
                        g.setAttribute('tabindex', '0');
                        g.addEventListener('keydown', function(ev) {
                            if (ev.key === 'Enter' || ev.key === ' ') {
                                ev.preventDefault();
                                const inner = g.querySelector('.form-control, select, input');
                                if (inner) inner.focus();
                            }
                        });
                        g._compactBound = true;
                    }

                    // add ARIA labels for screen readers based on contained control
                    const inner = g.querySelector('.form-control, select, input');
                    if (inner) {
                        if (inner.matches('input[type="date"]')) inner.setAttribute('aria-label', 'Filter Hari');
                        else if (inner.matches('select[name="filter_role"]')) inner.setAttribute('aria-label', 'Filter Akun');
                        else inner.setAttribute('aria-label', 'Filter');
                    }
                } else {
                    g.classList.remove('compact', 'date', 'role', 'default');
                    // remove compact-specific tabindex/aria when returning to normal
                    if (g._compactBound) {
                        g.removeAttribute('tabindex');
                        g._compactBound = false;
                    }
                }
            });
        });
    }

    // Apply on load and on resize (debounced)
    applyCompactFilters();
    let resizeTimer = null;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyCompactFilters, 120);
    });
    const adminViewSwitch = document.getElementById('adminViewSwitch');
    const superadminModal = document.getElementById('superadminModal');
    const superadminModalClose = superadminModal.querySelector('.modal-close');
    const superadminForm = document.getElementById('superadminForm');
    const superadminCodeInput = document.getElementById('superadmin-code');
    const superadminError = document.getElementById('superadmin-error');

    // Helper to clear any displayed superadmin error
    function clearSuperadminError() {
        if (superadminError) {
            superadminError.textContent = '';
            superadminError.style.display = 'none';
        }
    }

    if (adminViewSwitch) {
        // Set switch state based on URL parameter on page load
        const urlParams = new URLSearchParams(window.location.search);
        const currentView = urlParams.get('view_mode');
        if (currentView === 'admins') {
            adminViewSwitch.checked = true;
        }

        adminViewSwitch.addEventListener('change', function() {
            const params = new URLSearchParams(window.location.search);
            const vmInput = document.getElementById('viewModeHidden');
            if (this.checked) {
                params.set('view_mode', 'admins');
                if (vmInput) vmInput.value = 'admins';
            } else {
                params.set('view_mode', 'users');
                if (vmInput) vmInput.value = 'users';
            }
            const newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
            // Toggle visible headers/title immediately to match expected view
            try {
                const rekeningHeader = document.getElementById('rekeningHeader');
                const adminUidHeader = document.getElementById('adminUidHeader');
                const tableTitle = document.getElementById('tableTitle');
                if (this.checked) {
                    if (rekeningHeader) rekeningHeader.style.display = 'none';
                    if (adminUidHeader) adminUidHeader.style.display = 'table-cell';
                    if (tableTitle) tableTitle.textContent = 'Kelola Admin';
                } else {
                    if (rekeningHeader) rekeningHeader.style.display = 'table-cell';
                    if (adminUidHeader) adminUidHeader.style.display = 'none';
                    if (tableTitle) tableTitle.textContent = 'Kelola User & Guru';
                }
            } catch (e) {}
            try { history.pushState({}, '', newUrl); } catch (e) {}
            // Refresh only the accounts table using the same loader
            try { submitGlobalFilterForm(); } catch (e) { window.location.href = newUrl; }
        });
    }

    if (superadminModalClose) {
        superadminModalClose.onclick = () => {
            superadminModal.style.display = 'none';
            adminViewSwitch.checked = false; // Revert switch if modal is closed
            clearSuperadminError();
        };
    }

    // Close superadmin modal when clicking outside it
    window.addEventListener('click', function(event) {
        if (event.target == superadminModal) {
            superadminModal.style.display = 'none';
            if (adminViewSwitch) adminViewSwitch.checked = false;
            clearSuperadminError();
        }
    });

    if (superadminForm) {
        superadminForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Send code to server-side verification endpoint
            const fd = new FormData();
            fd.append('action', 'verify_global_access');
            fd.append('csrf', window.CSRF_TOKEN || '');
            fd.append('access_code', superadminCodeInput.value);

            fetch('../api/admin/admin_actions.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(r => r.json()).then(result => {
                if (result.success) {
                    // Redirect to admin view (server now has session flag)
                    window.location.href = 'kelola_akun.php?view_mode=admins';
                } else {
                        // Map common server messages to friendlier text for this UI
                        let msg = result.message || 'Kode akses salah.';
                        if (/CSRF/i.test(msg) || /token/i.test(msg) || msg.toLowerCase().includes('csrf')) {
                            msg = 'Admin token tidak valid.';
                        }
                        superadminError.textContent = msg;
                        superadminError.style.display = 'block';
                }
            }).catch(() => {
                    superadminError.textContent = 'Gagal memverifikasi kode. Coba lagi.';
                    superadminError.style.display = 'block';
            });
        });
    }

    // Error reports modal wiring
    const viewErrorBtn = document.getElementById('viewErrorReportsBtn');
    const errorReportsModal = document.getElementById('errorReportsModal');
    const errorReportsClose = errorReportsModal ? errorReportsModal.querySelector('.modal-close') : null;
    const errorReportsContent = document.getElementById('errorReportsContent');
    const errorReportsCloseBtn = document.getElementById('closeErrorReports');

    if (viewErrorBtn) {
        viewErrorBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!errorReportsModal) return;
            errorReportsContent.innerHTML = '<p>Memuat laporan...</p>';
            errorReportsModal.style.display = 'flex';
            fetch('../api/error/report.php', { method: 'GET', credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res.success && Array.isArray(res.reports)) {
                        if (res.reports.length === 0) {
                            errorReportsContent.innerHTML = '<p>Tidak ada laporan error.</p>';
                            return;
                        }
                        let html = '<ol style="padding-left:1rem;">';
                        res.reports.reverse().forEach(rep => {
                            html += `<li style="margin-bottom:0.6rem;border-bottom:1px dashed #e9ecef;padding-bottom:0.4rem;">`;
                            html += `<div style="font-weight:700;">${rep.ts} — ${rep.admin} (${rep.ip})</div>`;
                            html += `<div style="color:#333;margin-top:4px;">${rep.message}</div>`;
                            if (rep.details) html += `<pre style="background:#f8f9fa;padding:6px;border-radius:6px;margin-top:6px;">${rep.details}</pre>`;
                            html += `</li>`;
                        });
                        html += '</ol>';
                        errorReportsContent.innerHTML = html;
                    } else {
                        errorReportsContent.innerHTML = '<p>Gagal memuat laporan.</p>';
                    }
                }).catch(() => {
                    errorReportsContent.innerHTML = '<p>Gagal memuat laporan.</p>';
                });
        });
    }

    if (errorReportsClose) errorReportsClose.onclick = () => { errorReportsModal.style.display = 'none'; };
    if (errorReportsCloseBtn) errorReportsCloseBtn.onclick = () => { if (errorReportsModal) errorReportsModal.style.display = 'none'; };

    // --- Mobile filter modal (modal-only workflow) ---
    (function mobileFilterModal() {
        const filterModal = document.getElementById('filterModal');
        if (!filterModal) return;

        let lastFocusBeforeFilterModal = null;
        const filterOpenModalBtn = document.getElementById('filter-open-modal');
        const filterModalClose = document.getElementById('filterModalClose');
        const filterModalCancel = document.getElementById('filterModalCancel');
        const filterModalApply = document.getElementById('filterModalApply');

        const modalRole = document.getElementById('modal-filter-role');
        const modalDate = document.getElementById('modal-filter-date');
        const modalSort = document.getElementById('modal-sort-amount');

        const mainForm = document.getElementById('globalFilterForm');
        const mainSearch = document.getElementById('filter-search');
        const mainRole = document.getElementById('filter-role');
        const mainDate = document.getElementById('filter-date');
        const mainSort = document.getElementById('sort-amount');

        function openModal() {
            if (!filterModal) return;
            if (filterModal.getAttribute('data-opened') === '1') return;
            if (modalRole && mainRole) modalRole.value = mainRole.value;
            if (modalDate && mainDate) modalDate.value = mainDate.value;
            if (modalSort && mainSort) modalSort.value = mainSort.value;
            lastFocusBeforeFilterModal = document.activeElement;
            filterModal.style.display = 'flex';
            filterModal.setAttribute('data-opened', '1');
            setTimeout(() => { const first = filterModal.querySelector('.form-control'); if (first) first.focus(); }, 120);
            try { trapFocus(filterModal); } catch (e) {}
        }

        function closeModal() {
            if (!filterModal) return;
            if (filterModal.getAttribute('data-opened') !== '1') return;
            filterModal.style.display = 'none';
            try { if (lastFocusBeforeFilterModal && typeof lastFocusBeforeFilterModal.focus === 'function') lastFocusBeforeFilterModal.focus(); } catch (e) {}
            filterModal.removeAttribute('data-opened');
            lastFocusBeforeFilterModal = null;
            try { releaseFocus(); } catch (e) {}
        }

        if (filterOpenModalBtn) filterOpenModalBtn.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
        if (filterModalClose) filterModalClose.addEventListener('click', closeModal);
        if (filterModalCancel) filterModalCancel.addEventListener('click', closeModal);

        if (filterModalApply) {
            filterModalApply.addEventListener('click', function(e){
                e.preventDefault();
                if (mainRole && modalRole) mainRole.value = modalRole.value;
                if (mainDate && modalDate) mainDate.value = modalDate.value;
                if (mainSort && modalSort) mainSort.value = modalSort.value;
                const filterSearchMobile = document.getElementById('filter-search-mobile');
                if (mainSearch && filterSearchMobile) mainSearch.value = filterSearchMobile.value;
                closeModal();
                if (mainForm) submitGlobalFilterForm();
            });
        }

        window.addEventListener('click', function(event) {
            if (event.target == filterModal) closeModal();
        });
    })();

    // Accounts incremental loader (AJAX partial refresh)
    (function accountsLoader(){
        // Helper to programmatically submit the global filter form so our listener handles it
        window.submitGlobalFilterForm = function() {
            const f = document.getElementById('globalFilterForm');
            if (!f) return;
            try {
                f.dispatchEvent(new Event('submit', {cancelable: true}));
            } catch (e) {
                try { f.submit(); } catch (err) {}
            }
        };
        const tbody = document.querySelector('.table-responsive-wrapper table tbody');
        if (!tbody) return;
        const perPage = 10;
        let page = 1;
        let total = 0;
        let loading = false;

        let loadWrap = document.getElementById('accountsLoadMoreWrap');
        if (!loadWrap) {
            loadWrap = document.createElement('div');
            loadWrap.id = 'accountsLoadMoreWrap';
            loadWrap.style.textAlign = 'center';
            loadWrap.style.margin = '12px 0';
            const btn = document.createElement('button');
            btn.id = 'accountsLoadMoreBtn';
            btn.className = 'btn btn-link';
            btn.textContent = 'Next';
            loadWrap.appendChild(btn);
            const tableWrapper = document.querySelector('.table-responsive-wrapper');
            if (tableWrapper && tableWrapper.parentNode) tableWrapper.parentNode.insertBefore(loadWrap, tableWrapper.nextSibling);
        }
        const loadBtn = document.getElementById('accountsLoadMoreBtn');

        function readFilters() {
            const params = new URLSearchParams(window.location.search);
            const g = document.getElementById('global-search') || document.getElementById('filter-search');
            const d = document.getElementById('global-filter-date') || document.getElementById('filter-date');
            const r = document.getElementById('global-filter-role') || document.getElementById('filter-role');
            const vm = document.getElementById('viewModeHidden') || document.getElementById('viewModeHidden');
            if (g) { if (g.value) params.set('search', g.value); else params.delete('search'); }
            if (d) { if (d.value) params.set('filter_date', d.value); else params.delete('filter_date'); }
            if (r) { if (r.value) params.set('filter_role', r.value); else params.delete('filter_role'); }
            if (vm && vm.value) params.set('view_mode', vm.value);
            return params;
        }

        function updateLoadVisibility() {
            const maxPage = Math.ceil(total / perPage) || 1;
            if (page < maxPage) loadWrap.style.display = 'block'; else loadWrap.style.display = 'none';
        }

        // skeleton helper for accounts table
        function makeSkeletonHtml(count) {
            const ths = document.querySelectorAll('.table-responsive-wrapper table thead th');
            const colCount = ths.length || 8;
            let html = '';
            for (let i=0;i<count;i++) {
                html += '<tr class="table-skeleton-row">';
                for (let j=0;j<colCount;j++) {
                    html += '<td><div class="skeleton-line" style="width:70%"></div></td>';
                }
                html += '</tr>';
            }
            return html;
        }

        function setBtnClickedVisual() {
            if (!loadBtn) return;
            loadBtn.style.color = '#0b5cff';
            setTimeout(()=>{ loadBtn.style.color = ''; }, 350);
        }

        // Re-attach dynamic handlers (detail/update/delete) after content update
        function attachDynamicHandlers() {
            // detail
            document.querySelectorAll('button[data-action="detail"]').forEach(button => {
                button.removeEventListener('click', detailClickHandler);
                button.addEventListener('click', detailClickHandler);
            });
            // update
            document.querySelectorAll('button[data-action="update"]').forEach(button => {
                button.removeEventListener('click', updateClickHandler);
                button.addEventListener('click', updateClickHandler);
            });
                // delete action removed from UI
        }

        // lightweight handlers reused from top-of-file implementations
        function detailClickHandler() {
            const userId = this.getAttribute('data-userid');
            const detailModal = document.getElementById('userDetailModal');
            const detailContent = document.getElementById('userDetailContent');
            detailContent.innerHTML = '<p>Memuat data...</p>';
            detailModal.style.display = 'flex';
            fetch(`../api/user/get_detail.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const details = data.details;
                        const user = details.user;
                        let transactionsHtml = '<h4>5 Transaksi Terakhir:</h4>';
                        if (details.transactions.length > 0) {
                            transactionsHtml += '<div class="detail-transactions-grid">';
                            details.transactions.forEach(t => {
                                const amt = parseFloat(t.amount).toLocaleString('id-ID');
                                transactionsHtml += `
                                    <div class="dt-item">
                                        <div class="dt-date">${t.createdat}</div>
                                        <div class="dt-desc">${t.type} - Rp ${amt}</div>
                                    </div>`;
                            });
                            transactionsHtml += '</div>';
                        } else {
                            transactionsHtml += '<p>Tidak ada transaksi ditemukan.</p>';
                        }
                        let rekeningHtml = '';
                        if (user.role === 'admin') {
                            rekeningHtml = `<p><strong>Admin UID:</strong> ${user.admin_uid ?? 'N/A'}</p>`;
                        } else {
                            const rekeningDisplay = user.rekening ? user.rekening : 'N/A';
                            rekeningHtml = `<p><strong>No. Rekening:</strong> ${rekeningDisplay}</p>`;
                        }
                        // delete button removed from kelola_akun detail modal per request
                        let deleteBtnHtml = '';
                        detailContent.innerHTML = `
                            <p><strong>Username:</strong> ${user.username}</p>
                            ${rekeningHtml}
                            <p><strong>Role:</strong> ${user.role}</p>
                            <p><strong>Terdaftar:</strong> ${new Date(user.created_at).toLocaleString('id-ID')}</p>
                            <hr>
                            <h3>Saldo: Rp ${details.balance.toLocaleString('id-ID')}</h3>
                            <hr>
                            ${transactionsHtml}
                            ${deleteBtnHtml}
                        `;
                        // delete handler removed for kelola_akun; backend endpoint still available
                    } else {
                        detailContent.innerHTML = `<p class="alert alert-danger">${data.message || 'Gagal memuat detail.'}</p>`;
                    }
                });
        }

        function updateClickHandler() {
            const userId = this.getAttribute('data-userid');
            const modal = document.getElementById('updateUserModal');
            const updateUserForm = document.getElementById('updateUserForm');
            fetch(`../api/user/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        updateUserForm.innerHTML = `
                            <input type="hidden" name="id" value="${user.id}">
                            <input type="hidden" name="csrf" value="${data.csrf_token}">
                            <div class="form-group">
                                <label for="update-username">Username</label>
                                <input type="text" id="update-username" name="username" class="form-control" value="${user.username}" required>
                            </div>
                            <div class="form-group">
                                <label for="update-password">Password (Kosongkan jika tidak diubah)</label>
                                <input type="password" id="update-password" name="password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="update-role">Role</label>
                                <select id="update-role" name="role" class="form-control" required>
                                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                                    <option value="guru" ${user.role === 'guru' ? 'selected' : ''}>Guru</option>
                                    <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                                </select>
                            </div>
                            <div class="modal-actions">
                                <button type="button" id="deleteUserBtn" class="btn btn-danger" data-userid="${user.id}">Hapus Akun</button>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        `;
                        modal.style.display = 'flex';
                        // attach delete handler for update modal (kelola_akun)
                        (function(){
                            const del = document.getElementById('deleteUserBtn');
                            if (!del) return;
                            try {
                                const currentId = window.CURRENT_USER_ID || null;
                                if (String(currentId) === String(user.id)) { del.style.display = 'none'; return; }
                            } catch(e){}
                            del.addEventListener('click', async function(){
                                    if (!(await window.showConfirm({
                                        title: 'Hapus Akun',
                                        message: 'Yakin ingin menghapus akun ini? Tindakan ini tidak dapat dibatalkan.',
                                        confirmText: 'Hapus',
                                        cancelText: 'Batal'
                                    }))) return;
                                const btn = this;
                                const targetId = btn.getAttribute('data-userid');
                                btn.disabled = true; btn.classList.add('loading');
                                await new Promise(r => requestAnimationFrame(r));
                                const fd = new FormData(); fd.append('id', targetId); fd.append('csrf', window.CSRF_TOKEN || '');
                                let deleteUrl = '../api/user/hapus_user.php';
                                try { if (typeof BASE_URL !== 'undefined' && BASE_URL) { const base = BASE_URL.endsWith('/') ? BASE_URL : (BASE_URL + '/'); deleteUrl = base + 'api/user/hapus_user.php'; } } catch(e){}
                                const controller = new AbortController(); const timeoutId = setTimeout(()=>controller.abort(), 12000);
                                try {
                                    const resp = await fetch(deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin', signal: controller.signal });
                                    const text = await resp.text(); let res = null; try { res = JSON.parse(text); } catch(e) { res = { success: false, message: text || 'Invalid response' }; }
                                    if (res && res.success) {
                                        modal.style.display = 'none'; window.showToast(res.message || 'Akun dihapus.');
                                        try { submitGlobalFilterForm(); } catch(e){ location.reload(); }
                                    } else { window.showToast(res && res.message ? res.message : 'Gagal menghapus akun.'); }
                                } catch(err) { console.error('Delete failed', err); window.showToast('Gagal menghubungi server.'); }
                                finally { clearTimeout(timeoutId); try{ btn.disabled = false; btn.classList.remove('loading'); } catch(e){} }
                            });
                        })();
                    } else {
                        window.showToast('Gagal memuat data user.');
                    }
                });
        }

        // delete action removed from UI

        function fetchPage(p, append=false) {
            if (loading) return;
            loading = true;
            if (loadBtn) loadBtn.disabled = true;
            // show skeletons
            try {
                if (!append) tbody.innerHTML = makeSkeletonHtml(perPage);
                else tbody.insertAdjacentHTML('beforeend', makeSkeletonHtml(3));
            } catch (err) {}
            const params = readFilters();
            params.set('page', String(p));
            params.set('per_page', String(perPage));
            let url = '../api/user/fetch_users.php?' + params.toString();
            try {
                if (typeof BASE_URL !== 'undefined' && BASE_URL) {
                    const base = BASE_URL.endsWith('/') ? BASE_URL : (BASE_URL + '/');
                    url = base + 'api/user/fetch_users.php?' + params.toString();
                }
            } catch(e) {}
            fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                try { Array.from(tbody.querySelectorAll('.table-skeleton-row')).forEach(n=>n.remove()); } catch(e){}
                if (!append) tbody.innerHTML = data.html; else tbody.insertAdjacentHTML('beforeend', data.html);
                total = Number(data.total || 0);
                page = Number(data.page || p);
                attachDynamicHandlers();
                updateLoadVisibility();
            })
            .catch(err => { console.error('Failed to load users', err); })
            .finally(()=>{ loading = false; if (loadBtn) loadBtn.disabled = false; });
        }

        const globalForm = document.getElementById('globalFilterForm');
        if (globalForm) {
            globalForm.addEventListener('submit', function(e){
                e.preventDefault();
                const params = readFilters();
                const newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
                try { history.pushState({}, '', newUrl); } catch (e) {}
                page = 1;
                fetchPage(page, false);
            });
        }

        if (loadBtn) {
            loadBtn.addEventListener('click', function(e){
                e.preventDefault();
                setBtnClickedVisual();
                const nextPage = page + 1;
                fetchPage(nextPage, true);
            });
        }

        // initial fetch to replace server-rendered rows with canonical paginated content
        fetchPage(1, false);

        window.addEventListener('popstate', function(){ page = 1; fetchPage(1,false); });

    })();

    // Mobile search handler (submit global filter form when present)
    (function mobileSearch() {
        const filterSearchMobile = document.getElementById('filter-search-mobile');
        const filterSearchBtnMobile = document.getElementById('filter-search-btn-mobile');
        function doAccountsMobileSearch() {
            const q = (filterSearchMobile && filterSearchMobile.value) ? filterSearchMobile.value.trim() : '';
            const mainForm = document.getElementById('globalFilterForm');
            if (mainForm) {
                const g = document.getElementById('global-search');
                const f = document.getElementById('filter-search');
                if (g) g.value = q;
                if (f) f.value = q;
                try { submitGlobalFilterForm(); return; } catch (e) {}
            }
            const url = new URL(window.location.href);
            const params = new URLSearchParams();
            const preserve = ['view','view_as','view_mode'];
            preserve.forEach(k => { if (url.searchParams.has(k)) params.set(k, url.searchParams.get(k)); });
            if (q) params.set('search', q); else params.delete('search');
            window.location.href = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
        }
        if (filterSearchBtnMobile) filterSearchBtnMobile.addEventListener('click', function(e){ e.preventDefault(); doAccountsMobileSearch(); });
        if (filterSearchMobile) filterSearchMobile.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); doAccountsMobileSearch(); } });
    })();
});
