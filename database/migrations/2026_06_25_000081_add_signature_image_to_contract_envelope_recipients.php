<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL INFOSHEET · FASE 3.3 — RETROFIT del sobre con la FIRMA AUTÓGRAFA (DocuSign). Cada destinatario
 * firma dibujando/escribiendo su firma; la imagen PNG (data URL base64) se guarda en la columna
 * `signature_image` de SU FILA (el acto de aceptación congelado). Como es una columna del propio
 * documento firmado, el hash del sello (HasDigitalSignatures → digital_signatures) la CUBRE: la
 * firma queda "verificada e íntegra". NULLABLE y ADITIVO (los sobres viejos siguen válidos sin ella).
 *
 * MEDIUMTEXT como users.adopted_signature / infosheet_authorizations.signature_image (un PNG base64
 * rebasa TEXT ~64 KB). Va después de `sign_method`.
 */
class AddSignatureImageToContractEnvelopeRecipients extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('contract_envelope_recipients', 'signature_image')) {
            DB::statement("ALTER TABLE `contract_envelope_recipients` ADD COLUMN `signature_image` MEDIUMTEXT NULL AFTER `sign_method`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('contract_envelope_recipients', 'signature_image')) {
            DB::statement("ALTER TABLE `contract_envelope_recipients` DROP COLUMN `signature_image`");
        }
    }
}
