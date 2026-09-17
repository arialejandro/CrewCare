<?php

namespace Tests\Feature\Transport;

use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\Vehicle;
use App\Models\VehicleCheckPoint;
use App\Models\VehicleInspection;
use App\Models\VehicleInspectionDraft;
use App\Models\VehicleType;
use App\Support\VehicleChecklist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * TRANSPORTACIÓN · Bloque 1 — verificación de vehículos (endpoints REALES + catálogo de fábrica).
 *
 * Cubre: catálogo sembrado · membresía por applies_when · acta APTO/NO APTO sellada · fail-safe
 * (incompleto / foto obligatoria) · integridad del sello · RBAC híbrido (canFull/canLite) ·
 * verificador público 'veh' sin fuga del nivel interno · reevaluación encadenada · dos marcas.
 */
class VehicleModuleTest extends VehicleVerticalTestCase
{
    // ── Catálogo + membresía ─────────────────────────────────────────────────
    public function test_catalogo_de_fabrica_sembrado(): void
    {
        $this->assertSame(13, VehicleType::count(), '13 tipos de fábrica.');
        $this->assertSame(52, VehicleCheckPoint::count(), '52 puntos de fábrica (51 Bloque 1 + REM-005 calzas).');
    }

    public function test_applies_when_enciende_modulos_por_atributos(): void
    {
        $auto = $this->makeVehicle('auto'); // 5 plazas
        $van  = $this->makeVehicle('van');  // 12 plazas

        $autoCodes = $this->applicableCodes($auto);
        $vanCodes  = $this->applicableCodes($van);

        $this->assertContains('VEH-001', $autoCodes, 'Núcleo aplica siempre.');
        $this->assertNotContains('OCU-001', $autoCodes, 'Auto (5 plazas) NO enciende alta ocupación.');
        $this->assertContains('OCU-001', $vanCodes, 'Van (12 plazas) SÍ enciende alta ocupación.');
        $this->assertNotContains('ELE-001', $autoCodes, 'Combustión NO enciende tracción eléctrica.');
    }

    public function test_is_towed_excluye_puntos_de_conduccion_pero_conserva_modulos(): void
    {
        // planta_luz trae is_towed=true por perfil.
        $towed = $this->makeVehicle('planta_luz');
        $codes = $this->applicableCodes($towed);

        // Excluidos en una unidad remolcada (no se conduce): frenos de conducción, rodaje, luces de
        // cabina, cinturones, motor y combustible de propulsión.
        foreach (['VEH-001', 'VEH-010', 'VEH-011', 'VEH-013', 'VEH-021', 'VEH-015', 'VEH-019'] as $c) {
            $this->assertNotContains($c, $codes, "$c NO aplica a una unidad remolcada.");
        }
        // Conservados: llantas, equipo, energía (genset) y REMOLQUE (con REM-004 = frenos del remolque).
        $this->assertContains('VEH-005', $codes, 'Las llantas siguen aplicando.');
        $this->assertContains('VEH-022', $codes, 'El extintor sigue aplicando.');
        $this->assertContains('ENE-001', $codes, 'La energía (genset) sigue aplicando.');
        $this->assertContains('REM-004', $codes, 'Los frenos del remolque se revisan por REMOLQUE.');
        $this->assertContains('REM-005', $codes, 'Las calzas de la unidad remolcada (§0 Bloque 2) aplican por is_towed.');

        // Un camper AUTOPROPULSADO (is_towed=false) SÍ recibe los puntos de conducción.
        $self = $this->makeVehicle('planta_luz', [
            'attr_values' => \App\Support\VehicleChecklist::normalizeAttributes([
                'powertrain' => 'combustion', 'has_genset_or_heat_appliances' => true, 'is_towed' => false,
            ]),
        ]);
        $this->assertContains('VEH-011', $this->applicableCodes($self), 'Una unidad autopropulsada sí recibe luces principales.');
    }

