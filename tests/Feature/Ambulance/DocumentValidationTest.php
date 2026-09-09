<?php

namespace Tests\Feature\Ambulance;

use App\Models\ExternalAuthorization;

/**
 * DOCUMENTOS / AUTORIZACIONES del proveedor (Parte B) — invariante "capturar ≠ cotejar".
 *
 * Espejo de la cédula profesional: un documento NACE PENDIENTE (validated_* intactos, fuera de
 * $fillable). La validación es OTRA acción, con OTRA autoridad, que exige la DECLARACIÓN DE COTEJO
 * (attestation) y se registra a nombre de quien la hace (validated_by_id). Nadie puede colar la
 * validación por el POST de captura.
 */
class DocumentValidationTest extends AmbulanceVerticalTestCase
{
    // =====================================================================
    //  Captura → NACE PENDIENTE (validated_* nunca por POST).
    // =====================================================================

    public function test_documento_capturado_nace_pendiente(): void
    {
        $this->actingAsRole('safety-officer');
        $provider = $this->makeProvider();

        $resp = $this->post(route('ambulance.document.store'), [
            'holder_type'   => 'empresa',
            'holder_id'     => $provider->id,
            'document_type' => 'Licencia sanitaria',
            'authority'     => 'COFEPRIS',
            'origen'        => 'normativo',
            'status'        => 'presentado',
            // Intento de COLAR la validación por el POST (no está en fillable → debe ignorarse).
            'validated_at'  => now()->toDateTimeString(),
        ]);
        $resp->assertSessionHas('success');

        $doc = ExternalAuthorization::latest('id')->first();
        $this->assertNotNull($doc);
        $this->assertSame(ExternalAuthorization::LEVEL_COMPANY, $doc->level, 'El nivel se deriva del titular (empresa), no del cliente.');
        $this->assertTrue($doc->isPending(), 'El documento capturado NACE PENDIENTE.');
        $this->assertNull($doc->validated_at, 'validated_at no llega por el POST de captura.');
        $this->assertNull($doc->validated_by_id);
    }

    // =====================================================================
    //  Validar SIN declaración de cotejo → rebota, sigue pendiente.
    // =====================================================================

    public function test_validar_sin_attestation_no_valida(): void
    {
        $actor    = $this->actingAsRole('safety-officer');
        $provider = $this->makeProvider();
        $doc      = ExternalAuthorization::create([
            'holder_type'   => \App\Models\AmbulanceProvider::class,
            'holder_id'     => $provider->id,
            'level'         => ExternalAuthorization::LEVEL_COMPANY,
            'document_type' => 'Póliza de responsabilidad civil',
            'origen'        => 'contractual',
            'status'        => 'presentado',
            'is_active'     => 1,
        ]);

        // Sin marcar la declaración de cotejo.
        $this->post(route('ambulance.document.validate', $doc->id), [])
            ->assertSessionHas('error');

        $doc->refresh();
        $this->assertTrue($doc->isPending(), 'Sin declaración de cotejo el documento SIGUE pendiente.');
    }

    // =====================================================================
    //  Validar CON declaración → validado bajo la responsabilidad de quien lo hace.
    // =====================================================================

    public function test_validar_con_attestation_registra_quien_valido(): void
    {
        $actor    = $this->actingAsRole('safety-officer');
        $provider = $this->makeProvider();
        $doc      = ExternalAuthorization::create([
            'holder_type'   => \App\Models\AmbulanceProvider::class,
            'holder_id'     => $provider->id,
            'level'         => ExternalAuthorization::LEVEL_COMPANY,
            'document_type' => 'Licencia sanitaria',
            'origen'        => 'normativo',
            'status'        => 'presentado',
            'is_active'     => 1,
        ]);

        $this->post(route('ambulance.document.validate', $doc->id), ['attestation' => 1])
            ->assertSessionHas('success');

        $doc->refresh();
        $this->assertTrue($doc->isValidated(), 'Con la declaración marcada, el documento queda validado.');
        $this->assertSame($actor->id, (int) $doc->validated_by_id, 'La validación se registra a nombre de quien la hace.');
        // Hoy siempre "documentos revisados" (no hay consulta a registro).
        $this->assertSame(ExternalAuthorization::METHOD_DOCS, $doc->validation_method);
        $this->assertFalse($doc->wasCheckedAgainstRegistry());
    }
}
