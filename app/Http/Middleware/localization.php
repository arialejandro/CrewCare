<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class localization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Solo aplica un locale de la lista blanca (config('app.locales')); un valor
        // envenenado o null en la sesión NO debe cambiar el idioma → cae al default.
        $locale = session('locale');
        if ($locale && in_array($locale, config('app.locales', []), true)) {
            App::setLocale($locale);
        }
        return $next($request);
    }
}
