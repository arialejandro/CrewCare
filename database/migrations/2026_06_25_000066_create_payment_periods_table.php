<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VENTANA DE RECEPCIÓN POR PERIODO DE PAGO. La UNIDAD es el PERIODO (no el documento):
 * pertenece a una producción y declara frecuencia + ventana de recepción (apertura/cierre)
 * + a quién le toca (por frecuencia heredada del contrato). El documento cuelga del periodo
 * por `external_authorizations.payment_period_id` (delta hermano 000067).
 *
 *  - frequency: weekly | biweekly | day_player  (vocabulario de PayeeContract::FREQ_*).
 *  - DAY PLAYER no es excepción: es OTRO TIPO de periodo → no tiene semana, tiene `worked_on`
 *    (el día que trabajó) y se captura A MANO para un `payee_id` concreto (aún no hay roster).
 *  - La ventana ABRE y CIERRA pero NO RECHAZA: un documento fuera de ventana se recibe igual,
 *    marcado (`received_out_of_window`), para no perder la centralización.
 *  - status: open | closed. `reopened_*` es rastro de reapertura (decisión de política aparte).
 *
 * FK-soft (documento operativo, sin constraint dura). Idempotente (CREATE TABLE IF NOT EXISTS).
 */
class CreatePaymentPeriodsTable extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned NOT NULL,
  `frequency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opens_on` date NOT NULL,
  `closes_on` date NOT NULL,
  `worked_on` date DEFAULT NULL,
  `payee_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `closed_by_id` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pp_prod_idx` (`production_id`),
  KEY `pp_freq_idx` (`frequency`),
  KEY `pp_status_idx` (`status`),
  KEY `pp_payee_idx` (`payee_id`),
  KEY `pp_window_idx` (`opens_on`, `closes_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('payment_periods');
    }
}
