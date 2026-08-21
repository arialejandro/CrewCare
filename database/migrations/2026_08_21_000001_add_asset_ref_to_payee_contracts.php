<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REFERENCIA MÍNIMA DEL ACTIVO en un contrato de RENTA (marca/modelo/placa/año del vehículo o equipo).
 * Gancho para el módulo futuro de Transportación: hoy se captura aquí como JSON ligero y luego se
 * PROMUEVE a la entidad "Vehículo" (con auditorías + órdenes de transportación). NULLABLE y ADITIVO;
 * `payee_contracts` no se sella → no toca ningún hash.
 */
class AddAssetRefToPayeeContracts extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('payee_contracts', 'asset_ref')) {
            DB::statement("ALTER TABLE `payee_contracts` ADD COLUMN `asset_ref` TEXT NULL AFTER `title`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('payee_contracts', 'asset_ref')) {
            DB::statement("ALTER TABLE `payee_contracts` DROP COLUMN `asset_ref`");
        }
    }
}
