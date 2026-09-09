<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EL CONTRATO · PASO C — un ANEXO (PDF byte-intact) de la biblioteca de la producción. Nombre
 * LIBRE (la productora lo nombra), no fijado en código; se desactiva sin borrar. El sobre agrupa
 * los anexos activos junto a la carátula y el clausulado y se firman como paquete.
 */
class ContractAnnex extends Model
{
    protected $table = 'contract_annexes';

    protected $fillable = [
        'production_id', 'name', 'file_path', 'original_filename', 'file_hash',
        'is_active', 'sort_order', 'uploaded_by_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForProduction($query, $productionId)
    {
        return $query->where('production_id', $productionId);
    }
}
