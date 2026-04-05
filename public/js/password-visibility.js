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
        if (contentType.includes('application/json')) {
            return response.json().catch(() => ({}));
        }
        return {};
    };

    const toggleButtons = Array.from(document.querySelectorAll('[data-toggle-password]'));
    if (!toggleButtons.length) {
        return;
    }

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetSelector = button.getAttribute('data-target') || '';
            const input = targetSelector ? document.querySelector(targetSelector) : null;
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const nextType = input.type === 'password' ? 'text' : 'password';
            input.type = nextType;

            const isVisible = nextType === 'text';
            button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
            button.setAttribute('title', isVisible ? 'Hide password' : 'Show password');
        });
    });

    const resetPasswordForm = document.querySelector('[data-reset-password-form]');
    const resetFeedbackNode = document.querySelector('[data-reset-password-feedback]');
    if (resetPasswordForm) {
        let isResetSubmitting = false;
        const resetSubmitButton = resetPasswordForm.querySelector('button[type="submit"]');

        const setResetFeedback = (message = '', type = 'error') => {
            if (!resetFeedbackNode) {
                return;
            }
            const text = String(message || '').trim();
            if (!text) {
                resetFeedbackNode.hidden = true;
                resetFeedbackNode.textContent = '';
                resetFeedbackNode.classList.remove('success');
                return;
            }
            resetFeedbackNode.hidden = false;
            resetFeedbackNode.textContent = text;
            resetFeedbackNode.classList.toggle('success', type === 'success');
        };

        resetPasswordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (isResetSubmitting) {
                return;
            }

            setResetFeedback('');
            isResetSubmitting = true;
            if (resetSubmitButton instanceof HTMLButtonElement) {
                resetSubmitButton.disabled = true;
            }

            try {
                const response = await fetch(resetPasswordForm.action, {
                    method: 'POST',
                    body: new FormData(resetPasswordForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
                const payload = await parsePayload(response);

                if (!response.ok) {
                    setResetFeedback(firstErrorMessage(payload) || 'Failed to reset password.', 'error');
                    return;
                }

                const redirectUrl = (payload && typeof payload.redirect === 'string' && payload.redirect.trim() !== '')
                    ? payload.redirect
                    : '/login';
                window.location.href = redirectUrl;
            } catch (_) {
                setResetFeedback('Unable to reset password. Please try again.', 'error');
            } finally {
                if (resetSubmitButton instanceof HTMLButtonElement) {
                    resetSubmitButton.disabled = false;
                }
                isResetSubmitting = false;
            }
        });
    }

    const loginForm = document.querySelector('[data-login-form]');
    if (!loginForm) {
        return;
    }

    const latitudeInput = loginForm.querySelector('[data-login-latitude]');
    const longitudeInput = loginForm.querySelector('[data-login-longitude]');
    const captchaRoot = loginForm.querySelector('[data-login-captcha-root]');
    const captchaModeInput = captchaRoot?.querySelector('[data-captcha-mode]');
    const onlineCaptchaBox = captchaRoot?.querySelector('[data-captcha-online]');
    const offlineCaptchaBox = captchaRoot?.querySelector('[data-captcha-offline]');
    const recaptchaWidgetNode = captchaRoot?.querySelector('[data-recaptcha-widget]');
    const captchaErrorNode = captchaRoot?.querySelector('[data-captcha-error]');
    const recaptchaSiteKey = (captchaRoot?.getAttribute('data-recaptcha-site-key') || '').trim();
    const loginFeedbackNode = document.querySelector('[data-login-feedback]');
    const loginPopupModal = document.querySelector('[data-login-popup-modal]');
    const loginPopupText = loginPopupModal?.querySelector('[data-login-popup-text]') || null;
    const loginPopupCloseNodes = loginPopupModal ? Array.from(loginPopupModal.querySelectorAll('[data-close-login-popup]')) : [];
    const loginPopupInitialMessage = (loginPopupModal?.getAttribute('data-popup-message') || '').trim();
    let isSubmitting = false;
    let recaptchaWidgetId = null;
    let recaptchaLoaded = false;
    let loginPopupCloseTimer = null;

    const setLoginFeedback = (message = '', type = 'error') => {
        if (!loginFeedbackNode) {
            return;
        }

        const text = String(message || '').trim();
        if (!text) {
            loginFeedbackNode.hidden = true;
            loginFeedbackNode.textContent = '';
            loginFeedbackNode.classList.remove('success');
            return;
        }

        loginFeedbackNode.hidden = false;
        loginFeedbackNode.textContent = text;
        loginFeedbackNode.classList.toggle('success', type === 'success');
    };

    const fetchWithGeolocation = () => new Promise((resolve) => {
        if (!('geolocation' in navigator)) {
            resolve();
            return;
        }

        navigator.geolocation.getCurrentPosition((position) => {
            if (latitudeInput) {
                latitudeInput.value = String(position?.coords?.latitude ?? '');
            }
            if (longitudeInput) {
                longitudeInput.value = String(position?.coords?.longitude ?? '');
            }
            resolve();
        }, () => {
            resolve();
        }, {
            enableHighAccuracy: false,
            timeout: 8000,
            maximumAge: 0,
        });
    });

    const setCaptchaError = (message = '') => {
        if (!captchaErrorNode) {
            return;
        }
        if (!message) {
            captchaErrorNode.hidden = true;
            captchaErrorNode.textContent = '';
            return;
        }
        captchaErrorNode.hidden = false;
        captchaErrorNode.textContent = message;
    };

    const setCaptchaMode = (mode) => {
        const normalizedMode = mode === 'online' ? 'online' : 'offline';
        if (captchaModeInput) {
            captchaModeInput.value = normalizedMode;
        }
        if (onlineCaptchaBox) {
            onlineCaptchaBox.hidden = normalizedMode !== 'online';
        }
        if (offlineCaptchaBox) {
            offlineCaptchaBox.hidden = normalizedMode !== 'offline';
        }
    };

    const ensureRecaptchaWidget = () => {
        if (!recaptchaWidgetNode || !recaptchaSiteKey || !window.grecaptcha || typeof window.grecaptcha.render !== 'function') {
            return false;
        }

        if (recaptchaWidgetId === null) {
            recaptchaWidgetId = window.grecaptcha.render(recaptchaWidgetNode, {
                sitekey: recaptchaSiteKey,
                theme: 'light',
            });
        }

        return true;
    };

    const updateCaptchaAvailability = () => {
        if (!captchaRoot) {
            return;
        }

        const canUseOnline = navigator.onLine && recaptchaSiteKey !== '' && recaptchaLoaded && ensureRecaptchaWidget();
        setCaptchaMode(canUseOnline ? 'online' : 'offline');
        setCaptchaError('');
    };

    if (captchaRoot) {
        const initialMode = (captchaModeInput?.value || '').toLowerCase() === 'online' ? 'online' : 'offline';
        setCaptchaMode(initialMode);

        window.onRecaptchaLoaded = () => {
            recaptchaLoaded = true;
            updateCaptchaAvailability();
        };

        if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
            recaptchaLoaded = true;
        }

        updateCaptchaAvailability();
        window.addEventListener('online', updateCaptchaAvailability);
        window.addEventListener('offline', updateCaptchaAvailability);
    }

    const syncBodyScrollLock = () => {
        const hasOpenModal = Boolean(document.querySelector('.crop-modal:not([hidden])'));
        document.body.style.overflow = hasOpenModal ? 'hidden' : '';
    };

    const closeLoginPopup = () => {
        if (!loginPopupModal || loginPopupModal.hidden) {
            return;
        }

        loginPopupModal.classList.remove('is-enter');
        loginPopupModal.classList.add('is-leave');
        if (loginPopupCloseTimer) {
            window.clearTimeout(loginPopupCloseTimer);
        }
        loginPopupCloseTimer = window.setTimeout(() => {
            loginPopupModal.hidden = true;
            loginPopupModal.classList.remove('is-leave');
            syncBodyScrollLock();
            loginPopupCloseTimer = null;
        }, 260);
    };

    const openLoginPopup = (message) => {
        const text = String(message || '').trim();
        if (!loginPopupModal || text === '') {
            return false;
        }

        if (loginPopupCloseTimer) {
            window.clearTimeout(loginPopupCloseTimer);
            loginPopupCloseTimer = null;
        }

        if (loginPopupText) {
            loginPopupText.textContent = text;
        }

        loginPopupModal.hidden = false;
        loginPopupModal.classList.remove('is-leave');
        loginPopupModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => loginPopupModal.classList.add('is-enter'));
        syncBodyScrollLock();
        return true;
    };

    loginPopupCloseNodes.forEach((node) => node.addEventListener('click', closeLoginPopup));
    openLoginPopup(loginPopupInitialMessage);

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isSubmitting) {
            return;
        }

        setLoginFeedback('');

        if (captchaRoot) {
            const mode = (captchaModeInput?.value || 'offline').toLowerCase();
            if (mode === 'online') {
                const hasWidget = ensureRecaptchaWidget();
                const token = hasWidget && window.grecaptcha && recaptchaWidgetId !== null
                    ? window.grecaptcha.getResponse(recaptchaWidgetId)
                    : '';
                if (!token) {
                    setCaptchaError('Please complete online captcha first.');
                    return;
                }
            } else {
                const offlineInput = captchaRoot.querySelector('#offline_captcha_answer');
                const offlineAnswer = (offlineInput?.value || '').trim();
                if (!offlineAnswer) {
                    setCaptchaError('Please answer offline captcha question.');
                    return;
                }
            }
        }

        await fetchWithGeolocation();

        const submitButton = loginForm.querySelector('button[type="submit"]');
        try {
            isSubmitting = true;
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = true;
            }

            const formData = new FormData(loginForm);
            const response = await fetch(loginForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const payload = await parsePayload(response);

            if (response.ok) {
                setCaptchaError('');
                const redirectUrl = (payload && typeof payload.redirect === 'string' && payload.redirect.trim() !== '')
                    ? payload.redirect
                    : '/';
                window.location.href = redirectUrl;
                return;
            }

            const message = firstErrorMessage(payload) || 'Login failed.';
            if (payload?.errors?.captcha) {
                const captchaMessage = Array.isArray(payload.errors.captcha)
                    ? String(payload.errors.captcha[0] || message)
                    : String(payload.errors.captcha || message);
                setCaptchaError(captchaMessage);
            } else {
                setCaptchaError('');
            }
            if (!openLoginPopup(message)) {
                setLoginFeedback(message, 'error');
            }
        } catch (_) {
            setCaptchaError('');
            const fallbackMessage = 'Unable to submit login. Please try again.';
            if (!openLoginPopup(fallbackMessage)) {
                setLoginFeedback(fallbackMessage, 'error');
            }
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
            }
            isSubmitting = false;
        }
    });
})();
