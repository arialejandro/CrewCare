<?php

namespace App\Console\Commands;

use App\Support\FileDeliveryDispatcher;
use Illuminate\Console\Command;

/**
 * deliveries:dispatch {--limit=60}
 *
 * Drena el outbox de envíos ({@see \App\Models\FileDeliveryRecipient}): marca cada PDF con el nombre
 * en créditos de quien lo recibe y lo manda por correo. Pensado para el cron `schedule:run` (cada
 * minuto, `withoutOverlapping`) — así el envío masivo sale FLUIDO y sin bloquear ningún request. Con
 * la cola en `sync` (sin worker) este comando ES el motor de fondo del envío.
 */
class DispatchFileDeliveries extends Command
{
    protected $signature = 'deliveries:dispatch {--limit=60 : Máximo de destinatarios por corrida}';

    protected $description = 'Envía (con marca de agua por persona) los documentos encolados en el outbox.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $res   = FileDeliveryDispatcher::drain($limit);

        $this->info("Envíos: procesados {$res['processed']}, ok {$res['sent']}, fallidos {$res['failed']}.");

        return self::SUCCESS;
    }
}
