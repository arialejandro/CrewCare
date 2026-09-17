<?php

namespace Tests\Feature\Contract;

use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractArchitectures;
use App\Support\ContractTemplateRenderer;
use App\Support\CurrentProduction;
use Tests\QaTestCase;

/**
 * CONTRACT BUILDER · COMPOSICIÓN — el DOCUMENTO auto-llena los DATOS del contratado (su ficha de
 * "quién cobra") en vez de dejar huecos [CONFIRMAR]. Verifica los tokens nuevos de valuesFor() y
 * que el andamiaje bilingüe ya no traiga [CONFIRMAR] en los campos de dato. El clausulado y los
 * términos NEGOCIADOS por contrato siguen manuales (frontera legal).
 */
class ContractCompositionTest extends QaTestCase
{
    private function crewContract(Payee $payee): PayeeContract
    {
        return $payee->contracts()->create([
            'concept'       => PayeeContract::CONCEPT_CREW,
            'is_active'     => 1,
            'production_id' => CurrentProduction::id(),
            'title'         => 'Gaffer',
            'crew_activity' => 'Iluminación',
        ])->fresh();
    }

    public function test_valuesfor_fills_the_contratado_data(): void
    {
        $payee = Payee::create([
            'legal_nature' => 'fisica', 'name' => 'Juan Pérez López', 'rfc' => 'XAXX010101000',
            'phone' => '55 1111 2222', 'email' => 'juan@ejemplo.mx',
            'addr_street' => 'Av. Siempre Viva', 'addr_ext_no' => '742', 'addr_colonia' => 'Springfield',
            'addr_municipio' => 'Cuauhtémoc', 'addr_state' => 'CDMX', 'addr_cp' => '06000',
            'emergency_contact_name' => 'Ana Pérez', 'emergency_contact_phone' => '55 3333 4444',
        ]);
        $payee->beneficiaries()->create(['full_name' => 'Ana Pérez', 'relationship' => 'Hermana', 'percentage' => 100]);

        $v = ContractTemplateRenderer::valuesFor($this->crewContract($payee));

        $this->assertStringContainsString('Av. Siempre Viva', $v['domicilio_contratado']);
        $this->assertStringContainsString('No. 742', $v['domicilio_contratado']);
        $this->assertStringContainsString('C.P. 06000', $v['domicilio_contratado']);
        $this->assertSame('55 1111 2222', $v['tel_contratado']);
        $this->assertSame('juan@ejemplo.mx', $v['correo_contratado']);
        $this->assertStringContainsString('Ana Pérez', $v['emergencia_contratado']);
        $this->assertStringContainsString('55 3333 4444', $v['emergencia_contratado']);
        $this->assertStringContainsString('Ana Pérez', $v['beneficiario_contratado']);
        $this->assertStringContainsString('Hermana', $v['beneficiario_contratado']);

        // FÍSICA: loanout y representante legal quedan VACÍOS, jamás "[CONFIRMAR]".
        $this->assertSame('', (string) $v['loanout_contratado']);
        $this->assertSame('', (string) $v['representante_legal_contratado']);

        // Título del programa desde la producción del contrato.
        $this->assertNotEmpty($v['titulo_programa']);
    }

    public function test_phone_and_email_fall_back_to_linked_user(): void
    {
        $user  = $this->makeUser('super-admin', ['phone' => '55 9999 0000', 'email' => 'crew-link@ejemplo.mx']);
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Crew Ligado', 'user_id' => $user->id]);

        $v = ContractTemplateRenderer::valuesFor($this->crewContract($payee));

        $this->assertSame('55 9999 0000', $v['tel_contratado']);
        $this->assertSame('crew-link@ejemplo.mx', $v['correo_contratado']);
    }

    public function test_moral_payee_exposes_loanout_and_legal_rep(): void
    {
        $payee = Payee::create([
            'legal_nature' => 'moral', 'name' => 'Servicios de Producción S.A. de C.V.',
            'rfc' => 'XAXX010101000', 'legal_representative' => 'Laura Gómez',
        ]);

        $v = ContractTemplateRenderer::valuesFor($this->crewContract($payee));

        $this->assertSame('Servicios de Producción S.A. de C.V.', $v['loanout_contratado']);
        $this->assertSame('Laura Gómez', $v['representante_legal_contratado']);
    }

    public function test_bilingual_crew_scaffold_uses_chips_not_confirmar(): void
    {
        $body = ContractArchitectures::starter('bilingual_crew');

        // Los datos del contratado ahora son chips, no [CONFIRMAR].
        $this->assertStringNotContainsString('Address: [CONFIRMAR]', $body);
        $this->assertStringNotContainsString('Domicilio: [CONFIRMAR]', $body);
        $this->assertStringNotContainsString('Cellphone', $body);   // el renglón de celular se retiró
        $this->assertStringContainsString('{{domicilio_contratado}}', $body);
        $this->assertStringContainsString('{{titulo_programa}}', $body);
        $this->assertStringContainsString('{{beneficiario_contratado}}', $body);

        // La FRONTERA LEGAL se conserva: el clausulado sigue manual.
        $this->assertStringContainsString('[Draft', $body);
    }
}
