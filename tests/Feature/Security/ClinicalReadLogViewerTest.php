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
    /**
     * 🪤 Los `patient_ref` son ALTOS a propósito, y ahí está la historia.
     *
     * Antes eran 42 y 99, y las aserciones buscaban el texto "#42"/"#99". Ese "#id" es el
     * RESPALDO que pinta la vista sólo cuando el paciente NO corresponde a un usuario conocido:
     * en cuanto existe un usuario con ese id, se muestra su NOMBRE y el "#42" desaparece.
     *
     * La suite crea usuarios en cada prueba, así que el autoincremento sube conforme crece. El día
     * que pasó de 42 (al sumar el módulo Tech Scout) esta prueba se puso en rojo — y parecía una
     * FUGA DEL FILTRO en datos clínicos, que es de lo más alarmante que puede fallar aquí. No lo
     * era: el filtro siempre funcionó; lo que fallaba era la aserción.
     *
     * Con refs fuera del alcance de cualquier id de usuario, la prueba comprueba lo que dice
     * comprobar —que el filtro sólo lista al paciente pedido— y deja de depender de cuánta gente
     * haya creado el resto de la suite.
     */
    private const PACIENTE_A = 900042;
    private const PACIENTE_B = 900099;

    private function seedLogs(): void
    {
        DB::table('clinical_read_logs')->insert([
            ['reader_id' => 1, 'reader_is_clinical' => 0, 'record_type' => 'expediente', 'record_id' => null, 'patient_ref' => self::PACIENTE_A, 'ip_address' => '10.0.0.1', 'opened_at' => now(), 'created_at' => now()],
            ['reader_id' => 2, 'reader_is_clinical' => 1, 'record_type' => 'consulta', 'record_id' => 7, 'patient_ref' => self::PACIENTE_B, 'ip_address' => '10.0.0.2', 'opened_at' => now()->subDays(3), 'created_at' => now()],
        ]);
    }

    public function test_super_admin_abre_el_visor(): void
    {
        $this->seedLogs();
        $this->actingAsRole('super-admin');
        $this->get('/bitacora-clinica')->assertOk()
            ->assertSee('Bitácora de lectura clínica')
            ->assertSee('#' . self::PACIENTE_A);
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
        $res = $this->get('/bitacora-clinica?patient=' . self::PACIENTE_A);
        $res->assertOk()
            ->assertSee('#' . self::PACIENTE_A)
            ->assertDontSee('#' . self::PACIENTE_B);
    }
}
