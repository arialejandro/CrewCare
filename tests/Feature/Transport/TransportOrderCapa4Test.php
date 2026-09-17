<?php

namespace Tests\Feature\Transport;

use App\Models\TransportAddress;
use App\Models\TransportOrder;
use App\Models\VehicleType;
use App\Support\CurrentProduction;
use App\Support\TransportOrderSnapshot;

/**
 * Transportación · Capa 4 — PDF congelado, pantalla del driver, direcciones privadas + allowlist,
 * enlace desde la vista lite, editor de tipos. Cubre la lista de VERIFICACIÓN del owner.
 */
class TransportOrderCapa4Test extends VehicleVerticalTestCase
{
    private function privateAddress(string $label = 'Casa Juan', string $street = 'Calle Secreta 123', ?string $public = null): TransportAddress
    {
        return TransportAddress::create([
            'production_id' => CurrentProduction::id(),
            'label'         => $label,
            'address'       => $street,
            'public_label'  => $public,
            'is_private'    => 1,
            'is_active'     => 1,
        ]);
    }

    private function newDraft(string $role = 'safety-officer'): TransportOrder
    {
        $this->actingAsRole($role);
        $this->post(route('transport.order.create'), ['order_date' => now()->toDateString()])->assertStatus(302);

        return TransportOrder::latest('id')->first();
    }

