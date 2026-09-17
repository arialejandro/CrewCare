<?php

namespace App\Events;

use App\Models\ContractEnvelope;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * EL CONTRATO · PASO C — la ruta de firma del sobre se COMPLETÓ. Seam de "avisa": el listener corre
 * síncrono y despacha el Job del correo (a cola o inmediato, según el flag). Fired best-effort.
 */
class ContractEnvelopeCompleted
{
    use Dispatchable;

    public function __construct(public ContractEnvelope $envelope)
    {
    }
}
