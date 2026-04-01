<header class="site-header {{ !empty($showAdminMenu) ? 'site-header-with-admin' : '' }}" id="top" {{ !empty($showAdminMenu) ? 'data-admin-header-shell' : '' }}>
    @if (!empty($showAdminMenu))
        <button
            type="button"
            class="admin-top-sidebar-toggle admin-top-sidebar-toggle--column"
            data-admin-sidebar-toggle
            aria-label="Toggle sidebar"
            aria-expanded="true"
            title="Toggle sidebar"
        >
            <span></span><span></span><span></span>
        </button>
    @endif

    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}" data-admin-logo>
            <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="brand-logo">
            @if (!empty($website['show_name_in_brand']))
                <span>{{ $website['name'] ?? 'Neora Color Studio' }}</span>
            @endif
        </a>

        <button class="nav-toggle" type="button" aria-label="{{ __('ui.nav.toggle_navigation') }}" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-links" data-nav-menu>
            <a href="#home">{{ __('ui.nav.home') }}</a>
            <a href="#about">{{ __('ui.nav.about') }}</a>
            <a href="#service">{{ __('ui.nav.service') }}</a>
            <a href="#contact">{{ __('ui.nav.contact') }}</a>
        </nav>
    </div>
</header>

<div class="home-layout {{ !empty($showAdminMenu) ? 'with-admin-sidebar' : '' }}" data-home-layout>
    @if (!empty($showAdminMenu))
        @include('all.admin-sidebar')
    @endif

    <main class="home-main">
