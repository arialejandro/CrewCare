<?php

namespace Tests\Feature\Transport;

use App\Models\CallDay;
use App\Models\CallPersonSchedule;
use App\Models\ScoutingReport;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportPickupPoint;
use App\Models\TransportRunOccupant;
use App\Models\TransportTravelTime;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentProduction;
use App\Support\TransportAgenda;

/**
 * Transportación · Fase 4 — AGENDA POR VEHÍCULO. Agrupa por unidad en orden de hora y calcula qué
 * podría ADELANTARSE (híbrido traslado/hueco). Sólo informa, nunca mueve.
 */
class TransportAgendaTest extends VehicleVerticalTestCase
{
    private function draft(): TransportOrder
    {
        return TransportOrder::create([
            'production_id' => CurrentProduction::id(), 'order_date' => now()->toDateString(),
            'version' => 1, 'status' => 'draft',
        ]);
    }

    private function mkRun(TransportOrder $o, array $attrs): TransportOrderRun
    {
        return TransportOrderRun::create(array_merge([
            'transport_order_id' => $o->id, 'run_class' => 'fuera', 'run_type' => 'normal', 'is_active' => 1,
        ], $attrs));
    }

    // ── Agrupado + orden + aplicación aparte ─────────────────────────────────
    public function test_agrupa_por_vehiculo_y_aplicacion_va_aparte(): void
    {
        $o = $this->draft();
        $v = $this->makeVehicle('auto');
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '12:00']);
        $this->mkRun($o, ['vehicle_id' => $v->id, 'run_class' => 'evento', 'pickup_literal' => '08:00', 'end_literal' => '10:00', 'dest_text' => 'Mudanza']);
        // Aplicación: SIN vehículo → va a loose.
        $this->mkRun($o, ['run_type' => 'aplicacion', 'pickup_literal' => '07:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'Uber']);

        $ag = TransportAgenda::build($o->fresh());

        $this->assertCount(1, $ag['vehicles']);
        $items = $ag['vehicles'][0]['items'];
        $this->assertSame('08:00', $items[0]['start']['str'], 'ordena por hora: el evento (08:00) va primero');
        $this->assertSame('12:00', $items[1]['start']['str']);
        $this->assertCount(1, $ag['loose'], 'la corrida por aplicación va aparte');
    }

    // ── "Podría adelantarse" por HUECO (evento cuenta como corrida) ──────────
    public function test_hueco_cuando_no_hay_geolocalizacion(): void
    {
        $o = $this->draft();
        $v = $this->makeVehicle('auto');
        // Evento 08:00–10:00, luego corrida fuera a las 12:00 → hueco 120 min (sin traslado).
        $this->mkRun($o, ['vehicle_id' => $v->id, 'run_class' => 'evento', 'pickup_literal' => '08:00', 'end_literal' => '10:00', 'dest_text' => 'Taller']);
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '12:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'Casa']);

        $items = TransportAgenda::build($o->fresh())['vehicles'][0]['items'];
        $op = $items[1]['opportunity'];
        $this->assertNotNull($op, 'la corrida de las 12:00 puede adelantarse');
        $this->assertSame('gap', $op['method']);
        $this->assertSame(120, $op['minutes']);   // 12:00 − 10:00
    }

    public function test_sin_holgura_no_hay_aviso(): void
    {
        $o = $this->draft();
        $v = $this->makeVehicle('auto');
        // Evento hasta 10:00, siguiente a las 10:00 justo → sin holgura → sin aviso.
        $this->mkRun($o, ['vehicle_id' => $v->id, 'run_class' => 'evento', 'pickup_literal' => '08:00', 'end_literal' => '10:00', 'dest_text' => 'X']);
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '10:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'Y']);

        $items = TransportAgenda::build($o->fresh())['vehicles'][0]['items'];
        $this->assertNull($items[1]['opportunity']);
    }

    public function test_sin_fin_de_la_anterior_no_calcula(): void
    {
        $o = $this->draft();
        $v = $this->makeVehicle('auto');
        // La anterior NO tiene end_literal → no hay ancla → no se calcula.
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '08:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'A']);
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '12:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'B']);

        $items = TransportAgenda::build($o->fresh())['vehicles'][0]['items'];
        $this->assertNull($items[1]['opportunity']);
    }

    // ── "Podría adelantarse" CON TRASLADO (dos SET geolocalizadas) ───────────
    public function test_con_traslado_cuando_hay_geolocalizacion(): void
    {
        CallDay::updateOrCreate(
            ['production_id' => CurrentProduction::id(), 'call_date' => now()->toDateString()],
            ['general_call' => '07:00:00']
        );
        $pid = CurrentProduction::id();
        $p1 = TransportPickupPoint::create(['production_id' => $pid, 'name' => 'P1', 'lat' => 19.3, 'lng' => -99.1, 'is_active' => 1]);
        $p2 = TransportPickupPoint::create(['production_id' => $pid, 'name' => 'P2', 'lat' => 19.4, 'lng' => -99.2, 'is_active' => 1]);
        $d1 = ScoutingReport::forceCreate(['production_id' => $pid, 'location_name' => 'D1', 'latitude' => 19.5, 'longitude' => -99.3, 'status' => 'draft']);
        $d2 = ScoutingReport::forceCreate(['production_id' => $pid, 'location_name' => 'D2', 'latitude' => 19.6, 'longitude' => -99.4, 'status' => 'draft']);
        $mx = fn ($p, $d, $m) => TransportTravelTime::create(['production_id' => $pid, 'pickup_point_id' => $p->id, 'scouting_id' => $d->id, 'minutes' => $m, 'source' => 'corrected']);
        $mx($p1, $d1, 20);   // derivación de A
        $mx($p2, $d2, 60);   // derivación de B
        $mx($p2, $d1, 30);   // par destino(A=D1) → origen(B=P2): el traslado del encadenado

        $o = $this->draft();
        $v = $this->makeVehicle('auto');
        // A (SET) P1→D1, ocupante llamado 07:30 → pickup 07:10; fin 08:00.
        $A = $this->mkRun($o, ['vehicle_id' => $v->id, 'run_class' => 'set', 'pickup_point_id' => $p1->id, 'dest_location_ref' => $d1->id, 'end_literal' => '08:00']);
        // B (SET) P2→D2, ocupante llamado 10:00 → pickup 09:00.
        $B = $this->mkRun($o, ['vehicle_id' => $v->id, 'run_class' => 'set', 'pickup_point_id' => $p2->id, 'dest_location_ref' => $d2->id]);

        $ua = $this->makeUser('crew'); CallPersonSchedule::updateOrCreate(['production_id' => $pid, 'user_id' => $ua->id], ['schedule_offset_minutes' => 30]);  // 07:30
        TransportRunOccupant::create(['transport_order_run_id' => $A->id, 'source' => 'crew', 'user_id' => $ua->id, 'sort_order' => 1]);
        $ub = $this->makeUser('crew'); CallPersonSchedule::updateOrCreate(['production_id' => $pid, 'user_id' => $ub->id], ['schedule_offset_minutes' => 180]); // 10:00
        TransportRunOccupant::create(['transport_order_run_id' => $B->id, 'source' => 'crew', 'user_id' => $ub->id, 'sort_order' => 1]);

        $items = TransportAgenda::build($o->fresh())['vehicles'][0]['items'];
        // Orden: A (07:10) antes de B (09:00).
        $this->assertSame('07:10', $items[0]['start']['str']);
        $op = $items[1]['opportunity'];
        $this->assertNotNull($op);
        $this->assertSame('travel', $op['method']);
        // disponible = 08:00 (480) + traslado 30 = 510 (08:30); B a las 09:00 (540) → holgura 30.
        $this->assertSame(30, $op['minutes']);
    }

    // ── Ruta + flash al cancelar + gate ──────────────────────────────────────
    public function test_agenda_view_y_gate(): void
    {
        $o = $this->draft();
        $v = $this->makeVehicle('auto');
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '08:00']);

        $this->actingAsRole('line-producer'); // canLite
        $this->get(route('transport.order.agenda', $o))->assertOk();

        $this->actingAs($this->makeUser('crew')); // sin acceso a transporte
        $this->get(route('transport.order.agenda', $o))->assertStatus(403);
    }

    public function test_cancelar_informa_que_podria_adelantarse(): void
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        $o = TransportOrder::latest('id')->first();
        $v = $this->makeVehicle('auto');

        // Evento 08:00–10:00 + corrida 12:00 (holgura) + una tercera para cancelar.
        $this->mkRun($o, ['vehicle_id' => $v->id, 'run_class' => 'evento', 'pickup_literal' => '08:00', 'end_literal' => '10:00', 'dest_text' => 'X']);
        $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '12:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'Y']);
        $victim = $this->mkRun($o, ['vehicle_id' => $v->id, 'pickup_literal' => '14:00', 'pickup_place_kind' => 'text', 'pickup_place_text' => 'Z']);

        $res = $this->post(route('transport.order.run.destroy', [$o, $victim]));
        $res->assertSessionHas('info');
        $this->assertStringContainsString('adelantarse', (string) session('info'));
    }
}
