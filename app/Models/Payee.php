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

    /**
     * PASO 5 · si esta identidad ES un proveedor de ambulancias, su perfil de OPERAR
     * (documentos para operar + inspecciones viven en esa capa). hasOne inverso del puente.
     */
    public function ambulanceProvider()
    {
        return $this->hasOne(AmbulanceProvider::class, 'payee_id');
    }

    public function isAmbulanceProvider(): bool
    {
        return $this->ambulanceProvider()->exists();
    }

    /**
     * Documentos "para OPERAR" (nivel empresa: aviso de funcionamiento, dictamen, póliza…) vs
     * "para COBRAR" (el paquete fiscal del intake). Se separan por `level`: los de operar nacen
     * en nivel EMPRESA; los de cobrar, en nivel PERSONA. Así conviven sobre la misma entidad y
     * se muestran en secciones distintas (5.2).
     */
    public function operateDocuments()
    {
        return $this->documents()->where('level', ExternalAuthorization::LEVEL_COMPANY);
    }

    public function billingDocuments()
    {
        return $this->documents()->where('level', '!=', ExternalAuthorization::LEVEL_COMPANY);
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

    /**
     * PASO 4 · VISIBILIDAD — FUENTE ÚNICA de "quién ve qué payee". La usan el listado
     * (PayeeController) y, derivado, el {@see \App\Policies\PayeePolicy} (por objeto) y la
     * guarda del intake por quien contrata. Dos ejes, en OR:
     *
     *   (A) QUIÉN CONTRATÓ — el eje del Paso 4: el payee tiene un contrato cuyo
     *       `contracted_by_user_id` comparte departamento con el viewer (misma correlación
     *       que {@see User::applyContractingScope}). "Quien contrata es quien ve."
     *   (B) LIGA A CREW — el payee es la MISMA persona que un crew del departamento del viewer
     *       (scope de departamento estándar, {@see User::applyDepartmentScope}). NO es
     *       "aislamiento por propiedad" (ese eje no existe todavía): es el mismo scope de
     *       crew que ya gobierna toda la app.
     *
     * BYPASS: `crew.view.all-departments` (los MISMOS roles que hoy: line-producer,
     * coordinator, medic, safety, auditor, y super-admin por Gate::before) ve todo.
     * FALLA CERRADO: viewer sin departamento determinable no ve nada.
     */
    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->can('crew.view.all-departments')) {
            return $query;
        }

        $ownDeptIds = $viewer->ownDepartmentIds();
        if ($ownDeptIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }
        $ids = $ownDeptIds->all();

        return $query->where(function ($w) use ($ids) {
            // (A) el CONTRATANTE está en mi departamento.
            $w->whereExists(function ($s) use ($ids) {
                $s->selectRaw('1')->from('payee_contracts')
                  ->join('production_user', 'production_user.user_id', '=', 'payee_contracts.contracted_by_user_id')
                  ->whereColumn('payee_contracts.payee_id', 'payees.id')
                  ->whereIn('production_user.department_id', $ids);
            })
            // (B) el payee está LIGADO a un crew de mi departamento.
            ->orWhereExists(function ($s) use ($ids) {
                $s->selectRaw('1')->from('production_user')
                  ->whereColumn('production_user.user_id', 'payees.user_id')
                  ->whereIn('production_user.department_id', $ids);
            });
        });
    }

    /** Derivado del scope (mismo criterio, un solo objeto). Lo usa la Policy y el intake. */
    public function isVisibleTo(User $viewer): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($viewer)->exists();
    }
}
