<?php

namespace Tests\Unit;

use App\Support\ShootCalendarBuilder;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Generación de días desde los fines de semana marcados + regla de la madrugada (PURO, sin BD).
 * Fechas deterministas via Carbon::next() para no depender del día en que corra el test.
 */
class ShootCalendarBuilderTest extends TestCase
{
    private function fridayAfter(string $d): Carbon
    {
        return Carbon::parse($d)->next(Carbon::FRIDAY)->startOfDay();
    }

    public function test_deriva_los_dias_hacia_atras_desde_el_fin_saltando_domingo(): void
    {
        $fri = $this->fridayAfter('2026-09-06');           // un viernes real
        $plan = ShootCalendarBuilder::build([['end' => $fri->toDateString(), 'days' => 5]], 6);

        $this->assertCount(5, $plan);
        $fechas = array_column($plan, 'date');
        $this->assertSame($fri->toDateString(), end($fechas), 'El último día debe ser el fin marcado.');
        $this->assertSame($fri->copy()->subDays(4)->toDateString(), $fechas[0], '5 días atrás = lunes.');
        foreach ($fechas as $f) {
            $this->assertNotSame(Carbon::SUNDAY, Carbon::parse($f)->dayOfWeek, 'Ningún día de rodaje cae en domingo.');
        }
        // El último día es DÍA por default (no se marcó luz).
        $this->assertTrue($plan[array_key_last($plan)]['is_last']);
        $this->assertSame('DÍA', $plan[array_key_last($plan)]['slug']);
    }

    public function test_viernes_noche_empuja_el_arranque_de_la_semana_siguiente_al_lunes(): void
    {
        $fri1 = $this->fridayAfter('2026-09-06');
        $fri2 = $fri1->copy()->addWeek();
        $sat1 = $fri1->copy()->addDay();                   // el sábado inmediato al viernes nocturno
        $lunes = $fri1->copy()->addDays(3);                // vie +1 sáb +2 dom +3 lun

        $plan = ShootCalendarBuilder::build([
            ['end' => $fri1->toDateString(), 'days' => 5, 'last_slug' => 'NOCHE'],
            ['end' => $fri2->toDateString(), 'days' => 6],
        ], 6);

        $sem2 = array_values(array_filter($plan, fn ($d) => $d['week_no'] === 2));
        $fechas2 = array_column($sem2, 'date');

        $this->assertNotContains($sat1->toDateString(), $fechas2, 'El sábado nocturno se consume: NO es día de rodaje.');
        $this->assertSame($lunes->toDateString(), $fechas2[0], 'El siguiente día de rodaje es el LUNES, no el sábado.');
        $this->assertCount(5, $sem2, 'La semana 2 arranca en lunes: 5 días, no 6.');
    }

    public function test_mixto_tambien_empuja_pero_dia_no(): void
    {
        $fri1 = $this->fridayAfter('2026-09-06');
        $fri2 = $fri1->copy()->addWeek();
        $sat1 = $fri1->copy()->addDay();

        $conMixto = ShootCalendarBuilder::build([
            ['end' => $fri1->toDateString(), 'days' => 5, 'last_slug' => 'MIXTO'],
            ['end' => $fri2->toDateString(), 'days' => 6],
        ], 6);
        $fechasMixto = array_column(array_filter($conMixto, fn ($d) => $d['week_no'] === 2), 'date');
        $this->assertNotContains($sat1->toDateString(), $fechasMixto, 'MIXTO también cruza la madrugada → empuja.');

        // DÍA (o sin marcar) NO empuja: el sábado sí se trabaja.
        $conDia = ShootCalendarBuilder::build([
            ['end' => $fri1->toDateString(), 'days' => 5, 'last_slug' => 'DÍA'],
            ['end' => $fri2->toDateString(), 'days' => 6],
        ], 6);
        $fechasDia = array_column(array_filter($conDia, fn ($d) => $d['week_no'] === 2), 'date');
        $this->assertContains($sat1->toDateString(), $fechasDia, 'Con día normal el sábado SÍ se trabaja (no empuja).');
    }

    public function test_la_regla_solo_mira_el_ultimo_dia_no_entre_semana(): void
    {
        // El builder solo recibe last_slug (el último día). Un nocturno a media semana NO es entrada de
        // la regla → no puede empujar nada. Se comprueba que marcar NOCHE en una semana no altera las
        // FECHAS de esa misma semana (solo la luz de su último día), y que sin semana siguiente no hay push.
        $fri = $this->fridayAfter('2026-09-06');
        $dia = ShootCalendarBuilder::build([['end' => $fri->toDateString(), 'days' => 5, 'last_slug' => 'DÍA']], 6);
        $noche = ShootCalendarBuilder::build([['end' => $fri->toDateString(), 'days' => 5, 'last_slug' => 'NOCHE']], 6);

        $this->assertSame(array_column($dia, 'date'), array_column($noche, 'date'),
            'La luz del último día NO cambia qué fechas tiene su propia semana.');
    }
}
