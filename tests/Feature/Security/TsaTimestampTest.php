<?php

namespace Tests\Feature\Security;

use App\Models\InjuryReport;
use App\Support\Rfc3161;
use App\Support\SealVerifier;
use App\Support\TsaStamper;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Sello de tiempo TSA (RFC 3161). El timbre es EXTERNO y best-effort: cubre sellos ya existentes
 * (retroactivo), aparece en el verificador cuando existe, y si la TSA no responde NO bloquea ni
 * altera el sello. La red se corta con un transport inyectado (TSR de prueba).
 */
class TsaTimestampTest extends QaTestCase
{
    /** TSR mínimo: OID de TSTInfo + un GeneralizedTime que el parser encuentra. */
    private function cannedTsr(): string
    {
        return hex2bin('060b2a864886f70d0109100104') . chr(0x18) . chr(15) . '20260830120000Z';
    }

    protected function tearDown(): void
    {
        Rfc3161::$transport = null;
        parent::tearDown();
    }

    private function strictPayload(): array
    {
        return [
            'production_title' => 'Producción X', 'incident_date' => '2026-08-20', 'reported_date' => '2026-08-21',
            'name' => 'Juan Pérez', 'injury_type' => ['corte'], 'what_happened' => 'Se cortó.',
            'what_caused' => 'Filo.', 'preventions' => 'Guarda.', 'likelihood' => 'C', 'consequence' => 3,
            'treatment_level' => 'first_aid', 'manual_location_justification' => 'Interior sin GPS.',
        ];
    }

    private function sealedInjury(): InjuryReport
    {
        config(['features.progressive_capture' => false]);
        $this->actingAsRole('safety-officer');
        $this->post(route('injury_reports.store'), $this->strictPayload())->assertSessionHasNoErrors();
        return InjuryReport::latest('id')->first();
    }

    public function test_timbra_en_retroactivo_y_el_verificador_lo_muestra(): void
    {
        // El injury se sella ANTES de que exista timbre (retroactivo).
        $r = $this->sealedInjury();
        $this->assertTrue((bool) $r->verifyLatestSignature());

        Rfc3161::$transport = fn ($tsq) => $this->cannedTsr();   // TSA responde (sin red real)
        $res = TsaStamper::drain(50);

        $this->assertGreaterThanOrEqual(1, $res['stamped'], 'timbró al menos el injury');
        $sigId = (int) $r->signatures()->latest('id')->first()->id;
        $row = DB::table('signature_timestamps')->where('signature_id', $sigId)->first();
        $this->assertSame('stamped', $row->status);
        $this->assertNotNull($row->tsr);
        $this->assertSame('2026-08-30 12:00:00', \Carbon\Carbon::parse($row->gen_time)->format('Y-m-d H:i:s'));

        // El verificador público muestra el timbre cuando existe.
        $acuse = SealVerifier::resolve('injury', (string) $r->uuid);
        $this->assertNotEmpty($acuse['tsa_at']);
        $this->assertSame('freeTSA', $acuse['tsa_authority']);
    }

    public function test_best_effort_si_la_tsa_no_responde_no_bloquea_ni_altera_el_sello(): void
    {
        $r = $this->sealedInjury();

        Rfc3161::$transport = fn ($tsq) => null;   // freeTSA caída
        TsaStamper::drain(50);

        // El sello sigue ÍNTEGRO (cero ALTERADO) y NO quedó timbre.
        $this->assertTrue((bool) $r->fresh()->verifyLatestSignature(), 'el sello queda intacto');
        $sigId = (int) $r->signatures()->latest('id')->first()->id;
        $row = DB::table('signature_timestamps')->where('signature_id', $sigId)->first();
        $this->assertNotNull($row, 'se sembró la fila pendiente');
        $this->assertNotSame('stamped', $row->status, 'sin respuesta de la TSA no hay timbre');

        // Y el verificador NO inventa un timbre.
        $acuse = SealVerifier::resolve('injury', (string) $r->uuid);
        $this->assertArrayNotHasKey('tsa_at', $acuse);
    }

    public function test_desactivado_por_config_no_timbra(): void
    {
        $r = $this->sealedInjury();
        config(['crewcare.tsa.enabled' => false]);
        Rfc3161::$transport = fn ($tsq) => $this->cannedTsr();
        $res = TsaStamper::drain(50);
        $this->assertSame(0, $res['stamped']);
    }
}
