(() => {
    const popupModal = document.querySelector('[data-forgot-popup-modal]');
    if (!popupModal) {
        return;
    }

    const popupText = popupModal.querySelector('[data-forgot-popup-text]');
    const closeNodes = Array.from(popupModal.querySelectorAll('[data-close-forgot-popup]'));
    const message = (popupModal.getAttribute('data-popup-message') || '').trim();
    let closeTimer = null;

    const syncBodyScrollLock = () => {
        const hasOpenModal = Boolean(document.querySelector('.crop-modal:not([hidden])'));
        document.body.style.overflow = hasOpenModal ? 'hidden' : '';
    };

    const openPopup = (text) => {
        if (!text) {
            return;
        }
        if (closeTimer) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }

        if (popupText) {
            popupText.textContent = text;
        }

        popupModal.hidden = false;
        popupModal.classList.remove('is-leave');
        popupModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => popupModal.classList.add('is-enter'));
        syncBodyScrollLock();
    };

    const closePopup = () => {
        if (popupModal.hidden) {
            return;
        }

        popupModal.classList.remove('is-enter');
        popupModal.classList.add('is-leave');
        if (closeTimer) {
            window.clearTimeout(closeTimer);
        }
        closeTimer = window.setTimeout(() => {
            popupModal.hidden = true;
            popupModal.classList.remove('is-leave');
            syncBodyScrollLock();
            closeTimer = null;
        }, 240);
    };

    closeNodes.forEach((node) => node.addEventListener('click', closePopup));
    openPopup(message);
})();
