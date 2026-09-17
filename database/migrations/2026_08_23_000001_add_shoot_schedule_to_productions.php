<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PARTE A · CALENDARIO DE RODAJE (2026-08-23). La producción declara su duración planeada:
 * `shoot_weeks` (semanas) × `shoot_days_per_week` (5 o 6) = TOTAL de días de rodaje (la M de
 * "Día N de M"). De start_date + eso se deriva el wrap estimado. `end_date` (ya existente) sigue
 * siendo el wrap AJUSTABLE. NULLABLE y ADITIVO; `productions` no se sella → no toca ningún hash.
 */
class AddShootScheduleToProductions extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('productions', 'shoot_weeks')) {
            DB::statement("ALTER TABLE `productions` ADD COLUMN `shoot_weeks` INT NULL AFTER `end_date`");
        }
        if (! Schema::hasColumn('productions', 'shoot_days_per_week')) {
            DB::statement("ALTER TABLE `productions` ADD COLUMN `shoot_days_per_week` TINYINT NULL AFTER `shoot_weeks`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('productions', 'shoot_days_per_week')) {
            DB::statement("ALTER TABLE `productions` DROP COLUMN `shoot_days_per_week`");
        }
        if (Schema::hasColumn('productions', 'shoot_weeks')) {
            DB::statement("ALTER TABLE `productions` DROP COLUMN `shoot_weeks`");
        }
    }
}
