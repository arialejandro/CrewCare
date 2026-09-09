<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · PASO 2A — `shoot_days` gana `unit_id`: cada unidad tiene su propio conjunto de días.
 *
 * `unit_id` NULLABLE. Lo ya marcado queda en NULL = de la unidad PRINCIPAL. La unicidad se AMPLÍA de
 * (production_id, shoot_date) a incluir la unidad: una misma fecha puede ser día de rodaje de la unidad
 * principal Y de una 2ª unidad. Como `shoot_days` está vacía en la base real, reindexar es seguro.
 * (La unicidad efectiva por (pid, unit, fecha) la garantiza updateOrCreate del servicio; el índice
 * compuesto es para consulta — MySQL trata los NULL como distintos, así que no sirve de UNIQUE con
 * unit_id nullable.)
 *
 * ⚠ Raw + guardado por índice. `php artisan migrate --path=database/migrations/2026_09_06_000003_add_unit_id_to_shoot_days.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shoot_days')) {
            return;
        }
        if (! Schema::hasColumn('shoot_days', 'unit_id')) {
            DB::statement('ALTER TABLE `shoot_days` ADD COLUMN `unit_id` BIGINT UNSIGNED NULL AFTER `production_id`');
            DB::statement('ALTER TABLE `shoot_days` ADD INDEX `shoot_days_unit_id_index` (`unit_id`)');
        }
        // Quita la unicidad vieja (production_id, shoot_date) — impediría dos unidades el mismo día.
        if ($this->indexExists('shoot_days_production_id_shoot_date_unique')) {
            DB::statement('ALTER TABLE `shoot_days` DROP INDEX `shoot_days_production_id_shoot_date_unique`');
        }
        // Índice compuesto de consulta (production, unit, fecha).
        if (! $this->indexExists('shoot_days_pid_unit_date_idx')) {
            DB::statement('ALTER TABLE `shoot_days` ADD INDEX `shoot_days_pid_unit_date_idx` (`production_id`,`unit_id`,`shoot_date`)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shoot_days')) {
            return;
        }
        if ($this->indexExists('shoot_days_pid_unit_date_idx')) {
            DB::statement('ALTER TABLE `shoot_days` DROP INDEX `shoot_days_pid_unit_date_idx`');
        }
        if (Schema::hasColumn('shoot_days', 'unit_id')) {
            DB::statement('ALTER TABLE `shoot_days` DROP COLUMN `unit_id`');
        }
    }

    private function indexExists(string $name): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['shoot_days', $name]
        );
    }
};
