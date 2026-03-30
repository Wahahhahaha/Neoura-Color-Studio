(() => {
    const filter = document.querySelector('[data-payment-bank-filter]');
    const statusFilterWrap = document.querySelector('[data-payment-status-filter]');
    const listRoot = document.querySelector('[data-payment-list-root]');
    const fetchUrl = listRoot?.getAttribute('data-fetch-url') || '';
    if (!filter || !statusFilterWrap || !listRoot || !fetchUrl) {
        return;
    }

    const currentUrl = new URL(window.location.href);
    const statusFromUrl = (currentUrl.searchParams.get('status') || '').toLowerCase();
    const validStatuses = new Set(['', 'pending', 'approved', 'rejected']);
    const state = {
        page: Math.max(1, Number(currentUrl.searchParams.get('page') || '1') || 1),
        status: validStatuses.has(statusFromUrl) ? statusFromUrl : '',
    };

    const setActiveStatusButton = () => {
        const buttons = statusFilterWrap.querySelectorAll('[data-payment-status]');
        buttons.forEach((button) => {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            const value = (button.getAttribute('data-payment-status') || '').toLowerCase();
            button.classList.toggle('is-active', value === state.status);
        });
    };

    setActiveStatusButton();

    const loadPayments = async (nextPage = null) => {
        if (typeof nextPage === 'number' && Number.isFinite(nextPage)) {
            state.page = Math.max(1, Math.trunc(nextPage));
        }

        const url = new URL(fetchUrl, window.location.origin);
        const bank = filter.value || '';
        if (bank) {
            url.searchParams.set('bank', bank);
        } else {
            url.searchParams.delete('bank');
        }
        if (state.status) {
            url.searchParams.set('status', state.status);
        } else {
            url.searchParams.delete('status');
        }
        url.searchParams.set('page', String(state.page));

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (!payload?.html) {
                return;
            }

            listRoot.innerHTML = payload.html;
            state.page = Number(payload?.pagination?.page || state.page || 1);
            const historyUrl = new URL(window.location.href);
            if (bank) {
                historyUrl.searchParams.set('bank', bank);
            } else {
                historyUrl.searchParams.delete('bank');
            }
            if (state.status) {
                historyUrl.searchParams.set('status', state.status);
            } else {
                historyUrl.searchParams.delete('status');
            }
            historyUrl.searchParams.set('page', String(state.page));
            window.history.replaceState({}, '', historyUrl.toString());
        } catch (_) {
            // Keep existing content when ajax fails.
        }
    };

    filter.addEventListener('change', () => {
        state.page = 1;
        loadPayments(state.page);
    });

    statusFilterWrap.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-payment-status]') : null;
        if (!target) {
            return;
        }

        const nextStatus = (target.getAttribute('data-payment-status') || '').toLowerCase();
        if (!validStatuses.has(nextStatus) || nextStatus === state.status) {
            return;
        }

        state.status = nextStatus;
        state.page = 1;
        setActiveStatusButton();
        loadPayments(state.page);
    });

    listRoot.addEventListener('click', (event) => {
        const pageButton = event.target instanceof Element ? event.target.closest('[data-payment-page]') : null;
        if (!pageButton) {
            return;
        }

        const nextPage = Number(pageButton.getAttribute('data-payment-page') || '1');
        if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage === state.page) {
            return;
        }

        loadPayments(nextPage);
    });
})();
