<?php

namespace Tests\Feature\Ambulance;

use App\Models\AmbulanceInspection;
use Illuminate\Support\Facades\DB;

/**
 * VERIFICADOR PÚBLICO del acta de ambulancia (GET /verificar/ambu/{uuid}). SIN sesión: un tercero
 * (auditoría, la autoridad) llega escaneando el QR. Tres estados: INTEGRIDAD (íntegro/alterado/sin
 * sello) y VIGENCIA (vigente/retirado). Retirar NUNCA se lee como ALTERADO. Y NADA de contenido ni
 * identidad puede filtrarse (el DTO acotado es toda la defensa de privacidad).
 *
 * NO se usa actingAs: se prueba precisamente el acceso de un INVITADO.
 */
class PublicVerifierTest extends AmbulanceVerticalTestCase
{
    private function url(string $uuid): string
    {
        return '/verificar/ambu/' . $uuid;
    }

    public function test_acta_integra_200_sin_sesion(): void
    {
        $insp = $this->sealAmbulanceInspection();
        $resp = $this->get($this->url($insp->uuid));

        $resp->assertOk();
        $resp->assertSee('Documento íntegro y vigente');
        $resp->assertSee($insp->uuid);
        $resp->assertSee($insp->folio());          // AMBU-####
        $resp->assertSee('Acta de verificación de ambulancia'); // etiqueta genérica
    }

    /** El acuse público NO revela contenido ni identidad (observaciones, placas, proveedor, inspector, veredicto). */
    public function test_verificador_no_filtra_pii_ni_contenido(): void
    {
        $insp = $this->sealAmbulanceInspection([
            'observations'    => 'SECRETO-OBSERVACION-XYZ',
            'plates'          => 'PLACA-SECRETA-99',
            'provider_name'   => 'PROVEEDOR-CONFIDENCIAL',
            'inspector_name'  => 'INSPECTOR-CONFIDENCIAL',
        ]);
        $resp = $this->get($this->url($insp->uuid));

        $resp->assertOk();
        $resp->assertDontSee('SECRETO-OBSERVACION-XYZ');
        $resp->assertDontSee('PLACA-SECRETA-99');
        $resp->assertDontSee('PROVEEDOR-CONFIDENCIAL');
        $resp->assertDontSee('INSPECTOR-CONFIDENCIAL');
        // El VEREDICTO tampoco se nombra en el acuse (la etiqueta es genérica).
        $resp->assertDontSee('paro');
    }

    public function test_acta_alterada_en_bd_se_muestra_alterada(): void
    {
        $insp = $this->sealAmbulanceInspection(['plates' => 'ORIGINAL-1']);

        // Placas ENTRAN al hash (contenido, no estado).
        DB::table('ambulance_inspections')->where('id', $insp->id)
            ->update(['plates' => 'CAMBIADA-2']);

        $resp = $this->get($this->url($insp->uuid));
        $resp->assertOk();
        $resp->assertSee('Documento alterado');
        $resp->assertSee('NO coincide con el sello');
    }

    public function test_checklist_alterado_se_detecta(): void
    {
        $insp = $this->sealAmbulanceInspection();

        DB::table('ambulance_inspections')->where('id', $insp->id)
            ->update(['checklist_snapshot' => json_encode([['code' => 'X', 'answer' => 'fail', 'is_gate' => true]])]);

        $this->get($this->url($insp->uuid))->assertOk()->assertSee('Documento alterado');
    }

    /** Retirar es cambio de ESTADO (columnas hash-excluidas): íntegro pero no vigente, nunca "alterado". */
    public function test_acta_retirada_valida_pero_no_vigente(): void
    {
        $insp = $this->sealAmbulanceInspection();

        DB::table('ambulance_inspections')->where('id', $insp->id)
            ->update(['is_active' => 0, 'retired_at' => now(), 'retired_reason' => 'MOTIVO-CONFIDENCIAL-XYZ']);

        $resp = $this->get($this->url($insp->uuid));
        $resp->assertOk();
        $resp->assertSee('El sello es auténtico');
        $resp->assertSee('retirado');                 // "Válido, pero retirado"
        $resp->assertDontSee('Documento alterado');
        // El motivo de retiro (texto libre) NUNCA llega al verificador público.
        $resp->assertDontSee('MOTIVO-CONFIDENCIAL-XYZ');
    }

    public function test_acta_sin_sello_se_muestra_sin_sello(): void
    {
        $type = $this->aTerrestrialType();
        $insp = AmbulanceInspection::create([
            'trigger_scope' => AmbulanceInspection::TRIGGER_FULL,
            'type_code'     => $type->code,
            'verdict'       => AmbulanceInspection::VERDICT_APTA,
            'is_active'     => 1,
        ]);

        $this->get($this->url($insp->uuid))->assertOk()->assertSee('Documento sin sello');
    }

    public function test_uuid_inexistente_404_generico(): void
    {
        $this->get($this->url('11111111-2222-3333-4444-555555555555'))
            ->assertStatus(404)
            ->assertSee('No se encontró el documento');
    }
}
