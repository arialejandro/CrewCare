<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO A — FECHAS DE TRABAJO (tabla hija de `payee_contracts`).
 *
 *  - Cada renglón = una FECHA + su FASE (soft_prep | prep | shoot | wrap). La fase importa
 *    porque la tarifa de viáticos cambia según cuál sea.
 *  - Fechas NO CONTIGUAS: un one day player trabaja los días 1, 37 y 123 → varios renglones.
 *  - Diseñada para que agregar `unit_id` después sea ADITIVO (una columna nullable más).
 *  - "Descansa" vs "no llamado" será, si hace falta, un ESTADO en el renglón — NO se construye
 *    ahora. Hoy: contrato activo + fecha aquí = LLAMADO; activo sin fecha = NO LLAMADO.
 *
 * FK-soft (sin constraint dura, como el resto). Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreatePayeeContractWorkDatesTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `payee_contract_work_dates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_contract_id` bigint(20) unsigned NOT NULL,
  `work_date` date NOT NULL,
  `phase` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shoot',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pcwd_contract_date_uq` (`payee_contract_id`, `work_date`),
  KEY `pcwd_contract_idx` (`payee_contract_id`),
  KEY `pcwd_date_idx` (`work_date`),
  KEY `pcwd_phase_idx` (`phase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payee_contract_work_dates');
    }
}
