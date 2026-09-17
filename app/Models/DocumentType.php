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
        'code', 'name', 'aliases', 'family', 'scope', 'legal_nature', 'nationality',
        'validity_shape', 'validity_days', 'requires_positive_status',
        'is_repse', 'repse_phase', 'is_active', 'sort_order', 'expects_cfdi_xml',
    ];

    protected $casts = [
        'validity_days'            => 'integer',
        'requires_positive_status' => 'boolean',
        'is_repse'                 => 'boolean',
        'is_active'                => 'boolean',
        'sort_order'               => 'integer',
        'expects_cfdi_xml'         => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Fecha de caducidad DERIVADA de la forma de vigencia y la fecha de emisión.
     * Es lo que antes NO existía: nada calculaba caducidad, solo se guardaba valid_until.
     *   - days_from_emission → emisión + validity_days.
     *   - current_month      → con FECHA DE CORTE de producción ($cutDay): válido hasta la
     *                          próxima ocurrencia del día de corte posterior a la emisión
     *                          (cambiar el corte cambia el cálculo). Sin corte: fin del mes.
     *   - quarterly          → fin del trimestre calendario de la emisión.
     *   - permanent / null   → no caduca (null).
     * El VALOR de la fecha de corte lo pone la producción (Paso 2), no este modelo.
     *
     * @param  int|null  $cutDay  día de corte 1..28 (solo aplica a current_month).
     */
    public function expiryFrom($issuedAt, ?int $cutDay = null): ?Carbon
    {
        if ($issuedAt === null) {
            return null;
        }
        $d = $issuedAt instanceof Carbon ? $issuedAt->copy() : Carbon::parse($issuedAt);

        switch ($this->validity_shape) {
            case self::V_DAYS:
                return $this->validity_days ? $d->addDays((int) $this->validity_days) : null;
            case self::V_MONTH:
                if ($cutDay !== null) {
                    $cut  = max(1, min(28, (int) $cutDay));
                    $cand = $d->copy()->day($cut)->startOfDay();
                    if ($cand->lessThanOrEqualTo($d->copy()->startOfDay())) {
                        $cand = $cand->addMonthNoOverflow();
                    }
                    return $cand;
                }
                return $d->endOfMonth();
            case self::V_QUARTER:
                return $d->endOfQuarter();
            case self::V_PERMANENT:
            default:
                return null;
        }
    }
}
