<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Testigo de un Accidente/Lesión (1:N sobre injury_reports).
 *
 * Cierra la brecha detectada en la Auditoría de Contenido: los reportes de
 * lesión carecían de registro estructurado de testigos. Cada InjuryReport puede
 * tener múltiples testigos con nombre, teléfono y declaración libre.
 *
 * Tabla: witnesses (database/owner-apply/2026-07-12-modules-6-14.sql).
 */
class Witness extends Model
{
    protected $fillable = [
        'injury_report_id',
        'name',
        'phone',
        'statement',
    ];

    /**
     * El reporte de lesión al que pertenece este testigo.
     */
    public function injuryReport(): BelongsTo
    {
        return $this->belongsTo(InjuryReport::class, 'injury_report_id');
    }
}
