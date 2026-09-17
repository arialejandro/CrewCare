<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * daily_logs.action_taken varchar(255) → TEXT. Un texto largo desde el celular (acción correctiva)
 * daba SQLSTATE 22001 "Data too long" y un 500 que perdía lo capturado (DailyReportController::storeLog).
 *
 * SEGURO SOBRE PROD CON SELLOS: es un WIDENING (no cambia NINGÚN valor almacenado), y además
 * `daily_logs.action_taken` NO participa en ningún hash — `DailyLog` no usa HasDigitalSignatures y el
 * payload del sello del DSR (`DailyReport::canonicalSignaturePayload`) es `attributesToArray()` de la fila
 * de `daily_reports`, sin la relación `logs()`. Por tanto ningún sello se mueve. (`description` ya es TEXT.)
 *
 * Idempotente: sólo altera si la columna aún no es TEXT. `down()` NO revierte (estrechar podría truncar
 * datos ya guardados >255).
 *
 * ⚠ Aplicar con `php artisan migrate --path=database/migrations/2026_09_08_000002_action_taken_to_text_on_daily_logs.php`.
 *   NUNCA migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_logs') || ! Schema::hasColumn('daily_logs', 'action_taken')) {
            return;
        }
        $col = DB::selectOne(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_logs' AND COLUMN_NAME = 'action_taken'"
        );
        if ($col && strtolower((string) $col->DATA_TYPE) === 'text') {
            return; // ya está TEXT
        }
        DB::statement('ALTER TABLE `daily_logs` MODIFY `action_taken` TEXT NULL');
    }

    public function down(): void
    {
        // Estrechar de TEXT a varchar(255) podría truncar datos capturados > 255 → no se revierte.
    }
};
