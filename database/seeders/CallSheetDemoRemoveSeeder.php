<?php

namespace Database\Seeders;

use App\Support\CurrentProduction;
use Database\Seeders\Concerns\SoloEnLocal;
use Illuminate\Database\Seeder;

/**
 * CallSheetDemoRemoveSeeder — quita TODO lo que sembró {@see CallSheetDemoSeeder} (crew de demo del
 * llamado + config del día + offsets + notas), acotado a la producción vigente. Reversible.
 *
 * Uso:  php artisan db:seed --class=CallSheetDemoRemoveSeeder
 */
class CallSheetDemoRemoveSeeder extends Seeder
{
    use SoloEnLocal;

    public function run()
    {
        $this->exigirEntornoLocal('crew de demostración del llamado');
        CallSheetDemoSeeder::purge(CurrentProduction::id());
        CurrentProduction::forget();

        if ($this->command) {
            $this->command->info('CallSheetDemoRemoveSeeder: crew de demo del llamado eliminado.');
        }
    }
}
