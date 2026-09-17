<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

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

    // ESTADO DEL ROSTER derivado (crew_work). Sin fila por-persona-por-día: se calcula de
    // is_active + fechas de trabajo + vigencia definitiva. Así "apagar sin borrar" respeta el
    // unique(production_id,user_id) de production_user, que queda intacto.
    // ⚠ La CADENA completa (contrato firmado + autorizado) es una PUERTA que se añade en el
    // Paso C (el sobre): "sin ruta de firma completa no produce roster". Aquí va solo la base.
    const ROSTER_CALLED     = 'called';       // activo + fecha ese día + sobre completado
    const ROSTER_NOT_CALLED = 'not_called';   // activo, sin fecha ese día
    const ROSTER_OUT        = 'out';           // inactivo, o DAY PLAYER vencido (el crew fijo no vence), o no crew_work
    // 4º estado (2026-08-22, vista del roster): tiene fecha de trabajo ese día PERO el sobre de
    // firma no está completo → NO cuenta como llamado (la puerta no cambia), pero deja de ser
    // invisible: es el caso accionable "debería estar hoy y no ha firmado". Lo DERIVA la consulta
    // agregada del roster (DayRosterBuilder); rosterStateOn() —para un contrato suelto— NO cambia.
    const ROSTER_PENDING_SIGNATURE = 'pending_signature';

    protected $fillable = [
        'payee_id', 'fiscal_regime_id', 'production_id', 'concept', 'title', 'asset_ref',
        'contracted_by_user_id', 'payment_frequency', 'is_repse',
        'notes', 'is_active', 'sort_order', 'created_by_id',
        // PASO A · CARÁTULA crew_work (todo NULL en rental/service). La frecuencia de honorarios
        // reusa 'payment_frequency' (arriba). Congelados: credit_name, beneficiary_*, contractor_*.
        'crew_activity', 'department_id', 'credit_name',
        'effective_date', 'estimated_end_date', 'definitive_end_date',
        'fee_amount', 'fee_currency', 'issues_own_cfdi',
        'union_payroll', 'union_is_member', 'union_retention_pct',
        'perdiem_breakfast', 'perdiem_lunch', 'perdiem_dinner',
        'perdiem_weekly_prep', 'perdiem_weekly_shoot',
        'lodging_type', 'lodging_monthly_supplement',
        'round_flights', 'budget_account',
        'beneficiary_name', 'beneficiary_relationship', 'beneficiary_phone',
        'contractor_legal_name', 'contractor_rfc', 'contractor_address',
        'contractor_representative', 'contractor_email',
        // PASO B · congelado al emitir
        'clause_id', 'language', 'caratula_path', 'emitted_at', 'emitted_by_id',
        // EL INFOSHEET · importe POR FASE (semanas·tarifa·importe ×4) + desglose fiscal +
        // comprobante + caja chica. El total sigue en fee_amount; no se duplica.
        'fee_soft_prep_weeks', 'fee_soft_prep_rate', 'fee_soft_prep_amount',
        'fee_prep_weeks', 'fee_prep_rate', 'fee_prep_amount',
        'fee_shoot_weeks', 'fee_shoot_rate', 'fee_shoot_amount',
        'fee_wrap_weeks', 'fee_wrap_rate', 'fee_wrap_amount',
        'tax_iva', 'tax_isr_retention', 'tax_iva_retention',
        'payment_document_type', 'manages_petty_cash',
    ];

    protected $casts = [
        'is_repse'   => 'boolean',
        'is_active'  => 'boolean',
        'asset_ref'  => 'array',
        'sort_order' => 'integer',
        // PASO A · carátula crew_work
        'effective_date'             => 'date',
        'estimated_end_date'         => 'date',
        'definitive_end_date'        => 'date',
        'fee_amount'                 => 'decimal:2',
        'issues_own_cfdi'            => 'boolean',
        'union_is_member'            => 'boolean',
        'union_retention_pct'        => 'decimal:2',
        'perdiem_breakfast'          => 'decimal:2',
        'perdiem_lunch'              => 'decimal:2',
        'perdiem_dinner'             => 'decimal:2',
        'perdiem_weekly_prep'        => 'decimal:2',
        'perdiem_weekly_shoot'       => 'decimal:2',
        'lodging_monthly_supplement' => 'decimal:2',
        'round_flights'              => 'integer',
        'emitted_at'                 => 'datetime',
        // EL INFOSHEET · importe por fase + desglose fiscal
        'fee_soft_prep_weeks' => 'decimal:2', 'fee_soft_prep_rate' => 'decimal:2', 'fee_soft_prep_amount' => 'decimal:2',
        'fee_prep_weeks'      => 'decimal:2', 'fee_prep_rate'      => 'decimal:2', 'fee_prep_amount'      => 'decimal:2',
        'fee_shoot_weeks'     => 'decimal:2', 'fee_shoot_rate'     => 'decimal:2', 'fee_shoot_amount'     => 'decimal:2',
        'fee_wrap_weeks'      => 'decimal:2', 'fee_wrap_rate'      => 'decimal:2', 'fee_wrap_amount'      => 'decimal:2',
        'tax_iva'             => 'decimal:2', 'tax_isr_retention'  => 'decimal:2', 'tax_iva_retention'    => 'decimal:2',
        'manages_petty_cash'  => 'boolean',
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

    /** La producción del contrato (su nombre es el "Título del programa" del documento). */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
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

    /** PASO A · departamento del contrato crew_work (la actividad-objeto es de ese depto). */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** PASO A · fechas de trabajo (con fase). Fechas NO contiguas; una fila por día. */
    public function workDates(): HasMany
    {
        return $this->hasMany(PayeeContractWorkDate::class, 'payee_contract_id');
    }

    /** PASO B · el clausulado + versión EXACTA con que se emitió (congelado). */
    public function clause(): BelongsTo
    {
        return $this->belongsTo(ContractClause::class, 'clause_id');
    }

    /** PASO C · sobres de firma del contrato. */
    public function envelopes(): HasMany
    {
        return $this->hasMany(ContractEnvelope::class, 'payee_contract_id');
    }

    /** EL INFOSHEET · las AUTORIZACIONES (paso 2) de este contrato crew_work. */
    public function authorizations(): HasMany
    {
        return $this->hasMany(InfosheetAuthorization::class, 'payee_contract_id');
    }

    /** ¿Tiene un sobre COMPLETADO? Es la PUERTA del roster: sin ruta de firma completa, no produce. */
    public function hasCompletedEnvelope(): bool
    {
        return $this->envelopes()->where('status', ContractEnvelope::STATUS_COMPLETED)->exists();
    }

    /** ¿Ya se emitió? (tiene su carátula generada y sus datos congelados). Un emitido NO se edita. */
    public function isEmitted(): bool
    {
        return $this->emitted_at !== null;
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

    public function isCrewWork(): bool
    {
        return $this->concept === self::CONCEPT_CREW;
    }

    public function scopeCrewWork($query)
    {
        return $query->where('concept', self::CONCEPT_CREW);
    }

    /**
     * FUENTE ÚNICA del estado de roster de UNA fila/contrato en una fecha (PARTE C, 2026-08-23).
     * La usan POR IGUAL {@see \App\Support\DayRosterBuilder::stateOf} (consulta agregada, por fila) y
     * {@see rosterStateOn} (contrato suelto): así NO hay una segunda implementación del mapeo.
     *
     * DOS POBLACIONES, distinguidas por la frecuencia del periodo de pago:
     *  - CREW FIJO (weekly / biweekly / NULL): el VENCIMIENTO no lo saca; se queda hasta el wrap.
     *    Sólo lo sacan is_active=0 (contrato apagado) o users.activo=0 (persona dada de baja —
     *    filtrado en la consulta agregada, ver PARTE G).
     *  - DAY PLAYER (day_player): el vencimiento SÍ opera → pasada la vigencia definitiva, FUERA.
     *
     * 4 estados: OUT (inactivo, o day player vencido) · NOT_CALLED (sin fecha) · CALLED (fecha +
     * sobre completado) · PENDING_SIGNATURE (fecha ese día pero SIN sobre completado — la PUERTA:
     * nunca cuenta como llamado, pero es visible y accionable, no invisible).
     *
     * NO valida concept: eso es concern del contrato suelto (rosterStateOn lo guarda antes); la
     * consulta agregada ya filtra concept=crew_work.
     */
    public static function resolveRosterState(
        bool $isActive,
        ?string $frequency,
        $definitiveEndDate,
        bool $called,
        bool $envelopeCompleted,
        $date
    ): string {
        if (! $isActive) {
            return self::ROSTER_OUT;
        }

        // Vencimiento: SÓLO para day players. El crew fijo no cae a OUT por vencer (se queda con N/C).
        if ($frequency === self::FREQ_DAY_PLAYER && $definitiveEndDate) {
            $day = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
            if (Carbon::parse($definitiveEndDate)->startOfDay()->lt($day)) {
                return self::ROSTER_OUT;
            }
        }

        if (! $called) {
            return self::ROSTER_NOT_CALLED;
        }

        return $envelopeCompleted ? self::ROSTER_CALLED : self::ROSTER_PENDING_SIGNATURE;
    }

    /**
     * ESTADO DEL ROSTER de este contrato en una fecha — HELPER DE UNA SOLA FILA. Delega en
     * {@see resolveRosterState} (la fuente única). Un contrato que no es crew_work nunca produce
     * roster (guarda propia del contrato suelto). Devuelve los 4 estados, igual que la agregada.
     */
    public function rosterStateOn($date): string
    {
        if (! $this->isCrewWork()) {
            return self::ROSTER_OUT;
        }

        $day    = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $called = $this->workDates()->whereDate('work_date', $day->toDateString())->exists();

        return self::resolveRosterState(
            (bool) $this->is_active,
            $this->payment_frequency,
            $this->definitive_end_date,
            $called,
            $this->hasCompletedEnvelope(),
            $day
        );
    }

    /**
     * Tarifa SEMANAL de viáticos según la fase: shoot usa su monto; soft_prep/prep/wrap usan el
     * de prep/wrap (en los contratos reales son cifras distintas). Devuelve null si no se capturó.
     */
    public function weeklyPerdiemForPhase(string $phase): ?string
    {
        return $phase === PayeeContractWorkDate::PHASE_SHOOT
            ? $this->perdiem_weekly_shoot
            : $this->perdiem_weekly_prep;
    }
}
