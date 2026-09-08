<?php

namespace Tests\Browser;

use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CAPTURA de la pantalla de UNIDADES (/settings/unidades) en dos estados: VACÍA (una sola unidad, lo que ve
 * una producción normal) y CON UNA SEGUNDA ya creada. NETO-CERO: la 2ª unidad es temporal y se borra en
 * tearDown (sin documentos → borrable). Sólo lectura de la pantalla, no cambia nada permanente.
 *
 * Correr: php artisan dusk --filter=UnitsScreenScreenshotTest
 * Capturas: tests/Browser/screenshots/unidades-vacia.png · unidades-con-dos.png
 */
class UnitsScreenScreenshotTest extends DuskTestCase
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

    public function test_captura_pantalla_unidades(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();
        $prod  = CurrentProduction::get();
        $this->assertNotNull($prod, 'Debe haber producción vigente.');

        // 1) VACÍA: sólo la unidad principal (lo normal). Se captura ANTES de crear nada.
        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->resize(1360, 850)
                ->visit('/settings/unidades')
                ->pause(700)
                ->assertSee('Unidad principal')
                ->screenshot('unidades-vacia');
        });

        // 2) CON UNA SEGUNDA ya creada (temporal).
        $u2 = Unit::create([
            'production_id' => (int) $prod->id,
            'name'          => 'Segunda unidad',
            'sort_order'    => 90,
            'is_active'     => true,
        ]);
        $this->tmpUnitId = (int) $u2->id;

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->resize(1360, 850)
                ->visit('/settings/unidades')
                ->pause(700)
                ->assertSee('Segunda unidad')
                ->assertSee('Constructor')
                ->screenshot('unidades-con-dos');
        });
    }
}
