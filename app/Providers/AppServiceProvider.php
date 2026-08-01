<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // FEATURE FLAGS (2026-07-13, Pilar 5): directiva @feature('x')...@endfeature para
        // ocultar/mostrar módulos según el flag (config/features.php + tabla feature_flags).
        \Illuminate\Support\Facades\Blade::if('feature', function ($key) {
            return \App\Support\Features::enabled($key);
        });

        // PAGINACIÓN (2026-07-07): vista de marca PROPIA en TODA la app (markup .cc-pager, estilos
        // en layouts/_brand-theme). El default de Laravel era `tailwind` (enlaces pelones sin
        // Tailwind). Un markup propio evita chocar con estilos .page-link por-vista
        // (usuarioscrud/dsr) y da un diseño pulido y consistente con la marca. Un punto de verdad.
        Paginator::defaultView('pagination.brand');
        Paginator::defaultSimpleView('pagination.simple-brand');

        // BRANDING (2026-06-28): comparte $branding a TODAS las vistas (logo del cliente, nombre de
        // marca, título/PWA, color primario) y sobreescribe el nombre de la PWA desde settings.
        // try/catch: en consola/migraciones (sin BD lista) NO debe romper el arranque.
        try {
            $branding = \App\Support\Branding::all();
            \Illuminate\Support\Facades\View::share('branding', $branding);

            // El manifest de la PWA y el nombre se leen en tiempo de render del @laravelPWA.
            config([
                'laravelpwa.name'                => $branding['app_title'],
                'laravelpwa.manifest.name'       => $branding['app_title'],
                'laravelpwa.manifest.short_name' => $branding['brand_name'],
            ]);
        } catch (\Throwable $e) {
            // BD no disponible aún (p.ej. artisan en CI): comparte defaults para que $branding
            // SIEMPRE exista en las vistas y nunca dé "Undefined variable".
            \Illuminate\Support\Facades\View::share('branding', \App\Support\Branding::DEFAULTS);
        }

        // I18N (2026-07-07): expone el idioma activo + los disponibles a TODAS las vistas
        // (selector de idioma del header y del login). Un View::composer (no un share) porque el
        // locale lo fija el middleware DESPUÉS de bootear los providers; el composer se evalúa en
        // tiempo de render, con el locale ya resuelto por la sesión.
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            $view->with('currentLocale', app()->getLocale());
            $view->with('locales', config('app.locales', ['es']));
        });
    }
}
