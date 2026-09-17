<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RÉGIMEN FISCAL de una identidad ({@see Payee}). N por identidad: el SAT puede listar
 * más de uno en la CSF (p.ej. sueldos y salarios + arrendamiento). El CONTRATO
 * ({@see PayeeContract::fiscalRegime()}) apunta a CUÁL de ellos le aplica.
 */
class PayeeFiscalRegime extends Model
{
    protected $table = 'payee_fiscal_regimes';

    protected $fillable = ['payee_id', 'code', 'name', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
