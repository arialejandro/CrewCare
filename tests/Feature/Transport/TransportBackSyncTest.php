<?php

namespace Tests\Feature\Transport;

use App\Models\TransportAddress;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\TransportAttention;
use App\Support\TransportBackSync;
use App\Support\TransportPreload;
use Illuminate\Support\Facades\DB;

/**
 * Transportación · Fase 5 — LA VUELTA AL BACK + PROPUESTA + TRASLAPE + CONTADOR.
 * La orden devuelve el pick up refinado a call_person_schedules sin tocar cómo el back guarda.
 */
class TransportBackSyncTest extends VehicleVerticalTestCase
{
    private function draftViaRoute(): TransportOrder
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        return TransportOrder::latest('id')->first();
    }

    private function mark(int $userId): void
    {
        DB::table('transport_pickup_marks')->updateOrInsert(
            ['production_id' => CurrentProduction::id(), 'user_id' => $userId],
            ['is_marked' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    // ── La vuelta: ajustar siembra el pick up en el back ─────────────────────
    public function test_ajustar_siembra_pickup_en_el_back(): void
    {
        $o   = $this->draftViaRoute();
        $veh = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $o), [
            'run_class' => 'fuera', 'run_type' => 'normal', 'vehicle_id' => $veh->id,
            'pickup_literal' => '06:30', 'pickup_ref' => 'text', 'pickup_place_text' => 'Base de set',
        ]);
        $run = $o->runs()->first();
        $u   = $this->makeUser('crew');

        $this->post(route('transport.order.occupant.store', [$o, $run]), ['source' => 'crew', 'user_id' => $u->id]);

        $this->assertDatabaseHas('call_person_schedules', [
            'production_id'     => CurrentProduction::id(), 'user_id' => $u->id,
            'pickup_literal'    => '06:30', 'pickup_place_text' => 'Base de set',
        ]);
    }

    public function test_privada_llega_como_casa_sin_direccion_real(): void
    {
        $o   = $this->draftViaRoute();
        $veh = $this->makeVehicle('auto');
        $addr = TransportAddress::create([
            'production_id' => CurrentProduction::id(), 'label' => 'Casa de Juan',
            'address' => 'Calle Secreta 123', 'public_label' => 'CASA', 'is_private' => 1, 'is_active' => 1,
        ]);
        $this->post(route('transport.order.run.store', $o), [
            'run_class' => 'fuera', 'run_type' => 'normal', 'vehicle_id' => $veh->id,
            'pickup_literal' => '05:00', 'pickup_ref' => 'private:' . $addr->id,
        ]);
        $run = $o->runs()->first();
        $u   = $this->makeUser('crew');

        $this->post(route('transport.order.occupant.store', [$o, $run]), ['source' => 'crew', 'user_id' => $u->id]);

        $row = DB::table('call_person_schedules')->where('user_id', $u->id)->first();
        $this->assertSame($addr->publicLabel(), $row->pickup_place_text);
        $this->assertNotSame('Calle Secreta 123', $row->pickup_place_text, 'la dirección real NO llega al back');
        $this->assertNull($row->pickup_place_id);
    }

    public function test_cerrar_marca_na_a_quien_no_lleva(): void
    {
        $o     = $this->draftViaRoute();
        $veh   = $this->makeVehicle('auto');
        $rider = $this->makeUser('crew');   // este SÍ va en una corrida
        $idle  = $this->makeUser('crew');   // marcado pero SIN corrida → N/A
        $this->mark($idle->id);

        $this->post(route('transport.order.run.store', $o), [
            'run_class' => 'fuera', 'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '07:00',
        ]);
        $run = $o->runs()->first();
        $this->post(route('transport.order.occupant.store', [$o, $run]), ['source' => 'crew', 'user_id' => $rider->id]);

        $this->post(route('transport.order.freeze', $o))->assertStatus(302);

        $this->assertDatabaseHas('call_person_schedules', [
            'production_id' => CurrentProduction::id(), 'user_id' => $idle->id, 'pickup_literal' => 'N/A',
        ]);
    }

    // ── Propuesta (§2) ────────────────────────────────────────────────────────
    public function test_marcar_mas_genera_propuesta_no_corridas(): void
    {
        $u = $this->makeUser('crew');
        $this->mark($u->id);
        $o = $this->draftViaRoute();   // se crea SIN ese marcado dentro (o precarga como corrida de uno)

        // Si la precarga ya lo metió, lo saco para simular "marcado DESPUÉS de crear".
        TransportOrderRun::where('transport_order_id', $o->id)->update(['is_active' => 0]);

        $prop = TransportPreload::proposal($o->fresh());
        $this->assertSame(1, $prop['count']);
        $this->assertContains($u->id, $prop['pending_ids']);

        // Aceptar → ahora sí entra como corrida.
        $this->post(route('transport.order.proposal.accept', $o))->assertSessionHasNoErrors();
        $this->assertContains($u->id, TransportPreload::usersInOrder($o->fresh()));
    }

    // ── Traslape (§3) ─────────────────────────────────────────────────────────
    public function test_traslape_solo_cuando_comparten_unidad(): void
    {
        $o    = $this->draftViaRoute();
        $veh  = $this->makeVehicle('auto');
        $veh2 = $this->makeVehicle('auto');

        $fuera = TransportOrderRun::create(['transport_order_id' => $o->id, 'run_class' => 'fuera', 'run_type' => 'aeropuerto', 'vehicle_id' => $veh->id, 'pickup_literal' => '09:00', 'is_active' => 1]);
        $set   = TransportOrderRun::create(['transport_order_id' => $o->id, 'run_class' => 'set', 'run_type' => 'normal', 'vehicle_id' => $veh->id, 'is_active' => 1]);

        $this->assertCount(1, TransportBackSync::overlaps($o->fresh()), 'misma van → traslape');

        // Cambiar el set a otra van → sin traslape.
        $set->update(['vehicle_id' => $veh2->id]);
        $this->assertCount(0, TransportBackSync::overlaps($o->fresh()), 'vans distintas → nadie notifica');
    }

    // ── Contador de atención + gate ──────────────────────────────────────────
    public function test_contador_llega_a_lite_no_a_crew(): void
    {
        $u = $this->makeUser('crew');
        $this->mark($u->id);
        $this->actingAsRole('safety-officer');
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()]);
        // Deja el marcado FUERA de la orden para que cuente como propuesta.
        TransportOrderRun::where('transport_order_id', TransportOrder::latest('id')->value('id'))->update(['is_active' => 0]);

        $this->actingAsRole('line-producer'); // canLite (producción)
        $this->getJson(route('transport.attention.count'))->assertOk()->assertJson(['count' => 1]);

        $this->actingAs($this->makeUser('crew')); // sin acceso a transporte
        $this->getJson(route('transport.attention.count'))->assertOk()->assertJson(['count' => 0]);
    }
}
