<?php

namespace App\Listeners;

use App\Events\UnsafeConditionReported;
use App\Support\DsrHub;

/**
 * InjectUnsafeConditionIntoDsr — al reportarse una condición insegura, deja
 * rastro en el DSR del día observado. Defensivo/idempotente vía DsrHub.
 */
class InjectUnsafeConditionIntoDsr
{
    public function handle(UnsafeConditionReported $event)
    {
        $u = $event->model;

        DsrHub::inject(
            $u,
            (isset($u->date_observed) && $u->date_observed ? $u->date_observed : now()),
            [
                'log_time'     => substr((string) (isset($u->time_observed) && $u->time_observed ? $u->time_observed : now()->format('H:i')), 0, 5),
                'description'  => '⚠ Condición insegura — ' . trim((string) (isset($u->description_unsafe_cond) ? $u->description_unsafe_cond : '')),
                'action_taken' => (isset($u->corrective_action) ? $u->corrective_action : null),
            ],
            (isset($u->created_by_id) ? $u->created_by_id : null)
        );
    }
}
