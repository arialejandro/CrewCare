<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · tamaño de la letra base del contrato (en pt).
 *
 * El corpus real usa letra chica (~10-11pt); 12pt se veía enorme, sobre todo en monospace (glifos más
 * anchos). Elegible por plantilla; default '11'. Alimenta el body del PDF, la hoja del editor y el
 * medidor del salto (las tres deben usar el MISMO pt para que el corte caiga al píxel).
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-15-contract-template-font-size.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'font_size')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `font_size` VARCHAR(8) NOT NULL DEFAULT '11' AFTER `font_family`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_templates', 'font_size')) {
            DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `font_size`");
        }
    }
};
