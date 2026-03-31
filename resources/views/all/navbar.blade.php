<header class="site-header" id="top">
    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="brand-logo">
            @if (!empty($website['show_name_in_brand']))
                <span>{{ $website['name'] ?? 'Neora Color Studio' }}</span>
            @endif
        </a>

        <button class="nav-toggle" type="button" aria-label="{{ __('ui.nav.toggle_navigation') }}" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-links" data-nav-menu>
            <a href="{{ route('home') }}#home">{{ __('ui.nav.home') }}</a>
            <a href="{{ route('home') }}#about">{{ __('ui.nav.about') }}</a>
            <a href="{{ route('home') }}#service">{{ __('ui.nav.service') }}</a>
            <a href="{{ route('home') }}#contact">{{ __('ui.nav.contact') }}</a>
        </nav>
    </div>
</header>


