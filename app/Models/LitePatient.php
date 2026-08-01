<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * LitePatient — persona NO-crew atendida por el médico: extras, day players, visitantes,
 * proveedores. (2026-07-24 · módulo beta)
 *
 * NO es un usuario: sin cuenta, sin contraseña, sin rol, sin login. NO aparece en selectores de
 * asignación ni en listados de crew. Es un DIRECTORIO reutilizable: se captura una vez y se
 * reencuentra en la siguiente consulta (por eso NO es texto libre en la consulta — eso rompería
 * el cintillo de tratamiento previo, que necesita identidad estable).
 *
 * FUSIÓN DE DUPLICADOS: los duplicados van a pasar (el mismo extra capturado dos veces). `merged_into_id`
 * apunta a la fila SUPERVIVIENTE. La identidad se resuelve al GRUPO (superviviente + fundidas), pero
 * las consultas NUNCA se reescriben: cada `cmedic.lite_patient_id` conserva su valor original y por
 * tanto su SELLO. Ver identityGroupIds().
 */
class LitePatient extends Model
{
    use HasFactory;

    protected $table = 'lite_patients';

    protected $fillable = [
        'full_name',
        'dob',
        'age',
        'sex',
        'phone',
        'emergency_contact',
        'emergency_phone',
        'area',
        'origin',
        'merged_into_id',
        'created_by_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'age' => 'integer',
    ];

    /** ¿La tabla existe? Apaga el módulo sin reventar si el SQL no se aplicó (patrón de la casa). */
    public static function supported(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Schema::hasTable('lite_patients');
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** Fila superviviente en la que se fundió ésta (si es un duplicado). */
    public function mergedInto()
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** Duplicados fundidos EN ésta. */
    public function mergedFrom()
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    /** Consultas registradas directamente contra ESTA fila (no sigue el grupo de fusión). */
    public function consultas()
    {
        return $this->hasMany(cmedic::class, 'lite_patient_id', 'id');
    }

    /** ¿Es un duplicado ya fundido en otra persona? */
    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    /**
     * IDs de TODAS las filas que representan a esta persona: ella + las que se fundieron en ella.
     * Es la llave del cintillo y del historial: agrupa las consultas de todos los duplicados sin
     * tocar ninguna (cada consulta conserva su lite_patient_id y su sello).
     *
     * Se parte SIEMPRE de la superviviente: si a esta instancia la fundieron, se resuelve primero
     * hacia arriba para no perder consultas del otro lado.
     *
     * @return array<int>
     */
    public function identityGroupIds(): array
    {
        $survivor = $this->isMerged() && $this->mergedInto ? $this->mergedInto : $this;
        $ids = [(int) $survivor->id];
        foreach ($survivor->mergedFrom as $dup) {
            $ids[] = (int) $dup->id;
        }
        return array_values(array_unique($ids));
    }

    /**
     * (2026-07-31) Conteo de consultas POR GRUPO DE IDENTIDAD para un conjunto de supervivientes,
     * en UNA sola pasada (no N+1). Devuelve [survivor_id => total]. Suma las consultas de la
     * superviviente MÁS las de sus duplicados fundidos, sin reescribir ningún `lite_patient_id`
     * (cada consulta conserva su sello). FUENTE ÚNICA del contador — la usa el home médico
     * unificado (/medicocrud) y cualquier listado de pacientes sin cuenta.
     *
     * @param  array<int>  $survivorIds  ids de filas supervivientes (whereNull merged_into_id)
     * @return array<int,int>
     */
    public static function consultCountsFor(array $survivorIds): array
    {
        $counts = [];
        if (empty($survivorIds)) {
            return $counts;
        }
        // Duplicados fundidos en cada superviviente: id_duplicado => id_superviviente.
        $mergedMap = self::whereIn('merged_into_id', $survivorIds)->pluck('merged_into_id', 'id');
        // Todas las filas que aportan consultas (supervivientes + sus duplicados).
        $allIds = array_merge($survivorIds, $mergedMap->keys()->all());
        // Consultas por fila, en UNA sola consulta.
        $porFila = cmedic::whereIn('lite_patient_id', $allIds)
            ->selectRaw('lite_patient_id, COUNT(*) as n')
            ->groupBy('lite_patient_id')
            ->pluck('n', 'lite_patient_id');
        foreach ($survivorIds as $sid) {
            $sid = (int) $sid;
            $sum = (int) ($porFila[$sid] ?? 0);
            foreach ($mergedMap as $dupId => $survId) {
                if ((int) $survId === $sid) {
                    $sum += (int) ($porFila[$dupId] ?? 0);
                }
            }
            $counts[$sid] = $sum;
        }
        return $counts;
    }

    /** Nombre a mostrar. */
    public function displayName(): string
    {
        return trim((string) $this->full_name);
    }

    /**
     * Edad legible: usa `age` si se capturó, si no la deriva de `dob`. Null si no hay ninguno
     * (nunca inventa una edad).
     */
    public function ageDisplay()
    {
        if ($this->age !== null && $this->age !== '') {
            return (int) $this->age;
        }
        if ($this->dob) {
            try {
                return $this->dob->age;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}
