<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PARTIDA de una versión de cotización (congelada por versión). El multiplicador de días es
 * OPCIONAL: `days` NULL = cantidad × precio; con valor = cantidad × días × precio (así se
 * representan las dos formas reales sin fijar dos factores). El `line_total` se recalcula
 * SIEMPRE al guardar (no se confía en lo que llegue del formulario).
 */
class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_version_id', 'sort_order', 'description', 'detail',
        'quantity', 'days', 'unit_price', 'line_total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'days'       => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (QuotationItem $item) {
            $item->line_total = $item->computeLineTotal();
        });
    }

    /** cantidad × (días | 1) × precio unitario. */
    public function computeLineTotal(): float
    {
        $mult = $this->days !== null ? (float) $this->days : 1.0;
        return round((float) $this->quantity * $mult * (float) $this->unit_price, 2);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'quotation_version_id');
    }
}
