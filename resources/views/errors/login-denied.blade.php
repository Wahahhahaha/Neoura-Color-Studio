<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied | {{ $website['name'] ?? 'Neora Color Studio' }}</title>
    <link rel="icon" type="image/png" href="{{ $website['logo_url'] ?? asset('images/neora-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        :root {
            --accent-soft: {{ $website['theme_color_soft'] ?? '#F2D5C4' }};
            --accent-bold: {{ $website['theme_color_bold'] ?? '#C69278' }};
            --accent: var(--accent-soft);
            --accent-strong: var(--accent-bold);
        }
    </style>
</head>
<body>
<main class="error-shell">
    <div class="error-card error-card-elegant fade-in visible">
        <p class="error-brand">{{ $website['name'] ?? 'Neora Color Studio' }}</p>
        <p class="error-code">403</p>
        <h1>Access Denied</h1>
        <p class="error-text">You don't have access.</p>
        <div class="error-divider"></div>
        <a href="{{ route('home') }}" class="btn">Back to Home</a>
    </div>
</main>
</body>
</html>
