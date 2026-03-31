@php
    $currentLocale = app()->getLocale();
@endphp
<div class="lang-switcher" aria-label="{{ __('ui.language') }}">
    <span>{{ __('ui.language') }}:</span>
    <a href="{{ route('lang.switch', ['locale' => 'id']) }}" class="{{ $currentLocale === 'id' ? 'is-active' : '' }}">ID</a>
    <span>/</span>
    <a href="{{ route('lang.switch', ['locale' => 'en']) }}" class="{{ $currentLocale === 'en' ? 'is-active' : '' }}">EN</a>
</div>
