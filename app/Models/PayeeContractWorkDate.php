<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EL CONTRATO · PASO A — un renglón de FECHA DE TRABAJO de un contrato `crew_work`.
 * Fecha + FASE (soft_prep | prep | shoot | wrap). La fase determina la tarifa semanal de
 * viáticos ({@see PayeeContract::weeklyPerdiemForPhase()}). Fechas no contiguas: varios
 * renglones por contrato. Un `unit_id` futuro entraría como columna nullable aditiva; un
 * estado "descansa vs no llamado" sería un campo en el renglón — NINGUNO se construye aún.
 */
class PayeeContractWorkDate extends Model
{
    protected $table = 'payee_contract_work_dates';

    const PHASE_SOFT_PREP = 'soft_prep';
    const PHASE_PREP      = 'prep';
    const PHASE_SHOOT     = 'shoot';
    const PHASE_WRAP      = 'wrap';

    protected $fillable = ['payee_contract_id', 'work_date', 'phase'];

    protected $casts = ['work_date' => 'date'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PayeeContract::class, 'payee_contract_id');
    }

    /** Fases válidas (clave => etiqueta traducible) para selects y validación. */
    public static function phases(): array
    {
        return [
            self::PHASE_SOFT_PREP => __('Soft prep'),
            self::PHASE_PREP      => __('Prep'),
            self::PHASE_SHOOT     => __('Shoot'),
            self::PHASE_WRAP      => __('Wrap'),
        ];
    }

    public function isShoot(): bool
    {
        return $this->phase === self::PHASE_SHOOT;
    }
}
