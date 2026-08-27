<?php

namespace Tests\Feature\Transport;

use App\Models\CallDay;
use App\Models\CallPersonSchedule;
use App\Models\Position;
use App\Models\ScoutingReport;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportPickupPoint;
use App\Models\TransportPositionConfig;
use App\Models\TransportRunOccupant;
use App\Models\TransportTravelTime;
use App\Models\TransportVehicleAssignment;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\TransportPickupDeriver;
use Illuminate\Support\Facades\DB;

/**
 * Transportación · Pick up derivado — FASE 2. Dos clases, derivación, discreto, modo, asignación fija.
 */
class TransportDerivedPickupTest extends VehicleVerticalTestCase
{
    private function callDay(string $general = '07:00'): void
    {
        CallDay::updateOrCreate(
            ['production_id' => CurrentProduction::id(), 'call_date' => now()->toDateString()],
            ['general_call' => $general . ':00']
        );
    }

    private function sched(User $u, int $offset): void
    {
        CallPersonSchedule::updateOrCreate(
            ['production_id' => CurrentProduction::id(), 'user_id' => $u->id],
            ['schedule_offset_minutes' => $offset]
        );
    }

    private function point(string $name = 'Churubusco'): TransportPickupPoint
    {
        return TransportPickupPoint::create(['production_id' => CurrentProduction::id(), 'name' => $name, 'lat' => 19.35, 'lng' => -99.16, 'is_active' => 1]);
    }

    private function dest(string $name = 'Naucalpan'): ScoutingReport
    {
        return ScoutingReport::forceCreate(['production_id' => CurrentProduction::id(), 'location_name' => $name, 'latitude' => 19.47, 'longitude' => -99.24, 'status' => 'draft']);
    }

    private function matrix(TransportPickupPoint $p, ScoutingReport $d, int $min): void
    {
        TransportTravelTime::create(['production_id' => CurrentProduction::id(), 'pickup_point_id' => $p->id, 'scouting_id' => $d->id, 'minutes' => $min, 'source' => 'corrected']);
    }

    private function draft(): TransportOrder
    {
        return TransportOrder::create(['production_id' => CurrentProduction::id(), 'order_date' => now()->toDateString(), 'version' => 1, 'status' => 'draft']);
    }

    private function setRun(TransportOrder $order, TransportPickupPoint $p, ScoutingReport $d, array $attrs = []): TransportOrderRun
    {
        return TransportOrderRun::create(array_merge([
            'transport_order_id' => $order->id, 'run_class' => 'set', 'run_type' => 'normal',
            'pickup_point_id' => $p->id, 'dest_location_ref' => $d->id, 'travel_adjust_minutes' => 0, 'is_active' => 1,
        ], $attrs));
    }

    private function crewOcc(TransportOrderRun $run, User $u): void
    {
        TransportRunOccupant::create(['transport_order_run_id' => $run->id, 'source' => 'crew', 'user_id' => $u->id, 'name_snapshot' => User::displayName($u), 'sort_order' => 1]);
    }

    private function deriveTime(TransportOrder $order, TransportOrderRun $run): ?string
    {
        $order->load('runs.occupants');
        $d = TransportPickupDeriver::derive($run->fresh()->load('occupants'), TransportPickupDeriver::context($order));
        return $d['ok'] ? $d['time'] : null;
    }

    // ── Derivación ────────────────────────────────────────────────────────────
    public function test_set_deriva_llamado_menos_traslado_y_el_general_recalcula(): void
    {
        $this->callDay('07:00');
        $p = $this->point();
        $d = $this->dest();
        $this->matrix($p, $d, 60);
        $order = $this->draft();
        $run   = $this->setRun($order, $p, $d);
        $a = $this->makeUser('crew'); $this->sched($a, 0);   // llamado 07:00
        $b = $this->makeUser('crew'); $this->sched($b, 30);  // llamado 07:30
        $this->crewOcc($run, $a);
        $this->crewOcc($run, $b);

        // ancla = 07:00 (el más temprano) − 60 = 06:00.
        $this->assertSame('06:00', $this->deriveTime($order, $run));

        // Mover el general recalcula (borrador): 08:00 − 60 = 07:00.
        $this->callDay('08:00');
        $this->assertSame('07:00', $this->deriveTime($order, $run));
    }

