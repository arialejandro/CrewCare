<?php

namespace Tests\Feature\Payee;

use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\PayeePackage;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * VERIFICACIÓN del Paso 2 (paquetes configurables) + cierre del Paso 1 (regímenes).
 */
class PayeePackageTest extends QaTestCase
{
    private function prodId()
    {
        return DB::table('productions')->min('id');
    }

    /** CIERRE PASO 1: una identidad con dos regímenes; cada contrato apunta al suyo. */
    public function test_identity_two_regimes_each_contract_points_to_one(): void
    {
        $payee = Payee::create(['legal_nature' => Payee::NATURE_FISICA, 'name' => 'Juan Pérez']);
        $r1 = $payee->fiscalRegimes()->create(['code' => '605', 'name' => 'Sueldos y salarios']);
        $r2 = $payee->fiscalRegimes()->create(['code' => '606', 'name' => 'Arrendamiento']);

        $c1 = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_CREW,   'fiscal_regime_id' => $r1->id]);
        $c2 = $payee->contracts()->create(['concept' => PayeeContract::CONCEPT_RENTAL, 'fiscal_regime_id' => $r2->id]);

        $this->assertSame(2, $payee->fiscalRegimes()->count());
        $this->assertTrue($c1->fiscalRegime->is($r1));
        $this->assertTrue($c2->fiscalRegime->is($r2));
        $this->assertNotSame($c1->fiscal_regime_id, $c2->fiscal_regime_id, 'cada contrato factura bajo su régimen');
    }

    /** Persona física y persona moral piden paquetes distintos. */
    public function test_fisica_and_moral_ask_different_packages(): void
    {
        $fisica = PayeePackage::identityRequirements($this->prodId(), 'fisica')->pluck('code')->all();
        $moral  = PayeePackage::identityRequirements($this->prodId(), 'moral')->pluck('code')->all();

        $this->assertContains('INE', $fisica);
        $this->assertNotContains('ACTA_CONSTITUTIVA', $fisica);
        $this->assertContains('ACTA_CONSTITUTIVA', $moral);
        $this->assertNotContains('INE', $moral);
        // comparten el núcleo fiscal (naturaleza 'ambas')
        $this->assertContains('CSF', $fisica);
        $this->assertContains('CSF', $moral);
        $this->assertContains('OPINION_32D', $fisica);
        $this->assertNotSame($fisica, $moral);
    }

    /** Activar REPSE agrega las dos tandas; desactivarlo las quita sin borrar lo capturado. */
    public function test_repse_toggle_adds_and_removes_both_batches_without_deleting_captured(): void
    {
        $payee = Payee::create(['legal_nature' => Payee::NATURE_MORAL, 'name' => 'Proveedor SA']);
        $contract = $payee->contracts()->create(['concept' => 'service', 'is_repse' => false]);

        $before = PayeePackage::contractRequirements($this->prodId(), $contract)->pluck('code');
        $this->assertTrue($before->contains('FACT_RENTA'), 'facturas de contrato sí, sin REPSE');
        $this->assertFalse($before->contains('REPSE_STPS'), 'sin REPSE no hay tandas');

        $contract->update(['is_repse' => true]);
        $on = PayeePackage::contractRequirements($this->prodId(), $contract->fresh())->pluck('code');
        $this->assertTrue($on->contains('REPSE_STPS'), 'tanda ANTES');
        $this->assertTrue($on->contains('REPSE_CFDI_NOMINA'), 'tanda DESPUÉS');

        // Capturo un doc REPSE y luego apago REPSE: el REQUISITO lo quita, el DOC capturado queda.
        $dt = DocumentType::where('code', 'REPSE_STPS')->firstOrFail();
        $doc = $contract->documents()->create([
            'level' => 'persona', 'document_type' => $dt->name, 'document_type_id' => $dt->id,
            'origen' => 'contractual', 'status' => 'presentado', 'is_active' => 1,
        ]);
        $contract->update(['is_repse' => false]);
        $off = PayeePackage::contractRequirements($this->prodId(), $contract->fresh())->pluck('code');
        $this->assertFalse($off->contains('REPSE_STPS'), 'requisito quitado');
        $this->assertNotNull(ExternalAuthorization::find($doc->id), 'lo ya recibido NO se borra');
    }

    /** Una 32-D sin estado positivo no cuenta como recibida en regla. */
    public function test_32d_without_positive_is_not_in_regla(): void
    {
        $cutDay = PayeePackage::cutDay($this->prodId());
        $dt32 = DocumentType::where('code', 'OPINION_32D')->firstOrFail();
        $payee = Payee::create(['legal_nature' => Payee::NATURE_FISICA, 'name' => 'X']);

        $doc = $payee->documents()->create([
            'level' => 'persona', 'document_type' => $dt32->name, 'document_type_id' => $dt32->id,
            'issued_at' => now()->toDateString(), 'result_status' => ExternalAuthorization::RESULT_NEGATIVE,
            'origen' => 'contractual', 'status' => 'presentado', 'is_active' => 1,
        ]);

        $this->assertSame(PayeePackage::ST_NOT_POSITIVE, PayeePackage::evaluate($dt32, $payee->documents()->get(), $cutDay));

        $doc->update(['result_status' => ExternalAuthorization::RESULT_POSITIVE]);
        $this->assertSame(PayeePackage::ST_RECEIVED, PayeePackage::evaluate($dt32, $payee->documents()->get(), $cutDay));
    }

    /** Cambiar la fecha de corte cambia el cálculo de la 32-D; mes-corriente ≠ 90 días. */
    public function test_cut_date_changes_32d_expiry_and_shapes_differ(): void
    {
        $issued = \Carbon\Carbon::parse('2026-08-05');
        $dt32 = DocumentType::where('code', 'OPINION_32D')->firstOrFail();       // current_month
        $dom  = DocumentType::where('code', 'COMP_DOMICILIO')->firstOrFail();    // 90 días

        $this->assertSame('2026-09-01', $dt32->expiryFrom($issued, 1)->toDateString());
        $this->assertSame('2026-08-17', $dt32->expiryFrom($issued, 17)->toDateString());
        $this->assertNotSame(
            $dt32->expiryFrom($issued, 1)->toDateString(),
            $dt32->expiryFrom($issued, 17)->toDateString(),
            'cambiar el corte cambia la caducidad'
        );
        $this->assertSame($issued->copy()->addDays(90)->toDateString(), $dom->expiryFrom($issued)->toDateString());
    }

    /** Los paquetes se editan por producción sin tocar código (toggle is_required). */
    public function test_packages_are_editable_per_production(): void
    {
        $prodId = $this->prodId();
        $this->assertTrue(PayeePackage::identityRequirements($prodId, 'fisica')->pluck('code')->contains('COTIZACION'));

        $cotiz = DocumentType::where('code', 'COTIZACION')->firstOrFail();
        DocumentRequirement::where('production_id', $prodId)
            ->where('document_type_id', $cotiz->id)
            ->update(['is_required' => 0]);

        $this->assertFalse(
            PayeePackage::identityRequirements($prodId, 'fisica')->pluck('code')->contains('COTIZACION'),
            'apagar el requisito lo saca del paquete, sin tocar código'
        );
    }
}
