<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head admin-payment-hero">
                <div>
                    <p class="eyebrow">{{ __('ui.admin.payment.eyebrow') }}</p>
                    <h1>{{ __('ui.admin.payment.title') }}</h1>
                    <p>{{ __('ui.admin.payment.description') }}</p>
                </div>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div class="admin-payment-shell">
                <div class="admin-payment-toolbar">
                    @php
                        $statusFilter = strtolower((string) ($selectedStatus ?? ''));
                    @endphp
                    <div class="admin-service-card-actions" data-payment-status-filter>
                        <button type="button" class="btn btn-outline {{ $statusFilter === '' ? 'is-active' : '' }}" data-payment-status="">{{ __('ui.common.all') }}</button>
                        <button type="button" class="btn btn-outline {{ $statusFilter === 'pending' ? 'is-active' : '' }}" data-payment-status="pending">{{ __('ui.common.pending') }}</button>
                        <button type="button" class="btn btn-outline {{ $statusFilter === 'approved' ? 'is-active' : '' }}" data-payment-status="approved">{{ __('ui.common.approved') }}</button>
                        <button type="button" class="btn btn-outline {{ $statusFilter === 'rejected' ? 'is-active' : '' }}" data-payment-status="rejected">{{ __('ui.common.rejected') }}</button>
                    </div>
                    <div class="admin-payment-filter">
                        <label for="paymentBankFilter">{{ __('ui.admin.payment.filter_bank') }}</label>
                        <select id="paymentBankFilter" data-payment-bank-filter>
                            <option value="">{{ __('ui.admin.payment.all_banks') }}</option>
                            @foreach (($bankOptions ?? []) as $bank)
                                <option value="{{ $bank }}" @selected(($selectedBank ?? '') === $bank)>{{ $bank }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div data-payment-list-root data-fetch-url="{{ route('admin.payment') }}">
                    @include('admin.partials.payment-validation-list', [
                        'paymentRows' => $paymentRows,
                        'paymentPagination' => $paymentPagination,
                    ])
                </div>
            </div>
        </div>
    </div>
</section>

<div class="crop-modal" data-proof-modal hidden>
    <div class="crop-modal-backdrop" data-close-proof-modal></div>
    <div class="crop-modal-dialog proof-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.payment.payment_proof') }}">
        <div class="crop-modal-head">
            <h2 data-proof-title>{{ __('ui.admin.payment.payment_proof') }}</h2>
            <button type="button" class="crop-close" data-close-proof-modal aria-label="{{ __('ui.admin.payment.close_proof_modal') }}">x</button>
        </div>

        <div class="proof-modal-body">
            <img src="" alt="{{ __('ui.admin.payment.payment_proof') }}" class="proof-modal-image" data-proof-image hidden>
        </div>

        <div class="crop-actions">
            <button type="button" class="btn btn-outline" data-close-proof-modal>{{ __('ui.common.close') }}</button>
        </div>
    </div>
</div>
</main>
</div>
