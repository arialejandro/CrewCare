<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QUIÉN PIDE QUÉ, por producción (PASO 2). Fila = "esta producción exige este
 * document_type a esta naturaleza". Editable (toggle is_required / applies_to) sin
 * tocar código. El eje antes/después NO vive aquí: se DERIVA del tipo (repse_phase).
 */
class DocumentRequirement extends Model
{
    protected $table = 'document_requirements';

    const APPLIES_FISICA = 'fisica';
    const APPLIES_MORAL  = 'moral';
    const APPLIES_AMBAS  = 'ambas';

    protected $fillable = [
        'production_id', 'document_type_id', 'applies_to', 'is_required', 'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', 1);
    }
}
