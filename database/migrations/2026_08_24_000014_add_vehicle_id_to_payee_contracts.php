<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 1 — PUENTE del contrato a la entidad Vehículo.
 *
 * `payee_contracts.asset_ref` (JSON {make,model,plate,year}) fue el gancho; ahora el contrato
 * puede LIGAR a un `vehicles.id` reutilizable. NULLABLE y ADITIVO; `payee_contracts` no se
 * sella → no toca ningún hash. `asset_ref` se conserva intacto (fuente del backfill).
 */
class AddVehicleIdToPayeeContracts extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('payee_contracts', 'vehicle_id')) {
            DB::statement("ALTER TABLE `payee_contracts` ADD COLUMN `vehicle_id` BIGINT UNSIGNED NULL AFTER `asset_ref`, ADD KEY `payee_contracts_vehicle_idx` (`vehicle_id`)");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('payee_contracts', 'vehicle_id')) {
            DB::statement('ALTER TABLE `payee_contracts` DROP COLUMN `vehicle_id`');
        }
    }
}
