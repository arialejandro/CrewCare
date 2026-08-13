<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · CIERRE PASO 1 — el CONTRATO apunta a UNO de los regímenes de su
 * identidad (la identidad tiene N; el contrato, uno). Espejo de owner-apply.
 */
class AddFiscalRegimeToPayeeContracts extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE `payee_contracts`
  ADD COLUMN `fiscal_regime_id` bigint(20) unsigned DEFAULT NULL AFTER `payee_id`,
  ADD KEY `payee_contracts_regime_idx` (`fiscal_regime_id`)
SQL);
    }

    public function down()
    {
        Schema::table('payee_contracts', function ($table) {
            $table->dropColumn('fiscal_regime_id');
        });
    }
}
