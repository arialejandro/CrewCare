<?php

namespace Tests\Feature\Calendario;

use App\Models\Production;
use App\Models\ShootDay;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\ProductionCalendar;
use Carbon\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\QaTestCase;

/**
 * El calendario a través de la app: generar días desde los fines de semana (con madrugada), y las
 * excepciones a mano que ganan sobre la regla. Gate settings.manage.
 */
class ShootCalendarControllerTest extends QaTestCase
{
    private function admin(): User
    {
        $u = $this->makeUser('super-admin');
        try {
            $u->givePermissionTo('settings.manage');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ya la trae (o Gate::before del super-admin); seguimos.
        }

        return $u;
    }

    private function producciónVigente(string $start): Production
    {
        $prod = Production::query()->orderBy('id')->first();
        Production::query()->where('id', '!=', $prod->id)->update(['active' => 0]);
        $prod->forceFill(['active' => 1, 'start_date' => $start, 'end_date' => null,
            'shoot_weeks' => null, 'shoot_days_per_week' => 6])->save();
        CurrentProduction::forget();
        ProductionCalendar::forget();

        return $prod;
    }

    private function fri(string $d): Carbon
    {
        return Carbon::parse($d)->next(Carbon::FRIDAY)->startOfDay();
    }

    public function test_la_pagina_de_dias_carga_y_lista_lo_marcado(): void
    {
        $prod = $this->producciónVigente('2026-10-01');
        ShootDay::create(['production_id' => $prod->id, 'shoot_date' => '2026-10-05', 'is_shoot_day' => true, 'week_no' => 1]);

        $this->actingAs($this->admin())
            ->get(route('production.shootdays.edit'))
            ->assertOk()
            ->assertSee('Días de rodaje')
            ->assertSee('Octubre 2026')   // la rejilla abre en el mes del primer día marcado
            ->assertSee('Día 1');          // el día marcado muestra su número de rodaje en la celda
    }

    public function test_generar_persiste_los_dias_y_aplica_la_madrugada(): void
    {
        $fri1 = $this->fri('2026-10-04');
        $fri2 = $fri1->copy()->addWeek();
        $sat1 = $fri1->copy()->addDay();
        $lunes = $fri1->copy()->addDays(3);

        $prod = $this->producciónVigente($fri1->copy()->subDays(4)->toDateString());

        $this->actingAs($this->admin())
            ->post(route('production.shootdays.generate'), [
                'weeks' => [
                    ['end' => $fri1->toDateString(), 'days' => 5, 'last_slug' => 'NOCHE'],
                    ['end' => $fri2->toDateString(), 'days' => 6],
                    ['end' => '', 'days' => '', 'last_slug' => 'DÍA'],   // fila vacía: se ignora
                ],
            ])
            ->assertRedirect();   // vuelve al calendario conservando el mes

        // El viernes nocturno consume el sábado: NO es día de rodaje; el lunes SÍ.
        $this->assertFalse(
            ShootDay::forProduction($prod->id)->whereDate('shoot_date', $sat1->toDateString())->where('is_shoot_day', 1)->exists(),
            'El sábado tras un viernes nocturno no debe ser día de rodaje.'
        );
        $this->assertTrue(
            ShootDay::forProduction($prod->id)->whereDate('shoot_date', $lunes->toDateString())->where('is_shoot_day', 1)->exists(),
            'El lunes sí es día de rodaje.'
        );
        // El último día de la semana 1 quedó marcado NOCHE en el calendario (sin leer ningún DSR).
        $this->assertSame('NOCHE',
            ShootDay::forProduction($prod->id)->whereDate('shoot_date', $fri1->toDateString())->first()->slug());
    }

