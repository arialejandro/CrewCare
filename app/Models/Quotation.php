<?php

namespace App\Models;

use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * COTIZACIÓN — el paso PREVIO real (decide si se contrata o no). Nace ANTES que la persona:
 * vive con CORREO + NOMBRE del emisor y una PRODUCCIÓN; NO cuelga de `payees` (a esa altura
 * puede no haber payee). Solo al ACEPTAR se liga a un payee (existente o externo-lite) — y
 * NUNCA crea usuario al cotizar.
 *
 * La ACEPTACIÓN se sella con {@see HasDigitalSignatures}: las columnas accepted_* + el hash
 * del documento aceptado + la AUTÓGRAFA (`acceptance_signature_image`) entran al payload
 * canónico (attributesToArray), así el sello prueba qué se aceptó, quién y cuándo. El PDF
 * subido NUNCA se modifica (byte-intact); su prueba es `pdf_sha256` en la versión.
 *
 * El contenido negociable (partidas/PDF, importes, vigencia, condiciones) vive en
 * {@see QuotationVersion} (append-only): negociar es versionar.
 */
class Quotation extends Model
{
    use HasDigitalSignatures;

    const STATUS_RECEIVED    = 'recibida';
    const STATUS_NEGOTIATING = 'en_negociacion';
    const STATUS_ACCEPTED    = 'aceptada';
    const STATUS_REJECTED    = 'rechazada';

    protected $fillable = [
        'production_id', 'department_id', 'location_name',
        'emitter_name', 'emitter_email',
        'status', 'current_version_id', 'payee_id', 'created_by_id',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /**
     * El sello (aceptación) cubre TODO menos: el pointer mutable a la versión activa y la
     * ruta de la hoja de aceptación (derivada, se genera DESPUÉS de sellar).
     */
    protected $signatureExcludes = ['current_version_id', 'acceptance_sheet_path'];

    // ── Normalización ─────────────────────────────────────────────────────────
    /** Correo del emisor SIEMPRE normalizado (minúsculas, sin espacios). */
    public function setEmitterEmailAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['emitter_email'] = $v === '' ? null : mb_strtolower($v);
    }

    // ── Relaciones ─────────────────────────────────────────────────────────────
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuotationVersion::class, 'quotation_id')->orderBy('version_no');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'current_version_id');
    }

    public function acceptedVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'accepted_version_id');
    }

    // ── Estado ─────────────────────────────────────────────────────────────────
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /** ¿La versión activa está vencida HOY? (advierte al aceptar; no bloquea). */
    public function isExpired(): bool
    {
        $v = $this->relationLoaded('currentVersion') ? $this->currentVersion : $this->currentVersion()->first();
        return $v && $v->valid_until && $v->valid_until->startOfDay()->lessThan(now()->startOfDay());
    }

    // ── Visibilidad (calca el criterio de Payee: quien contrata ve lo suyo) ─────
    /**
     * Antes de aceptar la cotización no tiene payee, así que se scopea por DEPARTAMENTO
     * (como el resto de la app) + "lo que yo creé". `crew.view.all-departments` ve todo;
     * super-admin pasa por Gate::before.
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->can('crew.view.all-departments')) {
            return $query;
        }
        $ownDeptIds = $viewer->ownDepartmentIds();
        $ids = $ownDeptIds->all();

        return $query->where(function ($w) use ($ids, $viewer) {
            $w->where('quotations.created_by_id', $viewer->id);
            if (! empty($ids)) {
                $w->orWhereIn('quotations.department_id', $ids);
            }
        });
    }

    public function isVisibleTo(User $viewer): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($viewer)->exists();
    }
}
