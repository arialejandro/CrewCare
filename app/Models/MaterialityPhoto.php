<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MaterialityPhoto — foto de evidencia fiscal (materialidad SAT) del consumo médico.
 *
 * La fecha que cuenta para revisión fiscal es `created_at`: la fija el SERVIDOR (Eloquent
 * timestamps) al insertar; NO viene del formulario ni es editable. La galería y el PDF filtran
 * por esa fecha. `note` es el concepto opcional. Referencias blandas (captured_by_id /
 * consultation_id / production_id) sin FK dura.
 *
 * (2026-07-19) Pestaña Materialidad; gate permission:medical.materials.
 */
class MaterialityPhoto extends Model
{
    protected $table = 'materiality_photos';

    protected $fillable = [
        'image_path',
        'note',
        'captured_by_id',
        'consultation_id',
        'production_id',
    ];

    /** Usuario que capturó la evidencia (referencia blanda, opcional). */
    public function capturedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'captured_by_id');
    }
}
