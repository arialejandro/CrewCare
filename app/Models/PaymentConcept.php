<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CONCEPTO DE PAGO — el catálogo EDITABLE de los prefijos/tipos de concepto que contabilidad usa en
 * las facturas (SEM honorario, CA car allowance, Box Rental…). Vive APARTE del calendario: un concepto
 * es un tipo, y una factura lleva varios, así que no cabe como campo de la semana. Sembrado con los
 * conocidos como default GLOBAL (`production_id` NULL); cada producción agrega los suyos. No valida:
 * es vocabulario compartido para clasificar los conceptos de la factura (más tarde, desde el XML).
 */
class PaymentConcept extends Model
{
    protected $table = 'payment_concepts';

    protected $fillable = [
        'production_id', 'code', 'name', 'description', 'sort_order', 'is_active', 'created_by_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Conceptos visibles para una producción: los GLOBALES (production_id NULL) + los propios. */
    public function scopeForProduction($query, $productionId)
    {
        return $query->where(function ($w) use ($productionId) {
            $w->whereNull('production_id');
            if ($productionId) {
                $w->orWhere('production_id', $productionId);
            }
        });
    }
}