    // ── §1 · PDF ─────────────────────────────────────────────────────────────
    public function test_pdf_solo_exporta_una_congelada_no_un_borrador(): void
    {
        $order = $this->newDraft();
        $veh   = $this->makeVehicle('van');
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:00']);

        // Un borrador NO se imprime.
        $this->get(route('transport.order.pdf', $order))->assertRedirect(route('transport.order.show', $order));

        // Congelada SÍ.
        $this->post(route('transport.order.freeze', $order))->assertStatus(302);
        $res = $this->get(route('transport.order.pdf', $order->fresh()));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower((string) $res->headers->get('content-type')));
    }

    public function test_pdf_enmascara_las_privadas_a_casa_sin_excepcion(): void
    {
        $order = $this->newDraft();
        $veh   = $this->makeVehicle('van');
        $addr  = $this->privateAddress(); // Casa Juan / Calle Secreta / CASA
        $this->post(route('transport.order.run.store', $order), [
            'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:00',
            'pickup_ref' => 'private:' . $addr->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('transport.order.freeze', $order));
        $order->refresh();

        // La vista PÚBLICA del snapshot (la que rinde el PDF) enmascara a CASA.
        $pub    = TransportOrderSnapshot::publicView(TransportOrderSnapshot::resolvedFor($order));
        $pickup = $pub['runs'][0]['pickup'];
        $this->assertStringContainsString('CASA', $pickup);
        $this->assertStringNotContainsString('Calle Secreta', $pickup);
        $this->assertStringNotContainsString('Casa Juan', $pickup);

        $this->get(route('transport.order.pdf', $order))->assertOk();
    }

    public function test_pdf_bilingue_y_uuid_al_pie_de_todas_las_paginas(): void
    {
        $order = $this->newDraft();
        $veh   = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $order), ['run_type' => 'normal', 'vehicle_id' => $veh->id]);
        $this->post(route('transport.order.freeze', $order));
        $order->refresh();

        // ES y EN → ambos renderizan.
        session(['locale' => 'es']);
        $this->get(route('transport.order.pdf', $order))->assertOk();
        session(['locale' => 'en']);
        $this->get(route('transport.order.pdf', $order))->assertOk();

        // El pie con el UUID va en position:fixed → dompdf lo repite en CADA hoja.
        $pub  = TransportOrderSnapshot::publicView(TransportOrderSnapshot::resolvedFor($order));
        $html = view('transport.orders.pdf', [
            'order'      => $order,
            'snapshot'   => $pub,
            'diff'       => TransportOrderSnapshot::diff($pub, null),
            'roster'     => ['groups' => []],
            'production' => CurrentProduction::get(),
        ])->render();
        $this->assertStringContainsString('print-foot', $html);
        $this->assertStringContainsString('position: fixed', $html);
        $this->assertStringContainsString($order->uuid, $html);
    }

    public function test_la_orden_no_se_sella_ni_entra_al_verificador(): void
    {
        foreach (class_uses_recursive(TransportOrder::class) as $trait) {
            $this->assertStringNotContainsString('DigitalSignature', $trait, 'La orden NO usa firmas/sello.');
        }
    }

    // ── §2 · Pantalla del driver ─────────────────────────────────────────────
    public function test_driver_ve_sus_corridas_con_calle_real_y_no_las_de_otro(): void
    {
        $A = $this->makeUser('crew');
        $B = $this->makeUser('crew');

        $order = $this->newDraft('safety-officer');
        $veh   = $this->makeVehicle('van');
        $addr  = $this->privateAddress();
        // run1 → driver A, pick up en la privada.
        $this->post(route('transport.order.run.store', $order), [
            'run_type' => 'normal', 'vehicle_id' => $veh->id, 'driver_user_id' => $A->id,
            'pickup_literal' => '05:00', 'pickup_ref' => 'private:' . $addr->id,
        ])->assertSessionHasNoErrors();
        // run2 → driver B, destino texto PUNTO_B.
        $veh2 = $this->makeVehicle('auto');
        $this->post(route('transport.order.run.store', $order), [
            'run_type' => 'normal', 'vehicle_id' => $veh2->id, 'driver_user_id' => $B->id,
            'pickup_literal' => '06:00', 'dest_ref' => 'text', 'dest_text' => 'PUNTO_B',
        ])->assertSessionHasNoErrors();
        $this->post(route('transport.order.freeze', $order));

        // A ve su corrida CON la calle real; no la de B.
        $this->actingAs($A);
        $this->get(route('transport.driver.runs'))->assertOk()
            ->assertSee('Calle Secreta')->assertDontSee('PUNTO_B');

        // B ve la suya; no la de A ni la calle privada.
        $this->actingAs($B);
        $this->get(route('transport.driver.runs'))->assertOk()
            ->assertSee('PUNTO_B')->assertDontSee('Calle Secreta');

        // Un tercero sin corridas (y SIN permiso de transpo): 200, sin filtrar nada ajeno.
        $this->actingAs($this->makeUser('medic'));
        $this->get(route('transport.driver.runs'))->assertOk()->assertDontSee('Calle Secreta');
    }

    // ── §3/§4 · Enmascarado en la vista de la orden ──────────────────────────
    public function test_produccion_y_transpo_fuera_de_lista_ven_casa_en_la_orden(): void
    {
        $order = $this->newDraft('safety-officer');
        $veh   = $this->makeVehicle('van');
        $addr  = $this->privateAddress(); // Casa Juan / Calle Secreta / CASA
        $this->post(route('transport.order.run.store', $order), [
            'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:00',
            'pickup_ref' => 'private:' . $addr->id,
        ])->assertSessionHasNoErrors();
        // Congelada: sin formularios de captura (no hay picker que muestre la etiqueta).
        $this->post(route('transport.order.freeze', $order));

        // Producción (canLite) → CASA, sin etiqueta ni calle.
        $this->actingAsRole('line-producer');
        $this->get(route('transport.order.show', $order))->assertOk()
            ->assertSee('CASA')->assertDontSee('Casa Juan')->assertDontSee('Calle Secreta');

        // Transpo (canFull) FUERA de la allowlist → también CASA.
        $tw = $this->makeUser('safety-officer');
        $this->actingAs($tw);
        $this->get(route('transport.order.show', $order))->assertOk()
            ->assertSee('CASA')->assertDontSee('Casa Juan');

        // En la allowlist → ve la etiqueta interna.
        $addr->viewers()->syncWithoutDetaching([$tw->id]);
        $this->get(route('transport.order.show', $order))->assertOk()->assertSee('Casa Juan');
    }

    public function test_picker_filtra_privadas_por_allowlist_y_preserva_la_asignada(): void
    {
        $order = $this->newDraft('safety-officer'); // acting as A (creador, fuera de allowlist)
        $A     = auth()->user();
        $veh   = $this->makeVehicle('van');
        $priv  = $this->privateAddress('Casa Juan', 'Calle Secreta 123'); // privada
        TransportAddress::create([
            'production_id' => CurrentProduction::id(), 'label' => 'Bodega Norte',
            'address' => 'Nave 4', 'is_private' => 0, 'is_active' => 1,
        ]);

        // Corrida con pick up en la privada (asignada por quien sí la ve; aquí la fijamos directo).
        $this->post(route('transport.order.run.store', $order), [
            'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:00', 'pickup_ref' => 'private:' . $priv->id,
        ])->assertSessionHasNoErrors();
        $run = $order->runs()->first();

        // A (canFull, FUERA de la allowlist) abre el editor (borrador → con picker).
        $res = $this->get(route('transport.order.show', $order))->assertOk();
        $res->assertDontSee('Casa Juan');          // el picker NO lista la etiqueta interna
        $res->assertSee('Bodega Norte');            // la NO privada sí, para todos
        $res->assertSee('asignada');                // la asignada se preserva enmascarada

        // Guardar con el valor preservado NO pierde el pick up (sigue editable).
        $this->post(route('transport.order.run.update', [$order, $run]), [
            'run_type' => 'normal', 'vehicle_id' => $veh->id, 'pickup_literal' => '06:30', 'pickup_ref' => 'private:' . $priv->id,
        ])->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame('private', $run->pickup_place_kind);
        $this->assertSame((int) $priv->id, (int) $run->pickup_place_id, 'el pick up asignado se conserva');

        // Al entrar a la allowlist, el picker YA lista la etiqueta interna.
        $priv->viewers()->syncWithoutDetaching([$A->id]);
        $this->get(route('transport.order.show', $order))->assertOk()->assertSee('Casa Juan');
    }

    public function test_vista_lite_de_produccion_enlaza_a_la_orden(): void
    {
        $this->actingAsRole('line-producer'); // canLite
        $this->get(route('transport.lite'))->assertOk()->assertSee(route('transport.order.index'));
    }

    // ── §3 · Direcciones privadas: CRUD + allowlist + gate ───────────────────
    public function test_direcciones_crud_allowlist_y_gate(): void
    {
        // canLite no entra al CRUD.
        $this->actingAsRole('line-producer');
        $this->get(route('transport.address.index'))->assertStatus(403);

        // canFull: alta.
        $this->actingAsRole('safety-officer');
        $this->get(route('transport.address.index'))->assertOk();
        $this->post(route('transport.address.store'), ['label' => 'Hotel X', 'address' => 'Av Real 1', 'is_private' => 1])
            ->assertSessionHasNoErrors();
        $addr = TransportAddress::where('label', 'Hotel X')->first();
        $this->assertNotNull($addr);
        $this->assertSame('CASA', $addr->publicLabel());

        // Allowlist add/remove.
        $u = $this->makeUser('coordinator');
        $this->post(route('transport.address.viewer.add', $addr), ['user_id' => $u->id])->assertSessionHasNoErrors();
        $this->assertTrue($addr->viewers()->where('users.id', $u->id)->exists());
        $this->post(route('transport.address.viewer.remove', [$addr, $u]))->assertSessionHasNoErrors();
        $this->assertFalse($addr->viewers()->where('users.id', $u->id)->exists());

        // Baja = desactivar.
        $this->post(route('transport.address.destroy', $addr))->assertStatus(302);
        $this->assertFalse((bool) $addr->fresh()->is_active);
    }

    // ── §5 · Editor de tipos ─────────────────────────────────────────────────
    public function test_editar_perfil_del_tipo_no_cambia_vehiculos_ya_dados_de_alta(): void
    {
        $this->actingAsRole('safety-officer');
        $type   = VehicleType::where('code', 'auto')->firstOrFail();
        $veh    = $this->makeVehicle('auto'); // attr_values = snapshot del perfil al crear
        $before = $veh->attr_values;

        $this->post(route('transport.type.update', $type), [
            'code' => 'auto', 'name_es' => $type->name_es, 'sort_order' => $type->sort_order,
            'powertrain' => 'combustion', 'seats' => 9, 'has_cargo_box' => 1,
        ])->assertSessionHasNoErrors();

        $type->refresh();
        $veh->refresh();
        $this->assertSame(9, $type->profile()['seats'], 'el perfil del tipo cambió');
        $this->assertTrue($type->profile()['has_cargo_box']);
        // El vehículo conserva SU snapshot: 5 plazas y sin caja, NO los 9/caja del tipo editado.
        $this->assertSame(5, $veh->attr_values['seats'], 'el vehículo conserva sus 5 plazas');
        $this->assertFalse($veh->attr_values['has_cargo_box'], 'el vehículo NO adopta la caja del tipo');
        $this->assertEquals($before, $veh->attr_values, 'el contenido del vehículo NO cambió');
    }

    public function test_baja_de_tipo_con_vehiculos_desactiva_y_conserva(): void
    {
        $this->actingAsRole('safety-officer');
        $type = VehicleType::where('code', 'van')->firstOrFail();
        $veh  = $this->makeVehicle('van');

        $this->post(route('transport.type.destroy', $type))->assertStatus(302);
        $type->refresh();
        $this->assertFalse((bool) $type->is_active, 'el tipo queda DESACTIVADO');
        $this->assertDatabaseHas('vehicle_types', ['id' => $type->id]); // NO borrado físico
        $veh->refresh();
        $this->assertTrue((bool) $veh->is_active, 'el vehículo sigue');
        $this->assertNotEmpty($veh->attr_values);

        // Reactivar.
        $this->post(route('transport.type.restore', $type))->assertStatus(302);
        $this->assertTrue((bool) $type->fresh()->is_active);
    }

    public function test_crear_tipo_y_gate_del_editor(): void
    {
        // canLite no entra.
        $this->actingAsRole('line-producer');
        $this->get(route('transport.type.index'))->assertStatus(403);
        $this->post(route('transport.type.store'), ['code' => 'x', 'name_es' => 'X'])->assertStatus(403);

        // canFull crea con perfil.
        $this->actingAsRole('safety-officer');
        $this->get(route('transport.type.index'))->assertOk();
        $this->post(route('transport.type.store'), [
            'code' => 'grua_qa', 'name_es' => 'Grúa QA', 'powertrain' => 'combustion', 'tows' => 1,
        ])->assertSessionHasNoErrors();
        $new = VehicleType::where('code', 'grua_qa')->first();
        $this->assertNotNull($new);
        $this->assertTrue($new->profile()['tows']);
    }
}
