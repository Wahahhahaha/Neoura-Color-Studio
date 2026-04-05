<main class="forgot-main">
    <section class="section login-page">
        <div class="container">
            <div class="login-card fade-in fade-in-soft">
                <div class="login-brand">
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="login-logo">
                    <div>
                        <p class="eyebrow">{{ __('ui.forgot.common.eyebrow') }}</p>
                        <h1>{{ __('ui.forgot.phone.heading') }}</h1>
                    </div>
                </div>

                @if (!empty($showOtpForm))
                    <p class="service-meta">{{ __('ui.forgot.phone.otp_description', ['phone' => $otpMaskedPhone ?? __('ui.forgot.phone.your_phone_number')]) }}</p>
                @else
                    <p class="service-meta">{{ __('ui.forgot.phone.description') }}</p>
                @endif

                <div class="login-alert" data-forgot-phone-feedback role="alert" hidden></div>
                @if ($errors->any())
                    <div class="login-alert" role="alert">{{ $errors->first() }}</div>
                @endif
                @if (session('forgot_popup_error'))
                    <div class="login-alert" role="alert">{{ session('forgot_popup_error') }}</div>
                @endif
                @if (session('status'))
                    <div class="login-alert success" role="status">{{ session('status') }}</div>
                @endif

                @if (empty($showOtpForm))
                    <form class="login-form" method="post" action="{{ route('password.forgot.phone.send_otp') }}" novalidate data-forgot-phone-send-form>
                        @csrf

                        <label for="forgot_phone">{{ __('ui.forgot.phone.registered_phone') }}</label>
                        <input type="text" id="forgot_phone" name="phonenumber" value="{{ old('phonenumber') }}" placeholder="{{ __('ui.forgot.phone.phone_placeholder') }}" required>

                        <div class="login-actions">
                            <button type="submit" class="btn">{{ __('ui.forgot.phone.send_otp') }}</button>
                            <a href="{{ route('password.forgot.email') }}" class="forgot-link">{{ __('ui.forgot.phone.reset_by_email_instead') }}</a>
                        </div>
                    </form>
                @else
                    <form class="login-form" method="post" action="{{ route('password.forgot.phone.verify_otp') }}" novalidate data-forgot-phone-verify-form>
                        @csrf

                        <label for="otp_code">{{ __('ui.forgot.phone.otp_code') }}</label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" inputmode="numeric" placeholder="{{ __('ui.forgot.phone.otp_placeholder') }}" required>

                        <div class="login-actions">
                            <button type="submit" class="btn">{{ __('ui.forgot.phone.verify_otp') }}</button>
                        </div>
                    </form>
                @endif

                <p class="service-meta forgot-back-link">
                    <a href="{{ route('login') }}" class="forgot-link">{{ __('ui.forgot.common.back_to_login') }}</a>
                </p>
            </div>
        </div>
    </section>
</main>