    public function test_rem005_sustituye_a_car004_cuando_hay_caja_y_remolque(): void
    {
        // Camper de vestuario = ÚNICO tipo has_cargo_box + is_towed → las calzas NO se piden dos veces.
        $vestuario = $this->applicableCodes($this->makeVehicle('camper_vestuario'));
        $this->assertContains('REM-005', $vestuario, 'El remolcado recibe las calzas del remolque (critical).');
        $this->assertNotContains('CAR-004', $vestuario, 'REM-005 SUSTITUYE a CAR-004 cuando ambos aplican.');

        // Pickup con caja SIN remolque → conserva CAR-004; no hay REM-005.
        $pickup = $this->applicableCodes($this->makeVehicle('pickup'));
        $this->assertContains('CAR-004', $pickup, 'Un pickup con caja sin remolque conserva CAR-004.');
        $this->assertNotContains('REM-005', $pickup, 'Sin remolque no hay calzas del remolcado.');

        // Camper de baños remolcado SIN caja → recibe REM-005; CAR-004 nunca aplicó.
        $banos = $this->applicableCodes($this->makeVehicle('camper_banos'));
        $this->assertContains('REM-005', $banos, 'Un remolcado sin caja recibe REM-005.');
        $this->assertNotContains('CAR-004', $banos, 'Sin caja, CAR-004 no aplica.');
    }

    // ── Acta APTA (camino feliz) ─────────────────────────────────────────────
    public function test_checklist_completo_ok_da_apto_excelente_y_nace_sellada(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        $resp = $this->post(route('transport.inspect.store'), $this->checklistPayload($vehicle));
        $resp->assertSessionHasNoErrors();
        $resp->assertStatus(302);

        $acta = VehicleInspection::latest('id')->first();
        $this->assertNotNull($acta);
        $this->assertSame(VehicleInspection::VERDICT_APTO, $acta->verdict);
        $this->assertSame(VehicleInspection::LEVEL_EXCELENTE, $acta->level);
        $this->assertNotEmpty($acta->uuid);
        $this->assertTrue($acta->signatures()->exists(), 'El acta nace sellada.');
        $this->assertTrue($acta->verifyLatestSignature(), 'El sello recién creado verifica íntegro.');

        // La marca INSPECCIONADO queda activa en el vehículo.
        $this->assertSame('apto', $vehicle->fresh()->inspectionState());

        $this->get(route('transport.acta', $acta->uuid))->assertOk();
    }

    public function test_un_critico_caido_da_no_apto_alto_riesgo(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        // VEH-001 (frenos de servicio) es crítico.
        $this->post(route('transport.inspect.store'), $this->checklistPayload($vehicle, ['VEH-001' => 'fail']))
            ->assertSessionHasNoErrors();

        $acta = VehicleInspection::latest('id')->first();
        $this->assertSame(VehicleInspection::VERDICT_NO_APTO, $acta->verdict);
        $this->assertSame(VehicleInspection::LEVEL_ALTO, $acta->level);
        $this->assertSame(1, $acta->n_critical);
    }

    // ── Fail-safe ────────────────────────────────────────────────────────────
    public function test_checklist_incompleto_no_persiste(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        $payload = $this->checklistPayload($vehicle);
        // Quita una respuesta → verificación incompleta.
        $someCode = array_key_first($payload['answers']);
        unset($payload['answers'][$someCode]);

        $this->post(route('transport.inspect.store'), $payload)->assertSessionHas('error');
        $this->assertSame(0, VehicleInspection::count(), 'Un checklist incompleto NO se guarda.');
    }

    public function test_foto_obligatoria_faltante_no_persiste(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        $payload = $this->checklistPayload($vehicle);
        // VEH-005 exige foto (requires_photo). Quitarla debe abortar.
        unset($payload['point_photos']['VEH-005']);

        $this->post(route('transport.inspect.store'), $payload)->assertSessionHas('error');
        $this->assertSame(0, VehicleInspection::count(), 'Falta la foto obligatoria → no se guarda.');
    }

