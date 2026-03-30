(() => {
    const filter = document.querySelector('[data-payment-bank-filter]');
    const listRoot = document.querySelector('[data-payment-list-root]');
    const fetchUrl = listRoot?.getAttribute('data-fetch-url') || '';
    if (!filter || !listRoot || !fetchUrl) {
        return;
    }

    const currentUrl = new URL(window.location.href);
    const state = {
        page: Math.max(1, Number(currentUrl.searchParams.get('page') || '1') || 1),
    };

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
            const historyUrl = new URL(currentUrl.toString());
            if (bank) {
                historyUrl.searchParams.set('bank', bank);
            } else {
                historyUrl.searchParams.delete('bank');
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
