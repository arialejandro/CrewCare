<?php

namespace App\Listeners;

use App\Events\ContractEnvelopeCompleted;
use App\Jobs\DeliverSignedContractEmail;
use App\Support\Features;
use Illuminate\Support\Facades\Log;

/**
 * EL INFOSHEET · FASE 3.4 — al COMPLETARSE la ruta de firma (todas las partes firmaron), entrega al
 * CONTRATADO su paquete firmado. Cierra el pipeline: Infosheet → Autorización → Contrato → Firmas → **Correo**.
 *
 * El listener corre SÍNCRONO (best-effort dentro de la firma) y solo DECIDE el carril del envío según
 * el flag `contracts_queue_email` (admin):
 *  - APAGADO (default) → dispatchSync → correo INMEDIATO en el request (comportamiento histórico).
 *  - ENCENDIDO → dispatch → a la COLA (con driver real + worker sale del request; con `sync` corre
 *    inline sin daño). El correo NUNCA se pierde en ninguno de los dos casos.
 *
 * (El trabajo pesado — armar el correo con adjuntos y mandar SMTP — vive en el Job para poder salir
 * del request bajo carga; ver {@see \App\Jobs\DeliverSignedContractEmail}.)
 */
class EmailSignedContractToParty
{
    public function handle(ContractEnvelopeCompleted $event): void
    {
        try {
            if (Features::enabled('contracts_queue_email')) {
                DeliverSignedContractEmail::dispatch($event->envelope);
            } else {
                DeliverSignedContractEmail::dispatchSync($event->envelope);
            }
        } catch (\Throwable $e) {
            // Nunca romper la firma: el correo es secundario.
            Log::error('EmailSignedContractToParty: fallo al despachar — '.$e->getMessage());
        }
    }
}
