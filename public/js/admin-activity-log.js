(() => {
    const wrap = document.querySelector('[data-activitylog-table-wrap]');
    const feedback = document.querySelector('[data-activitylog-feedback]');
    const levelFilter = document.querySelector('[data-activitylog-level-filter]');
    const fetchUrl = wrap?.getAttribute('data-fetch-url') || '';
    const loadFailedMessage = wrap?.getAttribute('data-load-failed') || 'Failed to load activity log.';

    if (!wrap || !fetchUrl) {
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const initialLevel = (urlParams.get('level') || '').trim().toLowerCase();
    const selectedLevel = levelFilter ? (levelFilter.value || '0').trim().toLowerCase() : '0';
    const state = {
        page: Math.max(1, Number(urlParams.get('page') || '1') || 1),
        level: initialLevel !== '' ? initialLevel : selectedLevel,
    };
    if (levelFilter && state.level !== '') {
        levelFilter.value = state.level;
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

    const fetchPage = async (page) => {
        const params = new URLSearchParams();
        params.set('page', String(page));
        if (state.level && state.level !== '0') {
            params.set('level', state.level);
        }

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

        wrap.innerHTML = payload?.html || '';
        state.page = Number(payload?.pagination?.page || page || 1);
        if (payload?.filters?.level) {
            state.level = String(payload.filters.level);
            if (levelFilter) {
                levelFilter.value = state.level;
            }
        }
    };

    wrap.addEventListener('click', async (event) => {
        const target = event.target instanceof Element ? event.target.closest('[data-activitylog-page]') : null;
        if (!target) {
            return;
        }

        const nextPage = Number(target.getAttribute('data-activitylog-page') || '1');
        if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage === state.page) {
            return;
        }

        try {
            clearFeedback();
            await fetchPage(nextPage);
        } catch (error) {
            setFeedback(error.message || loadFailedMessage, 'error');
        }
    });

    if (levelFilter) {
        levelFilter.addEventListener('change', async () => {
            const nextLevel = (levelFilter.value || '0').trim().toLowerCase();
            if (nextLevel === state.level) {
                return;
            }

            state.level = nextLevel;
            try {
                clearFeedback();
                await fetchPage(1);
            } catch (error) {
                setFeedback(error.message || loadFailedMessage, 'error');
            }
        });
    }
})();
