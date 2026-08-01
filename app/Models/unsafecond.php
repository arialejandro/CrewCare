<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use App\Traits\CalculatesRiskMatrix;
use App\Traits\TracksCorrectiveActions;
use App\Traits\HasStandards;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;
use App\Traits\ResolvesHazardEvent;

class unsafecond extends Model
{
    // (2026-07-09) Matriz 5×5 (risk_level calculado server-side), motor PDCA de
    // acciones correctivas y vínculo N:M a normas (standardables).
    // (2026-07-12) Cimientos módulos 6-14: firma digital/no-repudio, uuid público.
    // (2026-07-13) Catálogo único de eventos: applyHazardEvent() homologa snapshot + N:M.
    use HasFactory, CalculatesRiskMatrix, TracksCorrectiveActions, HasStandards, HasDigitalSignatures, GeneratesUuidKey, ResolvesHazardEvent;

    /**
     * (2026-07-13) Pilar 2: al descubrir una condición insegura se inyecta al DSR.
     */
    protected $dispatchesEvents = [
        'created' => \App\Events\UnsafeConditionReported::class,
    ];

    protected $fillable = [
        'production_name',
        'name_loc',
        // (2026-07-07) GPS opcional: coordenadas + dirección detectada (reverse geocoding).
        'latitude',
        'longitude',
        'gps_address',
        'date_observed',
        'time_observed',
        'unsafe_act_notify',
        'date_notify_unsafe_act',
        'location_unsafe_cond',
        // (2026-07-24) `involved_department` RETIRADO del fillable: una condición insegura es un
        // peligro DEL LUGAR, no de una persona/área. La columna permanece en la tabla (nullable,
        // sin uso; conserva el histórico) pero ya NO se captura ni se lee.
        'description_unsafe_cond',
        'action_taken',
        'corrective_action',
        'main_image_path',
        'additional_images_paths',
        'make_by',
        'make_date',
        // (2026-06-28) Catálogo normativo: snapshot del badge/código de la norma aplicable
        // (mismo patrón que DailyLog). COPIA, no FK, para preservar el rastro de auditoría.
        'regulation_badge',
        'regulation_code',
        // (2026-06-28) Lectura rápida: nivel de riesgo + estado de la acción correctiva (chips).
        // risk_level y action_status SÍ son capturados por el usuario (no autofirma).
        'risk_level',
        'action_status',
        // AUTOFIRMA (sistema cerrado): id del usuario autor, fijado del lado del servidor.
        'created_by_id',
        // (2026-07-09) Matriz 5×5: ejes capturados por el usuario; risk_level se calcula server-side.
        'likelihood',
        'consequence',
        // (2026-07-12) Justificación cuando la ubicación se captura a mano (sin GPS) + uuid público.
        'manual_location_justification',
        'uuid',
        // (2026-07-13) Evento elegido del catálogo único (snapshot; norma vía applyHazardEvent).
        'hazard_event_id',
        // (2026-07-13) Pilar 4: override del criterio experto sobre la matriz 5×5.
        'override_risk_level',
        // (2026-07-13) Pilar 1: captura en 2 fases — 1 = falta completar compliance en back-office.
        'pending_compliance',
        // (2026-07-24) PASO 2/2: locación autoritativa (Scouting), enlace REAL al Acto hermano, y
        // recurrencia. `involved_user_id` NO va aquí: la condición se ancla al LUGAR (scouting +
        // name_loc) y a un RESPONSABLE de reparación, no a una persona (eso es del Acto).
        'scouting_report_id',
        'related_hazard_id',
        'is_recurrent',
    ];

    protected $casts = [
        'date_observed' => 'date',
        'additional_images_paths' => 'array', // Para guardar un array de rutas
        'make_date' => 'date',
        // is_recurrent se deja SIN cast a propósito: null = no capturado/legacy (no se imprime),
        // '0' = no recurrente, '1' = recurrente. Castear a boolean convertiría null en false y
        // borraría esa distinción.
    ];

    /**
     * (2026-07-24) Columnas del PASO 2/2 que se EXCLUYEN del hash de la firma SOLO cuando son
     * null. Preserva los sellos de reportes creados antes de existir estas columnas (req 7) y
     * protege las que llevan valor. Ver hazardnotification::canonicalSignaturePayload (mismo patrón).
     * `involved_user_id` se conserva en la lista (aunque ya NO se captura en la Condición) para no
     * alterar el hash de las condiciones selladas durante el 2/2 (donde ya estaba en null/excluida).
     */
    const NULLABLE_HASH_EXCLUDES = ['scouting_report_id', 'involved_user_id', 'related_hazard_id', 'is_recurrent'];

    /**
     * Override del payload canónico: quita volátiles + ksort recursivo (como el trait) y excluye
     * las columnas del PASO 2/2 cuando su valor es null.
     *
     * @return array
     */
    public function canonicalSignaturePayload(): array
    {
        $payload = $this->attributesToArray();
        foreach (['created_at', 'updated_at', 'uuid'] as $k) {
            unset($payload[$k]);
        }
        foreach (self::NULLABLE_HASH_EXCLUDES as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] === null) {
                unset($payload[$k]);
            }
        }
        $this->ksortRecursive($payload);
        return $payload;
    }

    /**
     * (2026-07-13) Evento del catálogo único elegido para este reporte.
     */
    public function hazardEvent()
    {
        return $this->belongsTo(\App\Models\HazardEvent::class, 'hazard_event_id');
    }

    /** (2026-07-24) Locación autoritativa: el Scouting donde ocurrió la condición. */
    public function scoutingReport()
    {
        return $this->belongsTo(\App\Models\ScoutingReport::class, 'scouting_report_id');
    }

    /** (2026-07-24) Acto inseguro hermano relacionado (enlace real; la fecha se deriva de él). */
    public function relatedHazard()
    {
        return $this->belongsTo(\App\Models\hazardnotification::class, 'related_hazard_id');
    }
}