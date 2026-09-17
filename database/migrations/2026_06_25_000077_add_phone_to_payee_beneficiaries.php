<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL INFOSHEET · FASE 1 — teléfono del BENEFICIARIO mortis causa. Es un dato DISTINTO del
 * contacto de emergencia (`payees.emergency_contact_*`): cada uno vive en su propia tabla y no se
 * confunden. Cierra un hueco conocido — `payee_contracts.beneficiary_phone` se congela al emitir
 * pero el intake nunca capturaba el teléfono del beneficiario. NULLABLE y ADITIVO.
 */
class AddPhoneToPayeeBeneficiaries extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('payee_beneficiaries', 'phone')) {
            DB::statement("ALTER TABLE `payee_beneficiaries` ADD COLUMN `phone` VARCHAR(40) NULL AFTER `relationship`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('payee_beneficiaries', 'phone')) {
            DB::statement("ALTER TABLE `payee_beneficiaries` DROP COLUMN `phone`");
        }
    }
}
