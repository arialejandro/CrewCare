<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · PDF FILLABLE — segundo modo de autoría del contrato.
 *
 * En vez de REDACTAR el body HTML, la producción sube el PDF ya hecho por su área legal y COLOCA las
 * etiquetas (firmas + datos) sobre las páginas, estilo DocuSign. Se estampa ENCIMA conservando el
 * texto (FPDI), no se aplana a imagen. CrewCare no redacta: solo coloca campos y estampa.
 *
 *   · source_kind        'html' (default) | 'pdf'.
 *   · pdf_path           ruta del PDF original en storage/app (solo source='pdf').
 *   · pdf_original_name  nombre original del archivo, para mostrarlo en el editor.
 *   · field_map          JSON [{page, x_pct, y_pct, w_pct, type:'data'|'sign', key}].
 *
 * Aditivo e idempotente. Gemelo owner-apply: 2026-08-16-contract-template-pdf-source.sql.
 * ⚠ NO correr `migrate` en `crewcare` (usar owner-apply); validado en crewcare_test.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_templates')) {
            return;
        }
        if (! Schema::hasColumn('contract_templates', 'source_kind')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `source_kind` VARCHAR(4) NOT NULL DEFAULT 'html' AFTER `body`");
        }
        if (! Schema::hasColumn('contract_templates', 'pdf_path')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `pdf_path` VARCHAR(255) NULL DEFAULT NULL AFTER `source_kind`");
        }
        if (! Schema::hasColumn('contract_templates', 'pdf_original_name')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `pdf_original_name` VARCHAR(191) NULL DEFAULT NULL AFTER `pdf_path`");
        }
        if (! Schema::hasColumn('contract_templates', 'field_map')) {
            DB::statement("ALTER TABLE `contract_templates` ADD COLUMN `field_map` JSON NULL DEFAULT NULL AFTER `pdf_original_name`");
        }
    }

    public function down(): void
    {
        foreach (['field_map', 'pdf_original_name', 'pdf_path', 'source_kind'] as $col) {
            if (Schema::hasColumn('contract_templates', $col)) {
                DB::statement("ALTER TABLE `contract_templates` DROP COLUMN `{$col}`");
            }
        }
    }
};
