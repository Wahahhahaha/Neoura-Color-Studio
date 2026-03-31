<main>
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">{{ __('ui.login.welcome_back') }}</p>
                        <h1>{{ $website['name'] ?? 'Neora Color Studio' }}</h1>
                    </div>
                </div>

                <form class="login-form" method="post" action="{{ route('login.submit') }}" novalidate data-login-form>
                    @csrf
                    <input type="hidden" name="login_form_token" value="{{ $loginFormToken ?? '' }}">
                    <input type="hidden" name="latitude" value="" data-login-latitude>
                    <input type="hidden" name="longitude" value="" data-login-longitude>

                    <label for="username">{{ __('ui.login.username') }}</label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="{{ __('ui.login.username_placeholder') }}" required>

                    <label for="password">{{ __('ui.login.password') }}</label>
                    <div class="password-field-shell">
                        <input type="password" id="password" name="password" placeholder="{{ __('ui.login.password_placeholder') }}" required>
                        <button
                            type="button"
                            class="password-visibility-btn"
                            data-toggle-password
                            data-target="#password"
                            aria-label="{{ __('ui.login.show_password') }}"
                            title="{{ __('ui.login.show_password') }}"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </button>
                    </div>

                    @if (!empty($requireCaptcha))
                        <div class="login-captcha-wrap" data-login-captcha-root data-recaptcha-site-key="{{ $recaptchaSiteKey ?? '' }}">
                            <input type="hidden" name="captcha_mode" value="{{ old('captcha_mode', 'offline') }}" data-captcha-mode>

                            <div class="login-captcha-online" data-captcha-online hidden>
                                <p class="login-captcha-title">{{ __('ui.login.security_check') }}</p>
                                <div class="login-recaptcha-box" data-recaptcha-widget></div>
                                <p class="login-captcha-note">{{ __('ui.login.complete_security_verification') }}</p>
                            </div>

                            <div class="login-captcha-offline" data-captcha-offline hidden>
                                <label for="offline_captcha_answer">{{ __('ui.login.security_check') }}</label>
                                <p class="login-captcha-note">{{ __('ui.login.answer_question') }} <strong>{{ $offlineCaptchaQuestion ?? '1 + 1 = ?' }}</strong></p>
                                <input
                                    type="text"
                                    id="offline_captcha_answer"
                                    name="offline_captcha_answer"
                                    value="{{ old('offline_captcha_answer') }}"
                                    placeholder="{{ __('ui.login.enter_result') }}"
                                    inputmode="numeric"
                                    autocomplete="off"
                                >
                            </div>

                            <p class="login-captcha-error" data-captcha-error hidden></p>
                        </div>
                    @endif

                    <div class="login-actions">
                        <button type="submit" class="btn">{{ __('ui.login.login') }}</button>
                        <a href="{{ route('password.forgot.email') }}" class="forgot-link">{{ __('ui.login.forgot_password') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

@if (!empty($recaptchaSiteKey))
    <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoaded&render=explicit" async defer></script>
@endif

@php
    $loginPopupMessage = (string) session('login_popup_error', '');
@endphp
<div
    class="crop-modal login-popup-modal"
    data-login-popup-modal
    data-popup-message="{{ e($loginPopupMessage) }}"
    hidden
>
    <div class="crop-modal-backdrop" data-close-login-popup></div>
    <div class="crop-modal-dialog login-popup-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.login.login_error_notice') }}">
        <p class="account-email-notice-text" data-login-popup-text></p>
        <div class="crop-actions account-email-notice-footer">
            <button type="button" class="btn" data-close-login-popup>{{ __('ui.login.ok') }}</button>
        </div>
    </div>
</div>
<script>
    (() => {
        const popupModal = document.querySelector('[data-login-popup-modal]');
        if (!popupModal) {
            return;
        }

        const popupText = popupModal.querySelector('[data-login-popup-text]');
        const closeNodes = Array.from(popupModal.querySelectorAll('[data-close-login-popup]'));
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
            }, 280);
        };

        closeNodes.forEach((node) => node.addEventListener('click', closePopup));
        openPopup(message);
    })();
</script>

