<main class="account-email-success-page">
    <section class="account-email-success-card fade-in visible">
        <p class="account-email-success-brand">{{ $website['name'] ?? 'Neora Color Studio' }}</p>
        <span class="account-email-success-pill">Verification Completed</span>
        <h1>New Email Address Verified</h1>
        <p class="account-email-success-text">
            Your account email has been updated successfully.
            You will be redirected to your account page in
            <span data-redirect-countdown>{{ (int) ($redirectSeconds ?? 5) }}</span>
            seconds.
        </p>

        <div class="account-email-success-progress" aria-hidden="true">
            <span data-redirect-progress></span>
        </div>

        <div class="account-email-success-actions">
            <a
                href="{{ $redirectUrl ?? route('account') }}"
                class="btn"
                data-success-redirect-link
                data-redirect-url="{{ $redirectUrl ?? route('account') }}"
                data-redirect-seconds="{{ (int) ($redirectSeconds ?? 5) }}"
            >Go to Account</a>
        </div>

        <p class="account-email-success-note">
            If you did not perform this action, contact support immediately.
        </p>
    </section>
</main>