    // ── Integridad del sello ─────────────────────────────────────────────────
    public function test_veredicto_falsificado_en_bd_rompe_el_sello(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');
        $this->post(route('transport.inspect.store'), $this->checklistPayload($vehicle));
        $acta = VehicleInspection::latest('id')->first();

        $this->assertTrue($acta->fresh()->verifyLatestSignature());

        DB::table('vehicle_inspections')->where('id', $acta->id)
            ->update(['verdict' => VehicleInspection::VERDICT_NO_APTO, 'level' => VehicleInspection::LEVEL_ALTO]);

        $this->assertFalse(
            VehicleInspection::find($acta->id)->verifyLatestSignature(),
            'FALLO DE INTEGRIDAD: un veredicto falsificado en BD no se detectó.'
        );
    }

    // ── RBAC híbrido ─────────────────────────────────────────────────────────
    public function test_produccion_view_solo_ve_lite_no_el_hub(): void
    {
        $this->actingAsRole('line-producer'); // transport.view, sin manage, sin depto
        $this->get(route('transport.lite'))->assertOk();
        $this->get(route('transport.index'))->assertForbidden();
        $this->get(route('transport.vehicles'))->assertForbidden();
    }

    public function test_crew_sin_permiso_ni_depto_queda_fuera(): void
    {
        $this->actingAsRole('crew');
        $this->get(route('transport.lite'))->assertForbidden();
        $this->get(route('transport.index'))->assertForbidden();
    }

    public function test_safety_gestiona_el_modulo(): void
    {
        $this->actingAsRole('safety-officer');
        $this->get(route('transport.index'))->assertOk();
        $this->get(route('transport.vehicles'))->assertOk();
        $this->get(route('transport.lite'))->assertOk();
    }

    // ── Verificador público 'veh' — sin fuga del nivel interno ────────────────
    public function test_verificador_publico_no_filtra_el_nivel_interno(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');
        $this->post(route('transport.inspect.store'), $this->checklistPayload($vehicle));
        $acta = VehicleInspection::latest('id')->first();

        // Sin sesión (verificador público).
        auth()->logout();
        $resp = $this->get(route('seal.verify', ['tipo' => 'veh', 'uuid' => $acta->uuid]));
        $resp->assertOk();
        $resp->assertSee($acta->folio(), false);
        // El nivel interno (excelente/alto riesgo/...) NUNCA sale al acuse público.
        $resp->assertDontSee('xcelente', false);
        $resp->assertDontSee('Nivel interno', false);
        $resp->assertDontSee('Alto riesgo', false);
    }

    // ── Reevaluación encadenada ──────────────────────────────────────────────
    public function test_reevaluacion_sustituye_al_acta_de_origen(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        // 1) NO APTO (falla un crítico).
        $this->post(route('transport.inspect.store'), $this->checklistPayload($vehicle, ['VEH-001' => 'fail']))
            ->assertSessionHasNoErrors();
        $origin = VehicleInspection::latest('id')->first();
        $this->assertTrue($origin->isNoApto());

        // 2) Reevaluación aprobatoria (todo ok, marca reparado).
        $payload = $this->checklistPayload($vehicle, [], [
            'reeval'   => $origin->id,
            'reparado' => ['VEH-001' => '1'],
        ]);
        $this->post(route('transport.inspect.store'), $payload)->assertSessionHasNoErrors();

        $new = VehicleInspection::latest('id')->first();
        $this->assertTrue($new->isApto());
        $this->assertTrue((bool) $new->is_reevaluation);
        $this->assertSame($origin->id, (int) $new->origin_inspection_id);

        // El acta de origen queda retirada y apuntando a la nueva (sin re-sellar: hash-excluido).
        $origin->refresh();
        $this->assertFalse((bool) $origin->is_active);
        $this->assertSame($new->id, (int) $origin->superseded_by_id);
        // Una sola acta ACTIVA por vehículo.
        $this->assertSame($new->id, $vehicle->fresh()->latestInspection()->id);
    }

