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
    const successModal = document.getElementById('successModal');
    const successMessageElement = document.getElementById('success-message');
    const successCloseBtns = document.querySelectorAll('.success-close');
    
    const deleteHistoryModal = document.getElementById('deleteHistoryModal');
    const deleteHistoryModalClose = deleteHistoryModal ? deleteHistoryModal.querySelector('.modal-close') : null;
    const cancelDeleteHistoryBtn = document.getElementById('cancelDeleteHistoryBtn');
    const confirmDeleteHistoryBtn = document.getElementById('confirmDeleteHistoryBtn');
    const deleteTransactionIdSpan = document.getElementById('delete-transactionid');

    let transactionIdToDelete = null;
    
    // Modal focus helpers (shared)
    let _previouslyFocused = null;
    function trapFocus(modal) {
        if (!modal) return;
        _previouslyFocused = document.activeElement;
        const focusableSelectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const nodes = Array.from(modal.querySelectorAll(focusableSelectors)).filter(n => n.offsetParent !== null);
        if (nodes.length === 0) return;
        const first = nodes[0];
        const last = nodes[nodes.length - 1];

        function handleTab(e) {
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }

        modal.__focusHandler = handleTab;
        modal.setAttribute('data-focus-trap-attached', '1');
        document.addEventListener('keydown', handleTab);
    }

    function releaseFocus() {
        if (_previouslyFocused) {
            try { _previouslyFocused.focus(); } catch (e) {}
        }
        const attached = document.querySelector('[data-focus-trap-attached]');
        if (attached && attached.__focusHandler) {
            document.removeEventListener('keydown', attached.__focusHandler);
            delete attached.__focusHandler;
            attached.removeAttribute('data-focus-trap-attached');
        }
    }

    successCloseBtns.forEach(btn => {
        btn.onclick = () => successModal.style.display = 'none';
    });

    // Transactions incremental loader (AJAX partial refresh)
    (function transactionsLoader(){
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

        // create load more container if not present
        let loadWrap = document.getElementById('transactionsLoadMoreWrap');
        if (!loadWrap) {
            loadWrap = document.createElement('div');
            loadWrap.id = 'transactionsLoadMoreWrap';
            loadWrap.style.textAlign = 'center';
            loadWrap.style.margin = '12px 0';
            const btn = document.createElement('button');
            btn.id = 'transactionsLoadMoreBtn';
            btn.className = 'btn btn-link';
            btn.textContent = 'Next';
            loadWrap.appendChild(btn);
            const tableWrapper = document.querySelector('.table-responsive-wrapper');
            if (tableWrapper && tableWrapper.parentNode) tableWrapper.parentNode.insertBefore(loadWrap, tableWrapper.nextSibling);
        }
        const loadBtn = document.getElementById('transactionsLoadMoreBtn');

        function readFilters() {
            const params = new URLSearchParams(window.location.search);
            const form = document.getElementById('globalFilterForm');
            if (form) {
                const g = document.getElementById('global-search');
                const d = document.getElementById('global-filter-date');
                const r = document.getElementById('global-filter-role');
                const s = document.getElementById('global-sort-amount');
                if (g) params.set('search', g.value || '');
                if (d) { if (d.value) params.set('filter_date', d.value); else params.delete('filter_date'); }
                if (r) { if (r.value) params.set('filter_role', r.value); else params.delete('filter_role'); }
                if (s) { if (s.value) params.set('sort_amount', s.value); else params.delete('sort_amount'); }
                Array.from(form.querySelectorAll('input[type=hidden]')).forEach(inp=>{
                    if (inp.name && ['search','filter_date','filter_role','sort_amount'].indexOf(inp.name)===-1) {
                        if (inp.value) params.set(inp.name, inp.value); else params.delete(inp.name);
                    }
                });
            }
            return params;
        }

        function updateLoadVisibility() {
            const maxPage = Math.ceil(total / perPage) || 1;
            if (page < maxPage) {
                loadWrap.style.display = 'block';
            } else {
                loadWrap.style.display = 'none';
            }
        }

        // skeleton helper
        function makeSkeletonHtml(count) {
            const ths = document.querySelectorAll('.table-responsive-wrapper table thead th');
            const colCount = ths.length || 7;
            let html = '';
            for (let i=0;i<count;i++) {
                html += '<tr class="table-skeleton-row">';
                for (let j=0;j<colCount;j++) {
                    html += '<td><div class="skeleton-line" style="width:80%"></div></td>';
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

        function deleteHistoryClickHandler() {
            transactionIdToDelete = this.getAttribute('data-transactionid');
            const deleteTransactionIdSpan = document.getElementById('delete-transactionid');
            const deleteHistoryModal = document.getElementById('deleteHistoryModal');
            if (deleteTransactionIdSpan) deleteTransactionIdSpan.textContent = transactionIdToDelete;
            if (deleteHistoryModal) deleteHistoryModal.style.display = 'flex';
        }

        function attachDeleteHandlers() {
            const sel = '.delete-history-btn, button[data-action="delete-transaction"]';
            document.querySelectorAll(sel).forEach(btn=>{
                btn.removeEventListener('click', deleteHistoryClickHandler);
                btn.addEventListener('click', deleteHistoryClickHandler);
            });
        }

        function fetchPage(p, append=false) {
            if (loading) return;
            loading = true;
            if (loadBtn) loadBtn.disabled = true;
            // show skeletons
            try {
                if (!append) {
                    tbody.innerHTML = makeSkeletonHtml(perPage);
                } else {
                    tbody.insertAdjacentHTML('beforeend', makeSkeletonHtml(3));
                }
            } catch (err) { /* ignore DOM issues */ }
            const params = readFilters();
            params.set('page', String(p));
            params.set('per_page', String(perPage));
            const url = BASE_URL + 'api/transactions/fetch_rows.php?' + params.toString();
            fetch(url, { credentials: 'same-origin' })
            .then(r=>r.json())
            .then(data=>{
                // remove any skeleton rows first
                try { Array.from(tbody.querySelectorAll('.table-skeleton-row')).forEach(n=>n.remove()); } catch(e){}
                if (!append) tbody.innerHTML = data.html;
                else tbody.insertAdjacentHTML('beforeend', data.html);
                total = Number(data.total || 0);
                page = Number(data.page || p);
                attachDeleteHandlers();
                updateLoadVisibility();
            })
            .catch(err=>{
                console.error('Failed to load transactions', err);
            })
            .finally(()=>{
                loading = false;
                if (loadBtn) loadBtn.disabled = false;
            });
        }

        // intercept globalFilterForm submit and do AJAX replace
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

        // initial load: fetch page 1 to get canonical total and server-rendered rows replaced
        fetchPage(1, false);

        // handle back/forward navigation
        window.addEventListener('popstate', function(){
            page = 1;
            fetchPage(1,false);
        });

    })();

    // Replace dropdowns with modal-based navigation (menu & panels)
    (function setupModalMenu() {
        const mainModal = document.getElementById('mainModal');
        const mainModalClose = document.getElementById('mainModalClose');
        const panelBackBtn = document.getElementById('panelBackBtn');
        const mainMenuBtn = document.getElementById('mainMenuBtn');
        const adminSettingsBtn = document.getElementById('adminSettingsBtn');
        const openSettingsFromMenu = document.getElementById('openSettingsFromMenu');
        const manageAccountsBtn = document.getElementById('manageAccountsBtn');
        const panels = mainModal ? Array.from(mainModal.querySelectorAll('.modal-panel')) : [];
        let panelStack = [];

        function showPanel(panelId, pushStack = true) {
            if (!mainModal) return;
            const target = mainModal.querySelector(`.modal-panel[data-panel="${panelId}"]`);
            if (!target) return;
            // find current visible panel
            const current = mainModal.querySelector('.modal-panel[aria-hidden="false"]');
            if (current && pushStack) {
                panelStack.push(current.getAttribute('data-panel'));
            }
            // hide all
            panels.forEach(p => {
                p.style.display = 'none';
                p.setAttribute('aria-hidden', 'true');
            });
            // show target
            target.style.display = 'block';
            target.setAttribute('aria-hidden', 'false');
            // back button visibility
            if (panelStack.length > 0) panelBackBtn.style.display = 'inline-block'; else panelBackBtn.style.display = 'none';
            // trap focus to modal
            trapFocus(mainModal);
        }

        function openMainModal(startPanel = 'menuPanel') {
            if (!mainModal) return;
            panelStack = [];
            mainModal.style.display = 'flex';
            showPanel(startPanel, false);
        }

        function closeMainModal() {
            if (!mainModal) return;
            mainModal.style.display = 'none';
            panels.forEach(p => { p.style.display = 'none'; p.setAttribute('aria-hidden', 'true'); });
            panelStack = [];
            releaseFocus();
        }

        function backPanel() {
            if (panelStack.length === 0) { closeMainModal(); return; }
            const prev = panelStack.pop();
            // show prev without pushing current
            panels.forEach(p => { p.style.display = 'none'; p.setAttribute('aria-hidden', 'true'); });
            const prevEl = mainModal.querySelector(`.modal-panel[data-panel="${prev}"]`);
            if (prevEl) { prevEl.style.display = 'block'; prevEl.setAttribute('aria-hidden', 'false'); }
            panelBackBtn.style.display = panelStack.length > 0 ? 'inline-block' : 'none';
        }

        if (mainMenuBtn) mainMenuBtn.addEventListener('click', function(e) { e.preventDefault(); openMainModal('menuPanel'); });
        if (adminSettingsBtn) adminSettingsBtn.addEventListener('click', function(e) { e.preventDefault(); openMainModal('settingsPanel'); });
        if (mainModalClose) mainModalClose.addEventListener('click', closeMainModal);
        if (panelBackBtn) panelBackBtn.addEventListener('click', backPanel);
        if (openSettingsFromMenu) openSettingsFromMenu.addEventListener('click', function(e) { e.preventDefault(); showPanel('settingsPanel'); });
        if (manageAccountsBtn) manageAccountsBtn.addEventListener('click', function(e) { e.preventDefault(); window.location.href = `${BASE_URL}admin/kelola_akun.php`; });

        // expose open/close/show helpers so other modal code can call them (used for back navigation)
        window.openMainModal = openMainModal;
        window.closeMainModal = closeMainModal;
        window.showMainPanel = showPanel;

        // When opening globalAccess or viewAs modals from menu, close main modal first
        // Query these here to avoid referencing TDZ/outer-scope consts declared later
        const _viewAllTransactionsBtn = document.getElementById('viewAllTransactionsBtn');
        const _viewAsAnotherAdminBtn = document.getElementById('viewAsAnotherAdminBtn');
        const _globalAccessModal = document.getElementById('globalAccessModal');
        const _viewAsAnotherAdminModal = document.getElementById('viewAsAnotherAdminModal');

        if (_viewAllTransactionsBtn) _viewAllTransactionsBtn.addEventListener('click', function(e) { e.preventDefault(); closeMainModal(); if (_globalAccessModal) _globalAccessModal.style.display = 'flex'; });
        if (_viewAsAnotherAdminBtn) _viewAsAnotherAdminBtn.addEventListener('click', function(e) { e.preventDefault(); closeMainModal(); if (_viewAsAnotherAdminModal) _viewAsAnotherAdminModal.style.display = 'flex'; });

        // Close when clicking outside mainModal content
        if (mainModal) {
            mainModal.addEventListener('click', function(ev) {
                if (ev.target === mainModal) closeMainModal();
            });
        }
    })();

    // Modal handler for dashboard filter (if partial renders modal)
    (function modalFilterHandler() {
        const filterModal = document.getElementById('filterModal');
        if (!filterModal) return;

        let lastFocusBeforeFilterModal = null;
        const filterOpenModalBtn = document.getElementById('filter-open-modal');
        const filterModalClose = document.getElementById('filterModalClose');
        const filterModalCancel = document.getElementById('filterModalCancel');
        const filterModalApply = document.getElementById('filterModalApply');

        // Elements inside modal
        const modalRole = document.getElementById('modal-filter-role');
        const modalDate = document.getElementById('modal-filter-date');
        const modalSort = document.getElementById('modal-sort-amount');

        // Main form inputs to sync with
        const mainForm = document.getElementById('globalFilterForm');
        const mainSearch = document.getElementById('filter-search');
        const mainRole = document.getElementById('filter-role');
        const mainDate = document.getElementById('filter-date');
        const mainSort = document.getElementById('sort-amount');

        function openModal() {
            if (!filterModal) return;
            if (filterModal.getAttribute('data-opened') === '1') return;
            // populate modal controls from main form values
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

        // Apply modal: copy values to main form and submit
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

        // close when clicking outside modal content
        window.addEventListener('click', function(event) {
            if (event.target == filterModal) {
                closeModal();
            }
        });
    })();

    // Mobile search-only button: preserve active filters and submit `#globalFilterForm` if present
    (function mobileSearchOnly() {
        const mobileSearchInput = document.getElementById('filter-search-mobile');
        const mobileSearchBtn = document.getElementById('filter-search-btn-mobile');
        function doSearchSubmit() {
            const q = (mobileSearchInput && mobileSearchInput.value) ? mobileSearchInput.value.trim() : '';
            const mainForm = document.getElementById('globalFilterForm');
            if (mainForm) {
                // ensure both legacy and global hidden fields are in sync
                const g = document.getElementById('global-search');
                const f = document.getElementById('filter-search');
                if (g) g.value = q;
                if (f) f.value = q;
                try { submitGlobalFilterForm(); return; } catch (e) {}
            }

            // Fallback: preserve view/view_as/view_mode only and redirect
            const preserve = ['view', 'view_as', 'view_mode'];
            const current = new URLSearchParams(window.location.search);
            const next = new URLSearchParams();
            preserve.forEach(k => { if (current.has(k)) next.set(k, current.get(k)); });
            if (q) next.set('search', q);
            const qs = next.toString();
            const path = window.location.pathname;
            window.location.href = path + (qs ? ('?' + qs) : '');
        }

        if (mobileSearchBtn && mobileSearchInput) {
            mobileSearchBtn.addEventListener('click', function (e) { e.preventDefault(); doSearchSubmit(); });
            mobileSearchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); doSearchSubmit(); } });
        }

        // Convert native title attributes in the mobile bar into the styled tooltip (data-tooltip)
        const mobileBar = document.querySelector('.filter-mobile-bar');
        if (mobileBar) {
            Array.from(mobileBar.querySelectorAll('[title]')).forEach(el => {
                const t = el.getAttribute('title');
                if (!t) return;
                el.setAttribute('data-tooltip', t);
                el.classList.add('has-tooltip');
                // remove native tooltip to avoid duplicate on hover (keep for a11y fallback)
                el.removeAttribute('title');
            });
        }
    })();
    // Delete history modal logic
    if (deleteHistoryModal) {
        document.querySelectorAll('.delete-history-btn, button[data-action="delete-transaction"]').forEach(button => {
            button.addEventListener('click', function() {
                transactionIdToDelete = this.getAttribute('data-transactionid');
                deleteTransactionIdSpan.textContent = transactionIdToDelete;
                deleteHistoryModal.style.display = 'flex';
            });
        });

        confirmDeleteHistoryBtn.addEventListener('click', function() {
            if (transactionIdToDelete) {
                fetch(`${BASE_URL}api/transactions/hapus_histori.php?id=${transactionIdToDelete}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        deleteHistoryModal.style.display = 'none';
                        // Show shadow/toast notification
                        showToast(data.message || 'Transaksi berhasil dihapus.');
                        // Remove the deleted row from the table if present
                        try {
                            const btn = document.querySelector(`.delete-history-btn[data-transactionid="${transactionIdToDelete}"], button[data-action="delete-transaction"][data-transactionid="${transactionIdToDelete}"]`);
                            if (btn) {
                                const tr = btn.closest('tr');
                                if (tr) {
                                    tr.style.transition = 'opacity 220ms ease';
                                    tr.style.opacity = '0';
                                    setTimeout(() => { tr.remove(); }, 240);
                                }
                            }
                        } catch (e) {
                            // fallback: reload if DOM manipulation fails
                            setTimeout(() => { location.reload(); }, 600);
                        }
                        transactionIdToDelete = null;
                        } else {
                        window.showToast(data.message || 'Gagal menghapus transaksi.');
                    }
                });
            }
        });

        deleteHistoryModalClose.onclick = () => deleteHistoryModal.style.display = 'none';
        cancelDeleteHistoryBtn.onclick = () => deleteHistoryModal.style.display = 'none';
    }

    // Global Access Logic
    const globalAccessModal = document.getElementById('globalAccessModal');
    const globalAccessClose = globalAccessModal ? globalAccessModal.querySelector('.modal-close') : null;
    const globalAccessForm = document.getElementById('globalAccessForm');
    const globalAccessError = document.getElementById('global-access-error');
    const viewAllTransactionsBtn = document.getElementById('viewAllTransactionsBtn');
    const globalAccessBackBtn = document.getElementById('globalAccessBack');

    if (globalAccessModal) {
        if (viewAllTransactionsBtn) {
            viewAllTransactionsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                globalAccessError.style.display = 'none'; // Reset error message
                globalAccessForm.reset(); // Reset form
                globalAccessModal.style.display = 'flex';
            });
        }

        if (globalAccessClose) {
            globalAccessClose.onclick = () => globalAccessModal.style.display = 'none';
        }

        if (globalAccessBackBtn) {
            globalAccessBackBtn.addEventListener('click', function(e) {
                e.preventDefault();
                globalAccessModal.style.display = 'none';
                if (window.openMainModal) window.openMainModal('menuPanel');
            });
        }

        if (globalAccessForm) {
            globalAccessForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch(`${BASE_URL}api/admin/admin_actions.php`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                        if (data.success) {
                        window.location.href = `${BASE_URL}auth/dashboard.php?view=all`;
                    } else {
                        // show as toast instead of browser default for unknown errors
                        window.showToast(data.message || 'Kode akses salah.');
                        globalAccessError.textContent = data.message || 'Kode akses salah.';
                        globalAccessError.style.display = 'block';
                    }
                });
            });
        }
    }

    // View As Another Admin Logic
    const viewAsAnotherAdminModal = document.getElementById('viewAsAnotherAdminModal');
    const viewAsAnotherAdminClose = viewAsAnotherAdminModal ? viewAsAnotherAdminModal.querySelector('.modal-close') : null;
    const viewAsAnotherAdminForm = document.getElementById('viewAsAnotherAdminForm');
    const viewAsError = document.getElementById('view-as-error');
    const viewAsAnotherAdminBtn = document.getElementById('viewAsAnotherAdminBtn');
    const viewAsBackBtn = document.getElementById('viewAsBack');

    if (viewAsAnotherAdminModal) {
        if (viewAsAnotherAdminBtn) {
            viewAsAnotherAdminBtn.addEventListener('click', function(e) {
                e.preventDefault();
                viewAsError.style.display = 'none'; // Reset error message
                viewAsAnotherAdminForm.reset(); // Reset form
                viewAsAnotherAdminModal.style.display = 'flex';
            });
        }

        if (viewAsAnotherAdminClose) {
            viewAsAnotherAdminClose.onclick = () => viewAsAnotherAdminModal.style.display = 'none';
        }

        if (viewAsBackBtn) {
            viewAsBackBtn.addEventListener('click', function(e) {
                e.preventDefault();
                viewAsAnotherAdminModal.style.display = 'none';
                if (window.openMainModal) window.openMainModal('menuPanel');
            });
        }

        if (viewAsAnotherAdminForm) {
            viewAsAnotherAdminForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch(`${BASE_URL}api/admin/admin_actions.php`, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                        if (data.success) {
                        const targetAdminId = formData.get('target_admin_id');
                        window.location.href = `${BASE_URL}auth/dashboard.php?view_as=${targetAdminId}`;
                    } else {
                        viewAsError.textContent = data.message || 'Verifikasi gagal.';
                        viewAsError.style.display = 'block';
                    }
                });
            });
        }
    }

    // Initial check for success message from PHP
    if (window.PHP_SUCCESS_MESSAGE) {
        successMessageElement.textContent = window.PHP_SUCCESS_MESSAGE;
        successModal.style.display = 'flex';
    }

    // Currency display + enforce multiples of 2000 for transaction amounts
    (function currencyAndMultiples() {
        const MULTIPLE = 2000;

        function onlyDigits(str) {
            return String(str).replace(/[^0-9-]/g, '') || '0';
        }

        function formatRupiah(num) {
            if (!isFinite(num)) return '0';
            return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Arrow keys change the value by MULTIPLE (no rounding applied)

        const groups = [
            { display: '#deposit-amount-display', hidden: '#deposit-amount' },
            { display: '#withdraw-amount-display', hidden: '#withdraw-amount' }
        ];

        groups.forEach(g => {
            const display = document.querySelector(g.display);
            const hidden = document.querySelector(g.hidden);
            if (!display || !hidden) return;

            // Live formatting while typing: show thousand-separated value (no 'Rp' prefix — prefix is a separate element)
            display.addEventListener('input', function(e) {
                const raw = onlyDigits(this.value);
                if (!raw || raw === '0') {
                    this.value = '';
                    hidden.value = '';
                    return;
                }
                // Set hidden input to numeric value (no rounding)
                hidden.value = String(Number(raw));
                // Format with dots for thousands (no 'Rp' prefix here)
                this.value = formatRupiah(raw);
                // place caret at end for simplicity
                try { this.selectionStart = this.selectionEnd = this.value.length; } catch (err) {}
            });

            // On focus: show raw digits for easier editing
            display.addEventListener('focus', function() {
                const raw = onlyDigits(this.value);
                this.value = raw === '0' ? '' : raw;
                try { this.selectionStart = this.selectionEnd = this.value.length; } catch (err) {}
            });

            // On blur: format display (no rounding)
            display.addEventListener('blur', function() {
                if (this.value === '') {
                    hidden.value = '';
                    this.value = '';
                    return;
                }
                const digits = Number(onlyDigits(this.value));
                hidden.value = String(Math.max(0, digits));
                this.value = (digits > 0) ? formatRupiah(digits) : '';
            });

            // Arrow key handling: increase/decrease by MULTIPLE
            display.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    const curr = Number(onlyDigits(this.value || hidden.value || '0')) || 0;
                    const delta = (e.key === 'ArrowUp') ? MULTIPLE : -MULTIPLE;
                    const next = Math.max(0, curr + delta);
                    hidden.value = String(next);
                    this.value = formatRupiah(next);
                    try { this.selectionStart = this.selectionEnd = this.value.length; } catch (err) {}
                }
            });

            // Stepper button clicks (plus/minus)
            document.querySelectorAll('.btn-step').forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetSel = this.getAttribute('data-target');
                    const stepVal = Number(this.getAttribute('data-step')) || 0;
                    const tgtDisplay = document.querySelector(targetSel);
                    if (!tgtDisplay) return;
                    const current = Number(onlyDigits(tgtDisplay.value || hidden.value || '0')) || 0;
                    const next = Math.max(0, current + stepVal);
                    hidden.value = String(next);
                    tgtDisplay.value = formatRupiah(next);
                });
            });

            // Ensure hidden input is set before form submit
            const form = display.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // if display empty, required will block submit
                    const digits = Number(onlyDigits(display.value || hidden.value || '0')) || 0;
                    hidden.value = String(Math.max(0, digits));
                    // Format display input as thousands-separated value (no 'Rp' prefix)
                    display.value = (digits > 0) ? formatRupiah(digits) : '';
                });
            }
        });
    })();

    // Admin settings wiring (toggle timeout, change minutes via superadmin modal)
    (function adminSettings() {
        const toggle = document.getElementById('toggleTimeoutCheckbox');
        const minutesDisplay = document.getElementById('timeoutMinutesDisplay');
        const changeBtn = document.getElementById('changeTimeoutBtn');
        const mobileModal = document.getElementById('mobileSettingsModal');
        const mobileClose = document.getElementById('mobileSettingsClose');
        const mobileToggle = document.getElementById('mobileToggleTimeout');
        const mobileMinutes = document.getElementById('mobileTimeoutMinutes');
        const mobileChangeBtn = document.getElementById('mobileChangeTimeoutBtn');
        const mobileError = document.getElementById('mobile-settings-error');
        if (!toggle) return; // not an admin or element missing

        // Use the global showToast (defined at top) for notifications

        // Focus trap helpers for modal accessibility
        let _previouslyFocused = null;
        function trapFocus(modal) {
            if (!modal) return;
            _previouslyFocused = document.activeElement;
            const focusableSelectors = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const nodes = Array.from(modal.querySelectorAll(focusableSelectors)).filter(n => n.offsetParent !== null);
            if (nodes.length === 0) return;
            const first = nodes[0];
            const last = nodes[nodes.length - 1];

            function handleTab(e) {
                if (e.key !== 'Tab') return;
                if (e.shiftKey) {
                    if (document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }

            modal.__focusHandler = handleTab;
            modal.setAttribute('data-focus-trap-attached', '1');
            document.addEventListener('keydown', handleTab);
        }

        function releaseFocus() {
            if (_previouslyFocused) {
                try { _previouslyFocused.focus(); } catch (e) {}
            }
            const attached = document.querySelector('[data-focus-trap-attached]');
            if (attached && attached.__focusHandler) {
                document.removeEventListener('keydown', attached.__focusHandler);
                delete attached.__focusHandler;
                attached.removeAttribute('data-focus-trap-attached');
            }
        }

        // Initialize from server-provided settings (convert seconds -> minutes)
        const gs = window.GLOBAL_SETTINGS || {};
        try {
            toggle.checked = !!gs.timeout_enabled;
            const initMins = Math.max(1, Math.round((gs.timeout_seconds || 300) / 60));
            if (minutesDisplay) minutesDisplay.textContent = initMins + ' menit';
            // initialize mobile if present
            if (mobileToggle) mobileToggle.checked = !!gs.timeout_enabled;
            if (mobileMinutes) mobileMinutes.value = String(initMins);
        } catch (err) {
            toggle.checked = false;
            if (minutesDisplay) minutesDisplay.textContent = '5 menit';
        }

        toggle.addEventListener('change', function() {
            const fd = new FormData();
            fd.append('action', 'toggle_timeout');
            fd.append('enable', this.checked ? '1' : '0');
            fd.append('csrf', window.CSRF_TOKEN || '');

            fetch(`${BASE_URL}api/admin/update_settings.php`, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        window.GLOBAL_SETTINGS = data.settings || window.GLOBAL_SETTINGS;
                        const newMins = Math.max(1, Math.round((window.GLOBAL_SETTINGS.timeout_seconds || 300) / 60));
                        if (minutesDisplay) minutesDisplay.textContent = newMins + ' menit';
                        showToast('Pengaturan timeout diperbarui.');
                    } else {
                        showToast(data.message || 'Gagal memperbarui pengaturan.');
                        // revert checkbox
                        toggle.checked = !(toggle.checked);
                    }
                })
                .catch(() => {
                    showToast('Gagal menghubungi server.');
                    toggle.checked = !(toggle.checked);
                });
        });

        // Modal elements
        const changeModal = document.getElementById('changeTimeoutModal');
        const changeModalClose = document.getElementById('changeTimeoutClose');
        const changeModalCancel = document.getElementById('changeTimeoutCancel');
        const changeForm = document.getElementById('changeTimeoutForm');
        const changeError = document.getElementById('change-time-error');
        const modalMinutesInput = document.getElementById('timeout-minutes');
        const modalCodeInput = document.getElementById('superadmin-code');

        let lastFocusedElement = null;
        function openChangeModal() {
            if (!changeModal) return;
            lastFocusedElement = document.activeElement;
            if (changeError) changeError.style.display = 'none';
            if (modalCodeInput) modalCodeInput.value = '';
            // modal needs numeric value; strip non-digits from display (e.g. "10 menit")
            const displayed = (minutesDisplay && minutesDisplay.textContent) ? minutesDisplay.textContent.replace(/\D/g, '') : '';
            if (modalMinutesInput) modalMinutesInput.value = displayed || '5';
            changeModal.style.display = 'flex';
            // find first focusable element
            setTimeout(() => {
                try { if (modalCodeInput) modalCodeInput.focus(); } catch (e) {}
            }, 10);
            document.addEventListener('keydown', modalKeyHandler);
            trapFocus(changeModal);
        }

        if (changeBtn) {
            changeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openChangeModal();
            });
        }

        // If mobile modal exists, wire its controls
        function openMobileSettings() {
            if (!mobileModal) return;
            mobileError.style.display = 'none';
            mobileModal.style.display = 'flex';
            mobileToggle.focus();
        }
        function closeMobileSettings() {
            if (!mobileModal) return;
            mobileModal.style.display = 'none';
        }
        if (mobileClose) mobileClose.addEventListener('click', () => closeMobileSettings());

        if (mobileToggle) {
            mobileToggle.addEventListener('change', function() {
                const fd = new FormData();
                fd.append('action', 'toggle_timeout');
                fd.append('enable', this.checked ? '1' : '0');
                fd.append('csrf', window.CSRF_TOKEN || '');
                fetch(`${BASE_URL}api/admin/update_settings.php`, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                            if (data && data.success) {
                                window.GLOBAL_SETTINGS = data.settings || window.GLOBAL_SETTINGS;
                                const newMins = Math.max(1, Math.round((window.GLOBAL_SETTINGS.timeout_seconds || 300) / 60));
                                if (minutesDisplay) minutesDisplay.textContent = newMins + ' menit';
                                if (mobileMinutes) mobileMinutes.value = String(newMins);
                                toggle.checked = mobileToggle.checked;
                                showToast('Pengaturan timeout diperbarui.');
                            } else {
                            mobileError.textContent = data.message || 'Gagal memperbarui pengaturan.';
                            mobileError.style.display = 'block';
                            mobileToggle.checked = !mobileToggle.checked;
                        }
                    })
                    .catch(() => {
                        mobileError.textContent = 'Gagal menghubungi server.';
                        mobileError.style.display = 'block';
                        mobileToggle.checked = !mobileToggle.checked;
                    });
            });
        }

        if (mobileChangeBtn) {
            mobileChangeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // open the superadmin change modal
                closeMobileSettings();
                openChangeModal();
            });
        }

        function closeChangeModal() {
            if (!changeModal) return;
            changeModal.style.display = 'none';
            document.removeEventListener('keydown', modalKeyHandler);
            releaseFocus();
            try {
                if (lastFocusedElement) lastFocusedElement.focus();
            } catch (e) {}
        }

        if (changeModalClose) changeModalClose.addEventListener('click', () => closeChangeModal());
        if (changeModalCancel) changeModalCancel.addEventListener('click', () => closeChangeModal());

        // Close when clicking outside modal-content
        window.addEventListener('click', function(ev) {
            if (!changeModal) return;
            if (ev.target === changeModal) {
                closeChangeModal();
            }
        });

        // ESC and Tab handling
        function modalKeyHandler(e) {
            if (e.key === 'Escape' || e.key === 'Esc') {
                closeChangeModal();
            }
            // Tab trapping handled by trapFocus helper
        }

        if (changeForm) {
            changeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (changeError) changeError.style.display = 'none';
                const code = modalCodeInput ? modalCodeInput.value.trim() : '';
                const minutes = modalMinutesInput ? parseInt(modalMinutesInput.value, 10) : NaN;
                if (!code) { if (changeError) { changeError.textContent = 'Kode superadmin diperlukan.'; changeError.style.display = 'block'; } return; }
                if (isNaN(minutes) || minutes <= 0) { if (changeError) { changeError.textContent = 'Waktu tidak valid.'; changeError.style.display = 'block'; } return; }

                const fd = new FormData();
                fd.append('action', 'set_timeout');
                fd.append('super_code', code);
                fd.append('seconds', String(minutes * 60));
                fd.append('csrf', window.CSRF_TOKEN || '');

                fetch(`${BASE_URL}api/admin/update_settings.php`, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            window.GLOBAL_SETTINGS = data.settings || window.GLOBAL_SETTINGS;
                            const newMins = Math.max(1, Math.round((window.GLOBAL_SETTINGS.timeout_seconds || 300) / 60));
                            if (minutesDisplay) minutesDisplay.textContent = newMins + ' menit';
                            toggle.checked = !!window.GLOBAL_SETTINGS.timeout_enabled;
                            closeChangeModal();
                            showToast('Waktu timeout diperbarui.');
                        } else {
                            if (changeError) { changeError.textContent = data.message || 'Gagal memperbarui timeout.'; changeError.style.display = 'block'; }
                        }
                    })
                    .catch(() => {
                        if (changeError) { changeError.textContent = 'Gagal menghubungi server.'; changeError.style.display = 'block'; }
                    });
            });
        }
    })();
});
