<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * NIVEL 2 · CONTRATO / concepto de cobro (BASE ÚNICA). VARIOS por identidad: así se
 * logra el N:M "quién contrató" sin duplicar a la persona — cada contrato lleva su
 * `contracted_by_user_id` (la visibilidad del Paso 4 se filtra por ahí, con el HERMANO
 * de applyDepartmentScope, {@see User::applyContractingScope()}).
 *
 * REPSE aplica AL CONTRATO (`is_repse`), no a la persona. La frecuencia del periodo de
 * pago se HEREDA por defecto del tipo de contratación y SE GUARDA; no se consume aún.
 *
 * Tabla propia a propósito: `production_user` tiene unique(production_id,user_id) y no
 * admite el N:M — ese pivote se queda intacto para la membresía del crew.
 *
 * Los DOCUMENTOS del contrato (factura de renta, factura de propiedad del equipo)
 * cuelgan por el ledger polimórfico {@see ExternalAuthorization} (holder = este modelo)
 * y NO se mezclan con los de la identidad.
 */
class PayeeContract extends Model
{
    protected $table = 'payee_contracts';

    const CONCEPT_CREW    = 'crew_work';        // trabajo de crew
    const CONCEPT_RENTAL  = 'equipment_rental'; // renta de equipo
    const CONCEPT_SERVICE = 'service';          // servicio

    // FRECUENCIA del periodo de pago (VENTANA DE RECEPCIÓN). Se HEREDA del contrato: fija
    // para crew y proveedores fijos, definible caso por caso para day players/apoyos/eventuales.
    // DAY PLAYER no es una excepción: es OTRO TIPO de periodo (no tiene semana, tiene el día).
    // Este modelo es la FUENTE del vocabulario; PaymentPeriod::frequency usa las mismas claves.
    const FREQ_WEEKLY     = 'weekly';       // semanal
    const FREQ_BIWEEKLY   = 'biweekly';     // quincenal
    const FREQ_DAY_PLAYER = 'day_player';   // day player (por día trabajado)

    protected $fillable = [
        'payee_id', 'fiscal_regime_id', 'production_id', 'concept', 'title',
        'contracted_by_user_id', 'payment_frequency', 'is_repse',
        'notes', 'is_active', 'sort_order', 'created_by_id',
    ];

    protected $casts = [
        'is_repse'   => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class, 'payee_id');
    }

    /** El régimen fiscal (de los N de la identidad) bajo el que se factura ESTE contrato. */
    public function fiscalRegime(): BelongsTo
    {
        return $this->belongsTo(PayeeFiscalRegime::class, 'fiscal_regime_id');
    }

    /** QUIÉN CONTRATA. Su departamento (vía production_user) resuelve el scope del Paso 4. */
    public function contractedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contracted_by_user_id');
    }

    /** Documentos del CONTRATO (factura de renta, propiedad del equipo). Polimórfico. */
    public function documents(): MorphMany
    {
        return $this->morphMany(ExternalAuthorization::class, 'holder');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Frecuencias válidas (clave => etiqueta traducible) para selects y validación. */
    public static function frequencies(): array
    {
        return [
            self::FREQ_WEEKLY     => __('Semanal'),
            self::FREQ_BIWEEKLY   => __('Quincenal'),
            self::FREQ_DAY_PLAYER => __('Day player'),
        ];
    }

    /** Etiqueta legible de la frecuencia de este contrato (o null si no está definida). */
    public function frequencyLabel(): ?string
    {
        return self::frequencies()[$this->payment_frequency] ?? null;
    }
}
