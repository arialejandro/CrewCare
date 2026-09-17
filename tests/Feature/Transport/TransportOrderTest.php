<?php

namespace Tests\Feature\Transport;

use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportParty;
use App\Models\TransportRunOccupant;

/**
 * Transportación · Bloque 2 — ORDEN de transportación (captura, Capa 2).
 *
 * La orden se CONGELA (no se sella): sin firmas, sin verificador público. canFull construye;
 * canLite (producción) consulta. Verifica: alta de orden/corrida/ocupante, la regla del vehículo
 * por tipo, el padrón ligero (se reusa), y que una orden congelada es inmutable.
 */
class TransportOrderTest extends VehicleVerticalTestCase
{
    private function makeOrderAs(string $role = 'safety-officer'): TransportOrder
    {
        $this->actingAsRole($role);
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()])
            ->assertStatus(302);

        $order = TransportOrder::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(TransportOrder::STATUS_DRAFT, $order->status);

        return $order;
    }

    public function test_canfull_arma_orden_corrida_y_ocupantes(): void
    {
        $order   = $this->makeOrderAs('safety-officer');
        $vehicle = $this->makeVehicle('van');

        // La página del editor renderiza.
        $this->get(route('transport.order.show', $order))->assertOk();

        // Corrida NORMAL sin vehículo → rechazada (§2).
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'normal'])
            ->assertSessionHasErrors('vehicle_id');
        $this->assertSame(0, $order->runs()->count());

        // Corrida NORMAL con vehículo → ok.
        $this->post(route('transport.order.run.store', $order), [
            'run_type'    => 'normal',
            'vehicle_id'  => $vehicle->id,
            'pickup_literal' => '06:30',
            'equipment'   => ['tag', 'hielera'],
        ])->assertSessionHasNoErrors();

        // Corrida de APLICACIÓN sin vehículo → permitida (único caso).
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'aplicacion'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $order->runs()->count());
        $run = $order->runs()->where('run_type', 'normal')->first();
        $this->assertSame(['tag', 'hielera'], $run->equipment);

        // Ocupante CAST a mano → alta en el padrón ligero.
        $this->post(route('transport.order.occupant.store', [$order, $run]), [
            'source' => 'cast', 'name' => 'Fulano de Tal', 'load_note' => 'Protagonista',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transport_parties', ['name' => 'Fulano de Tal', 'kind' => 'cast']);

        // El MISMO cast otra vez → NO duplica el padrón (se reusa).
        $this->post(route('transport.order.occupant.store', [$order, $run]), [
            'source' => 'cast', 'name' => 'Fulano de Tal',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, TransportParty::where('name', 'Fulano de Tal')->count());

        // Ocupante CREW por user_id.
        $crewUser = $this->makeUser('coordinator');
        $this->post(route('transport.order.occupant.store', [$order, $run]), [
            'source' => 'crew', 'user_id' => $crewUser->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, TransportRunOccupant::where('transport_order_run_id', $run->id)->count());
        $this->get(route('transport.order.show', $order))->assertOk();
    }

    public function test_canlite_consulta_pero_no_edita(): void
    {
        // Primero una orden creada por canFull.
        $order = $this->makeOrderAs('safety-officer');

        // Producción (canLite) ve el índice, pero no crea ni edita.
        $this->actingAsRole('line-producer');
        $this->get(route('transport.order.index'))->assertOk();
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()])->assertStatus(403);
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'aplicacion'])->assertStatus(403);
    }

    public function test_orden_congelada_no_se_edita(): void
    {
        $order = $this->makeOrderAs('safety-officer');
        $order->update(['status' => TransportOrder::STATUS_FROZEN]);

        // Aun con canFull, una orden congelada es inmutable.
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'aplicacion'])->assertStatus(403);

        // Pero se puede consultar (sólo lectura).
        $this->get(route('transport.order.show', $order))->assertOk();
    }

    public function test_congelar_emite_version_y_luego_es_inmutable(): void
    {
        $order   = $this->makeOrderAs('safety-officer');
        $vehicle = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'normal', 'vehicle_id' => $vehicle->id])
            ->assertSessionHasNoErrors();

        // Emitir = congelar: snapshot + status frozen.
        $this->post(route('transport.order.freeze', $order))->assertStatus(302);
        $order->refresh();
        $this->assertSame(TransportOrder::STATUS_FROZEN, $order->status);
        $this->assertNotEmpty($order->frozen_snapshot);
        $this->assertNotNull($order->frozen_at);
        $this->assertNotEmpty($order->uuid, 'La orden lleva UUID discreto para el pie.');

        // Ya congelada, no se edita.
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'aplicacion'])->assertStatus(403);
    }

    public function test_emitir_nueva_version_clona_conservando_run_key_y_encadena(): void
    {
        $v1      = $this->makeOrderAs('safety-officer');
        $vehicle = $this->makeVehicle('van');
        $this->post(route('transport.order.run.store', $v1), ['run_type' => 'normal', 'vehicle_id' => $vehicle->id, 'pickup_literal' => '06:00']);
        $origKey = $v1->runs()->first()->run_key;
        $this->assertNotEmpty($origKey);
        $this->post(route('transport.order.freeze', $v1))->assertStatus(302);

        // Emitir nueva versión = clonar la congelada.
        $this->post(route('transport.order.create'), ['order_date' => $v1->order_date->toDateString()])->assertStatus(302);
        $v2 = TransportOrder::where('prev_version_id', $v1->id)->first();
        $this->assertNotNull($v2, 'Se creó la versión encadenada.');
        $this->assertSame($v1->version + 1, $v2->version);
        $this->assertSame(TransportOrder::STATUS_DRAFT, $v2->status);
        // La corrida clonada CONSERVA su run_key (identidad estable).
        $this->assertSame($origKey, $v2->runs()->first()->run_key, 'El run_key sobrevive al versionar.');
    }

    public function test_editar_en_el_lugar_conserva_run_key(): void
    {
        $order   = $this->makeOrderAs('safety-officer');
        $vehicle = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'normal', 'vehicle_id' => $vehicle->id, 'pickup_literal' => '05:30']);
        $run = $order->runs()->first();
        $key = $run->run_key;

        $this->post(route('transport.order.run.update', [$order, $run]), [
            'run_type' => 'normal', 'vehicle_id' => $vehicle->id, 'pickup_literal' => '06:00',
        ])->assertSessionHasNoErrors();

        $run->refresh();
        $this->assertSame($key, $run->run_key, 'Editar en el lugar NO cambia la identidad.');
        $this->assertSame('06:00', $run->pickup_literal);
    }

    public function test_diff_marca_modificada_nueva_y_baja_por_run_key(): void
    {
        // v1 con dos corridas, congelada.
        $v1  = $this->makeOrderAs('safety-officer');
        $veh = $this->makeVehicle('van');
        $this->post(route('transport.order.run.store', $v1), ['run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:00']);
        $this->post(route('transport.order.run.store', $v1), ['run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '07:00']);
        $this->post(route('transport.order.freeze', $v1));
        $prev = \App\Support\TransportOrderSnapshot::build($v1);

        // v2 clona; muevo la 1ª media hora, borro la 2ª, agrego una nueva.
        $this->post(route('transport.order.create'), ['order_date' => $v1->order_date->toDateString()]);
        $v2 = TransportOrder::where('prev_version_id', $v1->id)->first();
        $runs = $v2->runs()->orderBy('id')->get();
        $this->post(route('transport.order.run.update', [$v2, $runs[0]]), ['run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:30']);
        $this->post(route('transport.order.run.destroy', [$v2, $runs[1]]));
        $this->post(route('transport.order.run.store', $v2), ['run_type' => 'aplicacion']);

        $v2->refresh();
        $current = \App\Support\TransportOrderSnapshot::build($v2);
        $diff = \App\Support\TransportOrderSnapshot::diff($current, $prev);

        $this->assertSame(1, $diff['summary']['counts']['modificada'], 'la 1ª sólo movió el pick up → modificada');
        $this->assertSame(1, $diff['summary']['counts']['nueva'], 'la de aplicación → nueva');
        $this->assertSame(1, $diff['summary']['counts']['baja'], 'la 2ª → baja');
        $this->assertContains('pickup', $diff['byKey'][$runs[0]->run_key]['changed'], 'el campo cambiado es el pick up');
    }

    public function test_notas_generales_se_guardan(): void
    {
        $order = $this->makeOrderAs('safety-officer');
        $this->post(route('transport.order.update', $order), ['notes_general' => 'Company move 07:00; sin corrida.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Company move 07:00; sin corrida.', $order->fresh()->notes_general);
    }
}
