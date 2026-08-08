<?php

namespace App\Models;

use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;
use App\Traits\TracksCorrectiveActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ACTA de verificación de ambulancia en sitio. Hermana de {@see ToolInspection}:
 * congela lo que citó, se sella con {@see HasDigitalSignatures} y es verificable
 * por QR (tipo público 'ambu' en SealVerifier).
 *
 * ENCUADRE (importa que el título y el texto lo digan): es CONSTANCIA DE
 * VERIFICACIÓN DE RECURSO DE EMERGENCIA EN SITIO, NO inspección sanitaria — eso lo
 * hace la autoridad. Registrar que el dictamen existe no lo sustituye.
 *
 * DOCTRINA DEL SELLO: TODO es contenido y entra al hash salvo el ESTADO posterior
 * (is_active + retiro), que va en $signatureExcludes → retirar no invalida el sello.
 * `unblocked_*` SÍ va en el hash: el desbloqueo re-sella sobre el estado resuelto.
 *
 * Fail-safe del veredicto (una compuerta caída JAMÁS da 'apta') vive en el
 * calculador que arma el controlador (sub-bloque C), no aquí.
 */
class AmbulanceInspection extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey, TracksCorrectiveActions;

    protected $table = 'ambulance_inspections';

    const VERDICT_PARO    = 'paro';
    const VERDICT_NO_EXEC = 'actividad_no_ejecutable';
    const VERDICT_APTA    = 'apta';

    const PATH_SAME_DAY = 'correccion_mismo_dia';
    const PATH_REPLACE  = 'reemplazo';

    // Un SOLO checklist: cada vez que hay ambulancia en set se abre la inspección y se corre
    // COMPLETO (como herramienta/maquinaria). `trigger_scope` guarda 'completa'. Las constantes
    // de disparador por evento quedan para lectura histórica, pero el flujo ya no las usa.
    const TRIGGER_FULL     = 'completa';
    const TRIGGER_IDENTITY = 'identidad';
    const TRIGGER_PERSON   = 'persona';
    const TRIGGER_UNIT     = 'unidad';
    const TRIGGER_CONSUMO  = 'consumo';
    const TRIGGER_RIESGO   = 'riesgo';
    const TRIGGERS = ['identidad', 'persona', 'unidad', 'consumo', 'riesgo'];

    protected $fillable = [
        'uuid', 'production_id', 'shoot_day', 'trigger_scope',
        'ambulance_type_id', 'type_code', 'type_name', 'rama', 'type_level', 'capacity_level',
        'provider_id', 'provider_name', 'plates', 'economic_number', 'unit_photo_path',
        'crew_snapshot', 'checklist_snapshot',
        'verdict', 'resolution_path', 'observations',
        'day_risk_level', 'correspondence_ok',
        'inspector_user_id', 'inspector_name', 'inspector_role', 'inspector_cedula',
        'unblocked_by_id', 'unblocked_by_name', 'unblocked_at',
        // Estado (hash-excluido) — se listan fillable para poder setearlos al retirar/desbloquear.
        'is_active', 'retired_at', 'retired_by_id', 'retired_reason', 'superseded_by_id',
    ];

    protected $casts = [
        'crew_snapshot'      => 'array',
        'checklist_snapshot' => 'array',
        'type_level'         => 'integer',
        'capacity_level'     => 'integer',
        'day_risk_level'     => 'integer',
        'correspondence_ok'  => 'boolean',
        'unblocked_at'       => 'datetime',
        'retired_at'         => 'datetime',
        'is_active'          => 'boolean',
    ];

    /**
     * Columnas de ESTADO fuera del hash: retirar/desactivar/sustituir NO re-sella
     * (el acta retirada sigue ÍNTEGRA; cambió el estado, no la integridad). TODO lo
     * demás (incluidas placas, serie, foto, tripulación, veredicto, unblocked_*) SÍ
     * entra al sello. Mismo conjunto que ToolInspection.
     */
    protected $signatureExcludes = ['is_active', 'retired_at', 'retired_by_id', 'retired_reason', 'superseded_by_id'];

    // ── Relaciones (todas FK-soft: documento histórico) ──────────────────────
    public function ambulanceType(): BelongsTo
    {
        return $this->belongsTo(AmbulanceType::class, 'ambulance_type_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AmbulanceProvider::class, 'provider_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by_id');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    // ── Contrato del verificador público ─────────────────────────────────────
    public function folio(): string
    {
        return 'AMBU-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Vigencia para el verificador público (solo se consulta si el sello está
     * íntegro). Retirar ≠ alterar. NO devuelve retired_reason (nunca al DTO público).
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
        return [
            'retired_at'       => $when ? $when->format('d/m/Y') : null,
            'superseded_folio' => $supersededFolio,
        ];
    }

    // ── Estado ───────────────────────────────────────────────────────────────
    public function isRetired(): bool
    {
        return $this->is_active === false || $this->retired_at !== null;
    }

    public function isParo(): bool
    {
        return $this->verdict === self::VERDICT_PARO;
    }

    public function isBlocked(): bool
    {
        return $this->isParo() && $this->unblocked_at === null;
    }

    public function isVigente(): bool
    {
        if ($this->isRetired()) {
            return false;
        }
        return ! $this->isBlocked();
    }

    public function unitPhotoUrl(): ?string
    {
        $p = trim((string) $this->unit_photo_path);
        return $p !== '' ? Storage::url($p) : null;
    }

    /**
     * Levanta el PARO con autor y re-sella sobre el estado resuelto (los
     * unblocked_* SÍ entran al hash). Idempotente. Patrón de ToolInspection.
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
}
