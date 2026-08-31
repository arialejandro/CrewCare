<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ClinicalReadLog — registra una LECTURA de expediente clínico. INVISIBLE y best-effort:
 * nunca lanza, nunca bloquea la vista. Se llama al TOPE de cada visor clínico, después del
 * control de acceso (registramos las lecturas PERMITIDAS).
 *
 * Importa porque producción (HOD/line-producer/coordinador) puede leer expedientes por decisión
 * del owner: sin esto, gente no clínica lee datos clínicos sin rastro. `reader_is_clinical`
 * (isMedic) distingue al médico de quien sólo observa.
 */
class ClinicalReadLog
{
    public const T_EXPEDIENTE      = 'expediente';
    public const T_CONSULTA        = 'consulta';
    public const T_EXPEDIENTE_LITE = 'expediente_lite';
    public const T_INJURY_COMPLETO = 'injury_completo';

    /**
     * Deja el rastro de una lectura. $recordId = id del documento; $patientRef = paciente
     * (users.id o lite_patients.id). Best-effort: cualquier fallo se traga (no rompe la vista).
     */
    public static function record(string $recordType, ?int $recordId = null, ?int $patientRef = null): void
    {
        try {
            if (! Schema::hasTable('clinical_read_logs')) {
                return;   // defensivo: instancia sin el SQL aplicado.
            }
            $u = Auth::user();
            $req = request();
            DB::table('clinical_read_logs')->insert([
                'reader_id'          => $u ? $u->id : null,
                'reader_is_clinical' => $u && method_exists($u, 'isMedic') ? (int) $u->isMedic() : 0,
                'record_type'        => $recordType,
                'record_id'          => $recordId,
                'patient_ref'        => $patientRef,
                'route_name'         => $req && $req->route() ? substr((string) $req->route()->getName(), 0, 64) : null,
                'ip_address'         => $req ? $req->ip() : null,
                'opened_at'          => now(),
                'created_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // Silencio deliberado: la bitácora JAMÁS debe tumbar la lectura de un expediente.
        }
    }
}
