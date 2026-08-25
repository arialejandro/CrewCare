<?php

namespace Tests\Feature\Transport;

use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\Vehicle;
use App\Models\VehicleCheckPoint;
use App\Models\VehicleInspection;
use App\Models\VehicleType;
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
        $this->assertSame(50, VehicleCheckPoint::count(), '50 puntos de fábrica.');
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
