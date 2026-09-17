<?php

namespace Tests\Feature\Security;

use App\Models\InjuryReport;
use App\Support\ClinicalReadLog;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Bitácora de LECTURA clínica (invisible) + healthcheck + retención. Producción puede leer
 * expedientes; esto deja el rastro de quién y cuándo, marcando si el lector es clínico.
 */
class ClinicalReadLogTest extends QaTestCase
{
    public function test_registra_lectura_y_marca_no_clinico(): void
    {
        $u = $this->actingAsRole('line-producer');   // produccion, NO clínico
        ClinicalReadLog::record(ClinicalReadLog::T_EXPEDIENTE, null, 77);

        $row = DB::table('clinical_read_logs')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame($u->id, (int) $row->reader_id);
        $this->assertSame(0, (int) $row->reader_is_clinical, 'line-producer no es clínico');
        $this->assertSame('expediente', $row->record_type);
        $this->assertSame(77, (int) $row->patient_ref);
        $this->assertNotNull($row->opened_at);
    }

    public function test_marca_al_lector_clinico(): void
    {
        $this->actingAsRole('medic');
        ClinicalReadLog::record(ClinicalReadLog::T_CONSULTA, 5, 9);
        $row = DB::table('clinical_read_logs')->latest('id')->first();
        $this->assertSame(1, (int) $row->reader_is_clinical, 'el médico sí es clínico');
    }

    public function test_abrir_injury_completo_deja_rastro(): void
    {
        config(['features.progressive_capture' => false]);
        $author = $this->actingAsRole('safety-officer');
        $this->post(route('injury_reports.store'), [
            'production_title' => 'Producción X', 'incident_date' => '2026-08-20', 'reported_date' => '2026-08-21',
            'name' => 'Juan Pérez', 'injury_type' => ['corte'], 'what_happened' => 'Se cortó.',
            'what_caused' => 'Filo.', 'preventions' => 'Guarda.', 'likelihood' => 'C', 'consequence' => 3,
            'treatment_level' => 'first_aid', 'manual_location_justification' => 'Interior sin GPS.',
        ])->assertSessionHasNoErrors();
        $injury = InjuryReport::latest('id')->first();

        $before = DB::table('clinical_read_logs')->count();
        // El autor pasa el gate viewMedical → abre el expediente COMPLETO.
        $this->get("/accident/{$injury->id}/completo")->assertOk();

        $this->assertSame($before + 1, DB::table('clinical_read_logs')->count());
        $row = DB::table('clinical_read_logs')->latest('id')->first();
        $this->assertSame('injury_completo', $row->record_type);
        $this->assertSame((int) $injury->id, (int) $row->record_id);
    }

    public function test_retencion_poda_lo_mayor_a_3_anios(): void
    {
        DB::table('clinical_read_logs')->insert([
            ['reader_id' => 1, 'record_type' => 'expediente', 'opened_at' => now()->subYears(4), 'created_at' => now()->subYears(4)],
            ['reader_id' => 1, 'record_type' => 'expediente', 'opened_at' => now()->subMonths(2), 'created_at' => now()->subMonths(2)],
        ]);
        $this->artisan('clinical-log:prune')->assertSuccessful();
        $this->assertSame(1, DB::table('clinical_read_logs')->count(), 'queda sólo la reciente');
    }

    public function test_healthcheck_responde_sano(): void
    {
        $res = $this->get('/healthz');
        $res->assertOk();
        $res->assertJsonPath('status', 'ok');
        $res->assertJsonPath('checks.db', true);
    }
}
