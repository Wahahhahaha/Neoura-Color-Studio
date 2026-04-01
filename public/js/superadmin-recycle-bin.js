(() => {
    const filterWrap = document.querySelector('[data-recycle-filter-wrap]');
    const levelFilter = document.querySelector('[data-recycle-level-filter]');
    const actionFilter = document.querySelector('[data-recycle-action-filter]');
    const tableContainer = document.querySelector('[data-recycle-table-container]');
    const feedback = document.querySelector('[data-recycle-feedback]');
    const fetchUrl = filterWrap?.getAttribute('data-fetch-url') || '';
    const loadFailedMessage = filterWrap?.getAttribute('data-load-failed') || 'Failed to load recycle data.';
    const state = {
        page: Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || '1') || 1),
    };

    if (!filterWrap || !levelFilter || !actionFilter || !tableContainer || !fetchUrl) {
        return;
    }

    const setFeedback = (message, type = 'error') => {
        if (!feedback) {
            return;
        }
        feedback.hidden = false;
        feedback.classList.remove('success', 'error');
        feedback.classList.add(type === 'success' ? 'success' : 'error');
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

    const fetchRecycleTable = async (nextPage = null) => {
        if (typeof nextPage === 'number' && Number.isFinite(nextPage)) {
            state.page = Math.max(1, Math.trunc(nextPage));
        }

        const params = new URLSearchParams();
        params.set('level', String(levelFilter.value || 'all'));
        params.set('action', String(actionFilter.value || 'all'));
        params.set('page', String(state.page));

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
            throw new Error(payload?.message || loadFailedMessage);
        }

        tableContainer.innerHTML = payload?.html || '';
        state.page = Number(payload?.pagination?.page || state.page || 1);
    };

    const applyFilters = async (nextPage = null) => {
        clearFeedback();
        try {
            await fetchRecycleTable(nextPage);
        } catch (error) {
            setFeedback(error.message || loadFailedMessage, 'error');
        }
    };

    levelFilter.addEventListener('change', () => {
        state.page = 1;
        applyFilters(state.page);
    });
    actionFilter.addEventListener('change', () => {
        state.page = 1;
        applyFilters(state.page);
    });

    tableContainer.addEventListener('click', (event) => {
        const pageButton = event.target instanceof Element ? event.target.closest('[data-recycle-page]') : null;
        if (!pageButton) {
            return;
        }

        const nextPage = Number(pageButton.getAttribute('data-recycle-page') || '1');
        if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage === state.page) {
            return;
        }

        applyFilters(nextPage);
    });
})();
