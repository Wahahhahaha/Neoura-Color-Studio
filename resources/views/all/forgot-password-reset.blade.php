<main>
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">Password Recovery</p>
                        <h1>{{ $formTitle ?? 'Reset Password' }}</h1>
                    </div>
                </div>

                <p class="service-meta">{{ $formDescription ?? 'Enter your new password.' }}</p>

                @if ($errors->any())
                    <div class="login-alert" role="alert">{{ $errors->first() }}</div>
                @endif

                <form class="login-form" method="post" action="{{ $formAction ?? '#' }}" novalidate>
                    @csrf

                    <label for="new_password">New Password</label>
                    <div class="password-field-shell">
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
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
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation" placeholder="Re-enter new password" required>
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

                    <div class="login-actions">
                        <button type="submit" class="btn">Update Password</button>
                        <a href="{{ $backUrl ?? route('login') }}" class="forgot-link">{{ $backLabel ?? 'Back' }}</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>