    public function test_dos_puntos_distintos_dan_pickups_distintos(): void
    {
        $this->callDay('07:00');
        $d  = $this->dest();
        $p1 = $this->point('Churubusco'); $this->matrix($p1, $d, 60);
        $p2 = $this->point('Condesa');    $this->matrix($p2, $d, 45);
        $order = $this->draft();
        $u = $this->makeUser('crew'); $this->sched($u, 0);
        $r1 = $this->setRun($order, $p1, $d); $this->crewOcc($r1, $u);
        $r2 = $this->setRun($order, $p2, $d); $this->crewOcc($r2, $u);

        $this->assertSame('06:00', $this->deriveTime($order, $r1)); // 07:00 − 60
        $this->assertSame('06:15', $this->deriveTime($order, $r2)); // 07:00 − 45
    }

    public function test_traslado_tecleado_cuando_el_par_no_esta_en_la_matriz(): void
    {
        $this->callDay('07:00');
        $p = $this->point();
        $d = $this->dest(); // SIN fila en la matriz
        $order = $this->draft();
        $run = $this->setRun($order, $p, $d, ['travel_minutes' => 50]); // tecleado en la corrida
        $u = $this->makeUser('crew'); $this->sched($u, 0);
        $this->crewOcc($run, $u);

        $this->assertSame('06:10', $this->deriveTime($order, $run)); // 07:00 − 50 (fallback)
    }

    public function test_fuera_no_se_mueve_al_cambiar_el_general(): void
    {
        $this->callDay('07:00');
        $order = $this->draft();
        $run = TransportOrderRun::create(['transport_order_id' => $order->id, 'run_class' => 'fuera', 'run_type' => 'aeropuerto', 'pickup_literal' => '04:00', 'is_active' => 1]);

        $snap1 = \App\Support\TransportOrderSnapshot::build($order->fresh());
        $this->callDay('09:00');
        $snap2 = \App\Support\TransportOrderSnapshot::build($order->fresh());

        $this->assertSame($snap1['runs'][0]['pickup'], $snap2['runs'][0]['pickup'], 'una corrida FUERA no deriva del general');
        $this->assertStringContainsString('04:00', $snap2['runs'][0]['pickup']);
    }

    // ── Ejes / reglas ──────────────────────────────────────────────────────────
    public function test_set_aplicacion_deriva_sin_exigir_vehiculo(): void
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        $order = TransportOrder::latest('id')->first();
        $p = $this->point(); $d = $this->dest();

