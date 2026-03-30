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

                    <div class="login-actions">
                        <button type="submit" class="btn">Login</button>
                        <a href="{{ route('password.forgot.email') }}" class="forgot-link">Forgot password?</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

