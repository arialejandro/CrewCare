<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * Visor de la bitácora de lectura clínica: SOLO super-admin (403 para todos los demás, auditor
 * incluido). Muestra quién leyó, a quién, cuándo, desde dónde y si era clínico; filtra por
 * paciente/lector/fechas. Es el REGISTRO, no el expediente.
 */
class ClinicalReadLogViewerTest extends QaTestCase
{
    private function seedLogs(): void
    {
        DB::table('clinical_read_logs')->insert([
            ['reader_id' => 1, 'reader_is_clinical' => 0, 'record_type' => 'expediente', 'record_id' => null, 'patient_ref' => 42, 'ip_address' => '10.0.0.1', 'opened_at' => now(), 'created_at' => now()],
            ['reader_id' => 2, 'reader_is_clinical' => 1, 'record_type' => 'consulta', 'record_id' => 7, 'patient_ref' => 99, 'ip_address' => '10.0.0.2', 'opened_at' => now()->subDays(3), 'created_at' => now()],
        ]);
    }

    public function test_super_admin_abre_el_visor(): void
    {
        $this->seedLogs();
        $this->actingAsRole('super-admin');
        $this->get('/bitacora-clinica')->assertOk()
            ->assertSee('Bitácora de lectura clínica')
            ->assertSee('#42');
    }

    public function test_auditor_recibe_403(): void
    {
        $this->actingAsRole('auditor');
        $this->get('/bitacora-clinica')->assertForbidden();
    }

    public function test_produccion_y_medico_reciben_403(): void
    {
        $this->actingAsRole('line-producer');
        $this->get('/bitacora-clinica')->assertForbidden();

        $this->actingAsRole('medic');
        $this->get('/bitacora-clinica')->assertForbidden();
    }

    public function test_filtra_por_paciente(): void
    {
        $this->seedLogs();
        $this->actingAsRole('super-admin');
        $res = $this->get('/bitacora-clinica?patient=42');
        $res->assertOk()->assertSee('#42')->assertDontSee('#99');
    }
}
