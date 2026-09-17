<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIRMA AUTÓGRAFA · módulo base — la firma ADOPTADA del usuario, reutilizable (como en DocuSign:
 * se adopta una vez y se reúsa en cada firma; se puede volver a dibujar). PNG base64. NULLABLE y
 * ADITIVO. NO es el sello de integridad (ese vive en digital_signatures): esto es la plantilla
 * visual reusable. La firma APLICADA en un acto concreto se congela en la entidad firmada
 * (infosheet_authorizations.signature_image / contract_envelope_recipients.signature_image) y ahí
 * el hash del sello la cubre.
 */
class AddAdoptedSignatureToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'adopted_signature')) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `adopted_signature` MEDIUMTEXT NULL");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'adopted_signature')) {
            DB::statement("ALTER TABLE `users` DROP COLUMN `adopted_signature`");
        }
    }
}
