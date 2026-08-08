<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;
use App\Traits\TracksCorrectiveActions;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ACTA DE INSPECCIÓN DE HERRAMIENTA (vertical de enforcement, delta #42).
 *
 * Es un DOCUMENTO CON CONSECUENCIA: se sella con HasDigitalSignatures sobre el
 * DATO (attributesToArray ksorteado), nunca sobre el render, y entra al
 * verificador público con su QR (registrado en SealVerifier::TYPES como 'insp').
 *
 * CONGELA su origen (doctrina cmedic/cédula congelada): el catálogo se une a las
 * normas, pero el acta las COPIA — `checklist_snapshot`, `tool_*`, `inspector_*`
 * son columnas propias, no relaciones. Si mañana cambia el catálogo, el acta sigue
 * diciendo lo que dijo y el sello lo prueba (se firma sobre columnas propias).
 *
 * El VEREDICTO se deriva del dato, no se captura: la calculadora
 * (App\Support\InspectionVerdict) lo computa desde `outcome_if_fail`/`is_gate` de
 * los puntos que cayeron. El PARO se DESBLOQUEA (acto con autor: unblocked_by/at)
 * y ese desbloqueo RE-SELLA (nueva firma sobre el estado resuelto).
 */
class ToolInspection extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey, TracksCorrectiveActions;

    protected $table = 'tool_inspections';

    /** Veredictos posibles (los 3 titulares de la calculadora de inoperatividad). */
    const VERDICT_PARO      = 'paro';                    // cayó un gate de correccion/reemplazo
    const VERDICT_NO_EXEC   = 'actividad_no_ejecutable'; // cayó un gate de actividad
    const VERDICT_APTA      = 'apta';                    // ningún gate cayó (condicionados = observación)

    /** Vías de salida del PARO (de outcome_if_fail del punto que gobierna). */
    const PATH_SAME_DAY     = 'correccion_mismo_dia';
    const PATH_REPLACE      = 'reemplazo';

    /** Modos del checklist doble (rótulo; una sola lista, dos modos). */
    const MODE_SAFETY       = 'safety';    // el safety, al detectar (operación detenida)
    const MODE_OPERATOR     = 'operator';  // el operador designado, antes del turno

    /** Momento de la inspección (A2). Cambia cómo se REDACTA el veredicto, no cómo se calcula. */
    const MOMENT_ARRIVAL    = 'llegada_equipo'; // llega el equipo, nada corriendo → ventana
    const MOMENT_PRE_USE    = 'previo_al_uso';  // antes de usar → ventana (default)
    const MOMENT_IN_USE     = 'en_uso';         // en uso → PARO INMEDIATO
    const MOMENT_FROM_HAZARD = 'por_hallazgo';  // desde un hallazgo → PARO (el equipo ya está en juego)

    /** Momentos PRE-USO: un fallo es "equipo no autorizado con ventana", no paro. */
    const PRE_USE_MOMENTS = [self::MOMENT_ARRIVAL, self::MOMENT_PRE_USE];

    /** Los 4 momentos válidos (validación + selector). */
    const MOMENTS = [self::MOMENT_ARRIVAL, self::MOMENT_PRE_USE, self::MOMENT_IN_USE, self::MOMENT_FROM_HAZARD];

    protected $fillable = [
        'uuid', 'production_id', 'shoot_day',
        'tool_id', 'tool_code', 'tool_name', 'tool_family_key',
        // Unidad FÍSICA (delta #47): marca/modelo/serie + foto real. La serie es la llave con
        // que la consulta agrupa por unidad. Todo esto es CONTENIDO → entra al sello.
        'tool_model', 'tool_brand', 'tool_serial', 'tool_photo_path', 'tool_standards_snapshot',
        'checklist_mode', 'checklist_snapshot',
        'inspection_moment', 'origin_type', 'origin_id',
        'verdict', 'resolution_path', 'observations',
        'department_id', 'department_name',
        'owner_user_id', 'owner_name',            // dueño de la herramienta (crew o texto libre), CONGELADO
        'inspector_user_id', 'inspector_name', 'inspector_role', 'inspector_cedula',
        'unblocked_by_id', 'unblocked_by_name', 'unblocked_at',
        'is_active', 'retired_at', 'retired_by_id', 'retired_reason', 'superseded_by_id',
    ];

    protected $casts = [
        'tool_standards_snapshot' => 'array',
        'checklist_snapshot'      => 'array',
        'unblocked_at'            => 'datetime',
        'retired_at'              => 'datetime',
        'is_active'               => 'boolean',
    ];

    /**
     * FUERA del hash: la bandera de retiro y sus columnas (retirar NO recalcula ni
     * re-firma el sello — el documento retirado sigue ÍNTEGRO, solo cambió de estado).
     * `inspection_moment` / `origin_*` SÍ se firman: son contenido fijado al crear.
     * El desbloqueo re-sella, así que sus columnas también van en el hash.
     */
    protected $signatureExcludes = ['is_active', 'retired_at', 'retired_by_id', 'retired_reason', 'superseded_by_id'];

    // ---- Relaciones (vivas, para lectura; el acta ya congeló lo que importa) ----
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    /** Dueño de la herramienta cuando es crew (FK-soft; el nombre ya quedó congelado en owner_name). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Etiqueta del dueño para mostrar: el nombre congelado (crew o texto libre) o null. */
    public function ownerLabel(): ?string
    {
        $n = trim((string) $this->owner_name);
        return $n !== '' ? $n : null;
    }

    /**
     * URL pública de la foto REAL de la unidad (disco 'public'), o null si no se subió.
     * La ruta es la que devolvió ImageCompressor::store() (misma forma que las fotos del DSR).
     */
    public function toolPhotoUrl(): ?string
    {
        $p = trim((string) $this->tool_photo_path);
        return $p !== '' ? \Illuminate\Support\Facades\Storage::url($p) : null;
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by_id');
    }

    /** Reporte de origen (condición/acto/DSR/accidente) cuando la inspección nace de uno. */
    public function origin(): MorphTo
    {
        return $this->morphTo('origin');
    }

    /** El acta que SUSTITUYE a ésta (una reinspección posterior). */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(ToolInspection::class, 'superseded_by_id');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_id');
    }

    // ---- Momento y redacción del veredicto ----
    /** Momentos PRE-USO: nada corriendo → un fallo es "equipo no autorizado", no paro. */
    public function isPreUse(): bool
    {
        return in_array($this->inspection_moment, self::PRE_USE_MOMENTS, true);
    }

    /** Un PARO es INMEDIATO (detiene el set) solo si NO es pre-uso. */
    public function paroIsImmediate(): bool
    {
        return $this->isParo() && ! $this->isPreUse();
    }

    /**
     * ¿Esta acta cuenta como inspección VIGENTE del equipo? (para la lista del día y el
     * "¿ya se inspeccionó y sigue vigente?"). Un acta retirada no cuenta; un PARO solo
     * cuenta si se desbloqueó (el equipo dejó de estar fuera de uso). APTA y ACTIVIDAD
     * NO EJECUTABLE cuentan: el equipo se inspeccionó y no quedó bloqueado.
     */
    public function isVigente(): bool
    {
        if (! $this->is_active || $this->retired_at !== null) {
            return false;
        }
        if ($this->isParo()) {
            return $this->unblocked_at !== null;
        }
        return true;
    }

    // ---- Retiro (Parte B) ----
    public function isRetired(): bool
    {
        return $this->is_active === false || $this->retired_at !== null;
    }

    /**
     * Estado de retiro que lee el verificador público (SealVerifier). Devuelve NULL
     * si el acta sigue vigente (el verificador la muestra "VÁLIDO Y VIGENTE"); si está
     * retirada, la fecha y el folio del acta que la sustituye (si existe). El retiro
     * NO afecta la integridad del sello: es estado, no alteración.
     *
     * @return array{retired_at:?string, superseded_folio:?string, reason:?string}|null
     */
    public function sealRetirement(): ?array
    {
        if (! $this->isRetired()) {
            return null;
        }
        $supersededFolio = null;
        if ($this->superseded_by_id) {
            $sup = self::find($this->superseded_by_id);
            $supersededFolio = $sup ? $sup->folio() : null;
        }
        $when = $this->retired_at ?: $this->updated_at;
        // NUNCA se devuelve `retired_reason`: es texto libre del safety y esto lo consume el
        // verificador PÚBLICO. El motivo se ve solo en la página interna del acta (autenticada).
        return [
            'retired_at'       => $when ? $when->format('d/m/Y') : null,
            'superseded_folio' => $supersededFolio,
        ];
    }

    // ---- Estado ----
    public function isParo(): bool
    {
        return $this->verdict === self::VERDICT_PARO;
    }

    public function isBlocked(): bool
    {
        // Un PARO sin desbloquear mantiene la herramienta fuera de uso.
        return $this->isParo() && $this->unblocked_at === null;
    }

    public function isUnblocked(): bool
    {
        return $this->isParo() && $this->unblocked_at !== null;
    }

    /**
     * Levanta el PARO (acto con autor) y RE-SELLA sobre el estado resuelto. Idempotente:
     * si no es paro o ya está desbloqueado, no hace nada. Lo usan el desbloqueo directo
     * (InspectionController@unblock) y el cierre del action item (ActionItemController@close):
     * "el paro se levanta al cerrar el item". El sello del PARO original queda en el historial.
     */
    public function unblock($user = null, $request = null): void
    {
        if (! $this->isParo() || $this->unblocked_at !== null) {
            return;
        }
        $this->unblocked_by_id   = $user ? $user->id : null;
        $this->unblocked_by_name = $user ? $user->fullName() : null;
        $this->unblocked_at      = now();
        $this->save();
        $this->refresh();
        $this->signDocument($user, $request);
    }

    /** Folio estable para el verificador público y la cadena CFDI. */
    public function folio(): string
    {
        return 'INSP-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
