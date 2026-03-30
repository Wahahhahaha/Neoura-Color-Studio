<main>
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">Password Recovery</p>
                        <h1>Reset Password by Email</h1>
                    </div>
                </div>

                <p class="service-meta">Enter your registered email address. We will send you a secure reset link.</p>

                @if ($errors->any())
                    <div class="login-alert" role="alert">{{ $errors->first() }}</div>
                @endif
                @if (session('status'))
                    <div class="login-alert success" role="status">{{ session('status') }}</div>
                @endif

                <form class="login-form" method="post" action="{{ route('password.forgot.email.send') }}" novalidate>
                    @csrf

                    <label for="forgot_email">Registered Email</label>
                    <input type="email" id="forgot_email" name="email" value="{{ old('email') }}" placeholder="Enter your registered email" required>

                    <div class="login-actions">
                        <button type="submit" class="btn">Send Reset Link</button>
                        <a href="{{ route('password.forgot.phone') }}" class="forgot-link">Forgot password by phone number</a>
                    </div>
                </form>

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
