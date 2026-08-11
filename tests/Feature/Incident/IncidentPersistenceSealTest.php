<?php

namespace Tests\Feature\Incident;

use App\Models\Addendum;
use App\Models\ActionItem;
use App\Models\hazardnotification;
use App\Models\InjuryReport;
use App\Models\unsafecond;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\QaTestCase;

/**
 * QA — VERTICAL INJURY/HAZARD/UNSAFE: PERSISTENCIA, SELLADO, PDCA y ADDENDUM.
 *
 * Ejercita los flujos por HTTP REALES (los mismos controladores de prod):
 *  - store() persiste + AUTOFIRMA (make_by/created_by_id server-side) + SELLA al crear.
 *  - crew puede crear su propio reporte (aunque luego no pueda verlo: gate de módulo).
 *  - El EPP viaja DENTRO del payload sellado del accidente (alterarlo rompe el sello).
 *  - Motor PDCA: cerrar/reabrir un action item (permission:hazards.manage) y el bloqueo
 *    de cierre del reporte mientras haya acciones abiertas.
 *  - Addendum médico: sello PROPIO + propiedad (AddendumPolicy) — un médico no edita el
 *    anexo de otro.
 *  - El INVOLUCRADO de un acto inseguro se persiste (para avisar) pero su NOMBRE no se
 *    imprime en el documento a un lector sin permiso de revelado.
 */
class IncidentPersistenceSealTest extends QaTestCase
{
    // ==================================================================================
    //  1. INJURY — store persiste + autofirma + sella al crear.
    // ==================================================================================

    public function test_injury_store_persiste_autofirma_y_sella(): void
    {
        $so = $this->actingAsRole('safety-officer');

        $resp = $this->post(route('injury_reports.store'), [
            'what_happened' => 'Golpe con estructura durante el montaje',
        ]);

        $injury = InjuryReport::latest('id')->first();
        $this->assertNotNull($injury, 'El store debe persistir el reporte.');
        $resp->assertRedirect(route('injury_reports.show', $injury->id));
        $resp->assertSessionHas('success');

        // AUTOFIRMA server-side (no falseable desde el form).
        $this->assertSame($so->name, $injury->make_by);
        $this->assertSame($so->id, (int) $injury->created_by_id);
        $this->assertNotEmpty($injury->uuid);

        // SELLO al crear: existe firma y el documento intacto verifica ÍNTEGRO.
        $fresh = InjuryReport::where('uuid', $injury->uuid)->first();
        $this->assertTrue($fresh->signatures()->exists(), 'El injury debe sellarse al crearse.');
        $this->assertTrue($fresh->verifyLatestSignature(), 'El sello del injury recién creado debe verificar.');
    }

    public function test_crew_puede_crear_su_propio_injury(): void
    {
        // crew tiene injury.create (self-service). El reporte se persiste y se autofirma con él
        // como capturador, aunque el gate de módulo (injury.view) luego no le deje abrirlo.
        $crew = $this->actingAsRole('crew');

        $this->post(route('injury_reports.store'), [
            'what_happened' => 'Me corté con un vidrio en el set',
        ]);

        $this->assertDatabaseHas('injury_reports', [
            'created_by_id' => $crew->id,
            'make_by'       => $crew->name,
            'what_happened' => 'Me corté con un vidrio en el set',
        ]);
    }

    // ==================================================================================
    //  2. INJURY — el EPP (ppe_details JSON) viaja DENTRO del sello del accidente.
    // ==================================================================================

    public function test_el_epp_se_persiste_estructurado_y_queda_dentro_del_sello(): void
    {
        $author = $this->makeUser('safety-officer');
        $injury = InjuryReport::create([
            'name'          => 'Con EPP',
            'what_happened' => 'x',
            'ppe_details'   => ['worn' => 'si', 'types' => ['casco', 'guantes'], 'condition' => 'bueno'],
            'created_by_id' => $author->id,
        ]);
        $injury->refresh();
        $injury->signDocument($author);

        // Round-trip del cast array (BD text/json ⇄ modelo).
        $fresh = InjuryReport::where('uuid', $injury->uuid)->first();
        $this->assertIsArray($fresh->ppe_details);
        $this->assertSame(['casco', 'guantes'], $fresh->ppe_details['types']);
        $this->assertTrue($fresh->verifyLatestSignature(), 'Intacto no debe dar falso positivo.');

        // El EPP es CONTENIDO SELLADO: manipularlo en BD debe romper la integridad.
        DB::table('injury_reports')->where('id', $injury->id)
            ->update(['ppe_details' => json_encode(['worn' => 'no', 'types' => [], 'condition' => 'alterado'])]);
        $fresh2 = InjuryReport::where('uuid', $injury->uuid)->first();
        $this->assertFalse(
            $fresh2->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: alterar el EPP del accidente sellado no se detectó.'
        );
    }

