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
}