        // set + aplicacion SIN vehículo → permitido.
        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'set', 'run_type' => 'aplicacion', 'pickup_point_id' => $p->id, 'dest_location_ref' => $d->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $order->runs()->count());

        // set + normal SIN vehículo → rechazado (la regla del vehículo aplica en ambas clases).
        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'set', 'run_type' => 'normal', 'pickup_point_id' => $p->id, 'dest_location_ref' => $d->id,
        ])->assertSessionHasErrors('vehicle_id');
    }

    public function test_ocupante_mas_temprano_adelanta_el_pickup_y_avisa(): void
    {
        $this->callDay('07:00');
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        $order = TransportOrder::latest('id')->first();
        $p = $this->point(); $d = $this->dest(); $this->matrix($p, $d, 30);
        $veh = $this->makeVehicle('van');

        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'set', 'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_point_id' => $p->id, 'dest_location_ref' => $d->id,
        ])->assertSessionHasNoErrors();
        $run = $order->runs()->first();

        $late  = $this->makeUser('crew'); $this->sched($late, 60);  // 08:00
        $early = $this->makeUser('crew'); $this->sched($early, 0);  // 07:00

        // Primer ocupante (tarde): sin aviso.
        $this->post(route('transport.order.occupant.store', [$order, $run]), ['source' => 'crew', 'user_id' => $late->id])
            ->assertSessionMissing('warn');

        // Ocupante MÁS TEMPRANO: adelanta el pick up → avisa.
        $this->post(route('transport.order.occupant.store', [$order, $run]), ['source' => 'crew', 'user_id' => $early->id])
            ->assertSessionHas('warn');
    }

    // ── Modo discreto ───────────────────────────────────────────────────────────
    public function test_discreto_solo_para_jefatura_cast_o_aplicacion(): void
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        $order = TransportOrder::latest('id')->first();
        $veh = $this->makeVehicle('van');

        // Corrida de crew NORMAL con un ocupante NO-jefatura → NO elegible.
        $this->post(route('transport.order.run.store', $order), ['run_class' => 'fuera', 'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:00']);
        $run = $order->runs()->first();
        $plain = $this->makeUser('crew'); // sin puesto de jefatura
        $this->post(route('transport.order.occupant.store', [$order, $run]), ['source' => 'crew', 'user_id' => $plain->id]);
        $this->post(route('transport.order.run.discreet', [$order, $run]))->assertSessionHasErrors('discreet');
        $this->assertFalse((bool) $run->fresh()->is_discreet);

        // Corrida de APLICACIÓN → elegible → se marca y desaparece del PDF/producción pero SÍ del build.
        $this->post(route('transport.order.run.store', $order), ['run_class' => 'fuera', 'run_type' => 'aplicacion', 'pickup_literal' => '05:00']);
        $app = $order->runs()->where('run_type', 'aplicacion')->first();
        $this->post(route('transport.order.run.discreet', [$order, $app]))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $app->fresh()->is_discreet);

        $full   = \App\Support\TransportOrderSnapshot::build($order->fresh());
        $public = \App\Support\TransportOrderSnapshot::publicView($full);
        $keysFull   = collect($full['runs'])->pluck('run_key')->all();
        $keysPublic = collect($public['runs'])->pluck('run_key')->all();
        $this->assertContains($app->run_key, $keysFull, 'la discreta SÍ está en el build (el back la publica)');
        $this->assertNotContains($app->run_key, $keysPublic, 'la discreta NO aparece en la orden/PDF');
    }

    // ── Modo de la orden ────────────────────────────────────────────────────────
    public function test_modo_ligero_masivo_se_guarda(): void
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        $order = TransportOrder::latest('id')->first();

        $this->post(route('transport.order.update', $order), ['pickup_mode' => 'ligero'])->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->isLigero());
    }

    // ── Config (asignación fija por puesto) ─────────────────────────────────────
    public function test_asignacion_por_puesto_es_del_puesto_no_de_la_persona(): void
    {
        $this->actingAsRole('safety-officer');
        $pos = Position::first();
        $veh = $this->makeVehicle('van');

        $this->post(route('transport.config.assign.store'), ['subject_kind' => 'position', 'position_id' => $pos->id, 'vehicle_id' => $veh->id])
            ->assertSessionHasNoErrors();
        $a = TransportVehicleAssignment::where('position_id', $pos->id)->first();
        $this->assertNotNull($a);
        $this->assertNull($a->user_id, 'la asignación es del PUESTO, sobrevive un cambio de persona');
        $this->assertSame($veh->id, (int) $a->vehicle_id);
    }

    public function test_config_gate(): void
    {
        $this->actingAsRole('line-producer'); // canLite
        $this->get(route('transport.config.index'))->assertStatus(403);

        $this->actingAsRole('safety-officer');
        $this->get(route('transport.config.index'))->assertOk();
    }

    // ── Pantalla del driver muestra el derivado ─────────────────────────────────
    public function test_driver_muestra_el_pickup_derivado_no_en_blanco(): void
    {
        $this->callDay('07:00');
        $A = $this->makeUser('crew'); $this->sched($A, 0);
        $p = $this->point(); $d = $this->dest(); $this->matrix($p, $d, 60);

        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        $order = TransportOrder::latest('id')->first();
        $veh = $this->makeVehicle('van');
        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'set', 'run_type' => 'normal', 'vehicle_id' => $veh->id, 'driver_user_id' => $A->id,
            'pickup_point_id' => $p->id, 'dest_location_ref' => $d->id,
        ]);
        $run = $order->runs()->first();
        $this->post(route('transport.order.occupant.store', [$order, $run]), ['source' => 'crew', 'user_id' => $A->id]);
        $this->post(route('transport.order.freeze', $order))->assertStatus(302);

        // El driver ve la hora derivada (06:00), no en blanco.
        $this->actingAs($A);
        $this->get(route('transport.driver.runs'))->assertOk()->assertSee('06:00');
    }
}
