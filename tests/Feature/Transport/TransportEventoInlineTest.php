<?php

namespace Tests\Feature\Transport;

use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\Vehicle;
use App\Support\CurrentProduction;
use App\Support\TransportOrderSnapshot;

/**
 * Transportación · Fase 3 — EVENTO de vehículo (run_class='evento', delta #116 `end_literal`) y
 * ALTA INLINE de vehículo por placa. Ambos aditivos; el evento hereda snapshot/diff/PDF/driver.
 */
class TransportEventoInlineTest extends VehicleVerticalTestCase
{
    private function draft(): TransportOrder
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        return TransportOrder::latest('id')->first();
    }

    // ── EVENTO ──────────────────────────────────────────────────────────────
    public function test_evento_guarda_descripcion_inicio_fin_con_vehiculo(): void
    {
        $order = $this->draft();
        $veh   = $this->makeVehicle('auto');

        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'evento', 'run_type' => 'normal', 'vehicle_id' => $veh->id,
            'event_desc' => 'Mudanza de utilería', 'event_start' => '07:00', 'event_end' => '12:00',
        ])->assertSessionHasNoErrors();

        $run = $order->runs()->first();
        $this->assertSame('evento', $run->run_class);
        $this->assertSame('Mudanza de utilería', $run->dest_text);   // descripción
        $this->assertSame('07:00', $run->pickup_literal);            // inicio
        $this->assertSame('12:00', $run->end_literal);               // fin
        $this->assertSame(0, $run->occupants()->count());            // sin ocupantes
    }

    public function test_evento_sin_vehiculo_rechazado(): void
    {
        $order = $this->draft();

        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'evento', 'run_type' => 'normal',
            'event_desc' => 'Mantenimiento', 'event_start' => '08:00', 'event_end' => '10:00',
        ])->assertSessionHasErrors('vehicle_id');

        $this->assertSame(0, $order->runs()->count());
    }

    public function test_evento_no_admite_ocupantes(): void
    {
        $order = $this->draft();
        $veh   = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'evento', 'run_type' => 'normal', 'vehicle_id' => $veh->id,
            'event_desc' => 'Traslado sin crew', 'event_start' => '06:00', 'event_end' => '07:00',
        ]);
        $run = $order->runs()->first();
        $u   = $this->makeUser('crew');

        $this->post(route('transport.order.occupant.store', [$order, $run]), ['source' => 'crew', 'user_id' => $u->id])
            ->assertSessionHasErrors('source');
        $this->assertSame(0, $run->occupants()->count());
    }

    public function test_evento_en_snapshot_pdf_y_driver(): void
    {
        $order = $this->draft();
        $A     = $this->makeUser('crew');
        $veh   = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $order), [
            'run_class' => 'evento', 'run_type' => 'normal', 'vehicle_id' => $veh->id, 'driver_user_id' => $A->id,
            'event_desc' => 'Recoge escenografía', 'event_start' => '07:00', 'event_end' => '11:30',
        ]);

        // Snapshot: la fila trae is_evento, la ventana en pickup y la descripción en dest.
        $snap = TransportOrderSnapshot::build($order->fresh());
        $row  = $snap['runs'][0];
        $this->assertTrue($row['is_evento']);
        $this->assertSame('Evento', $row['type_label']);
        $this->assertStringContainsString('07:00', $row['pickup']);
        $this->assertStringContainsString('11:30', $row['pickup']);
        $this->assertSame('Recoge escenografía', $row['dest']);

        // El editor (borrador) compila con el bloque de evento.
        $this->get(route('transport.order.show', $order))->assertOk()->assertSee('Recoge escenografía');

        // Congelar → PDF (dompdf) y pantalla del driver muestran el evento.
        $this->post(route('transport.order.freeze', $order))->assertStatus(302);
        $this->get(route('transport.order.pdf', $order))->assertOk();

        $this->actingAs($A);
        $this->get(route('transport.driver.runs'))->assertOk()->assertSee('Recoge escenografía');
    }

    // ── ALTA INLINE de vehículo por placa ────────────────────────────────────
    public function test_vehiculo_rapido_crea_por_placa(): void
    {
        $this->actingAsRole('safety-officer');
        $before = Vehicle::count();

        $res = $this->postJson(route('transport.order.vehicle.quick'), ['plate' => 'XYZ-9988']);
        $res->assertOk()->assertJson(['reused' => false]);

        $this->assertSame($before + 1, Vehicle::count());
        $v = Vehicle::where('plate', 'XYZ-9988')->first();
        $this->assertNotNull($v);
        $this->assertTrue((bool) $v->is_active);
        $this->assertNull($v->vehicle_type_id, 'nace sin tipo (verificación es acto aparte)');
    }

    public function test_vehiculo_rapido_reusa_placa_existente(): void
    {
        $this->actingAsRole('safety-officer');
        $existing = $this->makeVehicle('auto', ['plate' => 'ABC1234']);
        $before   = Vehicle::count();

        // Misma placa con OTRA puntuación → dedup fuerte: reúsa, no duplica.
        $res = $this->postJson(route('transport.order.vehicle.quick'), ['plate' => 'abc-1234']);
        $res->assertOk()->assertJson(['id' => $existing->id, 'reused' => true]);

        $this->assertSame($before, Vehicle::count());
    }

    public function test_vehiculo_rapido_gate_canfull(): void
    {
        $this->actingAsRole('line-producer'); // canLite, NO canFull
        $this->postJson(route('transport.order.vehicle.quick'), ['plate' => 'NO-9999'])->assertStatus(403);
    }
}
