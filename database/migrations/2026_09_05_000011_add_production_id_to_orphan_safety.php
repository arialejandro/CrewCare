<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · PASO 1 — SIEMBRA de `production_id` en las TRES safety HUÉRFANAS.
 *
 * `hazardnotifications`, `unsafeconds` e `injury_reports` NO tenían `production_id`: hoy se aíslan SOLO
 * por autor (created_by_id). Se siembra la columna en la MISMA pasada que `unit_id` para no re-arriesgar
 * sus 16 sellos una segunda vez. NULLABLE, SIN backfill, SIN FK. En null para todo lo existente → fuera
 * del hash (cada modelo la excluye cuando es null vía NULLABLE_HASH_EXCLUDES) → los sellos NO cambian.
 *
 * ⚠ Esto NO cambia cómo se filtran: siguen aislándose por autor. Solo se siembra la columna; el filtro
 *   por producción/unidad se cablea después, con cuidado y por separado (Paso 2).
 *
 * ⚠ Aplicar con `php artisan migrate --path=database/migrations/2026_09_05_000011_add_production_id_to_orphan_safety.php`.
 */
return new class extends Migration
{
    private array $tables = ['hazardnotifications', 'unsafeconds', 'injury_reports'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t) || Schema::hasColumn($t, 'production_id')) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('production_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, 'production_id')) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn('production_id');
            });
        }
    }
};
