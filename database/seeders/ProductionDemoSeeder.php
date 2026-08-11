<?php

namespace Database\Seeders;

use App\Models\Production;
use Illuminate\Database\Seeder;

/**
 * Crea EXACTAMENTE una fila en `productions`: la produccion que hospeda esta
 * instancia (modelo "una instancia = una produccion"). La app la necesita
 * estructuralmente (CurrentProduction es la fuente unica). Idempotente.
 *
 * Nombre/codigo por variables de entorno para un install de cliente:
 *   INSTALL_PRODUCTION_NAME (default 'Producción Demo' — el cliente lo sobreescribe)
 *   INSTALL_PRODUCTION_CODE (default 'DEMO')
 * El default se conserva para NO romper el flujo demo local ni los seeders legacy
 * que buscan 'Producción Demo' por nombre.
 */
class ProductionDemoSeeder extends Seeder
{
    public function run()
    {
        $name = env('INSTALL_PRODUCTION_NAME', 'Producción Demo');
        $code = env('INSTALL_PRODUCTION_CODE', 'DEMO');

        $production = Production::firstOrCreate(
            ['name' => $name],
            [
                'code' => $code,
                'status' => 'active',
                'active' => true,
            ]
        );

        $this->command->info('Production ready: '.$production->name.' (id '.$production->id.')');
    }
}
