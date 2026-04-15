<section class="section">
    <div class="container">
        <div class="setting-wrap">
            <div class="section-head">
                <h1>{{ __('ui.ttime.title') }}</h1>
                <p>{{ __('ui.ttime.description') }}</p>
            </div>

            <p class="service-meta">
                {{ __('ui.ttime.today_date') }}: <strong>{{ $slotDate ?? now()->toDateString() }}</strong><br>
                {{ __('ui.ttime.operational_hours') }}: <strong>{{ $slotOpen ?? '-' }} - {{ $slotClose ?? '-' }}</strong>
            </p>

            <div class="admin-user-table-wrap" style="margin-top: 16px;">
                <table class="admin-user-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.ttime.no') }}</th>
                            <th>{{ __('ui.ttime.start_time') }}</th>
                            <th>{{ __('ui.ttime.end_time') }}</th>
                            <th>{{ __('ui.ttime.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($slotRows ?? []) as $index => $row)
                            @php
                                $statusKey = (string) ($row['status_key'] ?? 'empty');
                                $statusClass = match ($statusKey) {
                                    'walkin' => 'is-pending',
                                    'booking' => 'is-danger',
                                    default => 'is-success',
                                };
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['start_time'] ?? '-' }}</td>
                                <td>{{ $row['end_time'] ?? '-' }}</td>
                                <td>
                                    <span class="booking-status-pill {{ $statusClass }}">
                                        {{ __('ui.ttime.status_' . $statusKey) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">{{ __('ui.ttime.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
