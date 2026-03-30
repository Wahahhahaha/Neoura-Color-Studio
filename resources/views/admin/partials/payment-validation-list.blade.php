@if (empty($paymentRows))
    <div class="admin-payment-empty">
        <h3>No Payment Requests</h3>
        <p>There are no payment records matching your current filter.</p>
    </div>
@else
    <div class="admin-payment-list">
        @foreach ($paymentRows as $row)
            @php
                $status = strtolower((string) ($row['status'] ?? 'pending'));
                $statusClass = 'is-pending';
                if ($status === 'approved') {
                    $statusClass = 'is-approved';
                } elseif ($status === 'rejected') {
                    $statusClass = 'is-rejected';
                }
            @endphp
            <article class="admin-payment-card">
                <div class="admin-payment-head">
                    <div class="admin-payment-head-main">
                        <p class="admin-payment-kicker">Payment Validation</p>
                        <h3>{{ $row['service_name'] }}</h3>
                        <p class="service-meta">Booking Code: <strong>{{ $row['booking_code'] }}</strong></p>
                    </div>
                    <span class="admin-payment-status {{ $statusClass }}">{{ $row['status'] }}</span>
                </div>

                <div class="admin-payment-grid" role="list">
                    <div class="admin-payment-cell" role="listitem">
                        <span>Name</span>
                        <strong>{{ $row['name'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Email</span>
                        <strong>{{ $row['email'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Phone</span>
                        <strong>{{ $row['phone'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Booking Date</span>
                        <strong>{{ $row['booking_date'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Session Time</span>
                        <strong>{{ $row['start_time'] }} - {{ $row['end_time'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Payment Date</span>
                        <strong>{{ $row['payment_date'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Bank</span>
                        <strong>{{ $row['bank'] }}</strong>
                    </div>
                    <div class="admin-payment-cell" role="listitem">
                        <span>Proof Status</span>
                        <strong>{{ !empty($row['proof_url']) ? 'Available' : 'Missing' }}</strong>
                    </div>
                </div>

                <div class="admin-payment-foot">
                    <div class="admin-payment-proof">
                        <p class="setting-label">Transfer Proof</p>
                        @if (!empty($row['proof_url']))
                            @if (!empty($row['is_image_proof']))
                                <button
                                    type="button"
                                    class="btn btn-outline"
                                    data-open-proof-modal
                                    data-proof-url="{{ $row['proof_url'] }}"
                                    data-proof-title="Transfer proof {{ $row['booking_code'] }}"
                                >
                                    View Proof
                                </button>
                            @else
                                <a href="{{ $row['proof_url'] }}" target="_blank" rel="noopener" class="btn btn-outline">Open File</a>
                            @endif
                        @else
                            <p class="service-meta">Proof file not found.</p>
                        @endif
                    </div>

                    <div class="admin-payment-actions">
                        <form method="post" action="{{ route('admin.payment.update', ['bookingid' => $row['bookingid']]) }}">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn" @disabled(strtolower($row['status']) === 'approved')>Approve</button>
                        </form>

                        <form method="post" action="{{ route('admin.payment.update', ['bookingid' => $row['bookingid']]) }}">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-outline service-delete-btn" @disabled(strtolower($row['status']) === 'rejected')>Reject</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif

<div class="admin-user-pagination" data-payment-pagination>
    <p class="admin-user-pagination-meta">
        Showing {{ $paymentPagination['from'] ?? 0 }}-{{ $paymentPagination['to'] ?? 0 }} of {{ $paymentPagination['total'] ?? 0 }}
    </p>
    <div class="admin-user-pagination-actions">
        @php
            $currentPage = (int) ($paymentPagination['page'] ?? 1);
            $lastPage = (int) ($paymentPagination['last_page'] ?? 1);
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
        @endphp
        <button type="button" class="btn btn-outline" data-payment-page="{{ max(1, $currentPage - 1) }}" {{ $currentPage <= 1 ? 'disabled' : '' }}>Prev</button>
        @for ($cursor = $start; $cursor <= $end; $cursor++)
            <button type="button" class="btn btn-outline {{ $cursor === $currentPage ? 'is-active' : '' }}" data-payment-page="{{ $cursor }}" {{ $cursor === $currentPage ? 'disabled' : '' }}>{{ $cursor }}</button>
        @endfor
        <button type="button" class="btn btn-outline" data-payment-page="{{ min($lastPage, $currentPage + 1) }}" {{ $currentPage >= $lastPage ? 'disabled' : '' }}>Next</button>
        <span class="admin-user-pagination-page">Page {{ $currentPage }} / {{ $lastPage }}</span>
    </div>
</div>
