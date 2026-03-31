<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? (($website['name'] ?? 'Neora Color Studio') . ' | Personal Color Analysis') }}</title>
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
@include('all.language-switcher')
