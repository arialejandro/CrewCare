<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\CalculatesRiskMatrix;
use App\Traits\TracksCorrectiveActions;
use App\Traits\HasStandards;
use App\Traits\DeterminesInjuryRecordability;
use App\Traits\HasDigitalSignatures;
use App\Traits\GeneratesUuidKey;
use App\Traits\ResolvesHazardEvent;
use App\Traits\HasMedicalAddendums;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InjuryReport extends Model
{
    // (2026-07-09) Matriz 5×5 (risk_level server-side), PDCA de acciones correctivas
    // (desde 'preventions'), vínculo N:M a normas, y reglas OSHA/fatiga
    // (is_recordable + hours_worked_prior) vía el Observer del trait.
    // (2026-07-12) Cimientos módulos 6-14: firma digital/no-repudio, uuid público.
    // (2026-07-13) Catálogo único de eventos: applyHazardEvent() homologa snapshot + N:M.
    // (2026-07-13) Pilar 4: addendums médicos (append-only, estadística efectiva).
    use CalculatesRiskMatrix, TracksCorrectiveActions, HasStandards, DeterminesInjuryRecordability, HasDigitalSignatures, GeneratesUuidKey, ResolvesHazardEvent, HasMedicalAddendums;

    /**
     * (2026-09-05 · Unidades P1) `unit_id` y `production_id` EXCLUIDAS del hash SOLO cuando son null.
     * injury_reports NO tenía `production_id` (se aislaba solo por autor, created_by_id): se siembra en
     * la misma pasada, en null para los 4 sellos existentes → fuera del hash → no cambian. El filtro por
     * autor NO se toca. La const la aplica el trait (HasDigitalSignatures::nullableHashExcludes). Paso 2
     * cablea los filtros; aquí solo se siembra la columna.
     */
    const NULLABLE_HASH_EXCLUDES = ['unit_id', 'production_id'];

    /**
     * (2026-07-13) Pilar 2 (Event-Driven): al CREARSE un accidente se dispara el
     * evento → el Listener inyecta un log al DSR del día (find-or-create). El
     * usuario no hace trabajo extra. DsrHub es defensivo/idempotente.
     */
    protected $dispatchesEvents = [
        'created' => \App\Events\AccidentReported::class,
    ];

    protected $fillable = [
        'production_title', 'production_dates', 'location', 'department',
        // (2026-07-09) Patrón/contratista del lesionado (aviso IMSS/STPS).
        'employer_name',
        'incident_date', 'reported_date', 'time',
        // (2026-07-09) call_time: inicio de turno del lesionado (cálculo de fatiga).
        'call_time', 'hours_worked_prior',
        'incident_location',
        // (2026-07-07) GPS opcional: coordenadas + dirección detectada (reverse geocoding).
        'latitude', 'longitude', 'gps_address',
        'name', 'position', 'dob', 'phone', 'other', 'user_id',
        'body_part', 'injury_type', 'treatment_type',
        'treatment_by', 'hospital', 'treatment_comments',
        // (2026-07-09) Registrabilidad OSHA 300/301.
        'treatment_level', 'days_away_from_work', 'days_restricted_work', 'is_recordable',
        'what_happened', 'what_caused',
        // (2026-07-09) Causa raíz estructurada (JSON) + EPP estructurado (JSON).
        'root_cause_analysis', 'ppe_details',
        'seriousness', 'frequency',
        // (2026-07-09) Matriz 5×5: ejes capturados; risk_level calculado server-side.
        'likelihood', 'consequence', 'risk_level',
        'notified_to_worksafe', 'date_notified', 'notified_by', 'notified_comment',
        'preventions', 'further_comments', 'main_image_path', 'additional_images_paths',
        // (2026-07-12) Notificaciones a autoridad (IMSS/STPS/OSHA) estructuradas (JSON),
        // justificación cuando la ubicación se captura a mano (sin GPS), y uuid público.
        'authority_notifications', 'manual_location_justification', 'uuid',
        'make_by', 'make_date',
        // (2026-06-28) Catálogo normativo: snapshot del badge/código de la norma aplicable
        // (mismo patrón que DailyLog). COPIA, no FK, para preservar el rastro de auditoría.
        'regulation_badge', 'regulation_code',
        // (2026-06-28) AUTOFIRMA: id del usuario autenticado que creó el reporte (server-side).
        'created_by_id',
        // (2026-07-13) Evento elegido del catálogo único (snapshot; norma vía applyHazardEvent).
        'hazard_event_id',
        // (2026-07-13) Pilar 4: override del criterio experto sobre la matriz 5×5.
        'override_risk_level',
        // (2026-07-13) Pilar 1: captura en 2 fases — 1 = falta completar compliance en back-office.
        'pending_compliance',
    ];

    protected $casts = [
        'injury_type' => 'array',
        'additional_images_paths' => 'array',
        'notified_to_worksafe' => 'boolean',
        // (2026-07-09) Nuevos:
        'root_cause_analysis' => 'array',
        'ppe_details' => 'array',
        'is_recordable' => 'boolean',
        'days_away_from_work' => 'integer',
        'days_restricted_work' => 'integer',
        // (2026-07-12) Notificaciones a autoridad estructuradas.
        'authority_notifications' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * (PASO B, 2026-07-19) Quién CAPTURÓ el reporte (autofirma server-side, ver
     * InjuryReportController). La columna `created_by_id` existía desde 2026-06-28 pero
     * nunca tuvo relación.
     *
     * NO ES EL MÉDICO TRATANTE. Puede ser un safety-officer, un coordinador o quien
     * levantara el parte. El médico que atendió sigue siendo TEXTO LIBRE en `hospital` /
     * `treatment_by`; el único vínculo real a un médico en todo el accidente es
     * `addendums.created_by_id`. Cuidado al etiquetarlo en pantalla: llamarle "médico" a
     * esto sería afirmar algo que el dato no dice.
     *
     * SEGURO PARA EL SELLO SHA-256: el hash se calcula sobre attributesToArray() — solo
     * columnas propias, nunca relaciones. Añadir esta relación no altera ningún hash ya
     * firmado. (Lo que SÍ lo rompería es añadir una COLUMNA a `injury_reports`.)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * (2026-07-12) Testigos del accidente/lesión (1:N).
     */
    public function witnesses(): HasMany
    {
        return $this->hasMany(\App\Models\Witness::class, 'injury_report_id');
    }

    /**
     * (2026-07-13) Evento del catálogo único elegido para este reporte.
     */
    public function hazardEvent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\HazardEvent::class, 'hazard_event_id');
    }
}