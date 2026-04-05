<section class="section">
    <div class="container">
        <div class="setting-wrap setting-shell">
            <div class="section-head setting-head">
                <h1>{{ __('ui.superadmin.setting.title') }}</h1>
                <p>{{ __('ui.superadmin.setting.description') }}</p>
            </div>

            @if (session('status'))
                <p class="setting-alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="setting-alert error">{{ $errors->first() }}</p>
            @endif

            <form method="post" action="{{ route('superadmin.setting.update') }}" enctype="multipart/form-data" class="setting-form">
                @csrf

                <div class="setting-logo-box">
                    <p class="setting-label">{{ __('ui.superadmin.setting.current_logo') }}</p>
                    <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ __('ui.superadmin.setting.website_logo_alt') }}" class="setting-logo-preview">
                </div>

                <label for="systemlogo">{{ __('ui.superadmin.setting.website_logo') }}</label>
                <input type="file" id="systemlogo" name="systemlogo" accept="image/*">

                <label for="systemname">{{ __('ui.superadmin.setting.website_name') }}</label>
                <input type="text" id="systemname" name="systemname" value="{{ old('systemname', $website['name'] ?? '') }}" required>

                @php
                    $showNameToggleChecked = old('website_name_visibility_toggle');
                    if ($showNameToggleChecked === null) {
                        $showNameToggleChecked = !empty($website['show_name_in_brand']);
                    } else {
                        $showNameToggleChecked = in_array((string) $showNameToggleChecked, ['1', 'true', 'on', 'yes'], true);
                    }
                @endphp
                <label class="setting-toggle-row" for="website_name_visibility_toggle">
                    <span>{{ __('ui.superadmin.setting.website_name_visibility') }}</span>
                    <span class="setting-toggle-wrap">
                        <input type="hidden" name="website_name_visibility_toggle" value="0">
                        <input
                            type="checkbox"
                            id="website_name_visibility_toggle"
                            name="website_name_visibility_toggle"
                            value="1"
                            class="setting-toggle-input"
                            {{ $showNameToggleChecked ? 'checked' : '' }}
                        >
                        <span class="setting-toggle-slider" aria-hidden="true"></span>
                    </span>
                </label>

                <label for="systemcontact">{{ __('ui.superadmin.setting.phone_number') }}</label>
                <input type="text" id="systemcontact" name="systemcontact" value="{{ old('systemcontact', $website['phone'] ?? '') }}">

                <label for="system_insta">Instagram</label>
                <input type="text" id="system_insta" name="system_insta" value="{{ old('system_insta', $website['instagram'] ?? '') }}">

                <label for="systemaddress">{{ __('ui.superadmin.setting.address') }}</label>
                <input type="text" id="systemaddress" name="systemaddress" value="{{ old('systemaddress', $website['address'] ?? '') }}">

                <div class="setting-theme-grid">
                    <div>
                        <label for="operational_open">{{ __('ui.superadmin.setting.operational_start') }}</label>
                        <input type="time" id="operational_open" name="operational_open" value="{{ old('operational_open', $website['operational_open'] ?? '10:00') }}" required>
                    </div>
                    <div>
                        <label for="operational_close">{{ __('ui.superadmin.setting.operational_end') }}</label>
                        <input type="time" id="operational_close" name="operational_close" value="{{ old('operational_close', $website['operational_close'] ?? '22:00') }}" required>
                    </div>
                </div>

                <div class="setting-bank-block">
                    <div class="setting-bank-head">
                        <p class="setting-label">{{ __('ui.superadmin.setting.bank_accounts') }}</p>
                        <button type="button" class="btn btn-outline" data-add-bank-row>{{ __('ui.superadmin.setting.add_bank') }}</button>
                    </div>

                    <div class="setting-bank-list" data-bank-list>
                        @php
                            $oldBankNames = old('bankname');
                            $oldBankNumbers = old('banknumber');
                            $renderRows = [];
                            if (is_array($oldBankNames) || is_array($oldBankNumbers)) {
                                $oldBankNames = is_array($oldBankNames) ? $oldBankNames : [];
                                $oldBankNumbers = is_array($oldBankNumbers) ? $oldBankNumbers : [];
                                $maxRows = max(count($oldBankNames), count($oldBankNumbers));
                                for ($i = 0; $i < $maxRows; $i++) {
                                    $renderRows[] = [
                                        'bankname' => (string) ($oldBankNames[$i] ?? ''),
                                        'banknumber' => (string) ($oldBankNumbers[$i] ?? ''),
                                    ];
                                }
                            } else {
                                $renderRows = $website['bank_accounts'] ?? [];
                            }
                            if (empty($renderRows)) {
                                $renderRows[] = ['bankname' => '', 'banknumber' => ''];
                            }
                        @endphp

                        @foreach ($renderRows as $row)
                            <div class="setting-bank-row" data-bank-row>
                                <div>
                                    <label>{{ __('ui.superadmin.setting.bank_name') }}</label>
                                    <input type="text" name="bankname[]" value="{{ $row['bankname'] ?? '' }}">
                                </div>
                                <div>
                                    <label>{{ __('ui.superadmin.setting.bank_number') }}</label>
                                    <input type="text" name="banknumber[]" value="{{ $row['banknumber'] ?? '' }}">
                                </div>
                                <button type="button" class="btn btn-outline setting-bank-remove" data-remove-bank-row>{{ __('ui.superadmin.setting.remove') }}</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="setting-actions">
                    <button type="submit" class="btn">{{ __('ui.superadmin.setting.save_setting') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
</main>
</div>

<template data-bank-row-template>
    <div class="setting-bank-row" data-bank-row>
        <div>
            <label>{{ __('ui.superadmin.setting.bank_name') }}</label>
            <input type="text" name="bankname[]">
        </div>
        <div>
            <label>{{ __('ui.superadmin.setting.bank_number') }}</label>
            <input type="text" name="banknumber[]">
        </div>
        <button type="button" class="btn btn-outline setting-bank-remove" data-remove-bank-row>{{ __('ui.superadmin.setting.remove') }}</button>
    </div>
</template>

<div class="crop-modal" data-crop-modal hidden>
    <div class="crop-modal-backdrop" data-close-crop-modal></div>
    <div class="crop-modal-dialog" role="dialog" aria-modal="true" aria-label="{{ __('ui.superadmin.setting.crop_logo_aria') }}">
        <div class="crop-modal-head">
            <h2>{{ __('ui.superadmin.setting.crop_logo_title') }}</h2>
            <button type="button" class="crop-close" data-close-crop-modal aria-label="{{ __('ui.superadmin.setting.close_crop_modal') }}">x</button>
        </div>

        <div class="crop-stage-wrap">
            <div class="crop-stage" data-crop-stage>
                <img src="" alt="{{ __('ui.superadmin.setting.logo_preview_alt') }}" data-crop-image>
                <div class="crop-box" data-crop-box>
                    <span class="crop-handle crop-handle-nw" data-crop-handle="nw" aria-hidden="true"></span>
                    <span class="crop-handle crop-handle-ne" data-crop-handle="ne" aria-hidden="true"></span>
                    <span class="crop-handle crop-handle-sw" data-crop-handle="sw" aria-hidden="true"></span>
                    <span class="crop-handle crop-handle-se" data-crop-handle="se" aria-hidden="true"></span>
                </div>
            </div>
        </div>

        <div class="crop-controls">
            <label for="cropZoom">{{ __('ui.superadmin.setting.zoom') }}</label>
            <input type="range" id="cropZoom" min="1" max="3" step="0.01" value="1" data-crop-zoom>
        </div>

        <div class="crop-actions">
            <button type="button" class="btn btn-outline" data-close-crop-modal>{{ __('ui.common.cancel') }}</button>
            <button type="button" class="btn" data-apply-crop>{{ __('ui.superadmin.setting.apply_crop') }}</button>
        </div>
    </div>
</div>
