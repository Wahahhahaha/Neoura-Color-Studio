<header class="site-header" id="top">
    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}" alt="{{ $website['name'] ?? 'Neora Color Studio' }} logo" class="brand-logo">
            @if (!empty($website['show_name_in_brand']))
                <span>{{ $website['name'] ?? 'Neora Color Studio' }}</span>
            @endif
        </a>

        <button class="nav-toggle" type="button" aria-label="Toggle navigation" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="nav-links" data-nav-menu>
            <a href="{{ route('home') }}#home">Home</a>
            <a href="{{ route('home') }}#about">About</a>
            <a href="{{ route('home') }}#service">Service</a>
            <a href="{{ route('home') }}#contact">Contact Us</a>
        </nav>
    </div>
</header>


