(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const tableWrap = document.querySelector('[data-userdata-table-wrap]');
    const feedback = document.querySelector('[data-userdata-feedback]');
    const fetchUrl = tableWrap?.getAttribute('data-fetch-url') || '';
    const resetTemplate = tableWrap?.getAttribute('data-reset-url-template') || '';
    const deleteTemplate = tableWrap?.getAttribute('data-delete-url-template') || '';
    const tbody = tableWrap?.querySelector('[data-userdata-tbody]') || null;
    const searchInput = document.querySelector('[data-userdata-search]');
    const levelFilter = document.querySelector('[data-userdata-level-filter]');
    const paginationWrap = document.querySelector('[data-userdata-pagination]');
    const paginationMeta = document.querySelector('[data-userdata-pagination-meta]');
    const paginationActions = document.querySelector('[data-userdata-pagination-actions]');

    const addModal = document.querySelector('[data-add-user-modal]');
    const openAddBtn = document.querySelector('[data-open-add-user-modal]');
    const closeAddNodes = addModal ? Array.from(addModal.querySelectorAll('[data-close-add-user-modal]')) : [];
    const addForm = addModal?.querySelector('[data-add-user-form]');
    const addFeedback = addModal?.querySelector('[data-add-user-feedback]');
    const addSaveBtn = addModal?.querySelector('[data-add-user-save]');
    const storeUrl = addModal?.getAttribute('data-store-url') || '';
    const importModal = document.querySelector('[data-import-userdata-modal]');
    const openImportBtn = document.querySelector('[data-open-import-userdata-modal]');
    const closeImportNodes = importModal ? Array.from(importModal.querySelectorAll('[data-close-import-userdata-modal]')) : [];
    const importForm = importModal?.querySelector('[data-import-userdata-form]');

    const state = {
        q: (searchInput?.value || '').trim(),
        levelid: Number(levelFilter?.value || '0') || 0,
        page: Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || '1') || 1),
        lastPage: 1,
    };

    let searchDebounceTimer = null;

    const setFeedback = (message, type = 'success') => {
        if (!feedback) {
            return;
        }
        feedback.hidden = false;
        feedback.classList.remove('success', 'error');
        feedback.classList.add(type === 'error' ? 'error' : 'success');
        feedback.textContent = message;
    };

    const clearFeedback = () => {
        if (!feedback) {
            return;
        }
        feedback.hidden = true;
        feedback.classList.remove('success', 'error');
        feedback.textContent = '';
    };

    const setAddFeedback = (message, type = 'success') => {
        if (!addFeedback) {
            return;
        }
        addFeedback.hidden = false;
        addFeedback.classList.remove('success', 'error');
        addFeedback.classList.add(type === 'error' ? 'error' : 'success');
        addFeedback.textContent = message;
    };

    const clearAddFeedback = () => {
        if (!addFeedback) {
            return;
        }
        addFeedback.hidden = true;
        addFeedback.classList.remove('success', 'error');
        addFeedback.textContent = '';
    };

    const buildUrl = (template, userId) => template.replace('__USER_ID__', String(userId));

    const anyModalOpen = () => [addModal, importModal].some((node) => node && !node.hidden);

    const openFadeModal = (targetModal) => {
        if (!targetModal) {
            return;
        }
        targetModal.hidden = false;
        targetModal.classList.remove('is-leave');
        window.requestAnimationFrame(() => targetModal.classList.add('is-enter'));
        document.body.style.overflow = 'hidden';
    };

    const closeFadeModal = (targetModal, onAfter = null) => {
        if (!targetModal || targetModal.hidden) {
            if (typeof onAfter === 'function') {
                onAfter();
            }
            return;
        }

        targetModal.classList.remove('is-enter');
        targetModal.classList.add('is-leave');
        window.setTimeout(() => {
            targetModal.hidden = true;
            targetModal.classList.remove('is-leave');
            if (!anyModalOpen()) {
                document.body.style.overflow = '';
            }
            if (typeof onAfter === 'function') {
                onAfter();
            }
        }, 220);
    };

    const postAction = async (url) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.status !== 'ok') {
            throw new Error(payload?.message || 'Action failed.');
        }

        return payload;
    };

    const postForm = async (url, formData) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: formData,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.status !== 'ok') {
            throw new Error(payload?.message || 'Action failed.');
        }

        return payload;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const rowHtml = (user) => `
        <tr data-userdata-row data-userid="${escapeHtml(user.userid)}">
            <td>${escapeHtml(user.username)}</td>
            <td>${escapeHtml(user.name)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>${escapeHtml(user.phonenumber)}</td>
            <td>
                <div class="admin-user-level-cell">
                    <span>${escapeHtml(user.level)}</span>
                    <div class="admin-user-actions">
                        <button type="button" class="admin-user-action-btn" title="Reset Password" aria-label="Reset Password" data-user-reset>
                            <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M20 11a8 8 0 1 0 2 5.5M20 4v7h-7"/></svg>
                        </button>
                        <button type="button" class="admin-user-action-btn admin-user-action-delete" title="Delete User" aria-label="Delete User" data-user-delete>
                            <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M6 7h12m-9 0V5h6v2m-7 0 1 12h8l1-12"/></svg>
                        </button>
                    </div>
                </div>
            </td>
        </tr>
    `;

    const noRowsHtml = `
        <tr>
            <td colspan="5">No user data found.</td>
        </tr>
    `;

    const renderRows = (rows) => {
        if (!tbody) {
            return;
        }
        if (!Array.isArray(rows) || rows.length === 0) {
            tbody.innerHTML = noRowsHtml;
            return;
        }
        tbody.innerHTML = rows.map((row) => rowHtml(row)).join('');
    };

    const createPageButtonHtml = (page, label, active = false, disabled = false) => `
        <button
            type="button"
            class="btn btn-outline${active ? ' is-active' : ''}"
            data-userdata-page="${page}"
            ${disabled ? 'disabled' : ''}
        >${label}</button>
    `;

    const renderPagination = (pagination) => {
        if (!paginationWrap || !paginationMeta || !paginationActions) {
            return;
        }

        const page = Number(pagination?.page || 1);
        const lastPage = Number(pagination?.last_page || 1);
        const total = Number(pagination?.total || 0);
        const from = Number(pagination?.from || 0);
        const to = Number(pagination?.to || 0);

        state.page = page;
        state.lastPage = lastPage;

        paginationMeta.textContent = `Showing ${from}-${to} of ${total}`;

        if (total <= 0) {
            paginationActions.innerHTML = '';
            return;
        }

        const pageButtons = [];
        const start = Math.max(1, page - 2);
        const end = Math.min(lastPage, page + 2);

        pageButtons.push(createPageButtonHtml(Math.max(1, page - 1), 'Prev', false, page <= 1));
        for (let cursor = start; cursor <= end; cursor++) {
            pageButtons.push(createPageButtonHtml(cursor, String(cursor), cursor === page, cursor === page));
        }
        pageButtons.push(createPageButtonHtml(Math.min(lastPage, page + 1), 'Next', false, page >= lastPage));

        paginationActions.innerHTML = pageButtons.join('') + `<span class="admin-user-pagination-page">Page ${page} / ${lastPage}</span>`;
    };

    const fetchUsers = async (nextPage = null) => {
        if (!fetchUrl) {
            return;
        }

        if (typeof nextPage === 'number' && Number.isFinite(nextPage)) {
            state.page = Math.max(1, Math.trunc(nextPage));
        }

        const params = new URLSearchParams();
        params.set('page', String(state.page));
        params.set('q', state.q);
        params.set('levelid', String(state.levelid));

        const response = await fetch(`${fetchUrl}?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.status !== 'ok') {
            throw new Error(payload?.message || 'Failed to load user data.');
        }

        renderRows(payload?.rows || []);
        renderPagination(payload?.pagination || {});
    };

    const openAddModal = () => {
        if (!addModal) {
            return;
        }
        clearAddFeedback();
        addModal.hidden = false;
        document.body.style.overflow = 'hidden';
        addForm?.querySelector('input,select')?.focus();
    };

    const closeAddModal = () => {
        if (!addModal) {
            return;
        }
        addModal.hidden = true;
        if (!anyModalOpen()) {
            document.body.style.overflow = '';
        }
        addForm?.reset();
        clearAddFeedback();
    };

    openAddBtn?.addEventListener('click', openAddModal);
    closeAddNodes.forEach((node) => node.addEventListener('click', closeAddModal));

    const openImportModal = () => openFadeModal(importModal);
    const closeImportModal = () => closeFadeModal(importModal, () => importForm?.reset());

    openImportBtn?.addEventListener('click', openImportModal);
    closeImportNodes.forEach((node) => node.addEventListener('click', closeImportModal));

    addForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!storeUrl || !addForm) {
            return;
        }

        clearAddFeedback();
        if (addSaveBtn) {
            addSaveBtn.disabled = true;
        }

        try {
            const payload = await postForm(storeUrl, new FormData(addForm));
            closeAddModal();
            await fetchUsers(state.page);
            setFeedback(payload?.message || 'User added successfully.', 'success');
        } catch (error) {
            setAddFeedback(error.message || 'Failed to add user.', 'error');
        } finally {
            if (addSaveBtn) {
                addSaveBtn.disabled = false;
            }
        }
    });

    if (!tableWrap) {
        return;
    }

    searchInput?.addEventListener('input', () => {
        state.q = (searchInput.value || '').trim();
        state.page = 1;
        if (searchDebounceTimer) {
            window.clearTimeout(searchDebounceTimer);
        }
        searchDebounceTimer = window.setTimeout(async () => {
            try {
                clearFeedback();
                await fetchUsers(state.page);
            } catch (error) {
                setFeedback(error.message || 'Failed to load user data.', 'error');
            }
        }, 300);
    });

    levelFilter?.addEventListener('change', async () => {
        state.levelid = Number(levelFilter.value || '0') || 0;
        state.page = 1;
        try {
            clearFeedback();
            await fetchUsers(state.page);
        } catch (error) {
            setFeedback(error.message || 'Failed to load user data.', 'error');
        }
    });

    paginationWrap?.addEventListener('click', async (event) => {
        const pageBtn = event.target.closest('[data-userdata-page]');
        if (!pageBtn) {
            return;
        }

        const nextPage = Number(pageBtn.getAttribute('data-userdata-page') || '1');
        if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage === state.page) {
            return;
        }

        try {
            clearFeedback();
            await fetchUsers(nextPage);
        } catch (error) {
            setFeedback(error.message || 'Failed to load user data.', 'error');
        }
    });

    tableWrap.addEventListener('click', async (event) => {
        const resetBtn = event.target.closest('[data-user-reset]');
        const deleteBtn = event.target.closest('[data-user-delete]');
        if (!resetBtn && !deleteBtn) {
            return;
        }

        const row = event.target.closest('[data-userdata-row]');
        if (!row) {
            return;
        }

        const userId = Number(row.getAttribute('data-userid') || '0');
        if (!userId) {
            setFeedback('Invalid user ID.', 'error');
            return;
        }

        clearFeedback();
        const activeButton = resetBtn || deleteBtn;
        activeButton.disabled = true;

        try {
            if (resetBtn) {
                const payload = await postAction(buildUrl(resetTemplate, userId));
                setFeedback(payload?.message || 'Password reset successful.', 'success');
                return;
            }

            const confirmed = window.confirm('Delete this user? This action cannot be undone.');
            if (!confirmed) {
                return;
            }

            const payload = await postAction(buildUrl(deleteTemplate, userId));
            await fetchUsers(state.page);
            setFeedback(payload?.message || 'User deleted.', 'success');
        } catch (error) {
            setFeedback(error.message || 'Action failed.', 'error');
        } finally {
            activeButton.disabled = false;
        }
    });
})();
