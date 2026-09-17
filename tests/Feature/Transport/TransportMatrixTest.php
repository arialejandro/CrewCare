<?php

namespace Tests\Feature\Transport;

use App\Models\ScoutingReport;
use App\Models\TransportPickupPoint;
use App\Models\TransportTravelTime;
use App\Support\CurrentProduction;

/**
 * Transportación · Pick up derivado — FASE 1: puntos de pickup + matriz de traslado.
 *
 * Catálogo de orígenes con coordenadas + tiempo por par origen→destino. El tiempo corregido a mano
 * persiste para ese par (unique); dos puntos distintos al mismo destino dan tiempos distintos.
 * 100% aditivo — no toca la corrida ni el pick up existentes.
 */
class TransportMatrixTest extends VehicleVerticalTestCase
{
    private function destino(string $name = 'Naucalpan', float $lat = 19.4700000, float $lng = -99.2400000): ScoutingReport
    {
        return ScoutingReport::forceCreate([
            'production_id' => CurrentProduction::id(),
            'location_name' => $name,
            'latitude'      => $lat,
            'longitude'     => $lng,
            'status'        => 'draft',
        ]);
    }

    private function punto(string $name = 'Churubusco', float $lat = 19.3567000, float $lng = -99.1620000): TransportPickupPoint
    {
        return TransportPickupPoint::create([
            'production_id' => CurrentProduction::id(),
            'name'          => $name,
            'lat'           => $lat,
            'lng'           => $lng,
            'is_active'     => 1,
        ]);
    }

    public function test_gate_del_editor(): void
    {
        $this->actingAsRole('line-producer'); // canLite
        $this->get(route('transport.matrix.index'))->assertStatus(403);
        $this->post(route('transport.point.store'), ['name' => 'X'])->assertStatus(403);

        $this->actingAsRole('safety-officer'); // canFull
        $this->get(route('transport.matrix.index'))->assertOk();
    }

    public function test_punto_de_pickup_crud(): void
    {
        $this->actingAsRole('safety-officer');

        $this->post(route('transport.point.store'), [
            'name' => 'Oficina de Producción', 'address' => 'Av. Insurgentes 1', 'lat' => '19.4100000', 'lng' => '-99.1700000',
        ])->assertSessionHasNoErrors();
        $p = TransportPickupPoint::where('name', 'Oficina de Producción')->first();
        $this->assertNotNull($p);
        $this->assertTrue($p->hasGeo());

        $this->post(route('transport.point.update', $p), ['name' => 'Oficina Prod', 'lat' => '19.42', 'lng' => '-99.18'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Oficina Prod', $p->fresh()->name);

        $this->post(route('transport.point.destroy', $p))->assertStatus(302);
        $this->assertFalse((bool) $p->fresh()->is_active);
    }

    public function test_tiempo_por_par_persiste_corregido_y_es_unico(): void
    {
        $this->actingAsRole('safety-officer');
        $punto = $this->punto();
        $dest  = $this->destino();

        // Guardar corregido a mano.
        $this->post(route('transport.time.save'), [
            'pickup_point_id' => $punto->id, 'scouting_id' => $dest->id, 'minutes' => 60, 'source' => 'corrected',
        ])->assertSessionHasNoErrors();

        $t = TransportTravelTime::where('pickup_point_id', $punto->id)->where('scouting_id', $dest->id)->first();
        $this->assertNotNull($t);
        $this->assertSame(60, $t->minutes);
        $this->assertTrue($t->isCorrected(), 'lo corregido a mano queda marcado');

        // Re-guardar el MISMO par NO duplica: actualiza (unique).
        $this->post(route('transport.time.save'), [
            'pickup_point_id' => $punto->id, 'scouting_id' => $dest->id, 'minutes' => 50, 'source' => 'corrected',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, TransportTravelTime::where('pickup_point_id', $punto->id)->where('scouting_id', $dest->id)->count());
        $this->assertSame(50, $t->fresh()->minutes, 'el tiempo corregido persiste para ese par');
    }

    public function test_dos_puntos_al_mismo_destino_dan_tiempos_distintos(): void
    {
        $this->actingAsRole('safety-officer');
        $dest = $this->destino();
        $a    = $this->punto('Churubusco', 19.3567, -99.1620);
        $b    = $this->punto('Condesa', 19.4110, -99.1710);

        $this->post(route('transport.time.save'), ['pickup_point_id' => $a->id, 'scouting_id' => $dest->id, 'minutes' => 60]);
        $this->post(route('transport.time.save'), ['pickup_point_id' => $b->id, 'scouting_id' => $dest->id, 'minutes' => 45]);

        $ta = TransportTravelTime::where('pickup_point_id', $a->id)->where('scouting_id', $dest->id)->value('minutes');
        $tb = TransportTravelTime::where('pickup_point_id', $b->id)->where('scouting_id', $dest->id)->value('minutes');
        $this->assertSame(60, $ta);
        $this->assertSame(45, $tb);
        $this->assertNotSame($ta, $tb, 'dos puntos distintos → tiempos distintos al mismo destino');
    }

    public function test_index_muestra_punto_y_destino(): void
    {
        $this->actingAsRole('safety-officer');
        $this->punto('Churubusco');
        $this->destino('Naucalpan');

        $this->get(route('transport.matrix.index'))->assertOk()
            ->assertSee('Churubusco')->assertSee('Naucalpan');
    }
}
