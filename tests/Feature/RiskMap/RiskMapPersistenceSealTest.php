<?php

namespace Tests\Feature\RiskMap;

use App\Models\RiskMap;
use App\Models\RiskMapMarker;
use Illuminate\Support\Facades\DB;

/**
 * PERSISTENCIA, SELLADO e INVARIANTES del MAPEO DE RIESGOS (delta #50).
 *
 * Ejercita los endpoints REALES (mismos controladores de prod):
 *  - store() crea un borrador; seal() lo congela + firma; sellado = inmutable (403 al editar).
 *  - Marcadores por AJAX: las COORDENADAS (x_pct/y_pct) persisten redondeadas a 3 decimales.
 *  - Un peligro sólo se coloca si está EVALUADO en el scouting (eligibleEvents); si no → 422.
 *  - INVARIANTE DEL SELLO: la POSICIÓN del pin entra al hash (moverlo en BD rompe el sello);
 *    la posición COSMÉTICA de la etiqueta (label_x/label_y) NO (moverla no lo rompe).
 */
class RiskMapPersistenceSealTest extends RiskMapVerticalTestCase
{
    // ==================================================================================
    //  1. STORE / SEAL — ciclo de vida.
    // ==================================================================================

    public function test_seal_congela_y_firma_el_mapeo(): void
    {
        // Borrador con una vista (seal exige >= 1 vista).
        $map  = $this->makeDraftMap();
        $view = $this->addView($map);
        $this->addResourceMarker($view);

        $this->actingAsRole('safety-officer');
        $resp = $this->post(route('riskmaps.seal', $map->id));

        $resp->assertRedirect(route('riskmaps.document', $map->id));
        $map->refresh();
        $this->assertTrue($map->isSealed(), 'El mapeo debe quedar sellado.');
        $this->assertSame('RMAP-' . str_pad((string) $map->id, 4, '0', STR_PAD_LEFT), $map->folio);
        $this->assertNotNull($map->sealed_at);
        $this->assertTrue($map->signatures()->exists(), 'El sellado debe crear la firma digital.');
        $this->assertTrue($map->verifyLatestSignature(), 'El mapeo recién sellado debe verificar íntegro.');
    }

    public function test_seal_sin_vistas_rebota_con_error(): void
    {
        $map = $this->makeDraftMap(); // sin vistas
        $this->actingAsRole('safety-officer');

        $this->from(route('riskmaps.edit', $map->id))
            ->post(route('riskmaps.seal', $map->id))
            ->assertSessionHasErrors('seal');

        $this->assertFalse($map->fresh()->isSealed(), 'Sin vistas no debe sellarse.');
    }

    /**
     * #1 (auditoría 2026-08-21) — EL SELLO NO DEBE MENTIR. Si el scouting quita del
     * risk_assessment un peligro que YA estaba mapeado, su pin queda COLGANTE: sin
     * nombre ni normas, pero su event_id entraría al hash igual. seal() lo BLOQUEA;
     * el mapa NO se sella y el pin NO se borra (lo resuelve el safety).
     */
    public function test_seal_bloqueado_por_pin_de_peligro_colgante(): void
    {
        $sc   = $this->makeScouting(true);            // risk_assessment cita un evento real
        $eid  = $this->firstEligibleEventId($sc);
        $map  = $this->makeDraftMap($sc);
        $view = $this->addView($map);

        // El pin se coloca por el flujo REAL (AJAX), que exige que el peligro sea elegible.
        $this->actingAsRole('safety-officer');
        $this->postJson(route('riskmaps.markers.store', ['id' => $map->id, 'view' => $view->id]), [
            'kind' => 'hazard', 'event_id' => $eid, 'x_pct' => 50, 'y_pct' => 50,
        ])->assertOk();

        // El scouting DEJA de evaluar ese peligro después de mapearlo → el pin queda colgante.
        $sc->risk_assessment = [];
        $sc->save();
        $this->assertTrue($map->fresh()->hasOrphanHazards(), 'precondición: hay un pin colgante');

        // El sello se bloquea con error y el mapa sigue en borrador.
        $this->from(route('riskmaps.edit', $map->id))
            ->post(route('riskmaps.seal', $map->id))
            ->assertSessionHasErrors('seal');

        $this->assertFalse($map->fresh()->isSealed(), 'No se sella con peligros colgantes.');
        // NO se borran pines por nuestra cuenta: el marcador sigue ahí.
        $this->assertDatabaseHas('risk_map_markers', [
            'view_id' => $view->id, 'kind' => 'hazard', 'event_id' => $eid,
        ]);
    }

