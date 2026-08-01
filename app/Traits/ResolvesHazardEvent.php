<?php

namespace App\Traits;

use App\Models\HazardEvent;
use Illuminate\Support\Facades\Schema;

/**
 * ResolvesHazardEvent — homologa el enlace evento→norma en los reportes de
 * VALOR ÚNICO (Hazard, Cond. Insegura, Lesión) y en la bitácora (DailyLog).
 *
 * Al elegir un "evento posible" en el formulario, el controlador llama
 * $report->applyHazardEvent($id) DESPUÉS de crear el reporte. El trait:
 *   1) guarda el id del evento elegido (columna snapshot hazard_event_id);
 *   2) copia el badge/código de la norma PRINCIPAL del evento a regulation_badge
 *      / regulation_code (compat con el snapshot histórico del catálogo);
 *   3) adjunta TODAS las normas del evento al pivote polimórfico `standardables`
 *      (si el modelo usa HasStandards → syncStandards).
 *
 * TODO con guards defensivos (Schema::hasTable / hasColumn): en PROD sin el SQL
 * aplicado, es un no-op seguro y el formulario sigue funcionando.
 */
trait ResolvesHazardEvent
{
    /**
     * @param  int|string|null  $eventId  id de hazard_events elegido en el form.
     * @return \App\Models\HazardEvent|null  el evento resuelto (o null).
     */
    public function applyHazardEvent($eventId)
    {
        if (empty($eventId) || !Schema::hasTable('hazard_events')) {
            return null;
        }

        $event = HazardEvent::with('standards')->find($eventId);
        if (!$event) {
            return null;
        }

        $table = $this->getTable();
        $dirty = false;

        if (Schema::hasColumn($table, 'hazard_event_id')) {
            $this->hazard_event_id = $event->id;
            $dirty = true;
        }

        // Norma PRINCIPAL = primera vinculada (el orden del seed pone la más
        // representativa primero). Snapshot para el rastro de auditoría histórico.
        $primary = $event->standards->first();
        if ($primary) {
            if (Schema::hasColumn($table, 'regulation_badge')) {
                $this->regulation_badge = $primary->regulation_badge;
                $dirty = true;
            }
            if (Schema::hasColumn($table, 'regulation_code')) {
                $this->regulation_code = $primary->regulation_code;
                $dirty = true;
            }
        }

        if ($dirty) {
            $this->save();
        }

        // Adjuntar TODAS las normas del evento al pivote N:M (si aplica el trait).
        if (method_exists($this, 'syncStandards') && Schema::hasTable('standardables')) {
            $this->syncStandards($event->standards->pluck('id')->toArray());
        }

        return $event;
    }
}
