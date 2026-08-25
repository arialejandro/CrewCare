<?php

namespace App\Models;

use App\Traits\GeneratesUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TransportOrder — LA ORDEN DE TRANSPORTACIÓN por día (Bloque 2 §1).
 *
 * ⚠ NO SE SELLA Y NO SE FIRMA. Se CONGELA: al emitir pasa a status=frozen y guarda
 * `frozen_snapshot` (el documento completo). Por eso NO usa HasDigitalSignatures ni entra al
 * verificador público. Sólo trae `GeneratesUuidKey` para el UUID DISCRETO del pie (control).
 *
 * Identidad = fecha + versión (sin folio). Cada emisión congela una versión y apunta a la
 * INMEDIATA anterior (`prev_version_id`) para resaltar cambios (§4).
 */
class TransportOrder extends Model
{
    use GeneratesUuidKey;

    protected $table = 'transport_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'order_date'      => 'date',
        'version'         => 'integer',
        'legend'          => 'array',
        'frozen_snapshot' => 'array',
        'frozen_at'       => 'datetime',
        'is_active'       => 'boolean',
    ];

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_FROZEN = 'frozen';

    // ── Relaciones ───────────────────────────────────────────────────────────
    public function runs(): HasMany
    {
        return $this->hasMany(TransportOrderRun::class, 'transport_order_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function prevVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_version_id');
    }

    // ── Estado ───────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Etiqueta corta de identidad: "24-ago · v3". */
    public function versionLabel(): string
    {
        return trim(optional($this->order_date)->format('d-M') . ' · v' . (int) $this->version);
    }

    /**
     * Siguiente número de versión para una producción+fecha (1 si no hay ninguna).
     * La versión NO es folio: es un consecutivo por día, reiniciado cada día.
     */
    public static function nextVersionFor(?int $productionId, $date): int
    {
        $max = static::query()
            ->where('production_id', $productionId)
            ->whereDate('order_date', $date)
            ->max('version');

        return ((int) $max) + 1;
    }

    /** La última versión CONGELADA de un día (la "inmediata anterior" para el diff §4). */
    public static function latestFrozenFor(?int $productionId, $date): ?self
    {
        return static::query()
            ->where('production_id', $productionId)
            ->whereDate('order_date', $date)
            ->where('status', self::STATUS_FROZEN)
            ->orderByDesc('version')
            ->first();
    }
}
