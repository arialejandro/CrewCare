<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportPickupPoint — ORIGEN estable de pickup (Fase 1). Catálogo pequeño y estable
 * (Churubusco, Oficina de Producción, Condesa), capturado UNA vez con coordenadas.
 * Es el origen del par de la matriz de traslado; NO es una dirección privada (esas viven en
 * `transport_addresses` con allowlist). Referencia BLANDA por producción.
 */
class TransportPickupPoint extends Model
{
    protected $table = 'transport_pickup_points';

    protected $guarded = ['id'];

    protected $casts = [
        'lat'        => 'float',
        'lng'        => 'float',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** ¿Tiene coordenadas para poder rutear con OSRM? */
    public function hasGeo(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
