<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DÍA de la producción (calendario de rodaje dinámico, 2026-09-06). Una fila por fecha. El
 * calendario MANDA: {@see \App\Support\ProductionCalendar::shootDates()} lee de aquí. NO es un
 * documento sellado — es planeación editable; por eso no usa HasDigitalSignatures ni toca ningún hash.
 *
 * `slug_time` usa el MISMO vocabulario que el DSR (`daily_reports.slug_time`) para que la confirmación
 * sea una comparación directa, no una traducción. null = DÍA.
 */
class ShootDay extends Model
{
    protected $table = 'shoot_days';

    protected $fillable = [
        'production_id', 'shoot_date', 'is_shoot_day', 'slug_time', 'week_no', 'is_manual', 'note', 'created_by_id',
    ];

    protected $casts = [
        'shoot_date'   => 'date',
        'is_shoot_day' => 'boolean',
        'is_manual'    => 'boolean',
        'week_no'      => 'integer',
    ];

    // Vocabulario de LUZ — idéntico al del DSR (dailyreports/create: "Luz").
    const SLUG_DIA       = 'DÍA';
    const SLUG_NOCHE     = 'NOCHE';
    const SLUG_AMANECER  = 'AMANECER';
    const SLUG_ATARDECER = 'ATARDECER';
    const SLUG_MIXTO     = 'MIXTO';

    const SLUGS = [self::SLUG_DIA, self::SLUG_NOCHE, self::SLUG_AMANECER, self::SLUG_ATARDECER, self::SLUG_MIXTO];

    /** Los slugs que CRUZAN la madrugada → disparan la regla acotada (solo en el último día de la semana). */
    const CROSSES_MIDNIGHT = [self::SLUG_NOCHE, self::SLUG_MIXTO];

    public function scopeForProduction($query, $productionId)
    {
        return $query->where('production_id', $productionId);
    }

    /** Solo días de rodaje (excluye descansos/festivos marcados). */
    public function scopeShootDays($query)
    {
        return $query->where('is_shoot_day', true);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /** Luz efectiva (null = DÍA). */
    public function slug(): string
    {
        $s = trim((string) $this->slug_time);

        return $s !== '' ? $s : self::SLUG_DIA;
    }

    /** ¿Este día cruza la madrugada (NOCHE/MIXTO)? Insumo de la regla acotada. */
    public function crossesMidnight(): bool
    {
        return in_array($this->slug(), self::CROSSES_MIDNIGHT, true);
    }
}
