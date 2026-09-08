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

        // 🔴 GUARDA: en PRODUCCIÓN el nombre/código se SELLAN en cada documento y no se pueden cambiar
        // después. Si quedaron en el default de demo (o en blanco), la instalación se NIEGA a correr —
        // es un ERROR, no un aviso, porque equivocarse aquí es irreversible. En local/otros entornos no
        // estorba (el default demo es legítimo ahí).
        if (app()->environment('production')) {
            $nameBad = trim((string) $name) === '' || trim((string) $name) === 'Producción Demo';
            $codeBad = trim((string) $code) === '' || strtoupper(trim((string) $code)) === 'DEMO';
            if ($nameBad || $codeBad) {
                throw new \RuntimeException(
                    "Instalación DETENIDA (APP_ENV=production): define INSTALL_PRODUCTION_NAME e "
                    . "INSTALL_PRODUCTION_CODE reales en .env antes de sembrar. Ahora: "
                    . "name=\"{$name}\", code=\"{$code}\". Esos valores se sellan en cada documento y NO "
                    . "se pueden cambiar después. Llena esas variables y vuelve a correr `php artisan db:seed`."
                );
            }
        }

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
