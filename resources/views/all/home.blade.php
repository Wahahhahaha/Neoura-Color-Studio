    <section class="hero" id="home">
        <div class="hero-carousel" data-carousel data-carousel-autoplay-ms="{{ $carouselAutoplayMs ?? 5000 }}">
            @if (!empty($showAdminMenu))
                <button type="button" class="carousel-edit-btn" data-open-carousel-editor aria-label="Edit carousel">
                    <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4zm12-12 2 2"/></svg>
                </button>
            @endif

            <div class="carousel-track" data-carousel-track>
                @foreach (($carouselSlides ?? []) as $index => $slide)
                    <article class="slide {{ $index === 0 ? 'is-active' : '' }}">
                        @if (!empty($slide['image_url']))
                            <img src="{{ $slide['image_url'] }}" alt="{{ $slide['title'] }}">
                        @else
                            <div class="slide-solid {{ $slide['solid_class'] ?? 'slide-solid-1' }}"></div>
                        @endif

                        <div class="slide-stack-card">
                            <p class="eyebrow">{{ __('ui.home.color_notes') }}</p>
                            <h2>{{ $slide['title'] ?? '' }}</h2>
                            <p>{{ $slide['description'] ?? '' }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            <button type="button" class="carousel-btn prev" aria-label="Previous slide" data-carousel-prev>&#10094;</button>
            <button type="button" class="carousel-btn next" aria-label="Next slide" data-carousel-next>&#10095;</button>

            <div class="carousel-dots" data-carousel-dots>
                @foreach (($carouselSlides ?? []) as $index => $slide)
                    <button type="button" class="dot {{ $index === 0 ? 'is-active' : '' }}" data-slide="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
        </div>
    </section>

    @if (!empty($showAdminMenu))
        <div class="crop-modal carousel-editor-modal" data-carousel-editor-modal hidden>
            <div class="crop-modal-backdrop" data-close-carousel-editor></div>
            <div class="crop-modal-dialog carousel-editor-dialog" role="dialog" aria-modal="true" aria-label="Edit carousel">
                <div class="crop-modal-head">
                    <h2>Edit Home Content</h2>
                    <button type="button" class="crop-close" data-close-carousel-editor aria-label="Close carousel editor">x</button>
                </div>

                <form method="post" action="{{ route('carousel.update') }}" enctype="multipart/form-data" class="carousel-editor-form">
                    @csrf
                    <p class="carousel-editor-feedback" data-carousel-editor-feedback hidden></p>

                    <div class="carousel-editor-card">
                        <div class="carousel-editor-card-head">
                            <h3>Carousel Settings</h3>
                        </div>

                        <label for="carousel_autoplay_ms">Slide Interval (ms)</label>
                        <input
                            type="number"
                            id="carousel_autoplay_ms"
                            name="carousel_autoplay_ms"
                            min="500"
                            max="60000"
                            step="100"
                            value="{{ old('carousel_autoplay_ms', $carouselAutoplayMs ?? 5000) }}"
                            required
                        >
                    </div>

                    <div class="carousel-editor-tools">
                        <h3>Slides</h3>
                        <button type="button" class="btn btn-outline" data-add-slide>Add Slide</button>
                    </div>

                    <div class="carousel-editor-list" data-slide-list>
                        @foreach (($carouselSlides ?? []) as $index => $slide)
                            <div class="carousel-editor-card" data-slide-card>
                                <div class="carousel-editor-card-head">
                                    <h3 data-slide-heading>Slide {{ $index + 1 }}</h3>
                                    <button type="button" class="carousel-editor-remove" data-remove-slide>Remove</button>
                                </div>

                                @if (!empty($slide['image_url']))
                                    <img src="{{ $slide['image_url'] }}" alt="Current slide {{ $index + 1 }}" class="carousel-editor-preview">
                                @endif

                                <input type="hidden" data-field="existing_image" name="slides[{{ $index }}][existing_image]" value="{{ $slide['image_path'] ?? '' }}">

                                <label>Slide Image</label>
                                <input type="file" data-field="image" name="slides[{{ $index }}][image]" accept="image/*">

                                <label>Slide Title</label>
                                <input type="text" data-field="title" name="slides[{{ $index }}][title]" value="{{ $slide['title'] ?? '' }}" required>

                                <label>Slide Description</label>
                                <textarea data-field="description" name="slides[{{ $index }}][description]" rows="3" required>{{ $slide['description'] ?? '' }}</textarea>
                            </div>
                        @endforeach
                    </div>

                    <template data-slide-template>
                        <div class="carousel-editor-card" data-slide-card>
                            <div class="carousel-editor-card-head">
                                <h3 data-slide-heading>Slide</h3>
                                <button type="button" class="carousel-editor-remove" data-remove-slide>Remove</button>
                            </div>

                            <input type="hidden" data-field="existing_image" value="">

                            <label>Slide Image</label>
                            <input type="file" data-field="image" accept="image/*">

                            <label>Slide Title</label>
                            <input type="text" data-field="title" value="" required>

                            <label>Slide Description</label>
                            <textarea data-field="description" rows="3" required></textarea>
                        </div>
                    </template>

                    <div class="crop-actions">
                        <button type="button" class="btn btn-outline" data-close-carousel-editor>Cancel</button>
                        <button type="submit" class="btn" data-carousel-editor-save>Save Home Content</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="crop-modal" data-carousel-crop-modal hidden>
            <div class="crop-modal-backdrop" data-close-carousel-crop></div>
            <div class="crop-modal-dialog" role="dialog" aria-modal="true" aria-label="Crop slide image">
                <div class="crop-modal-head">
                    <h2>Crop Image</h2>
                    <button type="button" class="crop-close" data-close-carousel-crop aria-label="Close crop modal">x</button>
                </div>

                <div class="crop-stage-wrap">
                    <div class="crop-stage" data-carousel-crop-stage>
                        <img src="" alt="Slide preview" data-carousel-crop-image>
                        <div class="crop-box" data-carousel-crop-box>
                            <span class="crop-handle crop-handle-nw" data-carousel-crop-handle="nw" aria-hidden="true"></span>
                            <span class="crop-handle crop-handle-ne" data-carousel-crop-handle="ne" aria-hidden="true"></span>
                            <span class="crop-handle crop-handle-sw" data-carousel-crop-handle="sw" aria-hidden="true"></span>
                            <span class="crop-handle crop-handle-se" data-carousel-crop-handle="se" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>

                <div class="crop-controls">
                    <label for="carouselCropZoom">Zoom</label>
                    <input type="range" id="carouselCropZoom" min="1" max="3" step="0.01" value="1" data-carousel-crop-zoom>
                </div>

                <div class="crop-actions">
                    <button type="button" class="btn btn-outline" data-close-carousel-crop>Cancel</button>
                    <button type="button" class="btn" data-apply-carousel-crop>Apply Crop</button>
                </div>
            </div>
        </div>
    @endif

    <section class="about section" id="about">
        <div class="container split fade-in">
            <div>
                <div class="about-head-actions">
                    <p class="eyebrow">{{ __('ui.about.heading') }}</p>
                    @if (!empty($showAdminMenu))
                        <button type="button" class="about-edit-btn" data-open-about-editor aria-label="{{ __('ui.about.edit_aria') }}">
                            <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4zm12-12 2 2"/></svg>
                        </button>
                    @endif
                </div>
                <h2 data-about-title>{{ $aboutContent['title'] ?? '' }}</h2>
            </div>
            <p data-about-description>{{ $aboutContent['description'] ?? '' }}</p>
        </div>
    </section>

    @if (!empty($showAdminMenu))
        <div class="crop-modal about-editor-modal" data-about-editor-modal hidden>
            <div class="crop-modal-backdrop" data-close-about-editor></div>
            <div class="crop-modal-dialog about-editor-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.about.edit_aria') }}">
                <div class="crop-modal-head">
                    <h2>{{ __('ui.about.edit_title') }}</h2>
                    <button type="button" class="crop-close" data-close-about-editor aria-label="{{ __('ui.about.close_edit_aria') }}">x</button>
                </div>

                <form method="post" action="{{ route('about.update') }}" class="service-modal-form" data-about-editor-form>
                    @csrf
                    <p class="carousel-editor-feedback" data-about-editor-feedback hidden></p>

                    <label for="about_editor_title">{{ __('ui.about.about_title_label') }}</label>
                    <input type="text" id="about_editor_title" name="about_title" value="{{ old('about_title', $aboutContent['title'] ?? '') }}" required>

                    <label for="about_editor_description">{{ __('ui.about.about_description_label') }}</label>
                    <textarea id="about_editor_description" name="about_description" rows="4" required>{{ old('about_description', $aboutContent['description'] ?? '') }}</textarea>

                    <div class="crop-actions">
                        <button type="button" class="btn btn-outline" data-close-about-editor>{{ __('ui.home.cancel') }}</button>
                        <button type="submit" class="btn" data-about-editor-save>{{ __('ui.about.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <section class="services section" id="service">
        <div class="container">
            <div class="section-head fade-in">
                <p class="eyebrow">{{ __('ui.home.services') }}</p>
                <h2>{{ __('ui.home.choose_consultation') }}</h2>
            </div>

            <div class="service-grid">
                @if (!empty($homeServices))
                    @foreach ($homeServices as $service)
                        <article class="service-card fade-in" data-card-link="{{ route('booking', ['plan' => $service['name']]) }}">
                            <h3>{{ $service['name'] }}</h3>
                            <p>{{ $service['detail'] ?? '' }}</p>
                            @if (!empty($service['descriptions']))
                                <ul>
                                    @foreach ($service['descriptions'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <p class="service-meta">{{ __('ui.home.duration') }}: {{ $service['duration'] }}</p>
                            <p class="service-price">{{ __('ui.home.price') }}: {{ $service['price'] }}</p>
                            <div class="card-actions">
                                <a href="{{ route('booking', ['plan' => $service['name']]) }}" class="btn">{{ __('ui.home.booking') }}</a>
                            </div>
                        </article>
                    @endforeach
                @else
                    <p class="service-meta">{{ __('ui.home.no_service_data') }}</p>
                @endif
            </div>

            <div class="booking-status-box fade-in" id="booking-status">
                <div class="booking-status-head">
                    <h3>{{ __('ui.home.check_booking') }}</h3>
                    <p>{{ __('ui.home.check_booking_description') }}</p>
                </div>

                <form method="post" action="{{ route('booking.status') }}" class="booking-status-form">
                    @csrf
                    <div class="booking-status-code-field">
                        <label for="booking_code_lookup" class="booking-status-label">{{ __('ui.home.booking_code') }}</label>
                        <input
                            type="text"
                            id="booking_code_lookup"
                            name="booking_code"
                            class="booking-status-input"
                            value="{{ old('booking_code') }}"
                            placeholder="e.g. NRA-ABC123"
                            required
                        >
                    </div>

                    <input type="hidden" name="phone_last4" value="{{ old('phone_last4') }}" data-phone-last4-hidden>
                    <button type="button" class="btn booking-status-search-btn" data-open-booking-verify>{{ __('ui.home.search_booking') }}</button>
                </form>

                @if (!empty($bookingLookupError))
                    <p class="setting-alert error">{{ $bookingLookupError }}</p>
                @endif
                @if ($errors->has('booking_code') || $errors->has('phone_last4'))
                    <p class="setting-alert error">{{ $errors->first('booking_code') ?: $errors->first('phone_last4') }}</p>
                @endif

                @if (!empty($bookingLookupResult))
                    @php
                        $statusValue = strtolower((string) ($bookingLookupResult['status'] ?? ''));
                        $statusClass = str_contains($statusValue, 'confirm') || str_contains($statusValue, 'complete') ? 'is-success' : (str_contains($statusValue, 'cancel') ? 'is-danger' : 'is-pending');
                    @endphp
                    <div class="booking-status-result">
                        <p><span>{{ __('ui.nav.service') }}</span><strong>{{ $bookingLookupResult['service_name'] ?? '-' }}</strong></p>
                        <p><span>{{ __('ui.home.date') }}</span><strong>{{ $bookingLookupResult['date'] ?? '-' }}</strong></p>
                        <p><span>{{ __('ui.home.time_start') }}</span><strong>{{ $bookingLookupResult['start_time'] ?? '-' }}</strong></p>
                        <p><span>{{ __('ui.home.time_end') }}</span><strong>{{ $bookingLookupResult['end_time'] ?? '-' }}</strong></p>
                        <p><span>{{ __('ui.home.name') }}</span><strong>{{ $bookingLookupResult['name'] ?? '-' }}</strong></p>
                        <p><span>{{ __('ui.home.status') }}</span><strong class="booking-status-pill {{ $statusClass }}">{{ $bookingLookupResult['status'] ?? '-' }}</strong></p>
                        <p><span>{{ __('ui.home.booking_code') }}</span><strong>{{ $bookingLookupResult['booking_code'] ?? '-' }}</strong></p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="crop-modal booking-verify-modal" data-booking-verify-modal hidden>
        <div class="crop-modal-backdrop" data-close-booking-verify></div>
        <div class="crop-modal-dialog booking-verify-dialog" role="dialog" aria-modal="true" aria-label="Verify phone">
            <div class="crop-modal-head">
                <h2>{{ __('ui.home.phone_verification') }}</h2>
                <button type="button" class="crop-close" data-close-booking-verify aria-label="Close phone verification">x</button>
            </div>

            <p class="service-meta">{{ __('ui.home.phone_last_4_hint') }}</p>
            <label for="phone_last4_modal">{{ __('ui.home.last_4_digits_phone') }}</label>
            <input
                type="text"
                id="phone_last4_modal"
                class="booking-verify-input"
                inputmode="numeric"
                maxlength="4"
                placeholder="e.g. 6789"
            >
            <p class="form-message error" data-booking-verify-error hidden>{{ __('ui.home.input_4_digits_error') }}</p>

            <div class="crop-actions">
                <button type="button" class="btn btn-outline" data-close-booking-verify>{{ __('ui.home.cancel') }}</button>
                <button type="button" class="btn" data-submit-booking-verify>{{ __('ui.home.continue') }}</button>
            </div>
        </div>
    </div>



    <section class="contact section" id="contact">
        <div class="container contact-box fade-in">
            <div>
                <div class="contact-head-actions">
                    <p class="eyebrow">{{ __('ui.nav.contact') }}</p>
                    @if (!empty($showAdminMenu))
                        <button type="button" class="about-edit-btn" data-open-contact-editor aria-label="{{ __('ui.contact.edit_aria') }}">
                            <svg viewBox="0 0 24 24" class="admin-icon" aria-hidden="true"><path d="M4 20h4l10-10-4-4L4 16v4zm12-12 2 2"/></svg>
                        </button>
                    @endif
                </div>
                <h2 data-contact-section-title>{{ $contactSectionContent['title'] ?? __('ui.home.visit_studio') }}</h2>
                <p data-contact-section-description>{{ $contactSectionContent['description'] ?? __('ui.home.contact_description') }}</p>
            </div>
            <div class="contact-list">
                <p>
                    <strong>{{ __('ui.home.phone') }}:</strong>
                    <a
                        href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}"
                        data-contact-phone-link
                        data-contact-phone-value
                    >{{ $contact['phone'] }}</a>
                </p>
                <p>
                    <strong>{{ __('ui.home.instagram') }}:</strong>
                    <a
                        href="https://instagram.com/{{ ltrim($contact['instagram'], '@') }}"
                        target="_blank"
                        rel="noopener"
                        data-contact-instagram-link
                        data-contact-instagram-value
                    >{{ $contact['instagram'] }}</a>
                </p>
                <p><strong>{{ __('ui.home.studio_address') }}:</strong> <span data-contact-address-value>{{ $contact['address'] }}</span></p>
                <p><a href="{{ $contact['maps'] }}" target="_blank" rel="noopener" data-contact-maps-link>{{ __('ui.home.open_google_maps') }}</a></p>
            </div>
        </div>
    </section>

    @if (!empty($showAdminMenu))
        <div class="crop-modal contact-editor-modal" data-contact-editor-modal hidden>
            <div class="crop-modal-backdrop" data-close-contact-editor></div>
            <div class="crop-modal-dialog contact-editor-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.contact.edit_aria') }}">
                <div class="crop-modal-head">
                    <h2>{{ __('ui.contact.edit_title') }}</h2>
                    <button type="button" class="crop-close" data-close-contact-editor aria-label="{{ __('ui.contact.close_edit_aria') }}">x</button>
                </div>

                <form method="post" action="{{ route('contact.update') }}" class="service-modal-form" data-contact-editor-form>
                    @csrf
                    <p class="carousel-editor-feedback" data-contact-editor-feedback hidden></p>

                    <label for="contact_editor_title">{{ __('ui.contact.title_label') }}</label>
                    <input type="text" id="contact_editor_title" name="contact_title" value="{{ old('contact_title', $contactSectionContent['title'] ?? __('ui.home.visit_studio')) }}" required>

                    <label for="contact_editor_description">{{ __('ui.contact.description_label') }}</label>
                    <textarea id="contact_editor_description" name="contact_description" rows="4" required>{{ old('contact_description', $contactSectionContent['description'] ?? __('ui.home.contact_description')) }}</textarea>

                    <div class="crop-actions">
                        <button type="button" class="btn btn-outline" data-close-contact-editor>{{ __('ui.home.cancel') }}</button>
                        <button type="submit" class="btn" data-contact-editor-save>{{ __('ui.contact.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</main>
</div>
