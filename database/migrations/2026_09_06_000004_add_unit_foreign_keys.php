<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · PASO 2A — FK de los `unit_id` sembrados en el Paso 1 (+ shoot_days) a `units`.
 *
 * ON DELETE RESTRICT: una unidad con documentos NO se puede borrar (la baja es DESACTIVACIÓN). Esto
 * PROTEGE el hash: un ON DELETE SET NULL pondría en null un unit_id SELLADO y lo marcaría ALTERADO.
 * Todos los unit_id existentes son null → la FK se agrega sin fricción. Guardado por FK (idempotente).
 *
 * ⚠ `php artisan migrate --path=database/migrations/2026_09_06_000004_add_unit_foreign_keys.php`.
 */
return new class extends Migration
{
    /** 12 selladas + 2 raíces operativas (Paso 1) + shoot_days (Paso 2A). */
    private array $tables = [
        'daily_reports', 'scouting_reports', 'unsafeconds', 'hazardnotifications', 'injury_reports',
        'risk_maps', 'medevac_posters', 'tool_inspections', 'ambulance_inspections',
        'emergency_action_plans', 'issued_permits', 'vehicle_inspections',
        'call_days', 'transport_orders',
        'shoot_days',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            return;
        }
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'unit_id') || $this->fkExists($t)) {
                continue;
            }
            DB::statement("ALTER TABLE `{$t}` ADD CONSTRAINT `fk_{$t}_unit` FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasTable($t) && $this->fkExists($t)) {
                DB::statement("ALTER TABLE `{$t}` DROP FOREIGN KEY `fk_{$t}_unit`");
            }
        }
    }

    private function fkExists(string $table): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'unit_id'
               AND REFERENCED_TABLE_NAME = 'units' LIMIT 1",
            [$table]
        );
    }
};
