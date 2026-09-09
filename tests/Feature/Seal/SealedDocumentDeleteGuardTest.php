<?php

namespace Tests\Feature\Seal;

use App\Models\InjuryReport;
use Tests\QaTestCase;

/**
 * INTEGRIDAD (2026-09-05) — un documento SELLADO no se borra en duro (hook `deleting` del trait
 * HasDigitalSignatures). Borrarlo dejaría su firma huérfana y destruiría la evidencia de integridad.
 * Un documento SIN firma sí se borra normalmente.
 */
class SealedDocumentDeleteGuardTest extends QaTestCase
{
    private function injurySellado(): InjuryReport
    {
        $user = $this->makeUser('safety-officer');
        $injury = InjuryReport::create(['name' => 'X', 'what_happened' => 'y']);
        $injury->refresh();
        $injury->signDocument($user);

        return $injury;
    }

    public function test_borrar_en_duro_un_documento_sellado_lanza_con_mensaje_claro(): void
    {
        $injury = $this->injurySellado();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('documento SELLADO');
        $injury->delete();
    }

    public function test_el_documento_sellado_y_su_firma_sobreviven_al_intento(): void
    {
        $injury = $this->injurySellado();

        try {
            $injury->delete();
        } catch (\RuntimeException $e) {
            // esperado
        }

        $this->assertNotNull(InjuryReport::find($injury->id), 'El documento sellado NO debió borrarse.');
        $this->assertTrue($injury->signatures()->exists(), 'La firma sigue viva: ni huérfana ni borrada.');
    }

    public function test_documento_sin_firma_se_borra_normalmente(): void
    {
        $injury = InjuryReport::create(['name' => 'X', 'what_happened' => 'y']);
        $id = $injury->id;

        $this->assertTrue((bool) $injury->delete());
        $this->assertNull(InjuryReport::find($id), 'Un documento SIN firma debe poder borrarse.');
    }
}
