<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PASO 5 · quien cobra — PUENTE proveedor de ambulancias → payee. El proveedor NO se destruye
 * (las actas selladas referencian `ambulance_providers.id` y congelan `provider_name`): se le
 * agrega `payee_id` para ligarlo a su identidad en la base única. Nace NULL; el backfill y el
 * alta de proveedor lo llenan. Los ids de proveedor NO cambian → los sellos siguen válidos.
 */
class AddPayeeIdToAmbulanceProviders extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('ambulance_providers', 'payee_id')) {
            DB::statement("ALTER TABLE `ambulance_providers`
                ADD COLUMN `payee_id` BIGINT UNSIGNED NULL AFTER `id`,
                ADD KEY `ambulance_providers_payee_idx` (`payee_id`)");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('ambulance_providers', 'payee_id')) {
            DB::statement("ALTER TABLE `ambulance_providers` DROP COLUMN `payee_id`");
        }
    }
}
