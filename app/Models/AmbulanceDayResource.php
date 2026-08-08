<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RECURSO DE TRASLADO DEL DÍA — uno por producción+día. Tres estados y solo el
 * primero lleva badge:
 *   1. ambulance_on_site  — hay ambulancia (el badge sale del acta {@see AmbulanceInspection}).
 *   2. declared_medium    — sin ambulancia, CON medio declarado (vehículo de producción
 *                           para lo no grave + a quién se llama y en cuánto llega para lo
 *                           grave). Es un arreglo LEGÍTIMO y frecuente, NO un hueco.
 *   3. none               — nada declarado: esto SÍ es hueco y debe verse como tal.
 *
 * El valor de escribirlo es que el criterio queda fijado ANTES, no improvisado por
 * quien esté ahí cuando pase algo. Elegir el estado 2 NO es falla ni advertencia.
 * El vehículo de producción NO se verifica con el catálogo de ambulancias.
 */
class AmbulanceDayResource extends Model
{
    protected $table = 'ambulance_day_resources';

    const STATE_AMBULANCE = 'ambulance_on_site';
    const STATE_MEDIUM    = 'declared_medium';
    const STATE_NONE      = 'none';
    const STATES = ['ambulance_on_site', 'declared_medium', 'none'];

    protected $fillable = [
        'production_id', 'shoot_day', 'resource_date', 'state',
        'provider_id', 'ambulance_inspection_id',
        'transport_means', 'call_service', 'call_phone', 'response_time',
        'notes', 'declared_by_id', 'declared_by_name', 'declared_at', 'is_active',
    ];

    protected $casts = [
        'resource_date' => 'date',
        'declared_at'   => 'datetime',
        'is_active'     => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AmbulanceProvider::class, 'provider_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(AmbulanceInspection::class, 'ambulance_inspection_id');
    }

    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_id');
    }

    public function hasAmbulance(): bool
    {
        return $this->state === self::STATE_AMBULANCE;
    }

    public function hasDeclaredMedium(): bool
    {
        return $this->state === self::STATE_MEDIUM;
    }

    /** Estado 3: hueco visible. */
    public function isGap(): bool
    {
        return $this->state === self::STATE_NONE;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
