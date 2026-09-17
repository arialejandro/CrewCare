<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 §0 — SUSTITUCIÓN de puntos (mismo mecanismo que el catálogo de
 * herramientas, `tool_check_points.supersedes`).
 *
 * `supersedes` = code del punto que ESTE reemplaza cuando ambos aplican (dice lo mismo con más
 * precisión). Con esto REM-005 (calzas del remolcado, critical) SUSTITUYE a CAR-004 (cuñas de la
 * caja, minor) en un vehículo que es `has_cargo_box` E `is_towed` (p. ej. camper de vestuario),
 * para no pedir calzas dos veces. Un pickup con caja sin remolque sigue con CAR-004; un remolcado
 * sin caja sigue con REM-005. Las actas ya selladas usan su `checklist_snapshot` congelado: no cambian.
 */
class AddSupersedesToVehicleCheckPoints extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('vehicle_check_points', 'supersedes')) {
            DB::statement("ALTER TABLE `vehicle_check_points` ADD COLUMN `supersedes` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL AFTER `applies_when`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('vehicle_check_points', 'supersedes')) {
            DB::statement("ALTER TABLE `vehicle_check_points` DROP COLUMN `supersedes`");
        }
    }
}