    /** Contraparte: con el peligro AÚN evaluado, el sello procede (la guarda no sobre-bloquea). */
    public function test_seal_procede_con_pin_de_peligro_elegible(): void
    {
        $sc   = $this->makeScouting(true);
        $eid  = $this->firstEligibleEventId($sc);
        $map  = $this->makeDraftMap($sc);
        $view = $this->addView($map);

        $this->actingAsRole('safety-officer');
        $this->postJson(route('riskmaps.markers.store', ['id' => $map->id, 'view' => $view->id]), [
            'kind' => 'hazard', 'event_id' => $eid, 'x_pct' => 50, 'y_pct' => 50,
        ])->assertOk();

        $this->assertFalse($map->fresh()->hasOrphanHazards(), 'sin colgantes: el peligro sigue evaluado');
        $this->post(route('riskmaps.seal', $map->id))
            ->assertRedirect(route('riskmaps.document', $map->id));
        $this->assertTrue($map->fresh()->isSealed(), 'con el peligro elegible, el sello procede');
    }

    public function test_mapeo_sellado_es_inmutable(): void
    {
        $map = $this->sealMap();
        $this->actingAsRole('safety-officer');

        // Editar meta de un sellado → 403 (draftOrFail aborta).
        $this->put(route('riskmaps.update', $map->id), ['title' => 'Otro'])->assertForbidden();
        // Borrar un sellado → 403.
        $this->delete(route('riskmaps.destroy', $map->id))->assertForbidden();
        // El editor de un sellado redirige al documento (no hay edición).
        $this->get(route('riskmaps.edit', $map->id))->assertRedirect(route('riskmaps.document', $map->id));

        $this->assertDatabaseHas('risk_maps', ['id' => $map->id, 'status' => 'sealed']);
    }

    // ==================================================================================
    //  2. MARCADORES — coordenadas persisten (AJAX).
    // ==================================================================================

