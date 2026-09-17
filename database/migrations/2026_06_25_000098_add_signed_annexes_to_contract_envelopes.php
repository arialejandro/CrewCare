<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SOBRE multi-documento — anexos firmados junto al contrato principal.
 *
 * `signed_annexes` = JSON lista de {path,hash,bytes,name,template_id,engine}: cada anexo (plantilla
 * categoría 'anexo') estampado con datos + firmas al completar el sobre. Se excluye del sello del
 * sobre igual que `signed_document`.
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-16-envelope-signed-annexes.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_envelopes') && ! Schema::hasColumn('contract_envelopes', 'signed_annexes')) {
            DB::statement("ALTER TABLE `contract_envelopes` ADD COLUMN `signed_annexes` JSON NULL DEFAULT NULL AFTER `signed_document`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_envelopes', 'signed_annexes')) {
            DB::statement("ALTER TABLE `contract_envelopes` DROP COLUMN `signed_annexes`");
        }
    }
};
