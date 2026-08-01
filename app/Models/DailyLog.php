<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TracksCorrectiveActions;
use App\Traits\ResolvesHazardEvent;
use App\Traits\HasStandards;

class DailyLog extends Model
{
    // (2026-07-09) El texto correctivo del Daily vive en el LOG (action_taken):
    // cada hallazgo con acción genera un ActionItem rastreable (PDCA). El Daily
    // no tiene estado "Final" (usa candado de 24 h), así que no aplica el bloqueo.
    //
    // (2026-07-21) HasStandards — HOMOLOGACIÓN CON LOS OTROS 4 REPORTES. Antes el
    // Daily era el único que guardaba SOLO la norma PRINCIPAL del evento (snapshot
    // plano badge/code), tirando el resto. Medición del catálogo: de los 207 eventos,
    // 0 tienen una sola norma — 22 tienen 2, 146 tienen 3 y 39 tienen 4. O sea el
    // snapshot plano estaba descartando entre la mitad y las tres cuartas partes de
    // la información normativa de cada hallazgo. Con el trait, applyHazardEvent()
    // entra a su rama method_exists($this,'syncStandards') y estampa TODAS las normas
    // en `standardables`, sin dejar de escribir el snapshot (que sigue alimentando el
    // heatmap de riesgos y el histórico ya capturado).
    use TracksCorrectiveActions, ResolvesHazardEvent, HasStandards;

    // SEGURIDAD (2026-06-28): antes `$guarded = []`. storeLog() inserta un array explícito de
    // campos derivados (incluidos regulation_badge/code que el backend resuelve por catálogo),
    // así que esto solo fija el contrato y blinda contra mass-assignment futuro.
    protected $fillable = [
        'daily_report_id', 'log_time', 'description', 'action_taken',
        'photo_path', 'regulation_badge', 'regulation_code',
        // (2026-07-13) Coherencia: autofirma del autor del log (trazabilidad).
        'created_by_id',
        // (2026-07-13) Evento elegido del catálogo único (snapshot; norma vía applyHazardEvent).
        'hazard_event_id',
        // (2026-07-13) Pilar 2: inyección polimórfica — de qué evento crítico proviene el log.
        'sourceable_id', 'sourceable_type',
        // (2026-07-22) EPP que exigió ESTE hallazgo, heredado de su evento del catálogo.
        'required_ppe',
    ];

    protected $casts = [
        // SNAPSHOT, no relación: si mañana se corrige el EPP de un evento del catálogo, el
        // hallazgo ya capturado —parte de un documento sellado— NO debe cambiar. El acta dice
        // lo que se exigió ese día, no lo que hoy diríamos que se debió exigir.
        'required_ppe' => 'array',
    ];

    // Relación: Un log pertenece a un reporte
    public function report()
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * (2026-07-13) Evento del catálogo único elegido para este hallazgo.
     */
    public function hazardEvent()
    {
        return $this->belongsTo(\App\Models\HazardEvent::class, 'hazard_event_id');
    }

    /**
     * (2026-07-13) Pilar 2: origen polimórfico del log cuando fue INYECTADO por un
     * evento crítico (Accidente / Condición Insegura / SFX / consulta ligada). NULL
     * si es un log manual del DSR.
     */
    public function sourceable()
    {
        return $this->morphTo();
    }

    /** ¿Este log fue inyectado automáticamente (no capturado a mano)? */
    public function getIsInjectedAttribute()
    {
        return !empty($this->sourceable_type);
    }
}