    public function test_marcador_de_recurso_persiste_con_coordenadas_redondeadas(): void
    {
        $map  = $this->makeDraftMap();
        $view = $this->addView($map);
        $this->actingAsRole('safety-officer');

        $resp = $this->postJson(route('riskmaps.markers.store', ['id' => $map->id, 'view' => $view->id]), [
            'kind'          => 'resource',
            'resource_type' => 'botiquin',
            'x_pct'         => 33.333333,   // se redondea a 3 decimales
            'y_pct'         => 12.9,
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);

        $marker = RiskMapMarker::where('view_id', $view->id)->latest('id')->first();
        $this->assertNotNull($marker);
        $this->assertSame('resource', $marker->kind);
        $this->assertSame('botiquin', $marker->resource_type);
        // DECIMAL(6,3): la POSICIÓN se guarda redondeada a milésimas.
        $this->assertEquals(33.333, (float) $marker->x_pct);
        $this->assertEquals(12.900, (float) $marker->y_pct);
    }

    public function test_recurso_sin_tipo_es_rechazado_422(): void
    {
        $map  = $this->makeDraftMap();
        $view = $this->addView($map);
        $this->actingAsRole('safety-officer');

        // kind=resource pero sin resource_type → regla de negocio (422).
        $this->postJson(route('riskmaps.markers.store', ['id' => $map->id, 'view' => $view->id]), [
            'kind'  => 'resource',
            'x_pct' => 10, 'y_pct' => 10,
        ])->assertStatus(422);
    }

    // ==================================================================================
    //  3. PELIGRO — sólo eventos EVALUADOS en el scouting son elegibles.
    // ==================================================================================

    public function test_peligro_evaluado_en_el_scouting_es_elegible(): void
    {
        $sc   = $this->makeScouting(true);           // risk_assessment con un event_id real
        $map  = $this->makeDraftMap($sc);
        $view = $this->addView($map);
        $eid  = $this->firstEligibleEventId($sc);
        $this->assertGreaterThan(0, $eid, 'La fábrica debe sembrar al menos un HazardEvent.');

        $this->actingAsRole('safety-officer');
        $resp = $this->postJson(route('riskmaps.markers.store', ['id' => $map->id, 'view' => $view->id]), [
            'kind'     => 'hazard',
            'event_id' => $eid,
            'x_pct'    => 50, 'y_pct' => 50,
        ]);

        $resp->assertOk();
        $this->assertDatabaseHas('risk_map_markers', [
            'view_id'  => $view->id,
            'kind'     => 'hazard',
            'event_id' => $eid,
        ]);
    }

    public function test_peligro_no_evaluado_es_rechazado_422(): void
    {
        $sc   = $this->makeScouting(true);
        $map  = $this->makeDraftMap($sc);
        $view = $this->addView($map);
        $this->actingAsRole('safety-officer');

        // Un event_id que NO está en el risk_assessment del scouting no es elegible.
        $this->postJson(route('riskmaps.markers.store', ['id' => $map->id, 'view' => $view->id]), [
            'kind'     => 'hazard',
            'event_id' => 999999,
            'x_pct'    => 50, 'y_pct' => 50,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('risk_map_markers', ['view_id' => $view->id, 'event_id' => 999999]);
    }

    // ==================================================================================
    //  4. INVARIANTE DEL SELLO — la POSICIÓN del pin está dentro del hash.
    // ==================================================================================

    public function test_mover_la_posicion_del_pin_en_bd_rompe_el_sello(): void
    {
        $map    = $this->sealMap([], ['x_pct' => 40.000, 'y_pct' => 60.000]);
        $marker = $map->views->first()->markers->first();
        $this->assertNotNull($marker);

        // Baseline: intacto verifica íntegro.
        $this->assertTrue($map->verifyLatestSignature());

        // Mover el pin (contenido sellado) debe detectarse.
        DB::table('risk_map_markers')->where('id', $marker->id)->update(['x_pct' => 10.000]);

        $fresh = RiskMap::where('uuid', $map->uuid)->first();
        $this->assertFalse(
            $fresh->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: mover la posición del peligro en BD no rompió el sello.'
        );
    }

    public function test_mover_la_etiqueta_cosmetica_no_rompe_el_sello(): void
    {
        $map    = $this->sealMap();
        $marker = $map->views->first()->markers->first();

        // label_x_pct / label_y_pct son posición COSMÉTICA del chip: fuera del hash.
        DB::table('risk_map_markers')->where('id', $marker->id)
            ->update(['label_x_pct' => 5.000, 'label_y_pct' => 5.000, 'label_side' => 'left']);

        $fresh = RiskMap::where('uuid', $map->uuid)->first();
        $this->assertTrue(
            $fresh->verifyLatestSignature(),
            'La posición cosmética de la etiqueta NO debe invalidar el sello (está fuera del hash).'
        );
    }

    public function test_cambiar_pin_scale_no_rompe_el_sello(): void
    {
        $map = $this->sealMap();

        // pin_scale es preferencia de DISPLAY: canonicalSignaturePayload la descarta.
        DB::table('risk_maps')->where('id', $map->id)->update(['pin_scale' => 'lg']);

        $fresh = RiskMap::where('uuid', $map->uuid)->first();
        $this->assertTrue(
            $fresh->verifyLatestSignature(),
            'Cambiar el tamaño del pin (display) NO debe invalidar el sello.'
        );
    }
}
