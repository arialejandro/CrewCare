<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * NIVEL 1 · IDENTIDAD de "quien cobra" (BASE ÚNICA). Una sola por persona o empresa.
 * Primera bifurcación por NATURALEZA JURÍDICA (física/moral): el paquete documental
 * cambia por naturaleza, no por módulo. Generaliza {@see AmbulanceProvider} (moral) y
 * {@see AmbulanceCrew} (física); el Paso 5 los migra aquí.
 *
 * Los datos fiscales (RFC, país, banco/CLABE) son DE LA PERSONA, no del contrato.
 * `user_id` es una LIGA OPCIONAL: si quien cobra es del crew, es la MISMA persona, no
 * dos registros. CURP y NSS NO viven aquí (solo existen atados al régimen REPSE del
 * contrato, Paso 2). El RÉGIMEN FISCAL queda fuera hasta decidir su cardinalidad.
 *
 * Los DOCUMENTOS de la identidad cuelgan por el ledger polimórfico
 * {@see ExternalAuthorization} (holder = este modelo) y NO se mezclan con los del
 * contrato (esos cuelgan de {@see PayeeContract}).
 */
class Payee extends Model
{
    protected $table = 'payees';

    const NATURE_FISICA = 'fisica';
    const NATURE_MORAL  = 'moral';

    protected $fillable = [
        'legal_nature', 'name', 'user_id',
        'rfc', 'tax_residence_country',
        'bank_name', 'bank_branch', 'bank_account', 'bank_clabe',
        'notes', 'is_active', 'sort_order', 'created_by_id',
        // PASO 3 · intake autoservicio
        'nationality', 'elector_credential', 'marital_status',
        'addr_street', 'addr_ext_no', 'addr_int_no', 'addr_colonia', 'addr_municipio',
        'addr_cp', 'addr_city', 'addr_state',
        'emergency_contact_name', 'emergency_contact_phone',
        'shirt_size', 'is_vegetarian', 'is_donor', 'intake_submitted_at',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
        'is_vegetarian'       => 'boolean',
        'is_donor'            => 'boolean',
        'intake_submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(PayeeContract::class, 'payee_id');
    }

    /** Regímenes fiscales (N por identidad; el SAT puede listar varios en la CSF). */
    public function fiscalRegimes(): HasMany
    {
        return $this->hasMany(PayeeFiscalRegime::class, 'payee_id');
    }

    /** Beneficiarios en caso de fallecimiento (dato mínimo de terceros; suman 100%). */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(PayeeBeneficiary::class, 'payee_id');
    }

    /** Equipo declarado para seguro (declaración firmada; ≠ equipo rentado). */
    public function declaredEquipment(): HasMany
    {
        return $this->hasMany(PayeeDeclaredEquipment::class, 'payee_id');
    }

    /** Documentos de la IDENTIDAD (paquete fiscal). Polimórfico al titular. */
    public function documents(): MorphMany
    {
        return $this->morphMany(ExternalAuthorization::class, 'holder');
    }

    public function isFisica(): bool
    {
        return $this->legal_nature === self::NATURE_FISICA;
    }

    public function isMoral(): bool
    {
        return $this->legal_nature === self::NATURE_MORAL;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
