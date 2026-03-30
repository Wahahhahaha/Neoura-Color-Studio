<header class="site-header" id="top">
    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}" data-admin-logo>
            <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="brand-logo">
            @if (!empty($website['show_name_in_brand']))
                <span>{{ $website['name'] ?? 'Neora Color Studio' }}</span>
            @endif
        </a>

        <button class="nav-toggle" type="button" aria-label="Toggle navigation" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-links" data-nav-menu>
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#service">Service</a>
            <a href="#contact">Contact Us</a>
        </nav>
    </div>
</header>

<div class="home-layout {{ !empty($showAdminMenu) ? 'with-admin-sidebar' : '' }}" data-home-layout>
    @if (!empty($showAdminMenu))
        @include('all.admin-sidebar')
    @endif

    <main class="home-main">
