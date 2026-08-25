<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use App\Traits\HasDigitalSignatures;
use App\Traits\TracksCorrectiveActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ACTA de verificación de vehículo. Hermana de {@see AmbulanceInspection}/{@see ToolInspection}:
 * congela lo que citó, se sella con {@see HasDigitalSignatures} y es verificable por QR
 * (tipo público 'veh' en SealVerifier).
 *
 * VEREDICTO GRADUADO ({@see \App\Support\VehicleVerdict}): `verdict` = apto|no_apto es la CARA
 * PÚBLICA; `level` (alto_riesgo..excelente) es INTERNO (solo transpo y safety). El fail-safe —un
 * punto aplicable sin contestar nunca da favorable— vive en el calculador y en el controlador.
 *
 * DOCTRINA DEL SELLO: TODO es contenido y entra al hash salvo el ESTADO posterior (is_active +
 * retiro), en $signatureExcludes → retirar/sustituir NO invalida el sello.
 */
class VehicleInspection extends Model
{
    use HasDigitalSignatures, GeneratesUuidKey, TracksCorrectiveActions;

    protected $table = 'vehicle_inspections';

    const VERDICT_APTO    = 'apto';
    const VERDICT_NO_APTO = 'no_apto';

    // Niveles internos (de más grave a menos). alto_riesgo/pobre = NO APTO; normal/bien/excelente = APTO.
    const LEVEL_ALTO      = 'alto_riesgo';
    const LEVEL_POBRE     = 'pobre';
    const LEVEL_NORMAL    = 'normal';
    const LEVEL_BIEN      = 'bien';
    const LEVEL_EXCELENTE = 'excelente';

    protected $fillable = [
        'uuid', 'production_id', 'shoot_day', 'vehicle_id',
        'vehicle_type_id', 'type_code', 'type_name',
        'make', 'model', 'year', 'color', 'plate', 'vin',
        'attributes_snapshot',
        'owner_kind', 'owner_name', 'driver_user_id', 'driver_name', 'km',
        'unit_photo_path', 'checklist_snapshot',
        'verdict', 'level', 'n_critical', 'n_major', 'n_minor', 'observations',
        'is_reevaluation', 'origin_inspection_id',
        'inspector_user_id', 'inspector_name', 'inspector_role', 'inspector_cedula',
        'created_by_id',
        // Estado (hash-excluido) — fillable para poder setearlos al retirar/sustituir.
        'is_active', 'retired_at', 'retired_by_id', 'retired_reason', 'superseded_by_id',
    ];

    protected $casts = [
        'attributes_snapshot' => 'array',
        'checklist_snapshot'  => 'array',
        'year'                => 'integer',
        'km'                  => 'integer',
        'n_critical'          => 'integer',
        'n_major'             => 'integer',
        'n_minor'             => 'integer',
        'is_reevaluation'     => 'boolean',
        'is_active'           => 'boolean',
        'retired_at'          => 'datetime',
    ];

    /**
     * Columnas de ESTADO fuera del hash: retirar/desactivar/sustituir NO re-sella (el acta
     * retirada sigue ÍNTEGRA). TODO lo demás —placas, VIN, atributos, checklist, veredicto,
     * nivel, conteos, inspector— entra al sello. Mismo conjunto base que AmbulanceInspection.
     */
    protected $signatureExcludes = [
        'is_active', 'retired_at', 'retired_by_id', 'retired_reason', 'superseded_by_id',
    ];

    // ── Relaciones (FK-soft) ──────────────────────────────────────────────────
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function originInspection(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_inspection_id');
    }

    // ── Contrato del verificador público ─────────────────────────────────────
    public function folio(): string
    {
        return 'VEHI-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Vigencia para el verificador público (solo si el sello está íntegro). Retirar ≠ alterar.
     * NO devuelve retired_reason (nunca al DTO público).
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

    // ── Estado / veredicto ────────────────────────────────────────────────────
    public function isRetired(): bool
    {
        return $this->is_active === false || $this->retired_at !== null;
    }

    public function isApto(): bool
    {
        return $this->verdict === self::VERDICT_APTO;
    }

    public function isNoApto(): bool
    {
        return $this->verdict === self::VERDICT_NO_APTO;
    }

    /** APTO pero con observaciones anotadas (nivel 'normal'). Sin plazo de resolución. */
    public function isAptoConObservaciones(): bool
    {
        return $this->isApto() && $this->level === self::LEVEL_NORMAL;
    }

    public function isVigente(): bool
    {
        return ! $this->isRetired();
    }

    /** Puntos que FALLARON en el checklist congelado. */
    public function failedPoints(): array
    {
        $out = [];
        foreach ((array) $this->checklist_snapshot as $p) {
            if (is_array($p) && ($p['answer'] ?? null) === 'fail') {
                $out[] = $p;
            }
        }
        return $out;
    }

    public function unitPhotoUrl(): ?string
    {
        $p = trim((string) $this->unit_photo_path);
        return $p !== '' ? Storage::url($p) : null;
    }
}