    // ==================================================================================
    //  3. HAZARD / UNSAFE — store persiste + sella + estado inicial 'Abierto'.
    // ==================================================================================

    public function test_hazard_store_persiste_sella_y_arranca_abierto(): void
    {
        $so = $this->actingAsRole('safety-officer');

        $resp = $this->post(route('hazard_notifications.store'), [
            'name_loc'                      => 'Foro 3',
            'description_hazard_unsafe_act' => 'Operario sin arnés a 4 m',
        ]);
        $resp->assertRedirect(route('hazard_notifications.index'));

        $haz = hazardnotification::latest('id')->first();
        $this->assertNotNull($haz);
        $this->assertSame($so->name, $haz->make_by);
        $this->assertSame($so->id, (int) $haz->created_by_id);
        $this->assertSame('Abierto', $haz->action_status, 'El acto nace con la acción "Abierto".');
        $this->assertTrue($haz->signatures()->exists(), 'El acto inseguro debe sellarse al crearse.');
        $this->assertTrue($haz->verifyLatestSignature());
    }

    public function test_unsafe_store_persiste_y_sella(): void
    {
        $so = $this->actingAsRole('safety-officer');

        $resp = $this->post(route('unsafenotifications.store'), [
            'name_loc'                => 'Bodega norte',
            'description_unsafe_cond' => 'Cableado sin canalizar en zona de paso',
        ]);
        $resp->assertRedirect(route('unsafenotifications.index'));

        $cond = unsafecond::latest('id')->first();
        $this->assertNotNull($cond);
        $this->assertSame($so->id, (int) $cond->created_by_id);
        $this->assertTrue($cond->signatures()->exists(), 'La condición insegura debe sellarse al crearse.');
        $this->assertTrue($cond->verifyLatestSignature());
    }

    // ==================================================================================
    //  4. PDCA — cerrar/reabrir action item (hazards.manage) + bloqueo de cierre.
    // ==================================================================================

    private function hazardConAccion(): array
    {
        $haz = hazardnotification::create([
            'production_name'               => 'Demo',
            'name_loc'                      => 'Set 1',
            'description_hazard_unsafe_act' => 'Extintor caducado',
        ]);
        $item = $haz->syncAutoActionItem('Reemplazar extintor', 'suggestions_corrective_action');
        $this->assertNotNull($item);
        $this->assertSame(ActionItem::STATUS_OPEN, $item->status);

        return [$haz, $item];
    }

    public function test_cerrar_action_item_exige_hazards_manage(): void
    {
        [, $item] = $this->hazardConAccion();

        // crew NO tiene hazards.manage.
        $this->actingAsRole('crew');
        $this->post(route('action_items.close', $item->id))->assertForbidden();

        $this->assertDatabaseHas('action_items', ['id' => $item->id, 'status' => ActionItem::STATUS_OPEN]);
    }

    public function test_manager_cierra_y_reabre_un_action_item(): void
    {
        [, $item] = $this->hazardConAccion();
        $manager = $this->actingAsRole('safety-officer');

        // Cerrar → status closed + verificador + fecha.
        $this->post(route('action_items.close', $item->id))->assertRedirect();
        $item->refresh();
        $this->assertSame(ActionItem::STATUS_CLOSED, $item->status);
        $this->assertSame($manager->id, (int) $item->verified_by_id);
        $this->assertNotNull($item->closed_at);

        // Reabrir → status open + se limpia el verificador.
        $this->post(route('action_items.reopen', $item->id))->assertRedirect();
        $item->refresh();
        $this->assertSame(ActionItem::STATUS_OPEN, $item->status);
        $this->assertNull($item->verified_by_id);
        $this->assertNull($item->closed_at);
    }

