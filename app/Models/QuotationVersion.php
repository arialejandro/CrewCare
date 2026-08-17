<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * VERSIÓN de una cotización — negociar es versionar. Append-only: cada ajuste crea una versión
 * nueva que referencia la anterior (`supersedes_id`); la anterior NO se altera ni se borra.
 *
 * Dos formas, mismo objeto ({@see $source_kind}):
 *   - 'pdf'   → PDF subido byte-intact (se hashea en `pdf_sha256`, NUNCA se modifica). Los
 *              importes se capturan a mano (al menos el total, para la hoja de aceptación).
 *   - 'items' → partidas capturadas ({@see QuotationItem}); los importes se CALCULAN aquí,
 *              respetando el multiplicador de días y si el IVA viene incluido o no.
 */
class QuotationVersion extends Model
{
    const SOURCE_PDF   = 'pdf';
    const SOURCE_ITEMS = 'items';

    protected $fillable = [
        'quotation_id', 'version_no', 'supersedes_id', 'source_kind',
        'pdf_path', 'pdf_original_name', 'pdf_sha256',
        'quotation_number', 'issued_at', 'valid_until',
        'payment_terms', 'bank_details',
        'iva_included', 'iva_rate', 'subtotal', 'iva_amount', 'total',
        'change_note', 'created_by_id',
    ];

    protected $casts = [
        'issued_at'    => 'date',
        'valid_until'  => 'date',
        'iva_included' => 'boolean',
        'iva_rate'     => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'iva_amount'   => 'decimal:2',
        'total'        => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'supersedes_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'quotation_version_id')->orderBy('sort_order');
    }

    public function isPdf(): bool
    {
        return $this->source_kind === self::SOURCE_PDF;
    }

    public function isItems(): bool
    {
        return $this->source_kind === self::SOURCE_ITEMS;
    }

    /**
     * Recalcula subtotal/IVA/total desde las partidas. Respeta `iva_included`:
     *   - incluido → la suma de líneas YA trae IVA: total = suma; subtotal e IVA se despejan.
     *   - no incluido → subtotal = suma; IVA = subtotal × tasa; total = subtotal + IVA.
     * Así una cotización con precios IVA-incluido NO vuelve a sumar IVA. Para PDF no aplica
     * (sus importes se capturan a mano).
     */
    public function recomputeTotals(): void
    {
        if (! $this->isItems()) {
            return;
        }
        $sumLines = (float) $this->items()->sum('line_total');
        $rate     = (float) $this->iva_rate / 100.0;

        if ($this->iva_included) {
            $total    = round($sumLines, 2);
            $subtotal = $rate > 0 ? round($total / (1 + $rate), 2) : $total;
            $iva      = round($total - $subtotal, 2);
        } else {
            $subtotal = round($sumLines, 2);
            $iva      = round($subtotal * $rate, 2);
            $total    = round($subtotal + $iva, 2);
        }

        $this->subtotal   = $subtotal;
        $this->iva_amount = $iva;
        $this->total      = $total;
    }
}
