<section class="section">
    <div class="container">
        <div class="setting-wrap setting-shell">
            <div class="section-head setting-head">
                <h1>{{ __('ui.admin.time_slot.title') }}</h1>
                <p>{{ __('ui.admin.time_slot.description') }}</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <p class="service-meta">
                {{ __('ui.admin.time_slot.date') }}: <strong>{{ $filterDate ?? now()->toDateString() }}</strong><br>
                {{ __('ui.home.operational_hours') }}: <strong>{{ $slotOpen ?? '-' }} - {{ $slotClose ?? '-' }}</strong>
            </p>

            <div class="admin-user-table-wrap" style="margin-top: 20px;">
                <h3 style="margin: 0 0 12px;">{{ __('ui.admin.time_slot.today_all_slots') }}</h3>
                <table class="admin-user-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>{{ __('ui.admin.time_slot.start_time') }}</th>
                            <th>{{ __('ui.admin.time_slot.end_time') }}</th>
                            <th>{{ __('ui.home.status') }}</th>
                            <th>{{ __('ui.admin.time_slot.walkin_status') }}</th>
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
                                $statusLabel = match ($statusKey) {
                                    'walkin' => __('ui.admin.time_slot.reason_walkin'),
                                    'booking' => __('ui.admin.time_slot.status_booking'),
                                    default => __('ui.admin.time_slot.status_empty'),
                                };
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['start_time'] ?? '-' }}</td>
                                <td>{{ $row['end_time'] ?? '-' }}</td>
                                <td><span class="booking-status-pill {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td>
                                    @if ($statusKey === 'empty')
                                        <button
                                            type="button"
                                            class="btn btn-outline"
                                            data-open-walkin-modal
                                            data-slot-date="{{ $filterDate ?? now()->toDateString() }}"
                                            data-slot-start="{{ $row['start_time'] ?? '' }}"
                                        >
                                            {{ __('ui.admin.time_slot.set_walkin') }}
                                        </button>
                                    @elseif ($statusKey === 'walkin')
                                        @php
                                            $walkInDetail = is_array($row['walkin_detail'] ?? null) ? $row['walkin_detail'] : [];
                                        @endphp
                                        <button
                                            type="button"
                                            class="btn btn-outline"
                                            data-open-walkin-detail-modal
                                            data-walkin-date="{{ $walkInDetail['booking_date'] ?? ($filterDate ?? now()->toDateString()) }}"
                                            data-walkin-start="{{ $walkInDetail['start_time'] ?? ($row['start_time'] ?? '-') }}"
                                            data-walkin-end="{{ $walkInDetail['end_time'] ?? ($row['end_time'] ?? '-') }}"
                                            data-walkin-name="{{ $walkInDetail['customer_name'] ?? '' }}"
                                            data-walkin-package="{{ $walkInDetail['package_name'] ?? '' }}"
                                            data-walkin-note="{{ $walkInDetail['note'] ?? '' }}"
                                        >
                                            {{ __('ui.common.detail') }}
                                        </button>
                                    @else
                                        <span class="service-meta">{{ __('ui.admin.time_slot.not_available') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">{{ __('ui.admin.time_slot.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="crop-modal walkin-modal" data-walkin-modal hidden>
    <div class="crop-modal-backdrop" data-close-walkin-modal></div>
    <div class="crop-modal-dialog walkin-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.admin.time_slot.walkin_modal_title') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.admin.time_slot.walkin_modal_title') }}</h2>
            <button type="button" class="crop-close" data-close-walkin-modal aria-label="{{ __('ui.common.close') }}">&times;</button>
        </div>

        <form method="post" action="{{ route('admin.timeslot.walkin.store') }}" class="setting-form walkin-modal-form" data-walkin-form>
            @csrf
            <input type="hidden" name="date" data-walkin-date>
            <input type="hidden" name="start_time" data-walkin-start>

            <p class="service-meta">
                {{ __('ui.admin.time_slot.walkin_slot') }}:
                <strong data-walkin-slot-label>-</strong>
            </p>
            <p class="setting-alert error" data-walkin-feedback hidden></p>

            <label for="walkin_customer_name">{{ __('ui.admin.time_slot.walkin_customer_name') }}</label>
            <input type="text" id="walkin_customer_name" name="customer_name" maxlength="255" required>

            <label for="walkin_package">{{ __('ui.admin.time_slot.walkin_package') }}</label>
            <select id="walkin_package" name="plan" required>
                <option value="">{{ __('ui.admin.time_slot.walkin_package_select') }}</option>
                @foreach (($walkInPackages ?? []) as $package)
                    @php
                        $packageName = trim((string) ($package['name'] ?? ''));
                        $packageDuration = trim((string) ($package['duration'] ?? ''));
                        $packagePrice = trim((string) ($package['price_display'] ?? ($package['price'] ?? '')));
                    @endphp
                    @if ($packageName !== '')
                        <option value="{{ $packageName }}">
                            {{ $packageName }}{{ $packageDuration !== '' ? ' | ' . $packageDuration : '' }}{{ $packagePrice !== '' ? ' | ' . $packagePrice : '' }}
                        </option>
                    @endif
                @endforeach
            </select>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-walkin-modal>{{ __('ui.common.cancel') }}</button>
                <button type="submit" class="btn" data-walkin-submit>{{ __('ui.admin.time_slot.walkin_submit') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="crop-modal walkin-modal walkin-detail-modal" data-walkin-detail-modal hidden>
    <div class="crop-modal-backdrop" data-close-walkin-detail-modal></div>
    <div class="crop-modal-dialog walkin-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.common.detail') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.common.detail') }}</h2>
            <button type="button" class="crop-close" data-close-walkin-detail-modal aria-label="{{ __('ui.common.close') }}">&times;</button>
        </div>

        <div class="setting-form walkin-modal-form">
            <p class="service-meta">
                {{ __('ui.admin.time_slot.walkin_slot') }}:
                <strong data-walkin-detail-slot>-</strong>
            </p>

            <label>{{ __('ui.admin.time_slot.walkin_customer_name') }}</label>
            <input type="text" data-walkin-detail-name readonly>

            <label>{{ __('ui.admin.time_slot.walkin_package') }}</label>
            <input type="text" data-walkin-detail-package readonly>

            <label>{{ __('ui.admin.time_slot.note') }}</label>
            <textarea rows="3" data-walkin-detail-note readonly></textarea>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-walkin-detail-modal>{{ __('ui.common.close') }}</button>
            </div>
        </div>
    </div>
</div>
</main>
</div>
