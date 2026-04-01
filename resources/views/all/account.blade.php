<section class="section account-page">
    <div class="container">
        <div class="account-shell">
            @php
                $accountName = trim((string) (old('name', $accountProfile['name'] ?? '')));
                $accountInitial = strtoupper(substr($accountName !== '' ? $accountName : ((string) ($accountProfile['username'] ?? 'A')), 0, 1));
                $accountRole = trim((string) ($adminAuth['levelname'] ?? ''));
            @endphp
            <div class="setting-wrap">
                <div class="section-head account-hero">
                    <div class="account-hero-copy">
                        <p class="eyebrow">{{ __('ui.account.eyebrow') }}</p>
                        <h1>{{ __('ui.account.title') }}</h1>
                        <p>{{ __('ui.account.description') }}</p>
                    </div>
                </div>

                <div class="account-alert-stack">
                    @if (session('status'))
                        <p class="setting-alert success">{{ session('status') }}</p>
                    @endif

                    @if ($errors->any())
                        <p class="setting-alert error">{{ $errors->first() }}</p>
                    @endif
                </div>

                <form
                    method="post"
                    action="{{ route('account.update') }}"
                    class="setting-form account-form"
                    data-phone-otp-form
                    data-account-update-url="{{ route('account.update') }}"
                    data-otp-send-url="{{ route('account.phone_otp.send') }}"
                    data-otp-verify-url="{{ route('account.phone_otp.verify') }}"
                    data-initial-phone="{{ old('phonenumber', $accountProfile['phone'] ?? '') }}"
                    data-initial-email="{{ $accountProfile['email'] ?? '' }}"
                    data-text-processing="{{ __('ui.account.processing') }}"
                    data-text-save-account="{{ __('ui.account.save_account') }}"
                    data-text-admin-fallback="{{ __('ui.account.admin_fallback') }}"
                    data-text-hide-password="{{ __('ui.account.hide_password') }}"
                    data-text-show-password="{{ __('ui.account.show_password') }}"
                    data-text-hide-password-confirmation="{{ __('ui.account.hide_password_confirmation') }}"
                    data-text-show-password-confirmation="{{ __('ui.account.show_password_confirmation') }}"
                    data-text-otp-sent-to="{{ __('ui.account.otp_sent_to') }}"
                    data-text-email-notice-default="{{ __('ui.account.email_notice_default') }}"
                    data-text-send-otp-failed="{{ __('ui.account.send_otp_failed') }}"
                    data-text-otp-verification-failed="{{ __('ui.account.otp_verification_failed') }}"
                    data-text-update-failed="{{ __('ui.account.update_failed') }}"
                    data-text-phone-required="{{ __('ui.account.phone_required') }}"
                    data-text-otp-resent="{{ __('ui.account.otp_resent') }}"
                    data-text-resend-otp-failed="{{ __('ui.account.resend_otp_failed') }}"
                    data-text-otp-six-digits="{{ __('ui.account.otp_six_digits') }}"
                    data-text-otp-session-expired="{{ __('ui.account.otp_session_expired') }}"
                    data-text-verify-otp-failed="{{ __('ui.account.verify_otp_failed') }}"
                    data-text-account-updated="{{ __('ui.account.account_updated') }}"
                    data-text-otp-sent-new-phone="{{ __('ui.account.otp_sent_new_phone') }}"
                    data-text-sending-otp-to="{{ __('ui.account.sending_otp_to') }}"
                    data-text-default-error="{{ __('ui.common.action_failed') }}"
                >
                    @csrf

                    <div class="account-form-grid">
                        <div class="account-panel">
                            <h2 class="account-panel-title">{{ __('ui.account.profile_information') }}</h2>

                            <label for="username">{{ __('ui.common.username') }}</label>
                            <input type="text" id="username" value="{{ $accountProfile['username'] ?? '' }}" readonly>

                            <div class="account-profile-grid">
                                <div class="account-field-block">
                                    <label for="name">{{ __('ui.common.name') }}</label>
                                    <input type="text" id="name" name="name" value="{{ old('name', $accountProfile['name'] ?? '') }}" required>
                                </div>

                                <div class="account-field-block">
                                    <label for="email">{{ __('ui.common.email') }}</label>
                                    <input type="email" id="email" name="email" value="{{ old('email', $accountProfile['email'] ?? '') }}" required>
                                    <p class="service-meta account-meta">{{ __('ui.account.email_change_hint') }}</p>
                                </div>

                                <div class="account-field-block account-field-block-full">
                                    <label for="phonenumber">{{ __('ui.common.phone_number') }}</label>
                                    <input type="text" id="phonenumber" name="phonenumber" value="{{ old('phonenumber', $accountProfile['phone'] ?? '') }}" required>
                                    <p class="service-meta account-meta">{{ __('ui.account.phone_change_hint') }}</p>
                                </div>
                            </div>

                            <p class="service-meta account-meta" data-phone-otp-feedback aria-live="polite"></p>
                        </div>

                        <div class="account-panel">
                            <h2 class="account-panel-title">{{ __('ui.account.password_security') }}</h2>

                            <label for="current_password">{{ __('ui.account.current_password') }}</label>
                            <div class="password-field-shell">
                                <input type="password" id="current_password" name="current_password" placeholder="{{ __('ui.account.current_password_placeholder') }}">
                                <button
                                    type="button"
                                    class="password-visibility-btn"
                                    data-toggle-password
                                    data-target="#current_password"
                                    aria-label="{{ __('ui.account.show_password') }}"
                                    title="{{ __('ui.account.show_password') }}"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                        <circle cx="12" cy="12" r="3.2"></circle>
                                    </svg>
                                </button>
                            </div>

                            <label for="new_password">{{ __('ui.account.new_password') }}</label>
                            <div class="password-field-shell">
                                <input type="password" id="new_password" name="new_password" placeholder="{{ __('ui.account.new_password_placeholder') }}">
                                <button
                                    type="button"
                                    class="password-visibility-btn"
                                    data-toggle-password
                                    data-target="#new_password"
                                    aria-label="{{ __('ui.account.show_password') }}"
                                    title="{{ __('ui.account.show_password') }}"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                        <circle cx="12" cy="12" r="3.2"></circle>
                                    </svg>
                                </button>
                            </div>

                            <label for="new_password_confirmation">{{ __('ui.account.confirm_new_password') }}</label>
                            <div class="password-field-shell">
                                <input type="password" id="new_password_confirmation" name="new_password_confirmation">
                                <button
                                    type="button"
                                    class="password-visibility-btn"
                                    data-toggle-password
                                    data-target="#new_password_confirmation"
                                    aria-label="{{ __('ui.account.show_password_confirmation') }}"
                                    title="{{ __('ui.account.show_password_confirmation') }}"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                        <circle cx="12" cy="12" r="3.2"></circle>
                                    </svg>
                                </button>
                            </div>

                            <div class="setting-actions account-actions">
                                <button type="submit" class="btn">{{ __('ui.account.save_account') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<div class="crop-modal account-otp-modal" data-account-otp-modal hidden>
    <div class="crop-modal-backdrop" data-close-account-otp></div>
    <div class="crop-modal-dialog account-otp-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.account.verify_otp') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.account.verify_new_phone') }}</h2>
            <button type="button" class="crop-close" data-close-account-otp aria-label="{{ __('ui.account.close_otp_modal') }}">x</button>
        </div>

        <p class="account-otp-subtitle">{{ __('ui.account.otp_subtitle') }}</p>
        <p class="service-meta account-otp-phone-info" data-account-otp-phone-info></p>
        <label for="account_otp_code_modal" class="account-otp-label">{{ __('ui.account.otp_code') }}</label>
        <input type="text" id="account_otp_code_modal" class="account-otp-code-input" maxlength="6" placeholder="{{ __('ui.account.otp_placeholder') }}" data-account-otp-input>
        <p class="form-message error" data-account-otp-error hidden></p>

        <div class="crop-actions account-otp-footer">
            <button type="button" class="btn btn-outline" data-resend-account-otp>{{ __('ui.account.resend_otp') }}</button>
            <button type="button" class="btn" data-confirm-account-otp>{{ __('ui.account.verify_and_save') }}</button>
        </div>
    </div>
</div>
<div class="crop-modal account-email-notice-modal" data-account-email-notice-modal hidden>
    <div class="crop-modal-backdrop" data-close-account-email-notice></div>
    <div class="crop-modal-dialog account-email-notice-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.account.email_change_information') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.account.email_change_request') }}</h2>
            <button type="button" class="crop-close" data-close-account-email-notice aria-label="{{ __('ui.account.close_email_notice_modal') }}">x</button>
        </div>

        <p class="account-email-notice-text" data-account-email-notice-text>
            {{ __('ui.account.email_notice_default') }}
        </p>

        <div class="crop-actions account-email-notice-footer">
            <button type="button" class="btn" data-close-account-email-notice>{{ __('ui.common.ok') }}</button>
        </div>
    </div>
</div>
</main>
</div>