    public function test_no_se_puede_cerrar_el_hazard_con_una_accion_abierta(): void
    {
        [$haz, $item] = $this->hazardConAccion();
        $this->actingAsRole('safety-officer');

        // Con la acción ABIERTA: intentar "Cerrar" el reporte rebota con error de validación.
        $this->from(route('hazard_notifications.show', $haz->id))
            ->post(route('hazard_notifications.status', $haz->id), ['action_status' => 'Cerrado'])
            ->assertSessionHasErrors('action_status');
        $this->assertNotSame('Cerrado', hazardnotification::find($haz->id)->action_status);

        // Cerrada la acción, el reporte SÍ puede cerrarse.
        $this->post(route('action_items.close', $item->id));
        $this->post(route('hazard_notifications.status', $haz->id), ['action_status' => 'Cerrado'])
            ->assertSessionDoesntHaveErrors('action_status');
        $this->assertSame('Cerrado', hazardnotification::find($haz->id)->action_status);
    }

    // ==================================================================================
    //  5. ADDENDUM médico — sello PROPIO + propiedad (AddendumPolicy).
    // ==================================================================================

    public function test_medico_agrega_addendum_con_sello_propio_sin_tocar_el_injury(): void
    {
        $author = $this->makeUser('safety-officer');
        $injury = InjuryReport::create(['name' => 'Base', 'what_happened' => 'x', 'created_by_id' => $author->id]);
        $injury->refresh();
        $injury->signDocument($author);
        $injuryHashAntes = $injury->computeDocumentHash();

        $medic = $this->actingAsRole('medic');
        $resp = $this->from(route('injury_reports.show_complete', $injury->id))
            ->post(route('addendums.store', $injury->id), [
                'type' => 'treatment_update',
                'body' => 'El paciente evolucionó a tratamiento médico; pasa a registrable.',
            ]);
        $resp->assertSessionHas('success');

        // El anexo se persiste con su autor-médico y su PROPIO sello.
        $addendum = Addendum::latest('id')->first();
        $this->assertNotNull($addendum);
        $this->assertSame($injury->id, (int) $addendum->injury_report_id);
        $this->assertSame($medic->id, (int) $addendum->created_by_id);
        $this->assertTrue($addendum->signatures()->exists(), 'El addendum tiene su propio sello.');
        $this->assertTrue($addendum->verifyLatestSignature());

        // El reporte base NO se tocó: su hash (y su sello) siguen idénticos.
        $this->assertSame(
            $injuryHashAntes,
            InjuryReport::find($injury->id)->computeDocumentHash(),
            'El addendum es append-only: el injury original permanece intacto.'
        );
    }

    public function test_solo_medico_crea_addendum_y_solo_el_dueno_lo_edita(): void
    {
        $injury = InjuryReport::create(['name' => 'Base', 'what_happened' => 'x']);
        $medicA = $this->makeUser('medic');
        $medicB = $this->makeUser('medic');
        $safety = $this->makeUser('safety-officer');

        // CREATE: es médico → sí; no-médico (safety-officer) → no (aunque pasara el permiso de ruta).
        $this->assertTrue(Gate::forUser($medicA)->allows('create', [Addendum::class, $injury]));
        $this->assertFalse(Gate::forUser($safety)->allows('create', [Addendum::class, $injury]));

        // UPDATE: sólo el médico que lo creó. Otro médico NO edita anexo ajeno.
        $addendumA = Addendum::create([
            'injury_report_id' => $injury->id,
            'type'             => 'note',
            'body'             => 'Nota de A',
            'created_by_id'    => $medicA->id,
        ]);
        $this->assertTrue(Gate::forUser($medicA)->allows('update', $addendumA), 'El dueño edita su anexo.');
        $this->assertFalse(
            Gate::forUser($medicB)->allows('update', $addendumA),
            'FUGA DE PROPIEDAD: un médico ajeno pudo editar el anexo de otro.'
        );
    }

    /** @dataProvider rolesSinMedicalCreate */
    public function test_addendum_store_esta_cerrado_a_roles_sin_medical_create(string $role): void
    {
        $injury = InjuryReport::create(['name' => 'Base', 'what_happened' => 'x']);
        $this->actingAsRole($role);
        // El middleware permission:medical.create corta antes del controlador.
        $this->post(route('addendums.store', $injury->id), ['type' => 'note', 'body' => 'x'])
            ->assertForbidden();
    }

    public function rolesSinMedicalCreate(): array
    {
        // Sólo super-admin y medic tienen medical.create. safety-officer/coordinator/crew no.
        return [['safety-officer'], ['coordinator'], ['crew']];
    }

