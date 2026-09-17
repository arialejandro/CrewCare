<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BORRADOR del checklist de vehículo (§1). NO es un acta: es guardado parcial EN SERVIDOR,
 * retomable desde cualquier dispositivo, DEL AUTOR. Al sellar el acta, se BORRA.
 *
 * No se sella ni entra a ningún hash. `point_photos` = {code: ruta} de fotos ya guardadas
 * (ImageCompressor::store) para no re-subirlas al retomar ni al sellar.
 */
class VehicleInspectionDraft extends Model
{
    protected $table = 'vehicle_inspection_drafts';

    protected $fillable = [
        'vehicle_id', 'production_id', 'created_by_id',
        'is_reevaluation', 'origin_inspection_id',
        'answers', 'point_photos', 'unit_photo_path', 'km', 'observations',
    ];

    protected $casts = [
        'answers'         => 'array',
        'point_photos'    => 'array',
        'km'              => 'integer',
        'is_reevaluation' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** El borrador VIVO del autor para un vehículo (o null). */
    public static function forAuthor($vehicleId, $userId): ?self
    {
        if (! $vehicleId || ! $userId) {
            return null;
        }
        return static::where('vehicle_id', $vehicleId)
            ->where('created_by_id', $userId)
            ->first();
    }
}
