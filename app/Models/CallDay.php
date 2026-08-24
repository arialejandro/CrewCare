<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PARTE D · config del LLAMADO de un día (una fila por producción+fecha). El `general_call` es el
 * ANCLA (N1) del que cuelgan todos los offsets. Concepto NUEVO: NO es `daily_reports.call_time`.
 * Jornada + wrap toggle derivan el wrap estimado del día; contingente cast/BG es el default de los
 * servicios de comida. Nada aquí se sella (la firma del back es opcional y aparte).
 */
class CallDay extends Model
{
    protected $table = 'call_days';

    protected $fillable = [
        'production_id', 'call_date', 'general_call', 'journey_minutes', 'wrap_estimate_enabled',
        'location_place_id', 'location_text', 'basecamp_place_id', 'basecamp_text',
        'notes', 'cast_count', 'bg_count', 'sign_enabled', 'footer_extra',
    ];

    protected $casts = [
        'call_date'             => 'date',
        'journey_minutes'       => 'integer',
        'wrap_estimate_enabled' => 'boolean',
        'cast_count'            => 'integer',
        'bg_count'              => 'integer',
        'sign_enabled'          => 'boolean',
        'footer_extra'          => 'array',
    ];

    public function meals(): HasMany
    {
        return $this->hasMany(CallDayMeal::class, 'call_day_id')->orderBy('sort_order')->orderBy('id');
    }

    /** El general como 'H:i' (sin segundos), o null. */
    public function generalHHMM(): ?string
    {
        if (empty($this->general_call)) {
            return null;
        }
        // general_call viene como 'HH:MM:SS' o 'HH:MM'.
        return substr((string) $this->general_call, 0, 5);
    }
}
