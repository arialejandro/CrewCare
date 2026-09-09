<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RBAC foundation — NEW first-class entity. A user can have a different role in each
 * production via the production_user pivot (withPivot role/department/position/is_lead).
 */
class Production extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'client_name', 'start_date', 'end_date',
        'status', 'active', 'settings',
        // PARTE A · CALENDARIO DE RODAJE (2026-08-23). La duración planeada del rodaje: de
        // start_date + shoot_weeks × shoot_days_per_week se derivan el TOTAL (la M de "Día N de M")
        // y el wrap estimado. end_date sigue siendo el wrap AJUSTABLE. Ver ProductionCalendar.
        'shoot_weeks', 'shoot_days_per_week',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'settings' => 'array',
        'shoot_weeks' => 'integer',
        'shoot_days_per_week' => 'integer',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'production_user')
            ->withPivot(['department_id', 'position_id', 'role', 'is_lead'])
            ->withTimestamps();
    }
}
