(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const serviceListRoot = document.querySelector('[data-service-list-root]');
    const serviceFetchUrl = serviceListRoot?.getAttribute('data-fetch-url') || '';
    const serviceState = {
        page: Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || '1') || 1),
    };

    const fetchServicePage = async (page) => {
        if (!serviceListRoot || !serviceFetchUrl) {
            return;
        }
        const params = new URLSearchParams();
        params.set('page', String(page));

        const response = await fetch(`${serviceFetchUrl}?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.status !== 'ok') {
            throw new Error(payload?.message || 'Failed to load service list.');
        }

        serviceListRoot.innerHTML = payload?.html || '';
        serviceState.page = Number(payload?.pagination?.page || page || 1);
    };

    const modal = document.querySelector('[data-service-modal]');

    if (modal) {
        const closeButtons = Array.from(modal.querySelectorAll('[data-close-service-modal]'));
        const saveForm = modal.querySelector('[data-service-save-form]');
        const deleteForm = modal.querySelector('[data-service-delete-form]');
        const nameInput = modal.querySelector('#service_name');
        const detailInput = modal.querySelector('#service_detail');
        const durationInput = modal.querySelector('#service_duration');
        const priceInput = modal.querySelector('#service_price');
        const descriptionsInput = modal.querySelector('#service_descriptions');
        const updateTemplate = modal.getAttribute('data-update-url-template') || '';
        const deleteTemplate = modal.getAttribute('data-delete-url-template') || '';

        const buildActionUrl = (template, serviceId) => template.replace('__SERVICE_ID__', String(serviceId));
        const parseDescriptions = (raw) => {
            if (!raw) {
                return '';
            }

            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    return parsed.join('\n');
                }
                if (typeof parsed === 'string') {
                    return parsed.replace(/\\n/g, '\n');
                }
            } catch (_) {
                // Fallback for previously stored plain strings.
            }

            return String(raw).replace(/\\n/g, '\n');
        };

        const openModal = () => {
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        };

        const closeModal = () => {
            modal.hidden = true;
            document.body.style.overflow = '';
        };

        document.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-open-service-modal]') : null;
            if (!button) {
                return;
            }

            const serviceId = button.getAttribute('data-service-id') || '';
            if (!serviceId || !saveForm || !deleteForm) {
                return;
            }

            saveForm.action = buildActionUrl(updateTemplate, serviceId);
            deleteForm.action = buildActionUrl(deleteTemplate, serviceId);

            if (nameInput) {
                nameInput.value = button.getAttribute('data-service-name') || '';
            }
            if (detailInput) {
                detailInput.value = button.getAttribute('data-service-detail') || '';
            }
            if (durationInput) {
                durationInput.value = button.getAttribute('data-service-duration') || '';
            }
            if (priceInput) {
                priceInput.value = button.getAttribute('data-service-price') || '';
            }
            if (descriptionsInput) {
                descriptionsInput.value = parseDescriptions(button.getAttribute('data-service-descriptions') || '');
            }

            openModal();
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });
    }

    const addModal = document.querySelector('[data-add-service-modal]');
    if (!addModal) {
        return;
    }

    const openAddButton = document.querySelector('[data-open-add-service-modal]');
    const closeAddButtons = Array.from(addModal.querySelectorAll('[data-close-add-service-modal]'));
    const addForm = addModal.querySelector('[data-add-service-form]');
    const addSaveBtn = addModal.querySelector('[data-add-service-save]');
    const addFeedback = addModal.querySelector('[data-add-service-feedback]');
    const storeUrl = addModal.getAttribute('data-store-url') || '';

    const setAddFeedback = (type, text) => {
        if (!addFeedback) {
            return;
        }
        addFeedback.hidden = false;
        addFeedback.classList.remove('success', 'error');
        addFeedback.classList.add(type === 'success' ? 'success' : 'error');
        addFeedback.textContent = text;
    };

    const clearAddFeedback = () => {
        if (!addFeedback) {
            return;
        }
        addFeedback.hidden = true;
        addFeedback.classList.remove('success', 'error');
        addFeedback.textContent = '';
    };

    const openAddModal = () => {
        clearAddFeedback();
        addModal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const closeAddModal = () => {
        addModal.hidden = true;
        document.body.style.overflow = '';
        addForm?.reset();
        clearAddFeedback();
    };

    openAddButton?.addEventListener('click', openAddModal);
    closeAddButtons.forEach((button) => button.addEventListener('click', closeAddModal));

    addForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearAddFeedback();
        if (!storeUrl || !addForm) {
            return;
        }

        if (addSaveBtn) {
            addSaveBtn.disabled = true;
        }

        try {
            const formData = new FormData(addForm);
            const response = await fetch(storeUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : 'Failed to add service.';
                setAddFeedback('error', errors);
                return;
            }

            setAddFeedback('success', payload?.message || 'Service added.');
            window.setTimeout(async () => {
                try {
                    await fetchServicePage(1);
                    closeAddModal();
                } catch (_) {
                    window.location.reload();
                }
            }, 500);
        } catch (_) {
            setAddFeedback('error', 'Network error while adding service.');
        } finally {
            if (addSaveBtn) {
                addSaveBtn.disabled = false;
            }
        }
    });

    serviceListRoot?.addEventListener('click', async (event) => {
        const pageButton = event.target instanceof Element ? event.target.closest('[data-service-page]') : null;
        if (!pageButton) {
            return;
        }

        const nextPage = Number(pageButton.getAttribute('data-service-page') || '1');
        if (!Number.isFinite(nextPage) || nextPage < 1 || nextPage === serviceState.page) {
            return;
        }

        try {
            await fetchServicePage(nextPage);
        } catch (_) {
            // Keep current content when ajax pagination fails.
        }
    });
})();
