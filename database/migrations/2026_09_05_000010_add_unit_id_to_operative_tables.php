<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · PASO 1 — SIEMBRA de `unit_id` en las tablas OPERATIVAS.
 *
 * Columna NULLABLE, SIN default, SIN backfill y SIN FK (la tabla `units` es Paso 2). Todo lo existente
 * queda en null → fuera del hash de firma (cada modelo sellado excluye `unit_id` cuando es null vía su
 * const NULLABLE_HASH_EXCLUDES) → NINGÚN sello se mueve. Con valor (documentos de una 2ª unidad) la
 * columna SÍ entra al hash: la unidad queda sellada. El índice deja la columna lista para filtrar en
 * Paso 2; aquí NO se cablea ningún filtro.
 *
 * 12 tablas SELLADAS + 2 raíces operativas NO selladas (call_days = el llamado del día; transport_orders
 * = la orden de transporte). Guardado por tabla/columna (degrade-safe si una tabla no existe en destino).
 *
 * ⚠ Aplicar con `php artisan migrate --path=database/migrations/2026_09_05_000010_add_unit_id_to_operative_tables.php`.
 *   NUNCA migrate:fresh (borraría sellos y timbres).
 */
return new class extends Migration
{
    /** Tablas operativas que reciben `unit_id`. */
    private array $tables = [
        // 12 selladas
        'daily_reports', 'scouting_reports', 'unsafeconds', 'hazardnotifications', 'injury_reports',
        'risk_maps', 'medevac_posters', 'tool_inspections', 'ambulance_inspections',
        'emergency_action_plans', 'issued_permits', 'vehicle_inspections',
        // 2 raíces operativas no selladas
        'call_days', 'transport_orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t) || Schema::hasColumn($t, 'unit_id')) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('unit_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'unit_id')) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn('unit_id');
            });
        }
    }
};
