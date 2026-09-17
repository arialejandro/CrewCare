<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * clinical-log:prune — retención de la bitácora de lectura clínica: HASTA 3 años. Borra lo más
 * viejo. (Al concluir el contrato el contenido baja del VPS y queda solo en la nube — eso es
 * operativo; esto sólo cuida que la bitácora no crezca sin fin.) Agendado mensual.
 */
class PruneClinicalReadLogs extends Command
{
    protected $signature = 'clinical-log:prune {--years=3 : Antigüedad máxima a conservar}';
    protected $description = 'Borra las lecturas clínicas más viejas que la retención (3 años por defecto).';

    public function handle(): int
    {
        if (! Schema::hasTable('clinical_read_logs')) {
            $this->info('clinical_read_logs no existe; nada que podar.');
            return self::SUCCESS;
        }
        $years  = max(1, (int) $this->option('years'));
        $cutoff = now()->subYears($years);
        $n = DB::table('clinical_read_logs')->where('opened_at', '<', $cutoff)->delete();
        $this->info("Bitácora clínica: podadas {$n} lecturas anteriores a {$cutoff->toDateString()}.");
        return self::SUCCESS;
    }
}