    // ── Borrador del checklist (§1) ──────────────────────────────────────────
    public function test_borrador_guarda_parcial_en_servidor_y_es_del_autor(): void
    {
        $a = $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        // Guardado PARCIAL: unas respuestas + una foto, sin cerrar.
        $this->post(route('transport.inspect.draft'), [
            'vehicle_id'   => $vehicle->id,
            'answers'      => ['VEH-001' => 'ok', 'VEH-002' => 'fail'],
            'point_photos' => ['VEH-005' => UploadedFile::fake()->image('x.jpg', 24, 24)],
            'km'           => 500,
        ])->assertSessionHasNoErrors();

        $draft = VehicleInspectionDraft::forAuthor($vehicle->id, $a->id);
        $this->assertNotNull($draft, 'El borrador se guarda en el servidor.');
        $this->assertSame('ok', $draft->answers['VEH-001'] ?? null);
        $this->assertSame('fail', $draft->answers['VEH-002'] ?? null);
        $this->assertNotEmpty($draft->point_photos['VEH-005'] ?? null, 'La foto se guarda al vuelo.');
        $this->assertSame(500, (int) $draft->km);

        // DEL AUTOR: otro usuario no ve el borrador.
        $b = $this->makeUser('safety-officer');
        $this->assertNull(VehicleInspectionDraft::forAuthor($vehicle->id, $b->id), 'Solo el autor ve su borrador.');
    }

    public function test_borrador_se_sella_reusando_sus_fotos_y_se_borra(): void
    {
        $a = $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');

        // 1) Borrador con las fotos obligatorias (VEH-005/007/022).
        $this->post(route('transport.inspect.draft'), [
            'vehicle_id'   => $vehicle->id,
            'point_photos' => [
                'VEH-005' => UploadedFile::fake()->image('a.jpg', 24, 24),
                'VEH-007' => UploadedFile::fake()->image('b.jpg', 24, 24),
                'VEH-022' => UploadedFile::fake()->image('c.jpg', 24, 24),
            ],
        ])->assertSessionHasNoErrors();
        $this->assertNotNull(VehicleInspectionDraft::forAuthor($vehicle->id, $a->id));

        // 2) Sellar SIN re-subir fotos (se retomó desde otro dispositivo): solo respuestas.
        $points  = VehicleChecklist::pointsFor($vehicle->resolvedAttributes());
        $answers = [];
        foreach ($points as $p) {
            $answers[$p->code] = 'ok';
        }
        $this->post(route('transport.inspect.store'), ['vehicle_id' => $vehicle->id, 'answers' => $answers])
            ->assertSessionHasNoErrors();

        $acta = VehicleInspection::latest('id')->first();
        $this->assertNotNull($acta, 'El acta se sella reusando las fotos del borrador.');
        $this->assertSame(VehicleInspection::VERDICT_APTO, $acta->verdict);
        $snap = collect($acta->checklist_snapshot)->firstWhere('code', 'VEH-005');
        $this->assertNotEmpty($snap['photo_path'] ?? null, 'El acta conserva la ruta de foto del borrador.');

        // El borrador se BORRA al sellar (el acta es la fuente inmutable).
        $this->assertNull(VehicleInspectionDraft::forAuthor($vehicle->id, $a->id), 'El borrador se borra al sellar.');
    }

    public function test_descartar_borrador_lo_elimina(): void
    {
        $a = $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');
        $this->post(route('transport.inspect.draft'), ['vehicle_id' => $vehicle->id, 'answers' => ['VEH-001' => 'ok']]);
        $this->assertNotNull(VehicleInspectionDraft::forAuthor($vehicle->id, $a->id));

        $this->post(route('transport.inspect.draft.discard', $vehicle))->assertSessionHasNoErrors();
        $this->assertNull(VehicleInspectionDraft::forAuthor($vehicle->id, $a->id));
    }

