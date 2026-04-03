@if (empty($paymentRows))
    <div class="admin-payment-empty">
        <h3>{{ __('ui.admin.payment.no_requests') }}</h3>
        <p>{{ __('ui.admin.payment.no_records_for_filter') }}</p>
    </div>
@else
    <div class="admin-payment-list">
        @foreach ($paymentRows as $row)
            @php
                $status = strtolower((string) ($row['status'] ?? 'pending'));
                $statusClass = 'is-pending';
                $statusLabel = __('ui.common.pending');
                if ($status === 'approved') {
                    $statusClass = 'is-approved';
                    $statusLabel = __('ui.common.approved');
                } elseif ($status === 'rejected') {
                    $statusClass = 'is-rejected';
                    $statusLabel = __('ui.common.rejected');
                }
            @endphp
            <article class="admin-payment-card">
                <div class="admin-payment-head">
                    <div class="admin-payment-head-main">
                        <p class="admin-payment-kicker">{{ __('ui.admin.payment.title') }}</p>
                        <div class="admin-payment-title-row">
                            <h3>{{ $row['service_name'] }}</h3>
                            <span class="admin-payment-booking-pill">{{ $row['booking_code'] }}</span>
                        </div>
                    </div>
                    <span class="admin-payment-status {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>

                <div class="admin-payment-quick-grid" role="list">
                    <div class="admin-payment-quick-item" role="listitem">
                        <span>{{ __('ui.common.name') }}</span>
                        <strong>{{ $row['name'] }}</strong>
                    </div>
                    <div class="admin-payment-quick-item" role="listitem">
                        <span>{{ __('ui.common.bank') }}</span>
                        <strong>{{ $row['bank'] }}</strong>
                    </div>
                    <div class="admin-payment-quick-item" role="listitem">
                        <span>{{ __('ui.admin.payment.booking_date') }}</span>
                        <strong>{{ $row['booking_date'] }}</strong>
                    </div>
                    <div class="admin-payment-quick-item" role="listitem">
                        <span>{{ __('ui.admin.payment.session_time') }}</span>
                        <strong>{{ $row['start_time'] }} - {{ $row['end_time'] }}</strong>
                    </div>
                </div>

                <div class="admin-payment-info-list" role="list">
                    <div class="admin-payment-info-item" role="listitem">
                        <span>{{ __('ui.common.email') }}</span>
                        <strong>{{ $row['email'] }}</strong>
                    </div>
                    <div class="admin-payment-info-item" role="listitem">
                        <span>{{ __('ui.common.phone') }}</span>
                        <strong>{{ $row['phone'] }}</strong>
                    </div>
                    <div class="admin-payment-info-item" role="listitem">
                        <span>{{ __('ui.admin.payment.payment_date') }}</span>
                        <strong>{{ $row['payment_date'] }}</strong>
                    </div>
                    <div class="admin-payment-info-item" role="listitem">
                        <span>{{ __('ui.admin.payment.proof_status') }}</span>
                        <strong>{{ !empty($row['proof_url']) ? __('ui.common.available') : __('ui.common.missing') }}</strong>
                    </div>
                </div>

                <div class="admin-payment-foot">
                    <div class="admin-payment-proof">
                        <p class="setting-label">{{ __('ui.admin.payment.transfer_proof') }}</p>
                        @if (!empty($row['proof_url']))
                            @if (!empty($row['is_image_proof']))
                                <button
                                    type="button"
                                    class="btn btn-outline"
                                    data-open-proof-modal
                                    data-proof-url="{{ $row['proof_url'] }}"
                                    data-proof-title="{{ __('ui.admin.payment.transfer_proof') }} {{ $row['booking_code'] }}"
                                >
                                    {{ __('ui.admin.payment.view_proof') }}
                                </button>
                            @else
                                <a href="{{ $row['proof_url'] }}" target="_blank" rel="noopener" class="btn btn-outline">{{ __('ui.admin.payment.open_file') }}</a>
                            @endif
                        @else
                            <p class="service-meta">{{ __('ui.admin.payment.proof_not_found') }}</p>
                        @endif
                    </div>

                    <div class="admin-payment-actions">
                        @if (strtolower((string) ($row['status'] ?? '')) !== 'approved')
                            <form method="post" action="{{ route('admin.payment.update', ['bookingid' => $row['bookingid']]) }}" class="admin-payment-action-form">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="redirect_status" value="{{ strtolower((string) request()->query('status', '')) }}">
                                <input type="hidden" name="redirect_bank" value="{{ (string) request()->query('bank', '') }}">
                                <input type="hidden" name="redirect_page" value="{{ max(1, (int) request()->query('page', 1)) }}">
                                <button type="submit" class="btn">{{ __('ui.common.approve') }}</button>
                            </form>

                            <form method="post" action="{{ route('admin.payment.update', ['bookingid' => $row['bookingid']]) }}" class="admin-payment-action-form">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="redirect_status" value="{{ strtolower((string) request()->query('status', '')) }}">
                                <input type="hidden" name="redirect_bank" value="{{ (string) request()->query('bank', '') }}">
                                <input type="hidden" name="redirect_page" value="{{ max(1, (int) request()->query('page', 1)) }}">
                                <button type="submit" class="btn btn-outline service-delete-btn" @disabled(strtolower($row['status']) === 'rejected')>{{ __('ui.common.reject') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif

<div class="admin-user-pagination" data-payment-pagination>
    <p class="admin-user-pagination-meta">
        {{ __('ui.common.showing_range_of_total', ['from' => $paymentPagination['from'] ?? 0, 'to' => $paymentPagination['to'] ?? 0, 'total' => $paymentPagination['total'] ?? 0]) }}
    </p>
    <div class="admin-user-pagination-actions">
        @php
            $currentPage = (int) ($paymentPagination['page'] ?? 1);
            $lastPage = (int) ($paymentPagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp
        <button type="button" class="btn btn-outline" data-payment-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>{{ __('ui.common.prev') }}</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-payment-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-payment-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>{{ __('ui.common.next') }}</button>
        <span class="admin-user-pagination-page">{{ __('ui.common.page_of', ['page' => $currentPage, 'last_page' => $lastPage]) }}</span>
    </div>
</div>
