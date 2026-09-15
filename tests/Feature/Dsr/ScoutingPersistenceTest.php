<?php

namespace Tests\Feature\Dsr;

use App\Models\ActionItem;
use App\Models\ScoutingReport;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * PERSISTENCIA + SELLADO + LÓGICA DE NEGOCIO del SCOUTING (endpoints REALES).
 *
 * Cubre: crear (draft y final), editar, la autofirma (make_by/created_by_id/make_date fijados
 * server-side), el sellado SÓLO cuando status='final', la regla condicional SB-132, la
 * normalización de hospital_distance_km (vacío -> null; para no romper DECIMAL en modo estricto)
 * y el bloqueo PDCA que impide finalizar con acciones correctivas abiertas.
 */
class ScoutingPersistenceTest extends QaTestCase
{
    private function scoutPayload(array $overrides = []): array
    {
        return array_merge([
            'location_name'    => 'Locación Scout ' . Str::random(6),
            'location_address' => 'Calle Falsa 123',
            'production_type'  => 'Film',
            'manager_name'     => 'PM QA',
            'safety_rep_name'  => 'Safety QA',
            'nearest_hospital' => 'Hospital QA Norte',
            'status'           => 'draft',
        ], $overrides);
    }

    // =====================================================================
    //  STORE — draft
    // =====================================================================

    public function test_safety_crea_scouting_draft_y_persiste(): void
    {
        $user = $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload();

        $resp = $this->post(route('scoutings.store'), $payload);
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $this->assertDatabaseHas('scouting_reports', [
            'location_name' => $payload['location_name'],
            'status'        => 'draft',
            // AUTOFIRMA: make_by/created_by_id se fijan server-side (no del form).
            // make_by guarda el NOMBRE DE CRÉDITOS (User::displayName), no `->name`: ese es sólo el
            // primer nombre y en un documento firmado resulta ambiguo (dos "Genaro" en la misma
            // producción dejan de distinguirse). Se asserta la REGLA, no una cadena literal, para
            // que el test siga valiendo si cambia el usuario de prueba.
            'make_by'       => \App\Models\User::displayName($user),
            'created_by_id' => $user->id,
        ]);
    }

    public function test_scouting_draft_NO_se_sella(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['status' => 'draft']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($scout);
        $this->assertFalse($scout->signatures()->exists(), 'Un scouting en borrador NO debe sellarse.');
        $this->assertNull($scout->verifyLatestSignature(), 'Sin firma, la verificación es null (unsealed).');
    }

    // =====================================================================
    //  STORE — final => sellado
    // =====================================================================

    public function test_scouting_final_nace_sellado_y_verifica_integro(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['status' => 'final']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($scout);
        $this->assertTrue($scout->signatures()->exists(), 'Un scouting FINAL debe nacer sellado.');
        $this->assertNotEmpty($scout->uuid);

        $fresh = ScoutingReport::where('uuid', $scout->uuid)->first();
        $this->assertTrue(
            $fresh->verifyLatestSignature(),
            'FALSO POSITIVO/INTEGRIDAD: un scouting final intacto debe verificar VÁLIDO.'
        );
    }

    // =====================================================================
    //  BUSINESS RULE — hospital_distance_km vacío -> null; con valor -> se guarda
    // =====================================================================

    public function test_hospital_distance_km_vacio_persiste_null(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['hospital_distance_km' => '']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNull($scout->hospital_distance_km, 'Distancia vacía -> null (DECIMAL no acepta "" en modo estricto).');
    }

    public function test_hospital_distance_km_con_valor_persiste(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['hospital_distance_km' => '12.5']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertEquals(12.5, (float) $scout->hospital_distance_km);
    }

    // =====================================================================
    //  BUSINESS RULE — SB-132 condicional
    // =====================================================================

    public function test_sb132_actividades_especiales_sin_desglose_es_rechazado(): void
    {
        $this->actingAsRole('safety-officer');
        // special_activities=1 SIN sb132_details -> exige activity_type/scene/certified.
        $payload = $this->scoutPayload(['special_activities' => 1]);

        $this->post(route('scoutings.store'), $payload)->assertSessionHasErrors([
            'sb132_details.activity_type',
            'sb132_details.scene_number',
            'sb132_details.certified_personnel_required',
        ]);
        $this->assertDatabaseMissing('scouting_reports', ['location_name' => $payload['location_name']]);
    }

    public function test_sb132_con_desglose_completo_persiste(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload([
            'special_activities' => 1,
            'sb132_details'      => [
                'activity_type'                => ['stunts'],
                'scene_number'                 => 'Esc. 42',
                'certified_personnel_required' => 'Coordinador de stunts certificado.',
            ],
        ]);

        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scouting_reports', ['location_name' => $payload['location_name']]);
    }

