(() => {
    const firstErrorMessage = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return '';
        }
        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }
        if (payload.errors && typeof payload.errors === 'object') {
            const firstKey = Object.keys(payload.errors)[0];
            const firstList = payload.errors[firstKey];
            if (Array.isArray(firstList) && firstList.length > 0) {
                return String(firstList[0] || '').trim();
            }
            if (typeof firstList === 'string' && firstList.trim() !== '') {
                return firstList.trim();
            }
        }
        return '';
    };

    const parsePayload = async (response) => {
        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');
        if (!isJson) {
            return {
                isJson: false,
                payload: {},
            };
        }
        return {
            isJson: true,
            payload: await response.json().catch(() => ({})),
        };
    };

    const forgotEmailForm = document.querySelector('[data-forgot-email-form]');
    const forgotEmailFeedback = document.querySelector('[data-forgot-email-feedback]');
    if (forgotEmailForm) {
        let isSubmitting = false;
        const submitButton = forgotEmailForm.querySelector('button[type="submit"]');

        const setEmailFeedback = (message = '', type = 'error') => {
            if (!forgotEmailFeedback) {
                return;
            }

            const text = String(message || '').trim();
            if (!text) {
                forgotEmailFeedback.hidden = true;
                forgotEmailFeedback.textContent = '';
                forgotEmailFeedback.classList.remove('success');
                return;
            }

            forgotEmailFeedback.hidden = false;
            forgotEmailFeedback.textContent = text;
            forgotEmailFeedback.classList.toggle('success', type === 'success');
        };

        forgotEmailForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (isSubmitting) {
                return;
            }

            setEmailFeedback('');
            isSubmitting = true;
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = true;
            }

            try {
                const response = await fetch(forgotEmailForm.action, {
                    method: 'POST',
                    body: new FormData(forgotEmailForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });

                const { isJson, payload } = await parsePayload(response);
                if (!response.ok || !isJson) {
                    setEmailFeedback(firstErrorMessage(payload) || 'Request failed.', 'error');
                    return;
                }

                setEmailFeedback(
                    (typeof payload?.message === 'string' && payload.message.trim() !== '' ? payload.message : 'Request sent.'),
                    'success'
                );
            } catch (_) {
                setEmailFeedback('Unable to submit request. Please try again.', 'error');
            } finally {
                if (submitButton instanceof HTMLButtonElement) {
                    submitButton.disabled = false;
                }
                isSubmitting = false;
            }
        });
    }

    const forgotPhoneFeedback = document.querySelector('[data-forgot-phone-feedback]');
    const forgotPhoneSendForm = document.querySelector('[data-forgot-phone-send-form]');
    const forgotPhoneVerifyForm = document.querySelector('[data-forgot-phone-verify-form]');
    const setPhoneFeedback = (message = '', type = 'error') => {
        if (!forgotPhoneFeedback) {
            return;
        }

        const text = String(message || '').trim();
        if (!text) {
            forgotPhoneFeedback.hidden = true;
            forgotPhoneFeedback.textContent = '';
            forgotPhoneFeedback.classList.remove('success');
            return;
        }

        forgotPhoneFeedback.hidden = false;
        forgotPhoneFeedback.textContent = text;
        forgotPhoneFeedback.classList.toggle('success', type === 'success');
    };

    const ajaxSubmit = async (form, fallbackError = 'Request failed.') => {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        const { isJson, payload } = await parsePayload(response);
        if (!response.ok || !isJson) {
            throw new Error(firstErrorMessage(payload) || fallbackError);
        }
        return payload;
    };

    if (forgotPhoneSendForm) {
        let sendingOtp = false;
        const sendButton = forgotPhoneSendForm.querySelector('button[type="submit"]');
        forgotPhoneSendForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (sendingOtp) {
                return;
            }

            sendingOtp = true;
            setPhoneFeedback('');
            if (sendButton instanceof HTMLButtonElement) {
                sendButton.disabled = true;
            }

            try {
                const payload = await ajaxSubmit(forgotPhoneSendForm, 'Failed to send OTP.');
                setPhoneFeedback(
                    (typeof payload?.message === 'string' && payload.message.trim() !== '' ? payload.message : 'OTP sent.'),
                    'success'
                );
                const redirectUrl = (typeof payload?.redirect === 'string' && payload.redirect.trim() !== '')
                    ? payload.redirect
                    : window.location.href;
                window.location.href = redirectUrl;
            } catch (error) {
                setPhoneFeedback(error.message || 'Failed to send OTP.', 'error');
            } finally {
                if (sendButton instanceof HTMLButtonElement) {
                    sendButton.disabled = false;
                }
                sendingOtp = false;
            }
        });
    }

    if (forgotPhoneVerifyForm) {
        let verifyingOtp = false;
        const verifyButton = forgotPhoneVerifyForm.querySelector('button[type="submit"]');
        forgotPhoneVerifyForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (verifyingOtp) {
                return;
            }

            verifyingOtp = true;
            setPhoneFeedback('');
            if (verifyButton instanceof HTMLButtonElement) {
                verifyButton.disabled = true;
            }

            try {
                const payload = await ajaxSubmit(forgotPhoneVerifyForm, 'OTP verification failed.');
                const redirectUrl = (typeof payload?.redirect === 'string' && payload.redirect.trim() !== '')
                    ? payload.redirect
                    : window.location.href;
                window.location.href = redirectUrl;
            } catch (error) {
                setPhoneFeedback(error.message || 'OTP verification failed.', 'error');
            } finally {
                if (verifyButton instanceof HTMLButtonElement) {
                    verifyButton.disabled = false;
                }
                verifyingOtp = false;
            }
        });
    }

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
