<?php

namespace Database\Seeders;

use App\Models\AmbulanceProvider;
use App\Models\ExternalAuthorization;
use App\Support\AmbulancePayeeLink;
use Illuminate\Database\Seeder;

/**
 * PASO 5 · backfill: liga los PROVEEDORES de ambulancias YA EXISTENTES a su identidad en la base
 * única (payee moral + contrato) y re-apunta sus documentos de operar. Idempotente: solo toca los
 * que aún no tienen `payee_id`. NO está en el chain de fábrica (un install nuevo no trae
 * proveedores; los nuevos nacen ligados desde el alta). Correr a mano DESPUÉS del SQL del puente:
 *     php artisan db:seed --class=MigrateAmbulanceProvidersToPayeesSeeder
 *
 * NO toca actas selladas (ids de proveedor intactos), ni el padrón, ni el catálogo.
 */
class MigrateAmbulanceProvidersToPayeesSeeder extends Seeder
{
    public function run(): void
    {
        $pending = AmbulanceProvider::whereNull('payee_id')->get();

        $migrated = 0;
        $docs = 0;

        foreach ($pending as $provider) {
            // Documentos que hoy cuelgan del proveedor (se re-apuntan al payee dentro de ensureFor).
            $docs += ExternalAuthorization::where('holder_type', $provider->getMorphClass())
                ->where('holder_id', $provider->id)
                ->count();

            AmbulancePayeeLink::ensureFor($provider);
            $migrated++;
        }

        $this->command->info(
            "MigrateAmbulanceProvidersToPayees: {$migrated} proveedor(es) ligado(s) a payee; "
            . "{$docs} documento(s) de operar re-apuntado(s). "
            . 'Padrón (ambulance_crew) y catálogo intactos.'
        );
    }
}
