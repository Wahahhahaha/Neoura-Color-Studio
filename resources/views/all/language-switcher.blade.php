@php
    $currentLocale = strtolower((string) app()->getLocale()) === 'id' ? 'id' : 'en';
    $nextLocale = $currentLocale === 'en' ? 'id' : 'en';
@endphp
<div class="lang-switcher {{ !empty($showAdminMenu) ? 'is-admin' : 'is-public' }}" aria-label="{{ __('ui.language') }}">
    <a
        href="{{ route('lang.switch', ['locale' => $nextLocale]) }}"
        class="lang-switcher-toggle"
        title="{{ __('ui.language') }}: {{ strtoupper($currentLocale) }}"
        aria-label="{{ __('ui.language') }}: {{ strtoupper($currentLocale) }}"
    >
        {{ strtoupper($currentLocale) }}
    </a>
</div>