    // ── Documentos → marca "Documentos revisados" ────────────────────────────
    public function test_documentos_validados_y_vigentes_encienden_la_marca(): void
    {
        $vehicle = $this->makeVehicle('auto');

        foreach (Vehicle::REQUIRED_DOC_CODES as $code) {
            $dt  = DocumentType::where('code', $code)->first();
            $this->assertNotNull($dt, "El tipo de documento {$code} debe estar sembrado.");
            $doc = ExternalAuthorization::create([
                'holder_type'      => Vehicle::class,
                'holder_id'        => $vehicle->id,
                'document_type'    => $dt->name,
                'document_type_id' => $dt->id,
                'valid_until'      => now()->addYear(),
                'status'           => ExternalAuthorization::STATUS_PRESENTED,
                'is_active'        => 1,
            ]);
            $doc->validated_at    = now();
            $doc->validation_method = ExternalAuthorization::METHOD_DOCS;
            $doc->save();
        }

        $this->assertTrue($vehicle->fresh()->docsReviewed(), 'Los 4 docs validados y vigentes → marca encendida.');

        // Uno caduca → la marca se apaga (se vuelve a pedir).
        ExternalAuthorization::where('holder_type', Vehicle::class)
            ->where('holder_id', $vehicle->id)->latest('id')->first()
            ->update(['valid_until' => now()->subDay()]);

        $this->assertFalse($vehicle->fresh()->docsReviewed(), 'Un documento caducado apaga la marca.');
    }

    // ── Licencia del conductor (§2): va con la PERSONA, no con el vehículo ────
    public function test_licencia_va_al_paquete_del_driver_y_es_unica_por_conductor(): void
    {
        $this->actingAsRole('safety-officer');
        $driver = $this->makeUser('crew');
        $v1 = $this->makeVehicle('auto', ['driver_user_id' => $driver->id]);

        $this->post(route('transport.driver.license.store', $v1), [
            'folio'       => 'LIC-1',
            'valid_until' => now()->addYear()->toDateString(),
        ])->assertSessionHasNoErrors();

        // Vive en el PAYEE del conductor (holder=Payee, level persona), no en el vehículo.
        $payee = \App\Models\Payee::where('user_id', $driver->id)->first();
        $this->assertNotNull($payee, 'Se asegura el payee del conductor.');
        $lic = $payee->documents()->whereHas('documentType', fn ($q) => $q->where('code', 'VEH_LICENCIA'))->first();
        $this->assertNotNull($lic, 'La licencia cuelga del payee del conductor.');
        $this->assertSame('persona', $lic->level);
        $this->assertSame(0, $v1->documents()->count(), 'La licencia NO cuelga del vehículo.');

        // La ficha la MUESTRA (no la copia) y ya no está en los docs requeridos del vehículo.
        $this->assertSame($lic->id, $v1->fresh()->driverLicense()->id);
        $this->assertNotContains('VEH_LICENCIA', Vehicle::REQUIRED_DOC_CODES);

        // Un segundo vehículo del MISMO conductor ve la MISMA licencia (una sola registrada).
        $v2 = $this->makeVehicle('suv', ['driver_user_id' => $driver->id]);
        $this->assertSame($lic->id, $v2->driverLicense()->id, 'Un driver con dos unidades tiene una sola licencia.');
    }

    public function test_captura_y_validacion_de_documento_por_endpoint(): void
    {
        $this->actingAsRole('safety-officer');
        $vehicle = $this->makeVehicle('auto');
        $dt = DocumentType::where('code', 'VEH_TARJETA')->first();

        $this->post(route('transport.document.store', $vehicle), [
            'document_type_id' => $dt->id,
            'folio'            => 'ABC-123',
            'valid_until'      => now()->addYear()->toDateString(),
        ])->assertSessionHasNoErrors();

        $doc = ExternalAuthorization::where('holder_type', Vehicle::class)->where('holder_id', $vehicle->id)->latest('id')->first();
        $this->assertNotNull($doc);
        $this->assertTrue($doc->isPending(), 'Nace pendiente (capturar ≠ validar).');

        $this->post(route('transport.document.validate', $doc->id), ['attestation' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($doc->fresh()->isValidated(), 'Tras validar, queda validado.');
    }
}