    public function test_una_excepcion_a_mano_gana_sobre_la_regla_al_regenerar(): void
    {
        $fri1 = $this->fri('2026-10-04');
        $miercoles = $fri1->copy()->subDays(2);
        $prod = $this->producciónVigente($fri1->copy()->subDays(4)->toDateString());
        $admin = $this->admin();

        $genPayload = ['weeks' => [['end' => $fri1->toDateString(), 'days' => 5]]];
        $this->actingAs($admin)->post(route('production.shootdays.generate'), $genPayload)->assertRedirect();
        $this->assertTrue(ShootDay::forProduction($prod->id)->whereDate('shoot_date', $miercoles->toDateString())->where('is_shoot_day', 1)->exists());

        // A MANO: el miércoles es festivo (descanso).
        $this->actingAs($admin)->post(route('production.shootdays.toggle'), [
            'date' => $miercoles->toDateString(), 'is_shoot' => 0,
        ])->assertRedirect();

        // REGENERAR la misma semana NO revive el miércoles: la excepción manda.
        $this->actingAs($admin)->post(route('production.shootdays.generate'), $genPayload)->assertRedirect();

        $row = ShootDay::forProduction($prod->id)->whereDate('shoot_date', $miercoles->toDateString())->first();
        $this->assertNotNull($row);
        $this->assertFalse($row->is_shoot_day, 'La excepción a mano (descanso) sobrevive a la regeneración.');
        $this->assertTrue($row->is_manual);
    }

    public function test_set_luz_captura_el_nocturno_en_el_calendario(): void
    {
        $fri1 = $this->fri('2026-10-04');
        $prod = $this->producciónVigente($fri1->copy()->subDays(4)->toDateString());
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('production.shootdays.generate'), ['weeks' => [['end' => $fri1->toDateString(), 'days' => 5]]])->assertRedirect();

        $mar = $fri1->copy()->subDays(3); // un martes de esa semana
        $this->actingAs($admin)->post(route('production.shootdays.light'), [
            'date' => $mar->toDateString(), 'slug' => 'NOCHE',
        ])->assertRedirect();

        $row = ShootDay::forProduction($prod->id)->whereDate('shoot_date', $mar->toDateString())->first();
        $this->assertSame('NOCHE', $row->slug(), 'La luz se captura en el calendario, sin leer el DSR.');
        $this->assertTrue($row->is_manual);
    }

    public function test_generar_para_una_unidad_no_toca_la_principal(): void
    {
        $fri1 = $this->fri('2026-10-04');
        $fri2 = $fri1->copy()->addWeeks(5);
        $prod = $this->producciónVigente($fri1->copy()->subDays(4)->toDateString());
        $u2   = Unit::create(['production_id' => $prod->id, 'name' => 'Segunda unidad', 'sort_order' => 1, 'is_active' => true]);
        $admin = $this->admin();

        // PRINCIPAL: una semana de 5.
        $this->actingAs($admin)->post(route('production.shootdays.generate'), ['weeks' => [['end' => $fri1->toDateString(), 'days' => 5]]])->assertRedirect();
        $this->assertSame(5, ShootDay::whereNull('unit_id')->count());

        // 2ª UNIDAD: otra semana de 3 — sus días llevan su unit_id; la principal NO se toca.
        $this->actingAs($admin)->post(route('production.shootdays.generate'), ['unit' => $u2->id, 'weeks' => [['end' => $fri2->toDateString(), 'days' => 3]]])->assertRedirect();
        $this->assertSame(3, ShootDay::where('unit_id', $u2->id)->count(), 'La 2ª unidad tiene sus días con su id.');
        $this->assertSame(5, ShootDay::whereNull('unit_id')->count(), 'La principal quedó intacta.');

        // Una excepción a mano en la 2ª unidad se guarda con su unit_id.
        $festivo = $fri2->copy()->subDays(1)->toDateString();
        $this->actingAs($admin)->post(route('production.shootdays.toggle'), ['unit' => $u2->id, 'date' => $festivo, 'is_shoot' => 0])->assertRedirect();
        $row = ShootDay::where('unit_id', $u2->id)->whereDate('shoot_date', $festivo)->first();
        $this->assertNotNull($row);
        $this->assertFalse($row->is_shoot_day);
        $this->assertTrue($row->is_manual);
    }
}
