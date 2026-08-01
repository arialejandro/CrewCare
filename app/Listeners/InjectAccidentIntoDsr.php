<?php

namespace App\Listeners;

use App\Events\AccidentReported;
use App\Support\DsrHub;

/**
 * InjectAccidentIntoDsr — al reportarse un accidente, deja rastro en el DSR del
 * día del incidente. DsrHub es defensivo/idempotente: si el flag está apagado o
 * falta el esquema, es no-op silencioso.
 */
class InjectAccidentIntoDsr
{
    public function handle(AccidentReported $event)
    {
        $a = $event->model;

        DsrHub::inject(
            $a,
            (isset($a->incident_date) && $a->incident_date ? $a->incident_date : now()),
            [
                'log_time'     => substr((string) (isset($a->time) && $a->time ? $a->time : now()->format('H:i')), 0, 5),
                'description'  => '🩹 Accidente reportado — ' . trim((string) (isset($a->what_happened) ? $a->what_happened : '')),
                'action_taken' => (isset($a->preventions) ? $a->preventions : null),
            ],
            (isset($a->created_by_id) ? $a->created_by_id : null)
        );
    }
}
