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

class hazardnotification extends Model
{
    // (2026-07-09) Matriz 5×5 (risk_level calculado server-side), motor PDCA de
    // acciones correctivas y vínculo N:M a normas (standardables).
    // (2026-07-12) Cimientos módulos 6-14: firma digital/no-repudio, uuid público.
    // (2026-07-13) Catálogo único de eventos: applyHazardEvent() homologa snapshot + N:M.
    use HasFactory, CalculatesRiskMatrix, TracksCorrectiveActions, HasStandards, HasDigitalSignatures, GeneratesUuidKey, ResolvesHazardEvent;

    /**
     * (2026-07-19) Pilar 2 / Aviso de seguridad: al CREARSE un acto inseguro se
     * despacha HazardReported (espejo de InjuryReport→AccidentReported y
     * unsafecond→UnsafeConditionReported). Lo consumen los Listeners del DSR (si
     * se cablea) y SendHighRiskSafetyAlert (correo Alto/Extremo). El Listener es
     * defensivo: nunca rompe el create() del reporte.
     */
    protected $dispatchesEvents = [
        'created' => \App\Events\HazardReported::class,
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
        'location_hazard_unsafe_act',
        'description_hazard_unsafe_act',
        'action_taken',
        'suggestions_corrective_action',
        'main_image_path',
        'additional_images_paths',
        'make_by',
        'make_date',
        // (2026-06-28) Catálogo normativo: snapshot del badge/código de la norma aplicable
        // (mismo patrón que DailyLog). COPIA, no FK, para preservar el rastro de auditoría.
        'regulation_badge',
        'regulation_code',
        // (2026-06-28) AUTOFIRMA: id del usuario autenticado que creó el reporte (servidor).
        'created_by_id',
        // (2026-06-28) Chips de lectura rápida: nivel de riesgo + estado de la acción correctiva
        // (cierre del ciclo). Son capturados por el usuario, NO autofirma.
        'risk_level',
        'action_status',
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
        // (2026-07-24) PASO 2/2: locación autoritativa (Scouting), persona involucrada (del crew;
        // NO se imprime), enlace REAL a la Condición hermana, y factor(es) humano(s) del acto.
        'scouting_report_id',
        'involved_user_id',
        'related_unsafecond_id',
        'human_factor',
    ];

    protected $casts = [
        'date_observed' => 'date',
        'additional_images_paths' => 'array', // Para guardar un array de rutas
        'make_date' => 'date',
        // (2026-07-24) Factor humano: 0..N claves (checkboxes) → JSON.
        'human_factor' => 'array',
    ];

    /**
     * (2026-07-24) Columnas del PASO 2/2 que se EXCLUYEN del hash de la firma SOLO cuando son
     * null. Un reporte sellado ANTES de que existieran estas columnas las tenía ausentes/null en
     * el payload firmado; al reaplicar el esquema seguirían en null → excluidas → el hash NO
     * cambia y el reporte NO se marca "ALTERADO" (req 7). Cuando llevan valor (reportes nuevos)
     * SÍ entran al hash → quedan protegidas contra manipulación.
     */
    // (2026-09-05 · Unidades P1) `unit_id` y `production_id` se suman a la exclusión-en-null. Esta tabla
    // NO tenía `production_id` (se aislaba solo por autor): se siembra en la misma pasada, en null para
    // todo lo existente → fuera del hash → los 6 sellos no cambian. El filtro por autor NO se toca.
    const NULLABLE_HASH_EXCLUDES = ['scouting_report_id', 'involved_user_id', 'related_unsafecond_id', 'human_factor', 'unit_id', 'production_id'];

    /**
     * (2026-07-24) FACTOR HUMANO del acto inseguro — el "por qué" de la conducta. Fuente ÚNICA
     * (clave estable → etiqueta ES) compartida por la validación, el formulario y el documento.
     * Un acto inseguro es una CONDUCTA: sin el factor humano la acción correctiva es genérica.
     * Se guarda como lista de CLAVES (JSON); se IMPRIME (es análisis, no señalamiento personal).
     */
    const HUMAN_FACTORS = [
        'capacitacion'      => 'Falta de capacitación',
        'supervision'       => 'Falta de supervisión',
        'presion_tiempo'    => 'Presión de tiempo',
        'desconocimiento'   => 'Desconocimiento del riesgo',
        'epp_no_disponible' => 'EPP no disponible',
        'atajo_deliberado'  => 'Atajo deliberado',
        'fatiga'            => 'Fatiga',
    ];

    /**
     * Override del payload canónico: replica el del trait (quita volátiles + ksort recursivo) y
     * además excluye las columnas del PASO 2/2 cuando su valor es null (ver constante arriba).
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

    /** (2026-07-24) Locación autoritativa: el Scouting donde ocurrió el hallazgo. */
    public function scoutingReport()
    {
        return $this->belongsTo(\App\Models\ScoutingReport::class, 'scouting_report_id');
    }

    /** (2026-07-24) Persona involucrada (del crew). NO se imprime en el documento. */
    public function involvedUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'involved_user_id');
    }

    /** (2026-07-24) Condición insegura hermana relacionada (enlace real, no el "bluff"). */
    public function relatedUnsafeCond()
    {
        return $this->belongsTo(\App\Models\unsafecond::class, 'related_unsafecond_id');
    }
}