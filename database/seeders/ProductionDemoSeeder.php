<?php

namespace Database\Seeders;

use App\Models\Production;
use Illuminate\Database\Seeder;

/**
 * Creates exactly one demo production named "Producción Demo" to host the existing
 * test crew. Generic name on purpose (no PII from the real call sheet). Idempotent.
 */
class ProductionDemoSeeder extends Seeder
{
    public function run()
    {
        $production = Production::firstOrCreate(
            ['name' => 'Producción Demo'],
            [
                'code' => 'DEMO',
                'status' => 'active',
                'active' => true,
            ]
        );

        $this->command->info('Production ready: '.$production->name.' (id '.$production->id.')');
    }
}
