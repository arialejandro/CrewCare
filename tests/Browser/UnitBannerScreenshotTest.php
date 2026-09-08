<?php

namespace Tests\Browser;

use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CAPTURA de la UNIDAD VIGENTE: el selector del topbar y la FRANJA que grita cuando se trabaja fuera de la
 * principal — la superficie que evita sellar documentos en la unidad equivocada. Corre contra la app REAL
 * pero es NETO-CERO: crea una 2ª unidad TEMPORAL (sin documentos → borrable), saca las capturas, y la BORRA.
 * `units` no se sella. La unidad vigente es de la sesión del navegador de Dusk (no filtra a la sesión real).
 *
 * Correr: php artisan dusk --filter=UnitBannerScreenshotTest
 * Capturas: tests/Browser/screenshots/unidad-selector-principal.png · unidad-franja-creacion.png · unidad-una-sola.png
 */
class UnitBannerScreenshotTest extends DuskTestCase
{
    private ?int $tmpUnitId = null;

    protected function tearDown(): void
    {
        try {
            if ($this->tmpUnitId) {
                Unit::where('id', $this->tmpUnitId)->delete();
            }
        } catch (\Throwable $e) {
            // best-effort
        }
        parent::tearDown();
    }

    public function test_captura_selector_y_franja(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();
        $prod  = CurrentProduction::get();
        $this->assertNotNull($prod, 'Debe haber producción vigente.');

        // 2ª unidad TEMPORAL. Se borra en tearDown (y abajo para el estado de una sola unidad).
        $u2 = Unit::create([
            'production_id' => (int) $prod->id,
            'name'          => 'Segunda unidad',
            'sort_order'    => 90,
            'is_active'     => true,
        ]);
        $this->tmpUnitId = (int) $u2->id;

        $this->browse(function (Browser $browser) use ($admin) {
            // 1) DOS unidades, en la PRINCIPAL: el selector aparece; NO hay franja.
            $browser->loginAs($admin)
                ->resize(1360, 950)
                ->visit('/settings/unidades')
                ->pause(800)
                ->assertSee('Unidad principal')   // chip principal del selector
                ->assertSee('Segunda unidad')     // chip de la 2ª unidad
                ->screenshot('unidad-selector-principal');

            // 2) Cambia a la 2ª unidad (clic en el chip del selector) → la FRANJA grita, sobre una pantalla
            //    de CREACIÓN (donde importa: que nadie cree el documento en la unidad equivocada).
            $browser->within('.cc-unit-switch', function (Browser $sel) {
                $sel->press('Segunda unidad');
            });
            $browser->pause(700)
                ->visit('/scoutings/create')
                ->pause(800)
                ->assertSee('Trabajando en')
                ->screenshot('unidad-franja-creacion');
        });

        // 3) UNA sola unidad: se borra la 2ª → ni selector ni franja (idéntico a hoy).
        $u2->delete();
        $this->tmpUnitId = null;

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->resize(1360, 950)
                ->visit('/settings/unidades')
                ->pause(800)
                ->assertDontSee('Trabajando en')
                ->screenshot('unidad-una-sola');
        });
    }
}
