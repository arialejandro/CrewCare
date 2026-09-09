<?php

namespace Tests\Feature\Permit;

use App\Models\ToolInspection;
use App\Support\SealVerifier;

/**
 * CICLO REAL del acta de inspección VÍA CONTROLADOR (rutas POST): PARO → desbloqueo
 * (re-sella) y APTA → retiro (no re-sella). Complementa a PublicVerifierTest, que llega
 * al mismo estado manipulando la BD: aquí se prueba que los HANDLERS reales lo producen.
 */
class InspectionLifecycleTest extends PermitVerticalTestCase
{
    /** Crea un acta de PARO a través del controlador y la devuelve. */
    private function issueParo(): ToolInspection
    {
        $tool  = $this->makeToolWithPoints([['gate' => true, 'outcome' => 'reemplazo']]);
        $codes = $this->toolPointCodes($tool);
        $this->actingAsRole('safety-officer');
        $this->post(route('tools.inspect.store', $tool->id),
            $this->inspectionStorePayload($tool, [$codes[0] => 'fail']))->assertStatus(302);

        $insp = ToolInspection::latest('id')->first();
        $this->assertTrue($insp->isParo());
        return $insp;
    }

    public function test_desbloquear_un_paro_resella_y_sigue_integro(): void
    {
        $insp = $this->issueParo();
        $this->assertTrue($insp->isBlocked());

        $resp = $this->post(route('tools.inspection.unblock', $insp->uuid), []);
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $insp->refresh();
        $this->assertNotNull($insp->unblocked_at, 'El desbloqueo debe registrar autor/hora.');
        $this->assertTrue($insp->isUnblocked());
        // Re-sellado sobre el estado resuelto: el sello nuevo verifica íntegro.
        $this->assertTrue($insp->verifyLatestSignature(), 'El desbloqueo debe re-sellar íntegro.');
        $this->assertSame('ok', SealVerifier::resolve('insp', $insp->uuid)['verdict']);
    }

    public function test_no_se_desbloquea_dos_veces(): void
    {
        $insp = $this->issueParo();
        $this->post(route('tools.inspection.unblock', $insp->uuid), [])->assertSessionHas('success');
        $this->post(route('tools.inspection.unblock', $insp->uuid), [])->assertSessionHas('error');
    }

    public function test_un_acta_apta_no_es_paro_no_se_desbloquea(): void
    {
        $tool = $this->makeToolWithPoints([['gate' => true, 'outcome' => 'reemplazo']]);
        $this->actingAsRole('safety-officer');
        $this->post(route('tools.inspect.store', $tool->id), $this->inspectionStorePayload($tool))->assertStatus(302);
        $insp = ToolInspection::latest('id')->first();
        $this->assertSame(ToolInspection::VERDICT_APTA, $insp->verdict);

        $this->post(route('tools.inspection.unblock', $insp->uuid), [])->assertSessionHas('error');
    }

    public function test_retirar_un_acta_via_controlador_no_altera_el_sello(): void
    {
        $tool = $this->makeToolWithPoints([['gate' => true, 'outcome' => 'reemplazo']]);
        $this->actingAsRole('safety-officer');
        $this->post(route('tools.inspect.store', $tool->id), $this->inspectionStorePayload($tool))->assertStatus(302);
        $insp = ToolInspection::latest('id')->first();

        $resp = $this->post(route('tools.inspection.retire', $insp->uuid), ['retired_reason' => 'Reinspección posterior']);
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        $insp->refresh();
        $this->assertTrue($insp->isRetired());
        // Retirar es estado, no alteración: el verificador lo lee VÁLIDO PERO RETIRADO.
        $acuse = SealVerifier::resolve('insp', $insp->uuid);
        $this->assertSame('ok', $acuse['verdict'], 'Retirar NO debe leerse como ALTERADO.');
        $this->assertTrue($acuse['retired']);
    }

    public function test_no_se_retira_dos_veces(): void
    {
        $tool = $this->makeToolWithPoints([['gate' => true, 'outcome' => 'reemplazo']]);
        $this->actingAsRole('safety-officer');
        $this->post(route('tools.inspect.store', $tool->id), $this->inspectionStorePayload($tool))->assertStatus(302);
        $insp = ToolInspection::latest('id')->first();

        $this->post(route('tools.inspection.retire', $insp->uuid), [])->assertSessionHas('success');
        $this->post(route('tools.inspection.retire', $insp->uuid), [])->assertSessionHas('error');
    }
}
