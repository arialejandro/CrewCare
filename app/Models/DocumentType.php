<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * CATÁLOGO de tipos de documento (BASE ÚNICA DE QUIEN COBRA). `document_type` deja
 * de ser texto libre: pasa a CLAVE. Guarda la FORMA DE VIGENCIA y si exige ESTADO
 * POSITIVO (32-D) como DATO, no como código. Contenido: {@see \Database\Seeders\DocumentTypeSeeder}.
 *
 * Familias sobre la misma entidad: 'billing' (COBRAR) y 'operate' (OPERAR — el que
 * ya tienen las ambulancias). Scope: dónde se ata el documento (identity/contract/person).
 */
class DocumentType extends Model
{
    protected $table = 'document_types';

    const FAMILY_BILLING = 'billing';
    const FAMILY_OPERATE = 'operate';

    const SCOPE_IDENTITY = 'identity';
    const SCOPE_CONTRACT = 'contract';
    const SCOPE_PERSON   = 'person';

    // Las cuatro FORMAS DE VIGENCIA (Paso 1). El cálculo real vive en expiryFrom().
    const V_DAYS      = 'days_from_emission'; // p.ej. domicilio / estado de cuenta (90 días)
    const V_MONTH     = 'current_month';      // CSF / 32-D (caducan al cambiar de mes)
    const V_QUARTER   = 'quarterly';          // ICSOE
    const V_PERMANENT = 'permanent';          // acta constitutiva

    const PHASE_BEFORE = 'before';
    const PHASE_AFTER  = 'after';

    protected $fillable = [
        'code', 'name', 'family', 'scope', 'legal_nature',
        'validity_shape', 'validity_days', 'requires_positive_status',
        'is_repse', 'repse_phase', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'validity_days'            => 'integer',
        'requires_positive_status' => 'boolean',
        'is_repse'                 => 'boolean',
        'is_active'                => 'boolean',
        'sort_order'               => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Fecha de caducidad DERIVADA de la forma de vigencia y la fecha de emisión.
     * Es lo que hoy NO existe: nada calculaba caducidad, solo se guardaba valid_until.
     *   - days_from_emission → emisión + validity_days.
     *   - current_month      → último día del mes de emisión.
     *   - quarterly          → fin del trimestre calendario de la emisión.
     *   - permanent / null   → no caduca (null).
     * NO decide la fecha de corte de la 32-D (eso es configuración de producción, Paso 2).
     */
    public function expiryFrom($issuedAt): ?Carbon
    {
        if ($issuedAt === null) {
            return null;
        }
        $d = $issuedAt instanceof Carbon ? $issuedAt->copy() : Carbon::parse($issuedAt);

        switch ($this->validity_shape) {
            case self::V_DAYS:
                return $this->validity_days ? $d->addDays((int) $this->validity_days) : null;
            case self::V_MONTH:
                return $d->endOfMonth();
            case self::V_QUARTER:
                return $d->endOfQuarter();
            case self::V_PERMANENT:
            default:
                return null;
        }
    }
}
