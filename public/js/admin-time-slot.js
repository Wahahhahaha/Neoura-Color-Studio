(function () {
    const ANIMATION_MS = 240;
    const modal = document.querySelector('[data-walkin-modal]');
    const detailModal = document.querySelector('[data-walkin-detail-modal]');
    if (!modal && !detailModal) {
        return;
    }

    const createModalController = (targetModal) => {
        if (!targetModal) {
            return {
                open: () => {},
                close: () => {},
            };
        }

        let hideTimer = null;

        const open = () => {
            if (hideTimer) {
                window.clearTimeout(hideTimer);
                hideTimer = null;
            }
            targetModal.hidden = false;
            targetModal.classList.remove('is-leave');
            window.requestAnimationFrame(() => {
                targetModal.classList.add('is-enter');
            });
        };

        const close = () => {
            if (hideTimer) {
                window.clearTimeout(hideTimer);
                hideTimer = null;
            }
            targetModal.classList.remove('is-enter');
            targetModal.classList.add('is-leave');
            hideTimer = window.setTimeout(() => {
                targetModal.hidden = true;
                targetModal.classList.remove('is-leave');
                hideTimer = null;
            }, ANIMATION_MS);
        };

        return { open, close };
    };

    const walkInModal = createModalController(modal);
    const detailModalController = createModalController(detailModal);
    const form = modal ? modal.querySelector('.walkin-modal-form') : null;
    const feedback = modal ? modal.querySelector('[data-walkin-feedback]') : null;
    const submitBtn = modal ? modal.querySelector('[data-walkin-submit]') : null;
    const slotLabel = modal ? modal.querySelector('[data-walkin-slot-label]') : null;
    const inputDate = modal ? modal.querySelector('[data-walkin-date]') : null;
    const inputStart = modal ? modal.querySelector('[data-walkin-start]') : null;
    const detailSlot = detailModal ? detailModal.querySelector('[data-walkin-detail-slot]') : null;
    const detailName = detailModal ? detailModal.querySelector('[data-walkin-detail-name]') : null;
    const detailPackage = detailModal ? detailModal.querySelector('[data-walkin-detail-package]') : null;
    const detailNote = detailModal ? detailModal.querySelector('[data-walkin-detail-note]') : null;

    const setFeedback = (message) => {
        if (!feedback) {
            return;
        }

        const text = (message || '').trim();
        feedback.textContent = text;
        feedback.hidden = text === '';
    };

    const openWalkInModal = (date, start) => {
        setFeedback('');
        if (inputDate) inputDate.value = date || '';
        if (inputStart) inputStart.value = start || '';
        if (slotLabel) slotLabel.textContent = `${date || '-'} | ${start || '-'}`;
        walkInModal.open();
    };

    const closeWalkInModal = () => {
        walkInModal.close();
        window.setTimeout(() => {
            if (form) {
                form.reset();
            }
            setFeedback('');
        }, ANIMATION_MS);
    };

    const openWalkInDetailModal = (payload) => {
        if (detailSlot) {
            const date = payload.date || '-';
            const start = payload.start || '-';
            const end = payload.end || '-';
            detailSlot.textContent = `${date} | ${start} - ${end}`;
        }
        if (detailName) {
            detailName.value = payload.name || '-';
        }
        if (detailPackage) {
            detailPackage.value = payload.packageName || '-';
        }
        if (detailNote) {
            detailNote.value = payload.note || '-';
        }

        detailModalController.open();
    };

    document.addEventListener('click', (event) => {
        const openBtn = event.target.closest('[data-open-walkin-modal]');
        if (openBtn) {
            openWalkInModal(
                openBtn.getAttribute('data-slot-date') || '',
                openBtn.getAttribute('data-slot-start') || ''
            );
            return;
        }

        const openDetailBtn = event.target.closest('[data-open-walkin-detail-modal]');
        if (openDetailBtn) {
            openWalkInDetailModal({
                date: openDetailBtn.getAttribute('data-walkin-date') || '',
                start: openDetailBtn.getAttribute('data-walkin-start') || '',
                end: openDetailBtn.getAttribute('data-walkin-end') || '',
                name: openDetailBtn.getAttribute('data-walkin-name') || '',
                packageName: openDetailBtn.getAttribute('data-walkin-package') || '',
                note: openDetailBtn.getAttribute('data-walkin-note') || '',
            });
            return;
        }

        const closeBtn = event.target.closest('[data-close-walkin-modal]');
        if (closeBtn) {
            closeWalkInModal();
            return;
        }

        const closeDetailBtn = event.target.closest('[data-close-walkin-detail-modal]');
        if (closeDetailBtn) {
            detailModalController.close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (modal && !modal.hidden) {
                closeWalkInModal();
            }
            if (detailModal && !detailModal.hidden) {
                detailModalController.close();
            }
        }
    });

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setFeedback('');

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            try {
                const response = await window.fetch(form.action, {
                    method: 'POST',
                    body: new window.FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validationErrors = payload && payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().filter(Boolean)
                        : [];
                    const errorText = validationErrors.length > 0
                        ? validationErrors[0]
                        : (payload && payload.message ? payload.message : 'Failed to save walk-in.');
                    setFeedback(errorText);
                    return;
                }

                window.location.reload();
            } catch (error) {
                setFeedback('Network error. Please try again.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        });
    }

})();
