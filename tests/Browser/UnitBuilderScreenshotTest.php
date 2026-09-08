<?php

namespace Tests\Browser;

use App\Models\Unit;
use App\Models\UnitMember;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\UnitMembership;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CAPTURA del CONSTRUCTOR de la 2ª unidad (Unidades 2c): comparación de CrewList por departamento con el
 * control de 3 estados por persona y el resumen de "compartidas". NETO-CERO: crea una 2ª unidad temporal,
 * marca un par de personas como compartidas y una exclusiva para que se lean los tres estados, saca la
 * captura, y BORRA la unidad (la FK ON DELETE CASCADE limpia unit_members). `units`/`unit_members` no se
 * sellan; la membresía de la sesión de Dusk no filtra a la real.
 *
 * Correr: php artisan dusk --filter=UnitBuilderScreenshotTest
 * Captura: tests/Browser/screenshots/unidad-constructor.png
 */
class UnitBuilderScreenshotTest extends DuskTestCase
{
    private ?int $tmpUnitId = null;

    protected function tearDown(): void
    {
        try {
            if ($this->tmpUnitId) {
                UnitMember::where('unit_id', $this->tmpUnitId)->delete();
                Unit::where('id', $this->tmpUnitId)->delete();
            }
        } catch (\Throwable $e) {
            // best-effort
        }
        parent::tearDown();
    }

    public function test_captura_del_constructor(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();
        $prod  = CurrentProduction::get();
        $this->assertNotNull($prod, 'Debe haber producción vigente.');

        $u2 = Unit::create([
            'production_id' => (int) $prod->id,
            'name'          => 'Segunda unidad',
            'sort_order'    => 90,
            'is_active'     => true,
        ]);
        $this->tmpUnitId = (int) $u2->id;

        // Marca en el PRIMER departamento del roster (arriba, visible en la captura): dos COMPARTIDAS
        // (ambas) y una EXCLUSIVA (solo) → se leen los tres estados con su color + el resumen.
        $roster = \App\Support\CrewRosterBuilder::build($admin, true);
        $crew   = collect($roster['groups'] ?? [])->first()['people'] ?? [];
        $crew   = collect($crew)->pluck('id')->all();
        if (isset($crew[0])) { UnitMembership::setState($u2->id, (int) $crew[0], 'ambas'); }
        if (isset($crew[1])) { UnitMembership::setState($u2->id, (int) $crew[1], 'ambas'); }
        if (isset($crew[2])) { UnitMembership::setState($u2->id, (int) $crew[2], 'solo'); }

        $this->browse(function (Browser $browser) use ($admin, $u2) {
            $browser->loginAs($admin)
                ->resize(1380, 1400)
                ->visit(route('production.units.builder', ['unit' => $u2->id], false))
                ->pause(900)
                ->assertSee('Constructor')
                ->assertSee('compartidas')
                ->assertSee('Ambas')
                ->screenshot('unidad-constructor');
        });
    }
}
