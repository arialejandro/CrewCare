<?php

namespace App\Console\Commands;

use App\Support\TsaStamper;
use Illuminate\Console\Command;

/**
 * tsa:stamp — motor de fondo del sello de tiempo RFC 3161. Con la cola en `sync` (sin worker)
 * este comando ES el que timbra: drena los sellos sin token contra freeTSA, best-effort. Nunca
 * bloquea el sellado (eso ya pasó); si freeTSA no responde, reintenta en la siguiente corrida.
 * Agendado cada 5 minutos en App\Console\Kernel. Inofensivo si no hay nada pendiente.
 */
class StampSignatureTimestamps extends Command
{
    protected $signature = 'tsa:stamp {--limit=40 : Máximo de firmas por corrida}';
    protected $description = 'Timbra (RFC 3161) los sellos digitales que aún no tienen sello de tiempo TSA.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $res   = TsaStamper::drain($limit);
        $this->info("TSA: sembradas {$res['seeded']}, procesadas {$res['processed']}, timbradas {$res['stamped']}, fallidas {$res['failed']}.");
        return self::SUCCESS;
    }
}
