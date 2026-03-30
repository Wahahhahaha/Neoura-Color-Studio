<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <h1>Payment Validation</h1>
                <p>Validate incoming payment proofs from users.</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <div class="admin-payment-filter">
                <label for="paymentBankFilter">Filter Bank</label>
                <select id="paymentBankFilter" data-payment-bank-filter>
                    <option value="">All Banks</option>
                    @foreach (($bankOptions ?? []) as $bank)
                        <option value="{{ $bank }}" @selected(($selectedBank ?? '') === $bank)>{{ $bank }}</option>
                    @endforeach
                </select>
            </div>

            <div data-payment-list-root data-fetch-url="{{ route('admin.payment') }}">
                @include('admin.partials.payment-validation-list', [
                    'paymentRows' => $paymentRows,
                    'paymentPagination' => $paymentPagination,
                ])
            </div>
        </div>
    </div>
</section>

<div class="crop-modal" data-proof-modal hidden>
    <div class="crop-modal-backdrop" data-close-proof-modal></div>
    <div class="crop-modal-dialog proof-modal-dialog" role="dialog" aria-modal="true" aria-label="Payment proof">
        <div class="crop-modal-head">
            <h2 data-proof-title>Payment Proof</h2>
            <button type="button" class="crop-close" data-close-proof-modal aria-label="Close proof modal">x</button>
        </div>

        <div class="proof-modal-body">
            <img src="" alt="Payment proof" class="proof-modal-image" data-proof-image hidden>
        </div>

        <div class="crop-actions">
            <button type="button" class="btn btn-outline" data-close-proof-modal>Close</button>
        </div>
    </div>
</div>
</main>
</div>
