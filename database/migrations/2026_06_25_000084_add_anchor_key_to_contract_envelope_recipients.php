<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRACT BUILDER · FASE 1c — enlaza cada DESTINATARIO del sobre con su ANCLA de firma de la
 * plantilla (`[[firma:CLAVE]]`). La clave se CONGELA al construir el sobre (contratado / dept_hod /
 * puesto:ID), igual que el resto de la ruta, para que el estampado de la plantilla sea inmune a
 * cambios posteriores de la configuración de firmantes.
 *
 * NULLABLE y ADITIVO (los sobres viejos siguen válidos sin ella). NO entra al hash del sello del
 * destinatario (está en $signatureExcludes): es metadato de ruta, no el acto de firma → los sobres
 * ya sellados no se vuelven "alterados". Va después de `cargo`.
 */
class AddAnchorKeyToContractEnvelopeRecipients extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('contract_envelope_recipients', 'anchor_key')) {
            DB::statement("ALTER TABLE `contract_envelope_recipients` ADD COLUMN `anchor_key` VARCHAR(64) NULL AFTER `cargo`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('contract_envelope_recipients', 'anchor_key')) {
            DB::statement("ALTER TABLE `contract_envelope_recipients` DROP COLUMN `anchor_key`");
        }
    }
}
