<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <h1>My Account</h1>
                <p>Update your profile details and password.</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <form
                method="post"
                action="{{ route('account.update') }}"
                class="setting-form"
                data-phone-otp-form
                data-account-update-url="{{ route('account.update') }}"
                data-otp-send-url="{{ route('account.phone_otp.send') }}"
                data-otp-verify-url="{{ route('account.phone_otp.verify') }}"
                data-initial-phone="{{ old('phonenumber', $accountProfile['phone'] ?? '') }}"
                data-initial-email="{{ $accountProfile['email'] ?? '' }}"
            >
                @csrf

                <label for="username">Username</label>
                <input type="text" id="username" value="{{ $accountProfile['username'] ?? '' }}" readonly>

                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $accountProfile['name'] ?? '') }}" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $accountProfile['email'] ?? '') }}" required>
                <p class="service-meta">If you change email, verification via email link is required before the new email is applied.</p>

                <label for="phonenumber">Phone Number</label>
                <input type="text" id="phonenumber" name="phonenumber" value="{{ old('phonenumber', $accountProfile['phone'] ?? '') }}" required>
                <p class="service-meta">If you change phone number, OTP verification is required.</p>
                <p class="service-meta" data-phone-otp-feedback aria-live="polite"></p>

                <label for="current_password">Current Password</label>
                <div class="password-field-shell">
                    <input type="password" id="current_password" name="current_password" placeholder="Required only when changing password">
                    <button
                        type="button"
                        class="password-visibility-btn"
                        data-toggle-password
                        data-target="#current_password"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                            <circle cx="12" cy="12" r="3.2"></circle>
                        </svg>
                    </button>
                </div>

                <label for="new_password">New Password</label>
                <div class="password-field-shell">
                    <input type="password" id="new_password" name="new_password" placeholder="Leave empty if you do not want to change">
                    <button
                        type="button"
                        class="password-visibility-btn"
                        data-toggle-password
                        data-target="#new_password"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                            <circle cx="12" cy="12" r="3.2"></circle>
                        </svg>
                    </button>
                </div>

                <label for="new_password_confirmation">Confirm New Password</label>
                <div class="password-field-shell">
                    <input type="password" id="new_password_confirmation" name="new_password_confirmation">
                    <button
                        type="button"
                        class="password-visibility-btn"
                        data-toggle-password
                        data-target="#new_password_confirmation"
                        aria-label="Show password confirmation"
                        title="Show password confirmation"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                            <circle cx="12" cy="12" r="3.2"></circle>
                        </svg>
                    </button>
                </div>

                <div class="setting-actions">
                    <button type="submit" class="btn">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</section>

<div class="crop-modal account-otp-modal" data-account-otp-modal hidden>
    <div class="crop-modal-backdrop" data-close-account-otp></div>
    <div class="crop-modal-dialog account-otp-dialog" role="dialog" aria-modal="true" aria-label="Verify OTP">
        <div class="crop-modal-head">
            <h2>Verify New Phone Number</h2>
            <button type="button" class="crop-close" data-close-account-otp aria-label="Close OTP modal">x</button>
        </div>

        <p class="account-otp-subtitle">Enter the verification code sent to your new phone number.</p>
        <p class="service-meta account-otp-phone-info" data-account-otp-phone-info></p>
        <label for="account_otp_code_modal" class="account-otp-label">OTP Code</label>
        <input type="text" id="account_otp_code_modal" class="account-otp-code-input" maxlength="6" placeholder="Enter 6 digit OTP" data-account-otp-input>
        <p class="form-message error" data-account-otp-error hidden></p>

        <div class="crop-actions account-otp-footer">
            <button type="button" class="btn btn-outline" data-resend-account-otp>Resend OTP</button>
            <button type="button" class="btn" data-confirm-account-otp>Verify & Save</button>
        </div>
    </div>
</div>
<div class="crop-modal account-email-notice-modal" data-account-email-notice-modal hidden>
    <div class="crop-modal-backdrop" data-close-account-email-notice></div>
    <div class="crop-modal-dialog account-email-notice-dialog" role="dialog" aria-modal="true" aria-label="Email Change Information">
        <div class="crop-modal-head">
            <h2>Email Change Request</h2>
            <button type="button" class="crop-close" data-close-account-email-notice aria-label="Close email notice modal">x</button>
        </div>

        <p class="account-email-notice-text" data-account-email-notice-text>
            Your account update has been saved. Please verify your new email using the link we sent.
        </p>

        <div class="crop-actions account-email-notice-footer">
            <button type="button" class="btn" data-close-account-email-notice>OK</button>
        </div>
    </div>
</div>
</main>
</div>
