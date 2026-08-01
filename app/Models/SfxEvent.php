<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SfxEvent — Dinámica de efectos especiales en vivo (Pilar 3, flag 'sds_sfx').
 *
 * Un "efecto" es un disparo real en set (humo, fuego, salvas...) con estado tipo TOGGLE:
 *   - status 'active'  → arrancó (started_at), aún corriendo, se ve como "● Activo".
 *   - status 'ended'   → se detuvo (ended_at).
 * Al iniciar/terminar se INYECTA un daily_log al DSR del día vía App\Support\DsrHub
 * (Pilar 2), de-duplicado por este mismo modelo fuente, para dejar traza en el reporte
 * de seguridad. `consumable_id` liga (opcional) su SDS; `daily_report_id` guarda el DSR
 * donde quedó registrado. DEFENSIVO: tabla detrás de Schema::hasTable() en el controlador.
 */
class SfxEvent extends Model
{
    protected $table = 'sfx_events';

    protected $fillable = [
        'consumable_id',
        'daily_report_id',
        'production_ref',
        'effect_label',
        'safety_criteria',
        'status',
        'started_by_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    /** SDS/consumible ligado a este efecto (opcional). */
    public function consumable()
    {
        return $this->belongsTo(Consumable::class);
    }

    /** DSR donde se registró el evento (si la inyección al Daily tuvo éxito). */
    public function report()
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /** Usuario que disparó el efecto (trazabilidad). */
    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    /** Solo efectos actualmente en curso. */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
