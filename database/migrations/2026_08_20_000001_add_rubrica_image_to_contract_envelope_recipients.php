<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIRMA vs RÚBRICA — dos marcas DISTINTAS (DocuSign). La firma completa va en `signature_image`; la
 * RÚBRICA (marca al margen: iniciales, una variante, o la propia firma — la elige cada persona) va en
 * `rubrica_image`. Las etiquetas de rúbrica estampan ESTA, no la firma. NULLABLE y ADITIVO; queda
 * FUERA del sello (ver $signatureExcludes) para no alterar sobres ya sellados.
 */
class AddRubricaImageToContractEnvelopeRecipients extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('contract_envelope_recipients', 'rubrica_image')) {
            DB::statement("ALTER TABLE `contract_envelope_recipients` ADD COLUMN `rubrica_image` MEDIUMTEXT NULL AFTER `signature_image`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('contract_envelope_recipients', 'rubrica_image')) {
            DB::statement("ALTER TABLE `contract_envelope_recipients` DROP COLUMN `rubrica_image`");
        }
    }
}
