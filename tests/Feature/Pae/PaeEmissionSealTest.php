<?php

namespace Tests\Feature\Pae;

use App\Models\EmergencyActionPlan;
use Illuminate\Support\Facades\DB;

/**
 * EMISIÓN, SELLADO, VERSIONADO y COMPOSICIÓN POR LOCACIÓN del PAE (endpoints REALES).
 *
 *  - store() CONGELA el payload (builder), crea y SELLA (firma el safety) → redirige al show.
 *  - Composición: 1 locación → 1 bloque; 2 locaciones (company move) → 2 bloques + is_move.
 *  - Límites: 1..2 locaciones (0 → required; 3 → max:2).
 *  - "Uno por llamado": editar emite una REVISIÓN que SUPERSEDE (apaga la anterior, hereda folio);
 *    is_active queda FUERA del hash (apagar no marca alterado).
 *  - Tamper: alterar el payload/día en BD rompe el sello.
 */
class PaeEmissionSealTest extends PaeVerticalTestCase
{
    // ==================================================================================
    //  1. STORE — congela + sella + redirige.
    // ==================================================================================

    public function test_store_emite_congela_y_sella(): void
    {
        $sc = $this->makeScouting();
        $so = $this->actingAsRole('safety-officer');

        $resp = $this->post(route('pae.store'), $this->storePayload([$sc->id], ['shoot_day' => 9]));

        $plan = EmergencyActionPlan::latest('id')->first();
        $this->assertNotNull($plan, 'El store debe persistir el PAE.');
        $resp->assertRedirect(route('pae.show', $plan->uuid));

        $this->assertSame(9, (int) $plan->shoot_day);
        $this->assertSame($so->id, (int) $plan->issued_by_id);
        $this->assertTrue((bool) $plan->is_active);
        $this->assertNotEmpty($plan->uuid);

        // SELLO al emitir: firma presente y el documento intacto verifica íntegro.
        $this->assertTrue($plan->signatures()->exists(), 'El PAE debe sellarse al emitirse.');
        $this->assertTrue($plan->verifyLatestSignature(), 'El PAE recién emitido debe verificar íntegro.');

        // El payload es una FOTOGRAFÍA congelada (no se recalcula al abrir).
        $this->assertIsArray($plan->payload);
        $this->assertArrayHasKey('header', $plan->payload);
        $this->assertArrayHasKey('locations', $plan->payload);
    }

    // ==================================================================================
    //  2. COMPOSICIÓN POR LOCACIÓN — 1 vs 2 (company move).
    // ==================================================================================

    public function test_una_locacion_un_bloque_sin_company_move(): void
    {
        $sc = $this->makeScouting();
        $this->actingAsRole('safety-officer');

        $this->post(route('pae.store'), $this->storePayload([$sc->id]));
        $plan = EmergencyActionPlan::latest('id')->first();

        $this->assertCount(1, $plan->pdata('locations', []));
        $this->assertFalse((bool) ($plan->pdata('company_move', [])['is_move'] ?? false));
        // El bloque congela el nombre y el hospital de ESA locación.
        $loc = $plan->pdata('locations')[0];
        $this->assertSame($sc->location_name, $loc['name']);
        $this->assertSame('Hospital QA Central', $loc['hospital']['name']);
    }

    public function test_dos_locaciones_dos_bloques_en_orden_con_company_move(): void
    {
        $a = $this->makeScouting(['location_name' => 'AAA-Primera']);
        $b = $this->makeScouting(['location_name' => 'BBB-Segunda']);
        $this->actingAsRole('safety-officer');

        // El ORDEN del arreglo manda (company move: A → B).
        $this->post(route('pae.store'), $this->storePayload([$a->id, $b->id], ['move_time' => '13:00']));
        $plan = EmergencyActionPlan::latest('id')->first();

        $locations = $plan->pdata('locations', []);
        $this->assertCount(2, $locations);
        $this->assertSame('AAA-Primera', $locations[0]['name']);
        $this->assertSame('BBB-Segunda', $locations[1]['name']);
        $this->assertTrue((bool) $plan->pdata('company_move')['is_move']);
    }

    public function test_store_exige_al_menos_una_locacion(): void
    {
        $this->actingAsRole('safety-officer');

        // Sin locaciones → required (tras limpiar enteros positivos).
        $this->post(route('pae.store'), $this->storePayload([]))->assertSessionHasErrors('scoutings');
        $this->assertDatabaseCount('emergency_action_plans', 0);
    }

    public function test_store_rechaza_mas_de_dos_locaciones(): void
    {
        $a = $this->makeScouting();
        $b = $this->makeScouting();
        $c = $this->makeScouting();
        $this->actingAsRole('safety-officer');

        // 3 locaciones → max:2.
        $this->post(route('pae.store'), $this->storePayload([$a->id, $b->id, $c->id]))
            ->assertSessionHasErrors('scoutings');
        $this->assertDatabaseCount('emergency_action_plans', 0);
    }

    // ==================================================================================
    //  3. "UNO POR LLAMADO" — el versionado: editar SUPERSEDE (no duplica el vigente).
    // ==================================================================================

