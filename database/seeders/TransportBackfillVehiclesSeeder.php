<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Support\VehicleChecklist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PROMUEVE payee_contracts.asset_ref (JSON {make,model,plate,year}) a la entidad `vehicles`.
 *
 * LEGACY / una sola vez: sube el gancho a entidad de primera clase y liga el contrato con
 * `payee_contracts.vehicle_id`. NO se encadena en el DatabaseSeeder de fábrica (una instancia
 * nueva no tiene contratos). Idempotente: solo toca contratos con asset_ref y sin vehicle_id.
 *
 * Correr a mano: php artisan db:seed --class=TransportBackfillVehiclesSeeder
 */
class TransportBackfillVehiclesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('payee_contracts') || ! Schema::hasTable('vehicles')
            || ! Schema::hasColumn('payee_contracts', 'asset_ref')
            || ! Schema::hasColumn('payee_contracts', 'vehicle_id')) {
            $this->command->warn('TransportBackfillVehiclesSeeder: faltan tablas/columnas (aplica los deltas de Transportación). Nada que hacer.');
            return;
        }

        $hasPayeeId = Schema::hasColumn('payee_contracts', 'payee_id');
        $cols = array_filter(['id', $hasPayeeId ? 'payee_id' : null, 'asset_ref']);

        $rows = DB::table('payee_contracts')
            ->whereNotNull('asset_ref')
            ->whereNull('vehicle_id')
            ->get($cols);

        $n = 0;
        foreach ($rows as $r) {
            $ref = json_decode((string) $r->asset_ref, true);
            if (! is_array($ref) || empty(array_filter($ref))) {
                continue;
            }

            $vehicle = Vehicle::create([
                'make'           => $ref['make'] ?? null,
                'model'          => $ref['model'] ?? null,
                'plate'          => $ref['plate'] ?? null,
                'year'           => isset($ref['year']) ? (int) $ref['year'] : null,
                'owner_kind'     => Vehicle::OWNER_PROVIDER,
                'owner_payee_id' => $hasPayeeId ? ($r->payee_id ?? null) : null,
                'attr_values'    => VehicleChecklist::normalizeAttributes([]),
                'is_active'      => 1,
            ]);

            DB::table('payee_contracts')->where('id', $r->id)->update(['vehicle_id' => $vehicle->id]);
            $n++;
        }

        $this->command->info("TransportBackfillVehiclesSeeder: {$n} vehículo(s) promovido(s) desde payee_contracts.asset_ref.");
    }
}
