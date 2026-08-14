<?php

namespace App\Events;

use App\Models\ContractEnvelope;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * EL CONTRATO · PASO C — la ruta de firma del sobre se COMPLETÓ. Seam de "avisa": un listener puede
 * resolver destinatarios con {@see \App\Support\InvolvedResolver} y mandar el correo. Fired best-effort.
 */
class ContractEnvelopeCompleted
{
    use Dispatchable;

    public function __construct(public ContractEnvelope $envelope)
    {
    }
}
