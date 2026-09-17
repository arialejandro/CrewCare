<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · inc.3c-1 — rúbrica del contratado en CADA página (como la "Inicial" del corpus).
 *
 * `initials_each_page` en la plantilla: cuando está ON, el documento estampa una rúbrica pequeña
 * (copia reducida de la firma del contratado) en cada hoja — repetida vía `position:fixed` en el PDF
 * y dibujada por hoja en la vista paginada.
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-14-contract-template-initials.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && ! Schema::hasColumn('contract_templates', 'initials_each_page')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `initials_each_page` TINYINT(1) NOT NULL DEFAULT 0 AFTER `page_size`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_templates', 'initials_each_page')) {
            DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `initials_each_page`");
        }
    }
};
