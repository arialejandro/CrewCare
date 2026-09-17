<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Smoke de navegador: inicia sesión como super-admin y visita el índice de cada
 * módulo del panel, detectando páginas de error de Laravel y sacando screenshot
 * de cada uno. Corre contra la app REAL (crewcarerr.test / BD crewcare); NO resetea
 * la BD (no usa DatabaseMigrations) → seguro para el entorno demo.
 *
 * Correr: php artisan dusk --filter=SmokeTest
 * Screenshots en tests/Browser/screenshots/smoke-*.png
 */
class SmokeTest extends DuskTestCase
{
    /** Módulo (etiqueta) => URL del índice del panel. */
    private array $modules = [
        'dashboard'        => '/',
        'accidentes'       => '/accidents',
        'dsr'              => '/dsr-reports',
        'scoutings'        => '/scoutings',
        'actos-inseguros'  => '/unsafeacts',
        'condiciones'      => '/unsafeconds',
        'ambulancia'       => '/ambulancia',
        'inspeccion'       => '/inspeccion',
        'inspeccion-actas' => '/inspeccion/actas',
        'pae'              => '/pae',
        'wrap'             => '/wrap',
        'mapeo-riesgos'    => '/mapeo-riesgos',
        'permisos'         => '/permisos',
        'vigilancia'       => '/vigilancia',
        'consumables'      => '/consumables',
        'standards'        => '/standards',
        'hazard-events'    => '/hazard-events',
        'sfx'              => '/sfx',
        'features'         => '/settings/features',
        'roles'            => '/rolescrud',
    ];

    /** Señales FUERTES de página de error (prácticamente nunca en contenido legítimo). */
    private const ERROR_MARKERS = [
        'SQLSTATE[',
        'QueryException',
        'ErrorException',
        'ParseError',
        'Call to undefined',
        'Undefined variable $',
        'syntax error, unexpected',
        'Class &quot;App',
        'Whoops, looks like',
    ];

    public function test_todos_los_modulos_cargan_sin_error(): void
    {
        $user = User::where('email', 'admin@127.0.0.1')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);

            $fail = [];
            foreach ($this->modules as $name => $url) {
                try {
                    $browser->visit($url)->pause(600);
                    $browser->screenshot('smoke-' . $name);

                    if (str_contains($browser->driver->getCurrentURL(), '/login')) {
                        $fail[$name] = 'redirigió a login (auth)';
                        continue;
                    }
                    $src = $browser->driver->getPageSource();
                    foreach (self::ERROR_MARKERS as $marker) {
                        if (str_contains($src, $marker)) {
                            $fail[$name] = 'error: ' . $marker;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $fail[$name] = get_class($e) . ': ' . substr($e->getMessage(), 0, 120);
                }
            }

            fwrite(STDERR, "\n=== SMOKE POR MÓDULO ===\n");
            foreach ($this->modules as $name => $url) {
                fwrite(STDERR, sprintf("%-18s %-20s %s\n", $name, $url, $fail[$name] ?? 'OK'));
            }

            $this->assertEmpty(
                $fail,
                'Módulos con problema: ' . json_encode($fail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        });
    }
}