    public function test_editar_emite_revision_que_supersede_a_la_anterior(): void
    {
        $sc = $this->makeScouting();
        $this->actingAsRole('safety-officer');

        // v1
        $this->post(route('pae.store'), $this->storePayload([$sc->id], ['shoot_day' => 4]));
        $v1 = EmergencyActionPlan::latest('id')->first();
        $this->assertSame(1, (int) $v1->revision);

        // Editar → v2 que reemplaza (supersedes_uuid = uuid de v1).
        $this->post(route('pae.store'), $this->storePayload([$sc->id], [
            'shoot_day'       => 4,
            'unit_name'       => 'Unidad B (corr.)',
            'supersedes_uuid' => $v1->uuid,
        ]));
        $v2 = EmergencyActionPlan::latest('id')->first();

        $this->assertSame(2, (int) $v2->revision);
        $this->assertSame($v1->id, (int) $v2->supersedes_id);

        // La vigente es UNA sola: v1 se apagó (is_active=0), v2 queda activa.
        $this->assertFalse((bool) $v1->fresh()->is_active, 'La versión anterior se apaga del listado.');
        $this->assertTrue((bool) $v2->fresh()->is_active);

        // Folio ESTABLE: v1 y v2 comparten el mismo PAE-#### (root ancla a la v1).
        $this->assertSame($v1->folio(), $v2->fresh()->folio());

        // Apagar la v1 NO la marca alterada: is_active está fuera del hash → sigue verificable.
        $this->assertTrue(
            $v1->fresh()->verifyLatestSignature(),
            'Superseder (is_active=0) NO debe romper el sello de la versión anterior.'
        );
    }

    // ==================================================================================
    //  4. TAMPER — el contenido congelado está dentro del hash.
    // ==================================================================================

    public function test_alterar_el_payload_en_bd_rompe_el_sello(): void
    {
        $plan = $this->sealPlan([], ['shoot_day' => 3]);
        $this->assertTrue($plan->verifyLatestSignature());

        // Reescribir el día dentro del payload congelado (contenido sellado) → alterado.
        $tampered = $plan->payload;
        $tampered['header']['shoot_day'] = 999;
        DB::table('emergency_action_plans')->where('id', $plan->id)
            ->update(['payload' => json_encode($tampered)]);

        $fresh = EmergencyActionPlan::where('uuid', $plan->uuid)->first();
        $this->assertFalse(
            $fresh->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: alterar el payload congelado no rompió el sello.'
        );
    }

    public function test_alterar_shoot_day_columna_rompe_el_sello(): void
    {
        $plan = $this->sealPlan([], ['shoot_day' => 3]);

        // shoot_day (columna, dentro de attributesToArray) es contenido sellado.
        DB::table('emergency_action_plans')->where('id', $plan->id)->update(['shoot_day' => 88]);

        $fresh = EmergencyActionPlan::where('uuid', $plan->uuid)->first();
        $this->assertFalse($fresh->verifyLatestSignature(), 'Alterar shoot_day en BD debe romper el sello.');
    }

    // ==================================================================================
    //  5. PREVIEW (patrón Wrap) — renderiza el documento REAL sin congelar ni sellar.
    // ==================================================================================

    public function test_preview_renderiza_borrador_sin_persistir_ni_sellar(): void
    {
        $sc = $this->makeScouting();
        $this->actingAsRole('safety-officer');

        $resp = $this->post(route('pae.preview'), $this->storePayload([$sc->id], ['shoot_day' => 9]));

        $resp->assertOk();
        $resp->assertSee('Hospital QA Central');     // el documento REAL se pinta en el borrador
        $resp->assertSee('Emitir y sellar');         // barra de confirmación (solo en borrador)
        // El preview NO congela ni sella: cero filas en la tabla.
        $this->assertDatabaseCount('emergency_action_plans', 0);
    }

    public function test_preview_valida_igual_que_store(): void
    {
        $a = $this->makeScouting();
        $b = $this->makeScouting();
        $c = $this->makeScouting();
        $this->actingAsRole('safety-officer');

        // 3 locaciones → max:2, misma validación que store; nada se persiste.
        $this->post(route('pae.preview'), $this->storePayload([$a->id, $b->id, $c->id]))
            ->assertSessionHasErrors('scoutings');
        $this->assertDatabaseCount('emergency_action_plans', 0);
    }

    // ==================================================================================
    //  6. MAPA DE RIESGOS — se PINTA cuando el payload trae la referencia (bug latente).
    // ==================================================================================

    public function test_show_pinta_el_mapa_de_riesgos_cuando_existe(): void
    {
        // Plan EN MEMORIA con una locación que trae la referencia del mapa sellado (folio + verify).
        $plan = new EmergencyActionPlan([
            'payload' => [
                'header'       => ['date' => now()->toDateString()],
                'org'          => ['crew' => []],
                'company_move' => ['is_move' => false],
                'locations'    => [[
                    'name'     => 'Loc con mapa',
                    'risks'    => [],
                    'hospital' => [],
                    'riskmap'  => ['folio' => 'RMAP-0042', 'verify_url' => 'https://qa.test/verificar/rmap/abc', 'views' => []],
                ]],
            ],
            'is_active' => 1,
        ]);

        $html = view('admin.pae.show', ['plan' => $plan])->render();

        $this->assertStringContainsString('RMAP-0042', $html);
        $this->assertStringContainsString('Mapa de riesgos', $html);
    }
}
