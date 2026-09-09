<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportParty — padrón LIGERO de ocupantes no-crew de una corrida (Bloque 2 §2).
 *
 * CAST / AGENCIA / CLIENTE: se teclean una vez y se re-encuentran por autocompletado dentro
 * de la producción (misma dinámica que LitePatient). NO es una cuenta ni un payee.
 */
class TransportParty extends Model
{
    protected $table = 'transport_parties';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const KIND_CAST   = 'cast';
    public const KIND_AGENCY = 'agency';
    public const KIND_CLIENT = 'client';

    public const KINDS = [self::KIND_CAST, self::KIND_AGENCY, self::KIND_CLIENT];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForProduction($query, ?int $productionId)
    {
        return $query->where('production_id', $productionId);
    }

    /** Etiqueta legible del tipo (para chips del typeahead). */
    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_AGENCY => 'Agencia',
            self::KIND_CLIENT => 'Cliente',
            default           => 'Cast',
        };
    }
}