    // ==================================================================================
    //  6. INVOLUCRADO — se persiste (para avisar) pero su NOMBRE no se imprime.
    // ==================================================================================

    public function test_involucrado_del_acto_se_persiste_pero_su_nombre_no_se_imprime(): void
    {
        $involved = $this->makeUser('crew', ['name' => 'ZZINVOLUCRADO', 'lname' => 'Confidencial']);
        $haz = hazardnotification::create([
            'production_name'               => 'Demo',
            'name_loc'                      => 'Set 5',
            'description_hazard_unsafe_act' => 'Conducta de riesgo',
            'involved_user_id'              => $involved->id,
        ]);

        // El vínculo QUEDA en el reporte (es lo que dispara el aviso al jefe directo).
        $this->assertSame($involved->id, (int) $haz->involved_user_id);

        // Un lector con hazards.view pero SIN hazards.manage (coordinator) y que no lidera el
        // depto del involucrado NO ve el nombre en el documento.
        $this->actingAsRole('coordinator');
        $this->get(route('hazard_notifications.show', $haz->id))
            ->assertOk()
            ->assertDontSee('ZZINVOLUCRADO');
    }

    // ==================================================================================
    //  7. HALLAZGOS (guardas de bug)
    // ==================================================================================

    /**
     * BUG-INC-01 — La redirección post-creación de crew apunta a una vista prohibida.
     *
     * crew tiene injury.create (self-service) pero NO injury.view, y InjuryReportController@store
     * SIEMPRE redirige a injury_reports.show (gate injury.view). Además InjuryReportPolicy::view()
     * —que su propio docblock dice que gatea "la ficha general"— es CÓDIGO MUERTO: no hay ningún
     * authorize('view')/can('view') en el código, así que la "ownership en Policy" que promete el
     * comentario del seeder ("can file own injury/hazard reports — ownership checked in Policy")
     * NUNCA se ejerce para VER. Resultado: crew envía su reporte y aterriza en un 403.
     *
     * Esta guarda DOCUMENTA el comportamiento ACTUAL (verde). Comportamiento esperado tras el fix
     * (decisión del owner): o bien redirigir a crew a una landing permitida, o bien cablear la
     * policy de propiedad para que el capturador vea su propia ficha LITE.
     */
    public function test_BUG_crew_tras_crear_injury_es_redirigido_a_una_vista_que_le_da_403(): void
    {
        $this->actingAsRole('crew');
        $resp = $this->post(route('injury_reports.store'), ['what_happened' => 'Reporte propio']);

        $injury = InjuryReport::latest('id')->first();
        $this->assertNotNull($injury);
        // El store redirige a la LITE...
        $resp->assertRedirect(route('injury_reports.show', $injury->id));
        // ...pero esa misma ruta le da 403 a crew (dangling redirect).
        $this->get(route('injury_reports.show', $injury->id))->assertForbidden();
    }

    /**
     * BUG-INC-02 — El PK de hazardnotifications y unsafeconds es tinyint(3) unsigned (tope 255).
     *
     * injury_reports.id es bigint, pero estos dos gemelos heredan del dump legacy un `id tinyint(3)
     * unsigned AUTO_INCREMENT`: la fila 256 de actos inseguros / condiciones inseguras fallará al
     * insertar (overflow de auto-incremento). En una producción activa es alcanzable. Ver:
     *   database/migrations/2026_06_25_000022_create_hazardnotifications_table.php:13
     *   database/migrations/2026_06_25_000045_create_unsafeconds_table.php:13
     *
     * SKIPPED hasta que el owner amplíe la columna (a bigint/int unsigned, homologado con injury).
     * Al aplicar el fix y quitar el skip, esta guarda debe pasar (el PK ya NO es tinyint).
     */
    public function test_BUG_id_de_hazard_y_unsafe_no_debe_toparse_en_255(): void
    {
        $this->markTestSkipped('BUG-INC-02: hazardnotifications.id / unsafeconds.id son tinyint(3) (tope 255); pendiente de ampliar por el owner.');

        foreach (['hazardnotifications', 'unsafeconds'] as $table) {
            $type = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('column_name', 'id')
                ->value('column_type');
            $this->assertStringStartsNotWith(
                'tinyint',
                (string) $type,
                "El PK de {$table} no debe ser tinyint (tope 255 filas)."
            );
        }
    }
}
