<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * EMPRESA prestadora de ambulancias. Se califica LA PRIMERA VEZ que se necesita
 * (puede ser en prep o el mismo día que llega la unidad); una vez calificada NO se
 * vuelve a preguntar cada día. Sus documentos viven en {@see ExternalAuthorization}
 * (nivel empresa) y su padrón en {@see AmbulanceCrew}.
 */
class AmbulanceProvider extends Model
{
    protected $table = 'ambulance_providers';

    protected $fillable = ['name', 'rfc', 'contact_phone', 'sanitary_manager', 'notes', 'is_active', 'created_by_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function crew(): HasMany
    {
        return $this->hasMany(AmbulanceCrew::class, 'provider_id');
    }

    /** Documentos de nivel EMPRESA (polimórfico). */
    public function authorizations(): MorphMany
    {
        return $this->morphMany(ExternalAuthorization::class, 'holder');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
