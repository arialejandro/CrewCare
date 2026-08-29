<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportación · Bloque 2 (Fase 3) — HORA DE FIN de una corrida (delta #116).
 *
 * `end_literal` (texto libre HH:MM, como `pickup_literal`) sirve a TRES cosas:
 *   1. La HORA DE FIN del evento de vehículo (`run_class='evento'`): un bloque de agenda
 *      con inicio y fin, sin ocupantes.
 *   2. El cálculo de la Fase 4 sobre qué puede ADELANTARSE cuando se libera un vehículo
 *      (cuándo termina una corrida/evento → cuándo queda libre la unidad).
 *   3. El TURNAROUND del driver: el fin de su corrida es su wrap (base para el descanso).
 *
 * Aditiva, nullable, no toca ninguna corrida existente (las viejas quedan con NULL).
 */
class AddEndLiteralToTransportOrderRuns extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('transport_order_runs', 'end_literal')) {
            DB::statement("ALTER TABLE `transport_order_runs` ADD COLUMN `end_literal` VARCHAR(16) COLLATE utf8mb4_unicode_ci NULL AFTER `pickup_literal`");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('transport_order_runs', 'end_literal')) {
            DB::statement("ALTER TABLE `transport_order_runs` DROP COLUMN `end_literal`");
        }
    }
}
