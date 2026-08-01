<?php

namespace App\Listeners;

use App\Events\MedicalConsultRecorded;
use App\Support\DsrHub;

/**
 * InjectLinkedConsultIntoDsr — FILTRO DE RUIDO + ENMASCARAMIENTO.
 *
 * Las consultas médicas COMUNES NO se inyectan (privacidad + señal/ruido). Solo
 * las LIGADAS a un accidente (injury_report_id) dejan un rastro de SEGUIMIENTO
 * en el DSR, sin diagnóstico ni datos clínicos: apenas el vínculo al accidente.
 */
class InjectLinkedConsultIntoDsr
{
    public function handle(MedicalConsultRecorded $event)
    {
        $c = $event->model;

        // Filtro de ruido: sin accidente ligado, no hay inyección.
        if (empty($c->injury_report_id)) {
            return;
        }

        DsrHub::inject(
            $c,
            (isset($c->consultation_date) && $c->consultation_date ? $c->consultation_date : now()),
            [
                'log_time'    => now()->format('H:i'),
                // Enmascarado: solo el vínculo, NADA clínico.
                'description' => '🏥 Seguimiento médico ligado a accidente #' . $c->injury_report_id,
            ],
            (isset($c->created_by_id) ? $c->created_by_id : null)
        );
    }
}
