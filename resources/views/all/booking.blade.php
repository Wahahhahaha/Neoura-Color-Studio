    <section class="section booking-page" id="booking">
        <div class="container booking-wrap fade-in fade-in-soft">
            <div>
                <p class="eyebrow">{{ __('ui.booking.selected_package') }}</p>
                <h1>{{ $bookingPackage['name'] }}</h1>
                <p class="booking-package-meta">{{ __('ui.booking.duration') }}: {{ $bookingPackage['duration'] }} | {{ __('ui.booking.price') }}: {{ $bookingPackage['price_display'] ?? $bookingPackage['price'] }}</p>
                <p>{{ $bookingPackage['description'] }}</p>

                <div class="package-detail-box">
                    <h2>{{ __('ui.booking.package_includes') }}</h2>
                    <ul>
                        @foreach ($bookingPackage['includes'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="payment-box">
                    <h2>{{ __('ui.booking.payment_accounts') }}</h2>
                    @php
                        $bankAccounts = $website['bank_accounts'] ?? [];
                    @endphp
                    @if (!empty($bankAccounts))
                        <ul>
                            @foreach ($bankAccounts as $bank)
                                <li>
                                    <strong>{{ $bank['bankname'] ?? '-' }}:</strong>
                                    {{ $bank['banknumber'] ?? '-' }}
                                    ({{ $website['name'] ?? 'Neora Color Studio' }})
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="service-meta">{{ __('ui.booking.no_payment_accounts') }}</p>
                    @endif
                </div>
            </div>

            <form
                id="bookingForm"
                class="booking-form"
                method="post"
                action="{{ route('booking.submit', ['plan' => $selectedPlan]) }}"
                enctype="multipart/form-data"
                data-plan="{{ $bookingPackage['name'] }}"
                data-duration-minutes="{{ $bookingDurationMinutes ?? 60 }}"
                data-schedule='@json($bookingSchedule ?? [])'
                data-msg-time-full="{{ __('ui.booking.slot_full') }}"
                data-msg-required="{{ __('ui.booking.required_fields') }}"
                data-msg-invalid-email="{{ __('ui.booking.invalid_email') }}"
                data-msg-invalid-phone="{{ __('ui.booking.invalid_phone') }}"
                data-label-full="{{ __('ui.booking.full_tag') }}"
                novalidate
            >
                @csrf
                @if ($errors->any())
                    <p class="setting-alert error">{{ $errors->first() }}</p>
                @endif

                <label for="full_name">{{ __('ui.booking.full_name') }}</label>
                <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required placeholder="{{ __('ui.booking.full_name_placeholder') }}">

                <label for="phone">{{ __('ui.booking.phone_number') }}</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required placeholder="{{ __('ui.booking.phone_placeholder') }}">

                <label for="email">{{ __('ui.booking.email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">

                <label for="booking_date">{{ __('ui.booking.booking_date') }}</label>
                <div class="booking-field-shell">
                    <input type="date" id="booking_date" name="booking_date" class="booking-date-input" value="{{ old('booking_date', $bookingDate ?? now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                </div>

                <label for="time_slot">{{ __('ui.booking.preferred_time') }}</label>
                <div class="booking-field-shell">
                    <select id="time_slot" name="time_slot" class="booking-time-select" required>
                        <option value="">{{ __('ui.booking.select_time_slot') }}</option>
                        @foreach (($bookingTimeOptions ?? []) as $slot)
                            <option value="{{ $slot['value'] }}" @selected(old('time_slot') === $slot['value'])>
                                {{ $slot['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <label for="payment_bank">{{ __('ui.booking.transfer_bank') }}</label>
                <div class="booking-field-shell">
                    <select id="payment_bank" name="payment_bank" class="booking-time-select" required>
                        <option value="">{{ __('ui.booking.select_destination_bank') }}</option>
                        @foreach (($website['bank_accounts'] ?? []) as $bank)
                            @php
                                $bankName = trim((string) ($bank['bankname'] ?? '-'));
                                $bankNumber = trim((string) ($bank['banknumber'] ?? '-'));
                                $bankOption = $bankName . ' - ' . $bankNumber;
                            @endphp
                            <option value="{{ $bankOption }}" @selected(old('payment_bank') === $bankOption)>{{ $bankOption }}</option>
                        @endforeach
                        @if (empty($website['bank_accounts'] ?? []))
                            <option value="{{ __('ui.booking.manual_confirmation') }}" @selected(old('payment_bank') === __('ui.booking.manual_confirmation'))>{{ __('ui.booking.manual_confirmation') }}</option>
                        @endif
                    </select>
                </div>

                <label for="payment_proof">{{ __('ui.booking.upload_payment_proof') }}</label>
                <input type="file" id="payment_proof" name="payment_proof" accept="image/*,.pdf" required>

                <button type="submit" class="btn">{{ __('ui.booking.submit') }}</button>
                <p id="formMessage" class="form-message" aria-live="polite"></p>
            </form>
        </div>
    </section>

    @php
        $hasBookingSuccess = session('status') && session('booking_code');
    @endphp
    <div class="crop-modal booking-success-modal" data-booking-success-modal @if (!$hasBookingSuccess) hidden @endif>
        <div class="crop-modal-backdrop" data-close-booking-success></div>
        <div class="crop-modal-dialog booking-success-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.booking.success_aria') }}">
            <div class="crop-modal-head">
                <h2>{{ __('ui.booking.success_title') }}</h2>
                <button type="button" class="crop-close" data-close-booking-success aria-label="{{ __('ui.booking.close_success_aria') }}">x</button>
            </div>
            <p class="service-meta">{{ session('status') }}</p>
            <p class="booking-code-note">{{ __('ui.booking.booking_code') }}: <strong>{{ session('booking_code') }}</strong></p>

            <div class="booking-success-list">
                @if (session('whatsapp_sent'))
                    <p class="booking-wa-note">{{ __('ui.booking.wa_sent') }}</p>
                @else
                    <p class="booking-wa-note">
                        {{ __('ui.booking.wa_failed') }}{{ session('whatsapp_error') ? ': ' . session('whatsapp_error') : '' }}.
                        @if (session('whatsapp_link'))
                            <a href="{{ session('whatsapp_link') }}" target="_blank" rel="noopener">{{ __('ui.booking.wa_send_manual') }}</a>.
                        @endif
                    </p>
                @endif

                @if (session('email_sent'))
                    <p class="booking-wa-note">{{ __('ui.booking.email_sent') }}</p>
                @else
                    <p class="booking-wa-note">
                        {{ __('ui.booking.email_failed') }}{{ session('email_error') ? ': ' . session('email_error') : '' }}.
                    </p>
                @endif
            </div>

            <div class="crop-actions">
                <button type="button" class="btn" data-close-booking-success>{{ __('ui.booking.ok') }}</button>
            </div>
        </div>
    </div>

    <div class="crop-modal booking-confirm-modal" data-booking-confirm-modal hidden>
        <div class="crop-modal-backdrop" data-close-booking-confirm></div>
        <div class="crop-modal-dialog booking-confirm-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.booking.confirmation_aria') }}">
            <div class="crop-modal-head">
                <h2>{{ __('ui.booking.confirmation_title') }}</h2>
                <button type="button" class="crop-close" data-close-booking-confirm aria-label="{{ __('ui.booking.close_confirmation_aria') }}">x</button>
            </div>

            <p class="service-meta">{{ __('ui.booking.confirmation_note') }}</p>
            <div class="booking-confirm-list">
                <p><span>{{ __('ui.booking.name') }}</span><strong data-confirm-name>-</strong></p>
                <p><span>{{ __('ui.booking.phone') }}</span><strong data-confirm-phone>-</strong></p>
                <p><span>{{ __('ui.booking.email') }}</span><strong data-confirm-email>-</strong></p>
                <p><span>{{ __('ui.booking.date') }}</span><strong data-confirm-date>-</strong></p>
                <p><span>{{ __('ui.booking.time') }}</span><strong data-confirm-time>-</strong></p>
                <p><span>{{ __('ui.booking.bank') }}</span><strong data-confirm-bank>-</strong></p>
            </div>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-booking-confirm>{{ __('ui.booking.cancel') }}</button>
                <button type="button" class="btn" data-submit-booking-confirm>{{ __('ui.booking.confirm') }}</button>
            </div>
        </div>
    </div>

    <div class="crop-modal booking-crop-modal" data-booking-crop-modal hidden>
        <div class="crop-modal-backdrop" data-close-booking-crop></div>
        <div class="crop-modal-dialog booking-crop-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.booking.crop_aria') }}">
            <div class="crop-modal-head">
                <h2>{{ __('ui.booking.crop_title') }}</h2>
                <button type="button" class="crop-close" data-close-booking-crop aria-label="{{ __('ui.booking.close_crop_aria') }}">x</button>
            </div>

            <div class="crop-stage-wrap">
                <div class="crop-stage" data-booking-crop-stage>
                    <img src="" alt="{{ __('ui.booking.crop_image_alt') }}" data-booking-crop-image>
                    <div class="crop-box" data-booking-crop-box>
                        <button type="button" class="crop-handle crop-handle-nw" data-booking-crop-handle="nw" aria-label="{{ __('ui.booking.resize_top_left') }}"></button>
                        <button type="button" class="crop-handle crop-handle-ne" data-booking-crop-handle="ne" aria-label="{{ __('ui.booking.resize_top_right') }}"></button>
                        <button type="button" class="crop-handle crop-handle-sw" data-booking-crop-handle="sw" aria-label="{{ __('ui.booking.resize_bottom_left') }}"></button>
                        <button type="button" class="crop-handle crop-handle-se" data-booking-crop-handle="se" aria-label="{{ __('ui.booking.resize_bottom_right') }}"></button>
                    </div>
                </div>
            </div>

            <div class="crop-controls">
                <label for="booking_crop_zoom">{{ __('ui.booking.zoom') }}</label>
                <input type="range" id="booking_crop_zoom" min="1" max="3" step="0.01" value="1" data-booking-crop-zoom>
            </div>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-booking-crop>{{ __('ui.booking.cancel') }}</button>
                <button type="button" class="btn" data-apply-booking-crop>{{ __('ui.booking.apply_crop') }}</button>
            </div>
        </div>
    </div>
