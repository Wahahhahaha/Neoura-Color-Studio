(() => {
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
    let submittingWithGeoResolved = false;
    let recaptchaWidgetId = null;
    let recaptchaLoaded = false;

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

    loginForm.addEventListener('submit', (event) => {
        if (submittingWithGeoResolved) {
            return;
        }

        if (captchaRoot) {
            const mode = (captchaModeInput?.value || 'offline').toLowerCase();
            if (mode === 'online') {
                const hasWidget = ensureRecaptchaWidget();
                const token = hasWidget && window.grecaptcha && recaptchaWidgetId !== null
                    ? window.grecaptcha.getResponse(recaptchaWidgetId)
                    : '';
                if (!token) {
                    event.preventDefault();
                    setCaptchaError('Please complete online captcha first.');
                    return;
                }
            } else {
                const offlineInput = captchaRoot.querySelector('#offline_captcha_answer');
                const offlineAnswer = (offlineInput?.value || '').trim();
                if (!offlineAnswer) {
                    event.preventDefault();
                    setCaptchaError('Please answer offline captcha question.');
                    return;
                }
            }
        }

        const continueSubmit = () => {
            submittingWithGeoResolved = true;
            loginForm.submit();
        };

        if (!('geolocation' in navigator)) {
            return;
        }

        event.preventDefault();
        navigator.geolocation.getCurrentPosition((position) => {
            if (latitudeInput) {
                latitudeInput.value = String(position?.coords?.latitude ?? '');
            }
            if (longitudeInput) {
                longitudeInput.value = String(position?.coords?.longitude ?? '');
            }
            continueSubmit();
        }, () => {
            continueSubmit();
        }, {
            enableHighAccuracy: false,
            timeout: 8000,
            maximumAge: 0,
        });
    });
})();
