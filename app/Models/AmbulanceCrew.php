<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * PADRÓN de tripulantes de un proveedor. El roster NO es estable: si quien se
 * presenta no está, se da de alta EN EL MOMENTO con foto de credencial (sin
 * fricción). Sus documentos (TAMP, cédula del médico, CONOCER) viven en
 * {@see ExternalAuthorization} (nivel persona).
 */
class AmbulanceCrew extends Model
{
    protected $table = 'ambulance_crew';

    protected $fillable = ['provider_id', 'full_name', 'crew_role', 'id_photo_path', 'notes', 'is_active', 'created_by_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AmbulanceProvider::class, 'provider_id');
    }

    public function authorizations(): MorphMany
    {
        return $this->morphMany(ExternalAuthorization::class, 'holder');
    }

    public function idPhotoUrl(): ?string
    {
        $p = trim((string) $this->id_photo_path);
        return $p !== '' ? Storage::url($p) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
