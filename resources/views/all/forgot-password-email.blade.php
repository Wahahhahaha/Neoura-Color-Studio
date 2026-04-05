<main class="forgot-main">
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">{{ __('ui.forgot.common.eyebrow') }}</p>
                        <h1>{{ __('ui.forgot.email.heading') }}</h1>
                    </div>
                </div>

                <p class="service-meta">{{ __('ui.forgot.email.description') }}</p>
                <div class="login-alert" data-forgot-email-feedback role="alert" hidden></div>

                @if ($errors->any())
                    <div class="login-alert" role="alert">{{ $errors->first() }}</div>
                @endif
                @if (session('forgot_popup_error'))
                    <div class="login-alert" role="alert">{{ session('forgot_popup_error') }}</div>
                @endif
                @if (session('status'))
                    <div class="login-alert success" role="status">{{ session('status') }}</div>
                @endif

                <form class="login-form" method="post" action="{{ route('password.forgot.email.send') }}" novalidate data-forgot-email-form>
                    @csrf

                    <label for="forgot_email">{{ __('ui.forgot.email.registered_email') }}</label>
                    <input type="email" id="forgot_email" name="email" value="{{ old('email') }}" placeholder="{{ __('ui.forgot.email.email_placeholder') }}" required>

                    <div class="login-actions">
                        <button type="submit" class="btn">{{ __('ui.forgot.email.send_reset_link') }}</button>
                        <a href="{{ route('password.forgot.phone') }}" class="forgot-link">{{ __('ui.forgot.email.forgot_by_phone') }}</a>
                    </div>
                </form>

                <p class="service-meta forgot-back-link">
                    <a href="{{ route('login') }}" class="forgot-link">{{ __('ui.forgot.common.back_to_login') }}</a>
                </p>
            </div>
        </div>
    </section>
</main>
