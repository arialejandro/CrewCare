<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLANTILLAS unificadas — una plantilla puede ser el CONTRATO principal o un ANEXO.
 *
 *   · category    'contrato' (default) | 'anexo'.
 *   · sort_order  orden de los anexos (default 0).
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-16-contract-template-category.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_templates')) {
            return;
        }
        if (! Schema::hasColumn('contract_templates', 'category')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `category` VARCHAR(10) NOT NULL DEFAULT 'contrato' AFTER `applies_to`");
        }
        if (! Schema::hasColumn('contract_templates', 'sort_order')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `category`");
        }
    }

    public function down(): void
    {
        foreach (['sort_order', 'category'] as $col) {
            if (Schema::hasColumn('contract_templates', $col)) {
                DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `{$col}`");
            }
        }
    }
};