    // =====================================================================
    //  EDIT / UPDATE — autofirma preservada
    // =====================================================================

    public function test_edicion_actualiza_pero_conserva_la_autofirma_original(): void
    {
        $autor = $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['location_name' => 'Antes ' . Str::random(6)]);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();
        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();

        // Edita como OTRO usuario: make_by NO debe cambiar (la autofirma original queda intacta).
        $editor = $this->actingAsRole('line-producer');
        $nuevoNombre = 'Después ' . Str::random(6);
        $resp = $this->put(route('scoutings.update', ['id' => $scout->id]), array_merge($payload, [
            'location_name' => $nuevoNombre,
        ]));
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $scout->refresh();
        $this->assertSame($nuevoNombre, $scout->location_name, 'La edición debe persistir.');
        $this->assertSame(\App\Models\User::displayName($autor), $scout->make_by, 'make_by (autofirma) NO se toca al editar.');
        $this->assertSame($autor->id, (int) $scout->created_by_id, 'created_by_id (autofirma) NO se toca al editar.');
    }

    /**
     * REGLA DEL OWNER: el autor de un documento se identifica por su NOMBRE EN CRÉDITOS.
     *
     * Los dos tests de arriba NO la cubren: su usuario de prueba no tiene `ncreditos`, así que
     * displayName() cae al nombre corto y pasarían igual con el `->name` de antes. Este sí la fija.
     *
     * El porqué no es cosmético: `->name` guardaba sólo el PRIMER nombre ("Ari"), mientras el mismo
     * documento imprimía el crédito completo en el bloque de firma ("Ari Rómulo"). Dos nombres para
     * la misma persona en la misma hoja, y el corto es AMBIGUO en cuanto hay dos personas que
     * comparten nombre de pila — inaceptable en algo con valor probatorio.
     */
    /**
     * Los datos de PRODUCCIÓN (tipo, gerente, rep. de seguridad) llegan prellenados al crear un
     * scouting nuevo: son idénticos en todos los de una misma producción y teclearlos cada vez es
     * trabajo inútil. Siguen siendo campos editables — se borran y se escribe otra cosa si cambia.
     */
    public function test_el_formulario_nuevo_hereda_los_datos_de_produccion_del_ultimo_scouting(): void
    {
        $this->actingAsRole('safety-officer');
        $this->post(route('scoutings.store'), $this->scoutPayload([
            'production_type' => 'Película',
            'manager_name'    => 'Adrián Aldana',
            'safety_rep_name' => 'Ari Rómulo',
        ]))->assertSessionHasNoErrors();

        $resp = $this->get(route('scoutings.create'));
        $resp->assertOk();
        $resp->assertSee('value="Adrián Aldana"', false);
        $resp->assertSee('value="Ari Rómulo"', false);
        $resp->assertSee('<option value="Película" selected>', false);
    }

    public function test_make_by_guarda_el_nombre_de_creditos_no_el_nombre_de_pila(): void
    {
        $user = $this->actingAsRole('safety-officer');
        $user->forceFill(['name' => 'Ari', 'lname' => 'Rómulo', 'ncreditos' => 'Ari Rómulo'])->save();

        $payload = $this->scoutPayload();
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertSame('Ari Rómulo', $scout->make_by, 'Debe quedar el nombre de CRÉDITOS, no "Ari".');
        $this->assertNotSame('Ari', $scout->make_by, 'El nombre de pila solo es ambiguo y no identifica.');
    }

    // =====================================================================
    //  API interna geo — /geo/scoutings-nearby (sólo auth; la usan los formularios)
    // =====================================================================

    public function test_nearby_devuelve_el_scouting_mas_cercano(): void
    {
        $locName = 'GeoLoc ' . Str::random(6);
        ScoutingReport::create([
            'location_name'    => $locName,
            'location_address' => 'Av. Reforma 100',
            'latitude'         => 19.4326077,
            'longitude'        => -99.1332080,
            'production_id'    => \App\Support\CurrentProduction::id(),
        ]);

        // Cualquier usuario autenticado puede consultarla (sin permiso locations.*): la usan los
        // formularios de campo para SUGERIR el nombre de la locación por GPS.
        $this->actingAsRole('crew');
        $resp = $this->getJson(route('geo.scoutings.nearby', [
            'lat' => 19.4326077, 'lng' => -99.1332080, 'radius' => 500,
        ]));
        $resp->assertOk();
        $resp->assertJsonFragment(['location_name' => $locName]);
    }

    public function test_nearby_exige_coordenadas_validas(): void
    {
        $this->actingAsRole('crew');
        // Sin lat/lng -> 422 (lat/lng required|numeric).
        $this->getJson(route('geo.scoutings.nearby'))->assertStatus(422);
        // Fuera de rango -> 422.
        $this->getJson(route('geo.scoutings.nearby', ['lat' => 999, 'lng' => 0]))->assertStatus(422);
    }

    public function test_nearby_requiere_sesion(): void
    {
        // Invitado: la ruta exige auth -> redirect a login (no JSON abierto).
        $this->get(route('geo.scoutings.nearby', ['lat' => 19.4, 'lng' => -99.1]))
            ->assertRedirect(route('login'));
    }

    // =====================================================================
    //  BUSINESS RULE — no se puede finalizar con acciones correctivas abiertas
    // =====================================================================

    public function test_no_se_puede_finalizar_scouting_con_accion_abierta(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['status' => 'draft']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();
        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();

        // Cuelga una acción correctiva ABIERTA del scouting.
        $scout->actionItems()->create([
            'description' => 'Reparar barandal suelto en acceso norte.',
            'status'      => ActionItem::STATUS_OPEN,
            'source'      => 'manual',
        ]);

        // Intentar pasar a FINAL debe bloquearse (assertActionItemsClosed -> ValidationException).
        $this->put(route('scoutings.update', ['id' => $scout->id]), array_merge($payload, [
            'status' => 'final',
        ]))->assertSessionHasErrors('status');

        $scout->refresh();
        $this->assertNotSame('final', $scout->status, 'No debe quedar final con una acción abierta.');
        $this->assertFalse($scout->signatures()->exists(), 'Y por tanto no debe sellarse.');
    }

    // =====================================================================
    //  RANGO DE RODAJE — una locación puede ocupar varios días (2026-09-14)
    // =====================================================================

    public function test_rango_de_rodaje_persiste_y_el_fin_no_puede_ser_anterior_al_inicio(): void
    {
        $this->actingAsRole('safety-officer');

        $ok = $this->scoutPayload(['date_shoot' => '2026-10-14', 'date_shoot_end' => '2026-10-17']);
        $this->post(route('scoutings.store'), $ok)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $ok['location_name'])->latest('id')->first();
        $this->assertSame('2026-10-17', $scout->date_shoot_end->toDateString());
        $this->assertTrue($scout->hasShootRange());
        $this->assertSame('2026-10-17', $scout->shootEndDate()->toDateString());

        // Un fin ANTERIOR al inicio no es un rango, es un error de dedo: se rechaza.
        $this->post(route('scoutings.store'), $this->scoutPayload([
            'date_shoot' => '2026-10-14', 'date_shoot_end' => '2026-10-09',
        ]))->assertSessionHasErrors('date_shoot_end');
    }

    public function test_sin_rango_el_scouting_se_comporta_como_de_un_solo_dia(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['date_shoot' => '2026-10-14']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNull($scout->date_shoot_end);
        $this->assertFalse($scout->hasShootRange(), 'Sin fin declarado NO hay rango que mostrar.');
        $this->assertSame('2026-10-14', $scout->shootEndDate()->toDateString());
    }

    /**
     * ⛔ LA PRUEBA QUE DE VERDAD IMPORTA. `scouting_reports` está SELLADA: el hash cubre la fila
     * entera, así que una columna nueva mueve el sello de TODO lo ya firmado y lo vuelve "ALTERADO"
     * —irreversible—. `date_shoot_end` está en NULLABLE_HASH_EXCLUDES justamente para eso: mientras
     * valga null NO entra al payload, y un scouting sellado sin rango sigue verificando íntegro.
     *
     * Este test vigila LAS DOS MITADES del contrato, porque una sola no sirve: fuera del hash
     * cuando es null (o los sellos viejos se caen) y DENTRO en cuanto lleva valor (o el rango sería
     * un dato no sellado dentro de un documento probatorio, alterable sin dejar rastro).
     */
    public function test_el_rango_sale_del_hash_si_esta_vacio_y_entra_en_cuanto_tiene_valor(): void
    {
        $this->actingAsRole('safety-officer');
        $payload = $this->scoutPayload(['status' => 'final', 'date_shoot' => '2026-10-14']);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertTrue($scout->signatures()->exists());
        $this->assertTrue($scout->verifyLatestSignature(), 'Sellado sin rango: debe verificar íntegro.');

        $this->assertArrayNotHasKey(
            'date_shoot_end',
            $scout->canonicalSignaturePayload(),
            'Vacío, el rango NO debe entrar al payload: si entrara, cada sello anterior se caería.'
        );

        $scout->date_shoot_end = '2026-10-17';
        $this->assertArrayHasKey(
            'date_shoot_end',
            $scout->canonicalSignaturePayload(),
            'Con valor, el rango SÍ debe entrar al hash: un dato del documento no puede quedar fuera del sello.'
        );
    }
}
