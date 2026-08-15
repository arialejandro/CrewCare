<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · tamaño de página del documento (Carta / Oficio-Legal / A4).
 *
 * El corpus real es CARTA (salvo Spectrum = Legal), así que el tamaño lo elige la plantilla; por
 * defecto 'carta'. Alimenta el `@page` del PDF y la "hoja" del editor Word-lite.
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-14-contract-template-page-size.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'page_size')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `page_size` VARCHAR(16) NOT NULL DEFAULT 'carta' AFTER `bilingual`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_templates', 'page_size')) {
            DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `page_size`");
        }
    }
};
