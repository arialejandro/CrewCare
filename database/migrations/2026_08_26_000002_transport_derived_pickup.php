<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Pick up derivado — FASE 2: dos clases de corrida + derivado + discreto + modo +
 * asignación fija por puesto.
 *
 * `transport_order_runs` gana los DOS EJES y los insumos del derivado:
 *   - run_class (set|fuera, default 'fuera' → toda fila legada convive como manual).
 *   - run_type ya existía (normal|aeropuerto|aplicacion); son EJES INDEPENDIENTES.
 *   - pickup_point_id (origen del catálogo Fase 1) · dest_location_ref (scouting = locación del día,
 *     ref BLANDA por id) · travel_minutes (fallback tecleado cuando no hay par en la matriz) ·
 *     travel_adjust_minutes (± de transpo). `pickup_offset_minutes` (ya existía) pasa a usarse y se
 *     MATERIALIZA al emitir.
 *   - is_discreet (capa de privacidad; sólo apaga su aparición en la orden/PDF; el back sí la publica).
 * `transport_orders` gana pickup_mode (ligero|masivo).
 * + config por producción: `transport_position_config` (jefatura + "lleva pick up siempre") y
 *   `transport_vehicle_assignments` (puesto|persona × vehículo, asignación fija). Todo configurable,
 *   NADA en código. Aditivo, nada se sella.
 */
class TransportDerivedPickup extends Migration
{
    public function up()
    {
        Schema::table('transport_order_runs', function (Blueprint $t) {
            $t->string('run_class', 12)->default('fuera')->after('run_type');
            $t->unsignedBigInteger('pickup_point_id')->nullable()->after('run_class');
            $t->integer('travel_minutes')->nullable()->after('pickup_offset_minutes');
            $t->integer('travel_adjust_minutes')->default(0)->after('travel_minutes');
            $t->unsignedBigInteger('dest_location_ref')->nullable()->after('dest_text');
            $t->boolean('is_discreet')->default(false)->after('dest_location_ref');
        });

        Schema::table('transport_orders', function (Blueprint $t) {
            $t->string('pickup_mode', 12)->default('masivo')->after('status');
        });

        DB::statement(<<<'SQL'
CREATE TABLE `transport_position_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `is_leadership` tinyint(1) NOT NULL DEFAULT '0',
  `always_pickup` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transport_poscfg_unique` (`production_id`,`position_id`),
  KEY `transport_poscfg_prod_idx` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE `transport_vehicle_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_vassign_prod_idx` (`production_id`),
  KEY `transport_vassign_pos_idx` (`position_id`),
  KEY `transport_vassign_user_idx` (`user_id`),
  KEY `transport_vassign_veh_idx` (`vehicle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down()
    {
        Schema::dropIfExists('transport_vehicle_assignments');
        Schema::dropIfExists('transport_position_config');
        Schema::table('transport_orders', function (Blueprint $t) {
            $t->dropColumn('pickup_mode');
        });
        Schema::table('transport_order_runs', function (Blueprint $t) {
            $t->dropColumn(['run_class', 'pickup_point_id', 'travel_minutes', 'travel_adjust_minutes', 'dest_location_ref', 'is_discreet']);
        });
    }
}
