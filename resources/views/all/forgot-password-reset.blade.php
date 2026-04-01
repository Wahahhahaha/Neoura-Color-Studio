<main>
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">{{ __('ui.forgot.common.eyebrow') }}</p>
                        <h1>{{ $formTitle ?? __('ui.forgot.reset.default_form_title') }}</h1>
                    </div>
                </div>

                <p class="service-meta">{{ $formDescription ?? __('ui.forgot.reset.default_form_description') }}</p>

                @if ($errors->any())
                    <div class="login-alert" role="alert">{{ $errors->first() }}</div>
                @endif

                <form class="login-form" method="post" action="{{ $formAction ?? '#' }}" novalidate>
                    @csrf

                    <label for="new_password">{{ __('ui.forgot.reset.new_password') }}</label>
                    <div class="password-field-shell">
                        <input type="password" id="new_password" name="new_password" placeholder="{{ __('ui.forgot.reset.new_password_placeholder') }}" required>
                        <button
                            type="button"
                            class="password-visibility-btn"
                            data-toggle-password
                            data-target="#new_password"
                            aria-label="{{ __('ui.login.show_password') }}"
                            title="{{ __('ui.login.show_password') }}"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </button>
                    </div>

                    <label for="new_password_confirmation">{{ __('ui.forgot.reset.confirm_new_password') }}</label>
                    <div class="password-field-shell">
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation" placeholder="{{ __('ui.forgot.reset.confirm_new_password_placeholder') }}" required>
                        <button
                            type="button"
                            class="password-visibility-btn"
                            data-toggle-password
                            data-target="#new_password_confirmation"
                            aria-label="{{ __('ui.forgot.reset.show_password_confirmation') }}"
                            title="{{ __('ui.forgot.reset.show_password_confirmation') }}"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                <circle cx="12" cy="12" r="3.2"></circle>
                            </svg>
                        </button>
                    </div>

                    <div class="login-actions">
                        <button type="submit" class="btn">{{ __('ui.forgot.reset.update_password') }}</button>
                        <a href="{{ $backUrl ?? route('login') }}" class="forgot-link">{{ $backLabel ?? __('ui.forgot.reset.back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>
