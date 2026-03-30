<main>
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">Welcome Back</p>
                        <h1>{{ $website['name'] ?? 'Neora Color Studio' }}</h1>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="login-alert" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif
                @if (session('status'))
                    <div class="login-alert success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <form class="login-form" method="post" action="{{ route('login.submit') }}" novalidate data-login-form>
                    @csrf
                    <input type="hidden" name="login_form_token" value="{{ $loginFormToken ?? '' }}">
                    <input type="hidden" name="latitude" value="" data-login-latitude>
                    <input type="hidden" name="longitude" value="" data-login-longitude>

                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Enter your username" required>

                    <label for="password">Password</label>
                    <div class="password-field-shell">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button
                            type="button"
                            class="password-visibility-btn"
                            data-toggle-password
                            data-target="#password"
                            aria-label="Show password"
                            title="Show password"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </button>
                    </div>

                    <div class="login-captcha-wrap" data-login-captcha-root data-recaptcha-site-key="{{ $recaptchaSiteKey ?? '' }}">
                        <input type="hidden" name="captcha_mode" value="{{ old('captcha_mode', 'offline') }}" data-captcha-mode>

                        <div class="login-captcha-online" data-captcha-online hidden>
                            <p class="login-captcha-title">Security Check</p>
                            <div class="login-recaptcha-box" data-recaptcha-widget></div>
                            <p class="login-captcha-note">Please complete the security verification.</p>
                        </div>

                        <div class="login-captcha-offline" data-captcha-offline hidden>
                            <label for="offline_captcha_answer">Security Check</label>
                            <p class="login-captcha-note">Answer this question: <strong>{{ $offlineCaptchaQuestion ?? '1 + 1 = ?' }}</strong></p>
                            <input
                                type="text"
                                id="offline_captcha_answer"
                                name="offline_captcha_answer"
                                value="{{ old('offline_captcha_answer') }}"
                                placeholder="Enter result"
                                inputmode="numeric"
                                autocomplete="off"
                            >
                        </div>

                        <p class="login-captcha-error" data-captcha-error hidden></p>
                    </div>

                    <div class="login-actions">
                        <button type="submit" class="btn">Login</button>
                        <a href="{{ route('password.forgot.email') }}" class="forgot-link">Forgot password?</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

@if (!empty($recaptchaSiteKey))
    <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoaded&render=explicit" async defer></script>
@endif

