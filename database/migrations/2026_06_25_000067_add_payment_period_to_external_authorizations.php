<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VENTANA DE RECEPCIÓN — el documento cuelga del PERIODO además del holder (PAYEE/contrato).
 * Dos columnas ADITIVAS y NULLABLE sobre el ledger polimórfico `external_authorizations`:
 *
 *  - `payment_period_id`      → el periodo de pago contra el que se recibió (null = sin periodo).
 *  - `received_out_of_window` → recibido pero FUERA de la ventana (marcado, nunca rechazado).
 *
 * Estas filas NO se sellan (los sellos viven en las actas con firma, otra tabla) → agregar
 * columnas aquí no toca ningún hash. Se estampan best-effort al capturar (IntakeController);
 * si fallan, la captura no se rompe y el tablero igual deriva el estado con PayeePackage.
 */
class AddPaymentPeriodToExternalAuthorizations extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('external_authorizations', 'payment_period_id')) {
            DB::statement("ALTER TABLE `external_authorizations`
                ADD COLUMN `payment_period_id` BIGINT UNSIGNED NULL AFTER `document_type_id`,
                ADD KEY `ext_auth_period_idx` (`payment_period_id`)");
        }
        if (! Schema::hasColumn('external_authorizations', 'received_out_of_window')) {
            DB::statement("ALTER TABLE `external_authorizations`
                ADD COLUMN `received_out_of_window` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_period_id`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('external_authorizations', 'received_out_of_window')) {
            DB::statement("ALTER TABLE `external_authorizations` DROP COLUMN `received_out_of_window`");
        }
        if (Schema::hasColumn('external_authorizations', 'payment_period_id')) {
            DB::statement("ALTER TABLE `external_authorizations` DROP COLUMN `payment_period_id`");
        }
    }
}
