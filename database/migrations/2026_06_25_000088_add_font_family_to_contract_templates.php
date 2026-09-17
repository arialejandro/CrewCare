<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · tipografía base del contrato (serif / sans / monospace).
 *
 * Fuentes del SISTEMA (cero peso extra). Por defecto 'mono' (neutra, tipo máquina), cambiable por
 * plantilla. Alimenta el `@page`/body del PDF, la hoja del editor y el medidor del salto de página
 * (las tres superficies deben usar la MISMA para que el corte caiga al píxel).
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-15-contract-template-font-family.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'font_family')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `font_family` VARCHAR(16) NOT NULL DEFAULT 'mono' AFTER `page_size`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_templates', 'font_family')) {
            DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `font_family`");
        }
    }
};
