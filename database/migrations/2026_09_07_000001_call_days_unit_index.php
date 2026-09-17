<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · 2b — `call_days` admite un llamado POR UNIDAD el mismo día.
 *
 * La columna `unit_id` ya existía (P1). Aquí se QUITA la unicidad vieja `(production_id, call_date)`, que
 * impedía que la principal Y una 2ª unidad tuvieran su propio call_day la misma fecha, y se pone un índice
 * compuesto de consulta `(production_id, unit_id, call_date)`. Mismo criterio que shoot_days en 2A: MySQL
 * trata los NULL como distintos, así que un UNIQUE con unit_id nullable no sirve; la unicidad efectiva por
 * (pid, unidad, fecha) la garantiza el `firstOrCreate` de CallSheetController::resolveCallDay (única vía de
 * creación de call_days). Con una sola unidad, la llave (production_id, call_date) sigue dedup-eando igual.
 *
 * ⚠ Raw + guardado por índice. `php artisan migrate --path=database/migrations/2026_09_07_000001_call_days_unit_index.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('call_days')) {
            return;
        }
        // Quita la unicidad vieja (production_id, call_date) — impediría dos unidades el mismo día. El
        // nombre difiere entre entornos: la base fresca usa el de Laravel; la base real la nombró
        // `call_days_prod_date`. Se cae cualquiera que exista.
        foreach (['call_days_production_id_call_date_unique', 'call_days_prod_date'] as $old) {
            if ($this->indexExists($old)) {
                DB::statement("ALTER TABLE `call_days` DROP INDEX `{$old}`");
            }
        }
        // Índice compuesto de consulta (production, unit, fecha).
        if (! $this->indexExists('call_days_pid_unit_date_idx')) {
            DB::statement('ALTER TABLE `call_days` ADD INDEX `call_days_pid_unit_date_idx` (`production_id`,`unit_id`,`call_date`)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_days')) {
            return;
        }
        if ($this->indexExists('call_days_pid_unit_date_idx')) {
            DB::statement('ALTER TABLE `call_days` DROP INDEX `call_days_pid_unit_date_idx`');
        }
        // Restaura la unicidad vieja SÓLO si los datos lo permiten (no la forzamos: podría fallar si ya hay
        // dos unidades el mismo día). Se deja como estaba antes: intento suave.
        if (! $this->indexExists('call_days_production_id_call_date_unique')) {
            try {
                DB::statement('ALTER TABLE `call_days` ADD UNIQUE `call_days_production_id_call_date_unique` (`production_id`,`call_date`)');
            } catch (\Throwable $e) {
                // Con datos de más de una unidad el mismo día, la unicidad vieja ya no aplica: se omite.
            }
        }
    }

    private function indexExists(string $name): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['call_days', $name]
        );
    }
};
