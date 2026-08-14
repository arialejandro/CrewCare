<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · incremento 1 — el FORMATO como dato del template.
 *
 * contract_templates += `architecture` (la FORMA del contrato: carátula tabla numerada /
 * ficha etiqueta:valor / declaraciones-primero) + `bilingual` (reservado para el 2-col del
 * incremento 2). El editor deja ELEGIR formato y el canvas carga el andamiaje real de esa forma.
 *
 * Aditivo e idempotente (guardas hasColumn). Gemelo owner-apply: 2026-08-14-contract-template-format.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test vía RefreshDatabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_templates')) {
            return;
        }
        if (! Schema::hasColumn('contract_templates', 'architecture')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `architecture` VARCHAR(32) NOT NULL DEFAULT 'caratula_numbered' AFTER `language`");
        }
        if (! Schema::hasColumn('contract_templates', 'bilingual')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `bilingual` TINYINT(1) NOT NULL DEFAULT 0 AFTER `architecture`");
        }
    }

    public function down(): void
    {
        foreach (['bilingual', 'architecture'] as $col) {
            if (Schema::hasColumn('contract_templates', $col)) {
                DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `{$col}`");
            }
        }
    }
};
