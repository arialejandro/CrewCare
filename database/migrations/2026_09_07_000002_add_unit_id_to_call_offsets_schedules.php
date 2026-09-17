<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · 2b — `call_dept_offsets` y `call_person_schedules` ganan `unit_id`: cada unidad tiene sus
 * PROPIOS offsets de departamento y horarios por persona. Si se comparten, cambiar el offset de cámara en
 * una unidad le cambia la hora a la otra sin que nadie se entere — justo lo que la separación existe para
 * impedir.
 *
 *  - `unit_id` NULLABLE. Lo existente queda en NULL = de la unidad PRINCIPAL. NINGUNA de las dos tablas se
 *    sella (verificado: sin HasDigitalSignatures) → no hay hash que romper.
 *  - Se QUITA la unicidad vieja (production_id, department_id) / (production_id, user_id) —impedía que dos
 *    unidades tuvieran valores distintos para el mismo depto/persona— y se pone un índice compuesto con la
 *    unidad. Nombres distintos por entorno (Laravel default vs `..._prod_dept`/`..._prod_user` en la real):
 *    se cae cualquiera. Criterio 2A: MySQL trata los NULL como distintos, así que un UNIQUE con unit_id
 *    nullable no sirve; la unicidad efectiva por (pid, unidad, depto/persona) la da el updateOrCreate del
 *    controlador (única vía de escritura).
 *  - `call_person_schedules` SIGUE SIN FECHA: es el estado del único día abierto (sólo se planea el día
 *    siguiente). No se le agrega fecha.
 *
 * ⚠ Raw + guardado por índice/columna. `php artisan migrate --path=database/migrations/2026_09_07_000002_add_unit_id_to_call_offsets_schedules.php`.
 */
return new class extends Migration
{
    /** tabla => [columna hija de la llave, nombres candidatos de la unique vieja, nombre del índice nuevo] */
    private array $spec = [
        'call_dept_offsets' => [
            'child'   => 'department_id',
            'olds'    => ['call_dept_offsets_production_id_department_id_unique', 'call_dept_offsets_prod_dept'],
            'new_idx' => 'call_dept_offsets_pid_unit_dept_idx',
        ],
        'call_person_schedules' => [
            'child'   => 'user_id',
            'olds'    => ['call_person_schedules_production_id_user_id_unique', 'call_person_prod_user'],
            'new_idx' => 'call_person_schedules_pid_unit_user_idx',
        ],
    ];

    public function up(): void
    {
        foreach ($this->spec as $table => $s) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'unit_id')) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `unit_id` BIGINT UNSIGNED NULL AFTER `production_id`");
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$table}_unit_id_index` (`unit_id`)");
            }
            foreach ($s['olds'] as $old) {
                if ($this->indexExists($table, $old)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$old}`");
                }
            }
            if (! $this->indexExists($table, $s['new_idx'])) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$s['new_idx']}` (`production_id`,`unit_id`,`{$s['child']}`)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->spec as $table => $s) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($this->indexExists($table, $s['new_idx'])) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$s['new_idx']}`");
            }
            if (Schema::hasColumn($table, 'unit_id')) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `unit_id`");
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $name]
        );
    }
};
