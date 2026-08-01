<?php

namespace App\Models;

use App\Traits\HasDigitalSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Addendum médico (Pilar 4) — enmienda APPEND-ONLY sobre un InjuryReport ya firmado.
 *
 * El reporte de lesión original es un expediente clínico/legal: una vez creado (y
 * firmado) NO se altera, ni él ni su PDF. Cuando el diagnóstico o el tratamiento
 * cambian (p. ej. una lesión que primeros-auxilios evoluciona a "días perdidos" y
 * se vuelve registrable OSHA), se agrega un Addendum que guarda el cambio SIN tocar
 * el registro base. Las estadísticas "efectivas" (nivel de tratamiento,
 * registrabilidad, días perdidos/restringidos) se leen del ÚLTIMO addendum —
 * ver App\Traits\HasMedicalAddendums en InjuryReport.
 *
 * SELLO PROPIO (2026-07-20): el addendum usa HasDigitalSignatures y se firma al
 * crearse (AddendumController::store). Es una firma polimórfica INDEPENDIENTE del
 * sello del InjuryReport: prueba que ESTE anexo no se alteró después, sin tocar el
 * hash del reporte original (cadena de custodia). El payload canónico excluye sólo
 * created_at/updated_at/uuid; el addendum no tiene columna uuid y eso es inocuo.
 *
 * Tabla: addendums (owner-apply SQL). Feature flag: 'medical_addendum'.
 */
class Addendum extends Model
{
    use HasDigitalSignatures;

    protected $fillable = [
        'injury_report_id',
        'type',
        'body',
        // Snapshot del NUEVO valor "efectivo" (nullable: un addendum tipo nota puede
        // no cambiar ninguna estadística). El registro original nunca se modifica.
        'new_treatment_level',
        'new_is_recordable',
        'new_days_away',
        'new_days_restricted',
        'created_by_id',
    ];

    protected $casts = [
        'new_is_recordable'  => 'boolean',
        'new_days_away'      => 'integer',
        'new_days_restricted' => 'integer',
    ];

    /**
     * El reporte de lesión enmendado por este addendum.
     */
    public function injuryReport(): BelongsTo
    {
        return $this->belongsTo(\App\Models\InjuryReport::class, 'injury_report_id');
    }

    /**
     * Autor del addendum (médico/responsable de seguridad autenticado).
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    /**
     * Etiquetas legibles de los tipos de addendum (para selects y vistas).
     *
     * @return array
     */
    public static function typeLabels(): array
    {
        return [
            'diagnosis_change' => 'Cambio de diagnóstico',
            'treatment_update' => 'Actualización de tratamiento',
            'note'             => 'Nota',
        ];
    }
}
