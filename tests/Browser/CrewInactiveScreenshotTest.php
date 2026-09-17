<?php

namespace Tests\Browser;

use App\Models\Production;
use App\Models\Unit;
use App\Models\UnitMember;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CAPTURA de la lista de DADOS DE BAJA (/crew/dados-de-baja): un "Apagado con «unidad»" y un "Baja
 * individual" en el mismo departamento, para que se lea de un vistazo cuál es cuál; y el AVISO de
 * reintegrar (el flash que evidencia que falta contrato). NETO-CERO: crea 1 unidad temporal + 2 usuarios
 * temporales + su membresía, saca las capturas y BORRA todo en tearDown. Corre contra la base real.
 *
 * Correr: php artisan dusk --filter=CrewInactiveScreenshotTest
 * Capturas: tests/Browser/screenshots/crew-dados-de-baja.png · crew-reintegrar-aviso.png
 */
class CrewInactiveScreenshotTest extends DuskTestCase
{
    private ?int $tmpUnitId = null;
    private array $tmpUserIds = [];

    protected function tearDown(): void
    {
        try {
            if ($this->tmpUnitId) {
                UnitMember::where('unit_id', $this->tmpUnitId)->delete();
                Unit::where('id', $this->tmpUnitId)->delete();
            }
            if (! empty($this->tmpUserIds)) {
                User::whereIn('id', $this->tmpUserIds)->delete();
            }
        } catch (\Throwable $e) {
            // best-effort
        }
        parent::tearDown();
    }

    public function test_captura_lista_y_aviso(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();
        $prod  = Production::where('active', 1)->orderBy('id')->first();
        $this->assertNotNull($prod, 'Debe haber producción vigente.');

        // Unidad temporal YA desactivada (representa "unidad cerrada").
        $unit = Unit::create([
            'production_id' => (int) $prod->id,
            'name'          => 'Segunda unidad',
            'sort_order'    => 91,
            'is_active'     => false,
        ]);
        $this->tmpUnitId = (int) $unit->id;

        // Dos inactivos en el MISMO depto (zone = etiqueta de depto → se agrupan bajo "Arte").
        $conUnidad = User::forceCreate([
            'name' => 'Diego', 'lname' => 'Ramírez', 'email' => 'tmp-cu-' . uniqid() . '@crewcare.test',
            'password' => bcrypt('x'), 'activo' => 0, 'crewlist_visible' => 1, 'zone' => 'Arte',
        ]);
        $individual = User::forceCreate([
            'name' => 'Sofía', 'lname' => 'Herrera', 'email' => 'tmp-bi-' . uniqid() . '@crewcare.test',
            'password' => bcrypt('x'), 'activo' => 0, 'crewlist_visible' => 1, 'zone' => 'Arte',
        ]);
        $this->tmpUserIds = [(int) $conUnidad->id, (int) $individual->id];

        // "Apagado con la unidad": membresía exclusiva marcada (el estado exacto que deja la cascada).
        UnitMember::create([
            'unit_id' => $unit->id, 'user_id' => $conUnidad->id,
            'exclusive' => 1, 'deactivated_with_unit' => 1,
        ]);
        // $individual sin fila → "Baja individual".

        // (1) La lista: los dos tipos de baja lado a lado.
        // OJO: los badges .inact-why llevan text-transform:uppercase → Selenium getText() devuelve el
        // texto YA en mayúsculas; por eso se aserta 'BAJA INDIVIDUAL' / 'APAGADO CON', no la forma original.
        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->resize(1360, 900)
                ->visit('/crew/dados-de-baja')
                ->pause(700)
                ->screenshot('crew-dados-de-baja')   // captura ANTES de asertar (queda pase o falle)
                ->assertSee('Diego')
                ->assertSee('Sofía')
                ->assertSee('BAJA INDIVIDUAL')
                ->assertSee('APAGADO CON');           // badge "Apagado con «Segunda unidad»"
        });

        // (2) El aviso de reintegrar: clic en Reintegrar → aceptar el confirm → flash que evidencia el contrato.
        $this->browse(function (Browser $browser) {
            $browser->resize(1360, 900)
                ->visit('/crew/dados-de-baja')
                ->pause(500)
                ->press('Reintegrar')             // primer botón (Diego)
                ->acceptDialog()                  // "…NO emite su contrato — quedará PENDIENTE…"
                ->waitForText('PENDIENTE')
                ->pause(300)
                ->screenshot('crew-reintegrar-aviso');
        });
    }
}
