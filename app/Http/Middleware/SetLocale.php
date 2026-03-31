<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'id'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = strtolower((string) $request->session()->get('locale', config('app.locale', 'en')));
        $locale = in_array($requested, self::SUPPORTED_LOCALES, true) ? $requested : config('app.fallback_locale', 'en');

        App::setLocale($locale);

        return $next($request);
    }
}
