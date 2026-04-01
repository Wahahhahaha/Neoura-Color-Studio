(() => {
    const form = document.querySelector('[data-phone-otp-form]');
    if (!form) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const nameInput = form.querySelector('#name');
    const phoneInput = form.querySelector('#phonenumber');
    const emailInput = form.querySelector('#email');
    const feedback = form.querySelector('[data-phone-otp-feedback]');
    const saveBtn = form.querySelector('button[type="submit"]');
    const sidebarAdminName = document.querySelector('[data-sidebar-admin-name]');
    const passwordToggleButtons = Array.from(form.querySelectorAll('[data-toggle-password]'));

    const updateUrl = form.getAttribute('data-account-update-url') || form.getAttribute('action') || '';
    const sendOtpUrl = form.getAttribute('data-otp-send-url') || '';
    const verifyOtpUrl = form.getAttribute('data-otp-verify-url') || '';

    let initialPhone = (form.getAttribute('data-initial-phone') || '').trim();
    let initialEmail = (form.getAttribute('data-initial-email') || '').trim().toLowerCase();
    let pendingFormData = null;
    let pendingEmailChangeNotice = false;
    let isSubmitting = false;

    const otpModal = document.querySelector('[data-account-otp-modal]');
    const otpInput = otpModal?.querySelector('[data-account-otp-input]');
    const otpError = otpModal?.querySelector('[data-account-otp-error]');
    const otpPhoneInfo = otpModal?.querySelector('[data-account-otp-phone-info]');
    const confirmOtpBtn = otpModal?.querySelector('[data-confirm-account-otp]');
    const resendOtpBtn = otpModal?.querySelector('[data-resend-account-otp]');
    const closeOtpNodes = otpModal ? Array.from(otpModal.querySelectorAll('[data-close-account-otp]')) : [];

    const emailNoticeModal = document.querySelector('[data-account-email-notice-modal]');
    const emailNoticeText = emailNoticeModal?.querySelector('[data-account-email-notice-text]');
    const closeEmailNoticeNodes = emailNoticeModal
        ? Array.from(emailNoticeModal.querySelectorAll('[data-close-account-email-notice]'))
        : [];
    let emailNoticeCloseTimer = null;

    const syncBodyScrollLock = () => {
        const hasOpenModal = Boolean(document.querySelector('.crop-modal:not([hidden])'));
        document.body.style.overflow = hasOpenModal ? 'hidden' : '';
    };

    const isEmailChangedFromInitial = () => {
        const currentEmail = (emailInput?.value || '').trim().toLowerCase();
        return currentEmail !== '' && currentEmail !== initialEmail;
    };

    const setFeedback = (message, isError = false) => {
        if (!feedback) {
            return;
        }
        feedback.textContent = message;
        feedback.className = isError ? 'setting-alert error' : 'setting-alert success';
    };

    const setSaveButtonState = (disabled) => {
        if (!saveBtn) {
            return;
        }
        saveBtn.disabled = disabled;
        saveBtn.textContent = disabled ? 'Processing...' : 'Save Account';
    };

    const syncSidebarAdminName = () => {
        if (!sidebarAdminName) {
            return;
        }

        const nextName = (nameInput?.value || '').trim() || 'Admin';
        sidebarAdminName.textContent = nextName;
    };

    const bindPasswordVisibilityToggle = () => {
        if (!passwordToggleButtons.length) {
            return;
        }

        passwordToggleButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetSelector = button.getAttribute('data-target') || '';
                const input = targetSelector ? form.querySelector(targetSelector) : null;
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
    };

    const clearOtpError = () => {
        if (!otpError) {
            return;
        }
        otpError.hidden = true;
        otpError.textContent = '';
    };

    const setOtpError = (message) => {
        if (!otpError) {
            return;
        }
        otpError.hidden = false;
        otpError.textContent = message;
    };

    const openOtpModal = (phone) => {
        clearOtpError();
        if (!otpModal) {
            return;
        }
        if (otpInput) {
            otpInput.value = '';
        }
        if (otpPhoneInfo) {
            otpPhoneInfo.textContent = `OTP has been sent to ${phone}.`;
        }
        otpModal.hidden = false;
        otpModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => otpModal.classList.add('is-enter'));
        document.body.style.overflow = 'hidden';
        otpInput?.focus();
    };

    const closeOtpModal = () => {
        if (!otpModal) {
            return;
        }
        otpModal.hidden = true;
        otpModal.classList.remove('is-enter');
        syncBodyScrollLock();
        clearOtpError();
    };

    const openEmailNoticeModal = (message) => {
        if (!emailNoticeModal) {
            return;
        }

        if (emailNoticeCloseTimer) {
            window.clearTimeout(emailNoticeCloseTimer);
            emailNoticeCloseTimer = null;
        }

        if (emailNoticeText) {
            emailNoticeText.textContent = message || 'Your account update has been saved. Please verify your new email using the link we sent.';
        }

        emailNoticeModal.hidden = false;
        emailNoticeModal.classList.remove('is-leave');
        emailNoticeModal.classList.remove('is-enter');
        window.requestAnimationFrame(() => emailNoticeModal.classList.add('is-enter'));
        syncBodyScrollLock();
    };

    const closeEmailNoticeModal = () => {
        if (!emailNoticeModal || emailNoticeModal.hidden) {
            return;
        }

        emailNoticeModal.classList.remove('is-enter');
        emailNoticeModal.classList.add('is-leave');

        if (emailNoticeCloseTimer) {
            window.clearTimeout(emailNoticeCloseTimer);
        }
        emailNoticeCloseTimer = window.setTimeout(() => {
            emailNoticeModal.hidden = true;
            emailNoticeModal.classList.remove('is-leave');
            syncBodyScrollLock();
            emailNoticeCloseTimer = null;
        }, 240);
    };

    const parseResponse = async (response) => {
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, body };
    };

    const postJson = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        return parseResponse(response);
    };

    const postForm = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: payload,
        });

        return parseResponse(response);
    };

    const firstErrorMessage = (body, fallback) => {
        if (body?.message && typeof body.message === 'string') {
            return body.message;
        }
        if (body?.errors && typeof body.errors === 'object') {
            const firstKey = Object.keys(body.errors)[0];
            const firstList = body.errors[firstKey];
            if (Array.isArray(firstList) && firstList[0]) {
                return String(firstList[0]);
            }
        }
        return fallback;
    };

    const sendOtp = async (phone) => {
        const { ok, body } = await postJson(sendOtpUrl, { phonenumber: phone });
        if (!ok) {
            throw new Error(firstErrorMessage(body, 'Failed to send OTP.'));
        }
        return body;
    };

    const verifyOtp = async (phone, otp) => {
        const { ok, body } = await postJson(verifyOtpUrl, {
            phonenumber: phone,
            otp_code: otp,
        });
        if (!ok) {
            throw new Error(firstErrorMessage(body, 'OTP verification failed.'));
        }
        return body;
    };

    const submitAccountUpdate = async (formData) => {
        const { ok, body } = await postForm(updateUrl, formData);
        if (!ok) {
            throw new Error(firstErrorMessage(body, 'Failed to update account.'));
        }
        return body;
    };

    closeOtpNodes.forEach((node) => node.addEventListener('click', closeOtpModal));
    closeEmailNoticeNodes.forEach((node) => node.addEventListener('click', closeEmailNoticeModal));
    bindPasswordVisibilityToggle();

    resendOtpBtn?.addEventListener('click', async () => {
        const phone = (phoneInput?.value || '').trim();
        if (!phone) {
            setOtpError('Phone number is required.');
            return;
        }

        resendOtpBtn.disabled = true;
        try {
            await sendOtp(phone);
            clearOtpError();
            setFeedback('OTP resent successfully.');
        } catch (error) {
            setOtpError(error.message || 'Failed to resend OTP.');
        } finally {
            resendOtpBtn.disabled = false;
        }
    });

    confirmOtpBtn?.addEventListener('click', async () => {
        const phone = (phoneInput?.value || '').trim();
        const otp = (otpInput?.value || '').trim();
        if (!/^\d{6}$/.test(otp)) {
            setOtpError('OTP must be exactly 6 digits.');
            otpInput?.focus();
            return;
        }

        if (!(pendingFormData instanceof FormData)) {
            setOtpError('Session expired. Please click Save Account again.');
            return;
        }

        confirmOtpBtn.disabled = true;
        resendOtpBtn && (resendOtpBtn.disabled = true);
        try {
            await verifyOtp(phone, otp);
            const result = await submitAccountUpdate(pendingFormData);
            initialPhone = phone;
            form.setAttribute('data-initial-phone', initialPhone);
            initialEmail = (emailInput?.value || '').trim().toLowerCase();
            form.setAttribute('data-initial-email', initialEmail);
            pendingFormData = null;
            closeOtpModal();
            syncSidebarAdminName();
            setFeedback(result?.message || 'Account updated successfully.');
            if (pendingEmailChangeNotice) {
                openEmailNoticeModal(result?.message || 'Your account update has been saved. Please verify your new email using the link we sent.');
            }
            setSaveButtonState(false);
        } catch (error) {
            setOtpError(error.message || 'Failed to verify OTP.');
        } finally {
            confirmOtpBtn.disabled = false;
            resendOtpBtn && (resendOtpBtn.disabled = false);
            isSubmitting = false;
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isSubmitting) {
            return;
        }
        isSubmitting = true;
        setSaveButtonState(true);

        const currentPhone = (phoneInput?.value || '').trim();
        if (!currentPhone) {
            setFeedback('Phone number is required.', true);
            phoneInput?.focus();
            isSubmitting = false;
            setSaveButtonState(false);
            return;
        }

        const formData = new FormData(form);
        pendingEmailChangeNotice = isEmailChangedFromInitial();
        if (currentPhone !== initialPhone) {
            pendingFormData = formData;
            openOtpModal(currentPhone);
            if (otpPhoneInfo) {
                otpPhoneInfo.textContent = `Sending OTP to ${currentPhone}...`;
            }
            confirmOtpBtn && (confirmOtpBtn.disabled = true);
            resendOtpBtn && (resendOtpBtn.disabled = true);
            try {
                await sendOtp(currentPhone);
                setFeedback('OTP sent to new phone number.');
                if (otpPhoneInfo) {
                    otpPhoneInfo.textContent = `OTP has been sent to ${currentPhone}.`;
                }
                confirmOtpBtn && (confirmOtpBtn.disabled = false);
                resendOtpBtn && (resendOtpBtn.disabled = false);
                otpInput?.focus();
            } catch (error) {
                setOtpError(error.message || 'Failed to send OTP.');
                setFeedback(error.message || 'Failed to send OTP.', true);
                confirmOtpBtn && (confirmOtpBtn.disabled = true);
                resendOtpBtn && (resendOtpBtn.disabled = false);
                isSubmitting = false;
                setSaveButtonState(false);
            }
            return;
        }

        try {
            const result = await submitAccountUpdate(formData);
            initialEmail = (emailInput?.value || '').trim().toLowerCase();
            form.setAttribute('data-initial-email', initialEmail);
            syncSidebarAdminName();
            setFeedback(result?.message || 'Account updated successfully.');
            if (pendingEmailChangeNotice) {
                openEmailNoticeModal(result?.message || 'Your account update has been saved. Please verify your new email using the link we sent.');
            }
        } catch (error) {
            setFeedback(error.message || 'Failed to update account.', true);
        } finally {
            isSubmitting = false;
            setSaveButtonState(false);
        }
    });
})();
