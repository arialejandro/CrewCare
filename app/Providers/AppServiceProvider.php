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
        // (2026-08-30 · endurecimiento) PRODUCCIÓN: genera todas las URLs en https (los enlaces,
        // assets y correos apuntan a https detrás del reverse-proxy TLS). El local NO se toca:
        // sigue en http. La redirección http→https y HSTS los pone SecurityHeaders.
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // FEATURE FLAGS (2026-07-13, Pilar 5): directiva @feature('x')...@endfeature para
        // ocultar/mostrar módulos según el flag (config/features.php + tabla feature_flags).
        \Illuminate\Support\Facades\Blade::if('feature', function ($key) {
            return \App\Support\Features::enabled($key);
        });

        // (2026-08-11 · upgrade L9) Las directivas @checked/@selected/@disabled/@readonly/@required
        // ahora son NATIVAS del core (Laravel 9+). Se retiró el shim que las replicaba a mano en L8.8.

        // HEIC/HEIF (fotos de iPhone/iPad). Regla 'heic_ok': deja pasar cualquier imagen que NO
        // sea HEIC, y sólo acepta un HEIC cuando ESTE servidor puede convertirlo (Imagick+libheif).
        // Si no puede, el mensaje dice QUÉ HACER (cambiar a JPG) en vez de un error genérico —
        // nunca se guarda una foto que después no se podría ver. En los iPad/iPhone del set la
        // conversión ya ocurre en el navegador (public/js/cc-photo.js), así que el servidor
        // normalmente recibe un JPEG y esta regla ni se dispara.
        \Illuminate\Support\Facades\Validator::extend('heic_ok', function ($attribute, $value, $parameters, $validator) {
            if (!$value instanceof \Illuminate\Http\UploadedFile) {
                return true;
            }
            if (!\App\Support\ImageCompressor::isHeic($value)) {
                return true;
            }
            return \App\Support\ImageCompressor::heicSupport();
        }, 'Esta foto está en formato HEIC (iPhone/iPad). Cámbiala a JPG y vuelve a subirla: en tu iPhone entra a Ajustes › Cámara › Formatos y elige «Más compatible», o comparte la foto como JPG.');

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

        // Transportación · Fase 5: contador de atención del topbar (propuestas + traslapes de la orden
        // abierta). Sólo para quien tiene acceso lite (transpo + producción). Cacheado por-request.
        \Illuminate\Support\Facades\View::composer(['layouts.header', 'layouts._transport-notify'], function ($view) {
            $show = false;
            $count = 0;
            $u = \Illuminate\Support\Facades\Auth::user();
            if ($u && \App\Support\TransportAccess::canLite($u)) {
                $show  = true;
                $count = \App\Support\TransportAttention::countForUser((int) $u->id);
            }
            $view->with('__truckShow', $show)->with('__truckCount', $count);
        });
    }
}
