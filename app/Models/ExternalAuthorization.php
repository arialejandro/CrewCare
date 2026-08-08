<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * DOCUMENTO/autorización externa del proveedor de ambulancias, polimórfico al
 * titular (empresa {@see AmbulanceProvider} o persona {@see AmbulanceCrew}).
 *
 * VALIDACIÓN = DECLARACIÓN MANUAL, calcada de {@see MedicCredential}:
 *   `validated_at` NULL          → PENDIENTE (capturado, nadie cotejó).
 *   `validated_at` + validated_by → validado por esa persona (bajo su responsabilidad).
 * `validation_method` distingue lo que el badge NO debe colapsar:
 *   'documents_reviewed' (hoy siempre: alguien vio el papel) vs
 *   'registry_checked'   (futuro: hubo consulta real a un registro).
 * Los campos de validación NO llegan por POST (no están en $fillable): los escribe
 * solo el flujo de validación. El que captura ≠ el que valida.
 *
 * "En trámite" SIN folio ni fecha compromiso NO es en trámite: es "no lo tiene"
 * ({@see effectiveStatus()}).
 */
class ExternalAuthorization extends Model
{
    protected $table = 'external_authorizations';

    const LEVEL_COMPANY = 'empresa';
    const LEVEL_PERSON  = 'persona';

    const METHOD_DOCS     = 'documents_reviewed';   // hoy siempre
    const METHOD_REGISTRY = 'registry_checked';     // futuro

    const STATUS_PRESENTED = 'presentado';
    const STATUS_PENDING   = 'en_tramite';
    const STATUS_NA        = 'no_aplica';

    // Datos de captura. Los de validación (validated_*, validation_method) quedan
    // FUERA: los escribe solo validate(), nunca el POST del formulario.
    protected $fillable = [
        'holder_type', 'holder_id', 'level',
        'document_type', 'authority', 'folio', 'valid_until', 'photo_path',
        'origen', 'exigido_por', 'is_gate',
        'status', 'pending_commit_date',
        'standard_code', 'standard_name',
        'is_active', 'created_by_id',
    ];

    protected $casts = [
        'valid_until'         => 'date',
        'pending_commit_date' => 'date',
        'is_gate'             => 'boolean',
        'is_active'           => 'boolean',
        'validated_at'        => 'datetime',
        'validated_snapshot'  => 'array',
    ];

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_id');
    }

    // ── Estado de validación ──────────────────────────────────────────────────
    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    public function isPending(): bool
    {
        return $this->validated_at === null;
    }

    /** El discriminador que el badge NO debe colapsar (hoy siempre false). */
    public function wasCheckedAgainstRegistry(): bool
    {
        return $this->isValidated() && $this->validation_method === self::METHOD_REGISTRY;
    }

    /**
     * Estado EFECTIVO: "en trámite" sin folio NI fecha compromiso se degrada a
     * "no lo tiene" — no se puede afirmar un trámite sobre la nada.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PENDING) {
            $hasFolio  = trim((string) $this->folio) !== '';
            $hasCommit = $this->pending_commit_date !== null;
            if (! $hasFolio && ! $hasCommit) {
                return 'no_lo_tiene';
            }
        }
        return (string) $this->status;
    }

    /** Texto para la UI: registro vs documentos vs pendiente (nunca se colapsan). */
    public function validationLabel(): string
    {
        if ($this->isPending()) {
            return 'Pendiente de validar';
        }
        $who  = optional($this->validatedBy)->fullName() ?? ($this->validated_snapshot['validated_by'] ?? 'validador no registrado');
        $when = $this->validated_at ? $this->validated_at->format('d/m/Y') : '';
        $verb = $this->wasCheckedAgainstRegistry() ? 'Verificado contra registro' : 'Documentos revisados';
        return trim("{$verb} por {$who}" . ($when !== '' ? " el {$when}" : ''));
    }

    public function photoUrl(): ?string
    {
        $p = trim((string) $this->photo_path);
        return $p !== '' ? Storage::url($p) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
