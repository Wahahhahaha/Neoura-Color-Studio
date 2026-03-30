    <section class="section booking-page" id="booking">
        <div class="container booking-wrap fade-in fade-in-soft">
            <div>
                <p class="eyebrow">Selected Package</p>
                <h1>{{ $bookingPackage['name'] }}</h1>
                <p class="booking-package-meta">Duration: {{ $bookingPackage['duration'] }} | Price: {{ $bookingPackage['price'] }}</p>
                <p>{{ $bookingPackage['description'] }}</p>

                <div class="package-detail-box">
                    <h2>Package Includes</h2>
                    <ul>
                        @foreach ($bookingPackage['includes'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="payment-box">
                    <h2>Payment Accounts</h2>
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
                        <p class="service-meta">No payment account configured yet.</p>
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
                novalidate
            >
                @csrf
                @if ($errors->any())
                    <p class="setting-alert error">{{ $errors->first() }}</p>
                @endif

                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required placeholder="Enter your full name">

                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required placeholder="e.g. +1 2025550123 or +62 81234567890">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">

                <label for="booking_date">Booking Date</label>
                <div class="booking-field-shell">
                    <input type="date" id="booking_date" name="booking_date" class="booking-date-input" value="{{ old('booking_date', $bookingDate ?? now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                </div>

                <label for="time_slot">Preferred Time</label>
                <div class="booking-field-shell">
                    <select id="time_slot" name="time_slot" class="booking-time-select" required>
                        <option value="">Select a time slot</option>
                        @foreach (($bookingTimeOptions ?? []) as $slot)
                            <option value="{{ $slot['value'] }}" @selected(old('time_slot') === $slot['value'])>
                                {{ $slot['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <label for="payment_bank">Transfer Bank</label>
                <div class="booking-field-shell">
                    <select id="payment_bank" name="payment_bank" class="booking-time-select" required>
                        <option value="">Select destination bank</option>
                        @foreach (($website['bank_accounts'] ?? []) as $bank)
                            @php
                                $bankName = trim((string) ($bank['bankname'] ?? '-'));
                                $bankNumber = trim((string) ($bank['banknumber'] ?? '-'));
                                $bankOption = $bankName . ' - ' . $bankNumber;
                            @endphp
                            <option value="{{ $bankOption }}" @selected(old('payment_bank') === $bankOption)>{{ $bankOption }}</option>
                        @endforeach
                        @if (empty($website['bank_accounts'] ?? []))
                            <option value="Manual Confirmation" @selected(old('payment_bank') === 'Manual Confirmation')>Manual Confirmation</option>
                        @endif
                    </select>
                </div>

                <label for="payment_proof">Upload Payment Proof</label>
                <input type="file" id="payment_proof" name="payment_proof" accept="image/*,.pdf" required>

                <button type="submit" class="btn">Submit Booking</button>
                <p id="formMessage" class="form-message" aria-live="polite"></p>
            </form>
        </div>
    </section>

    @php
        $hasBookingSuccess = session('status') && session('booking_code');
    @endphp
    <div class="crop-modal booking-success-modal" data-booking-success-modal @if (!$hasBookingSuccess) hidden @endif>
        <div class="crop-modal-backdrop" data-close-booking-success></div>
        <div class="crop-modal-dialog booking-success-dialog" role="dialog" aria-modal="true" aria-label="Booking success">
            <div class="crop-modal-head">
                <h2>Booking Success</h2>
                <button type="button" class="crop-close" data-close-booking-success aria-label="Close booking success">x</button>
            </div>
            <p class="service-meta">{{ session('status') }}</p>
            <p class="booking-code-note">Booking Code: <strong>{{ session('booking_code') }}</strong></p>

            <div class="booking-success-list">
                @if (session('whatsapp_sent'))
                    <p class="booking-wa-note">WhatsApp confirmation was sent successfully.</p>
                @else
                    <p class="booking-wa-note">
                        WhatsApp auto-send failed{{ session('whatsapp_error') ? ': ' . session('whatsapp_error') : '' }}.
                        @if (session('whatsapp_link'))
                            <a href="{{ session('whatsapp_link') }}" target="_blank" rel="noopener">Send confirmation manually</a>.
                        @endif
                    </p>
                @endif

                @if (session('email_sent'))
                    <p class="booking-wa-note">Email confirmation was sent successfully.</p>
                @else
                    <p class="booking-wa-note">
                        Email auto-send failed{{ session('email_error') ? ': ' . session('email_error') : '' }}.
                    </p>
                @endif
            </div>

            <div class="crop-actions">
                <button type="button" class="btn" data-close-booking-success>OK</button>
            </div>
        </div>
    </div>

    <div class="crop-modal booking-confirm-modal" data-booking-confirm-modal hidden>
        <div class="crop-modal-backdrop" data-close-booking-confirm></div>
        <div class="crop-modal-dialog booking-confirm-dialog" role="dialog" aria-modal="true" aria-label="Booking confirmation">
            <div class="crop-modal-head">
                <h2>Confirmation Booking</h2>
                <button type="button" class="crop-close" data-close-booking-confirm aria-label="Close booking confirmation">x</button>
            </div>

            <p class="service-meta">Pastikan information yang diisi sudah benar.</p>
            <div class="booking-confirm-list">
                <p><span>Name</span><strong data-confirm-name>-</strong></p>
                <p><span>Phone</span><strong data-confirm-phone>-</strong></p>
                <p><span>Email</span><strong data-confirm-email>-</strong></p>
                <p><span>Date</span><strong data-confirm-date>-</strong></p>
                <p><span>Time</span><strong data-confirm-time>-</strong></p>
                <p><span>Bank</span><strong data-confirm-bank>-</strong></p>
            </div>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-booking-confirm>Cancel</button>
                <button type="button" class="btn" data-submit-booking-confirm>Confirm</button>
            </div>
        </div>
    </div>

    <div class="crop-modal booking-crop-modal" data-booking-crop-modal hidden>
        <div class="crop-modal-backdrop" data-close-booking-crop></div>
        <div class="crop-modal-dialog booking-crop-dialog" role="dialog" aria-modal="true" aria-label="Crop payment proof">
            <div class="crop-modal-head">
                <h2>Crop Payment Proof</h2>
                <button type="button" class="crop-close" data-close-booking-crop aria-label="Close crop modal">x</button>
            </div>

            <div class="crop-stage-wrap">
                <div class="crop-stage" data-booking-crop-stage>
                    <img src="" alt="Crop payment proof" data-booking-crop-image>
                    <div class="crop-box" data-booking-crop-box>
                        <button type="button" class="crop-handle crop-handle-nw" data-booking-crop-handle="nw" aria-label="Resize top left"></button>
                        <button type="button" class="crop-handle crop-handle-ne" data-booking-crop-handle="ne" aria-label="Resize top right"></button>
                        <button type="button" class="crop-handle crop-handle-sw" data-booking-crop-handle="sw" aria-label="Resize bottom left"></button>
                        <button type="button" class="crop-handle crop-handle-se" data-booking-crop-handle="se" aria-label="Resize bottom right"></button>
                    </div>
                </div>
            </div>

            <div class="crop-controls">
                <label for="booking_crop_zoom">Zoom</label>
                <input type="range" id="booking_crop_zoom" min="1" max="3" step="0.01" value="1" data-booking-crop-zoom>
            </div>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-booking-crop>Cancel</button>
                <button type="button" class="btn" data-apply-booking-crop>Apply Crop</button>
            </div>
        </div>
    </div>
