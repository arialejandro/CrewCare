<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BASE ÚNICA DE QUIEN COBRA — EXTIENDE el ledger `external_authorizations` (no se
 * duplica): + document_type_id (clave del catálogo) · + issued_at (fecha de emisión,
 * para calcular caducidad) · + result_status (estado requerido: 32-D positiva/negativa).
 * Corre después de 2026_06_25_000017 (create). Espejo de owner-apply/2026-08-13-payee-base.sql.
 */
class ExtendExternalAuthorizationsForPayees extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE `external_authorizations`
  ADD COLUMN `document_type_id` bigint(20) unsigned DEFAULT NULL AFTER `document_type`,
  ADD COLUMN `issued_at` date DEFAULT NULL AFTER `valid_until`,
  ADD COLUMN `result_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `status`,
  ADD KEY `external_auth_doctype_idx` (`document_type_id`)
SQL);
    }

    public function down()
    {
        Schema::table('external_authorizations', function ($table) {
            $table->dropColumn(['document_type_id', 'issued_at', 'result_status']);
        });
    }
}
