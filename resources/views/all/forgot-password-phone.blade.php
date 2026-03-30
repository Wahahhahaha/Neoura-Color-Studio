<main>
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">Password Recovery</p>
                        <h1>Reset Password by Phone</h1>
                    </div>
                </div>

                @if (!empty($showOtpForm))
                    <p class="service-meta">Enter the 6-digit OTP sent to {{ $otpMaskedPhone ?? 'your phone number' }}.</p>
                @else
                    <p class="service-meta">Enter your registered phone number. We will send an OTP verification code.</p>
                @endif

                @if ($errors->any())
                    <div class="login-alert" role="alert">{{ $errors->first() }}</div>
                @endif
                @if (session('status'))
                    <div class="login-alert success" role="status">{{ session('status') }}</div>
                @endif

                @if (empty($showOtpForm))
                    <form class="login-form" method="post" action="{{ route('password.forgot.phone.send_otp') }}" novalidate>
                        @csrf

                        <label for="forgot_phone">Registered Phone Number</label>
                        <input type="text" id="forgot_phone" name="phonenumber" value="{{ old('phonenumber') }}" placeholder="Enter your registered phone number" required>

                        <div class="login-actions">
                            <button type="submit" class="btn">Send OTP</button>
                            <a href="{{ route('password.forgot.email') }}" class="forgot-link">Reset by email instead</a>
                        </div>
                    </form>
                @else
                    <form class="login-form" method="post" action="{{ route('password.forgot.phone.verify_otp') }}" novalidate>
                        @csrf

                        <label for="otp_code">OTP Code</label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" inputmode="numeric" placeholder="Enter 6 digit OTP" required>

                        <div class="login-actions">
                            <button type="submit" class="btn">Verify OTP</button>
                        </div>
                    </form>
                @endif

                <p class="service-meta forgot-back-link">
                    <a href="{{ route('login') }}" class="forgot-link">Back to Login</a>
                </p>
            </div>
        </div>
    </section>

    @php
        $forgotPopupMessage = (string) session('forgot_popup_error', '');
    @endphp
    <div
        class="crop-modal forgot-popup-modal"
        data-forgot-popup-modal
        data-popup-message="{{ e($forgotPopupMessage) }}"
        hidden
    >
        <div class="crop-modal-backdrop" data-close-forgot-popup></div>
        <div class="crop-modal-dialog forgot-popup-dialog" role="dialog" aria-modal="true" aria-label="Forgot Password Notice">
            <p class="account-email-notice-text" data-forgot-popup-text></p>
            <div class="crop-actions account-email-notice-footer">
                <button type="button" class="btn" data-close-forgot-popup>OK</button>
            </div>
        </div>
    </div>
</main>
