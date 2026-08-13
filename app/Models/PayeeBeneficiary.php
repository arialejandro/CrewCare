<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficiario en caso de fallecimiento (PASO 3). Dato de TERCEROS que no consienten ni
 * acceden al sistema (misma familia que los antecedentes heredo-familiares): se guarda lo
 * MÍNIMO — nombre (como en identificación/acta), parentesco y porcentaje. Pueden ser
 * menores. Los porcentajes del conjunto deben sumar 100% (se valida al guardar, no aquí).
 */
class PayeeBeneficiary extends Model
{
    protected $table = 'payee_beneficiaries';

    protected $fillable = ['payee_id', 'full_name', 'relationship', 'percentage', 'sort_order'];

    protected $casts = [
        'percentage' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }
}
