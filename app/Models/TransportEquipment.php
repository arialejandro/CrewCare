<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportEquipment — catálogo del equipamiento declarable de una corrida (Bloque 2 §2).
 *
 * Sale como ÍCONO en la orden, no como texto. Las corridas guardan sólo los `code` en su
 * JSON `equipment`. Editable por transpo; se siembra un set inicial (TransportEquipmentSeeder).
 */
class TransportEquipment extends Model
{
    protected $table = 'transport_equipment';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name_es');
    }

    /** Nombre según idioma de sesión. */
    public function name(): string
    {
        if (app()->getLocale() === 'en' && ! empty($this->name_en)) {
            return $this->name_en;
        }

        return $this->name_es;
    }

    /** Mapa code => registro activo, para resolver los íconos de una corrida sin N+1. */
    public static function activeByCode(): \Illuminate\Support\Collection
    {
        return static::query()->active()->ordered()->get()->keyBy('code');
    }
}
