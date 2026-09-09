<?php

namespace Tests\Browser;

use App\Models\ShootDay;
use App\Models\User;
use App\Support\CurrentProduction;
use Carbon\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CAPTURA de la rejilla mensual de días de rodaje (la sesión del navegador in-app está expirada, así que
 * se muestra con Dusk, que autentica con loginAs — sin teclear contraseña). Corre contra la app REAL pero
 * es NETO-CERO: siembra días SINTÉTICOS marcados con una nota, saca el screenshot, y los BORRA en tearDown.
 * `shoot_days` no se sella, así que no toca ningún hash. Mes lejano (2028-03) sin datos reales.
 *
 * Correr: php artisan dusk --filter=ShootCalendarScreenshotTest
 * Screenshot: tests/Browser/screenshots/shoot-calendar.png
 */
class ShootCalendarScreenshotTest extends DuskTestCase
{
    private const MARK = '__shot_demo__';

    protected function tearDown(): void
    {
        try {
            ShootDay::where('note', self::MARK)->delete();
        } catch (\Throwable $e) {
            // best-effort
        }
        parent::tearDown();
    }

    public function test_captura_de_la_rejilla_mensual(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();
        $prod  = CurrentProduction::get();
        $this->assertNotNull($prod, 'Debe haber producción vigente.');
        $pid = (int) $prod->id;

        // Limpia por si una corrida previa dejó restos, y siembra un mes ilustrativo.
        ShootDay::where('note', self::MARK)->delete();

        $mon1 = Carbon::parse('2028-03-01')->next(Carbon::MONDAY);
        $filas = [];
        // Semana 1: lunes-sábado (6), el sábado NOCHE (fin de semana que cruza la madrugada).
        for ($i = 0; $i < 6; $i++) {
            $filas[] = ['date' => $mon1->copy()->addDays($i)->toDateString(), 'shoot' => true,
                'slug' => $i === 5 ? 'NOCHE' : null, 'manual' => false, 'wk' => 1];
        }
        // Semana 2: lunes-viernes; miércoles DESCANSO a mano (festivo), jueves MIXTO.
        $mon2 = $mon1->copy()->addWeek();
        for ($i = 0; $i < 5; $i++) {
            $filas[] = ['date' => $mon2->copy()->addDays($i)->toDateString(),
                'shoot' => $i !== 2, 'slug' => $i === 3 ? 'MIXTO' : null, 'manual' => $i === 2, 'wk' => 2];
        }
        // Semana 3: lunes AMANECER marcado a mano.
        $filas[] = ['date' => $mon1->copy()->addWeeks(2)->toDateString(), 'shoot' => true, 'slug' => 'AMANECER', 'manual' => true, 'wk' => 3];

        foreach ($filas as $f) {
            ShootDay::create([
                'production_id' => $pid, 'shoot_date' => $f['date'],
                'is_shoot_day' => $f['shoot'], 'slug_time' => $f['slug'],
                'week_no' => $f['wk'], 'is_manual' => $f['manual'], 'note' => self::MARK,
            ]);
        }

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->resize(1360, 1180)
                ->visit('/settings/dias-rodaje?month=2028-03')
                ->pause(700)
                ->screenshot('shoot-calendar');

            $browser->assertSee('Marzo 2028');
            $browser->assertSee('Día 1');
        });
    }
}
