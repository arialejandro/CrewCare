<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ToolInspectionRegimeSeeder — fija el `tools.inspection_regime` confirmado por el owner.
 * 2026-07-26 (delta #43).
 *
 * DERIVACIÓN (no clasificación a mano de los 73):
 *   · por_jornada    = requires_designated_operator=1 (equipo pesado con operador; falla grave
 *                      de un día a otro). Son 5: Planta, Tijera, Brazo, Montacargas, Grúa.
 *   · por_colocacion = ESTRUCTURA TEMPORAL que sigue puesta (reusa la mecánica de site_scope).
 *                      6 confirmados por el owner. NO se incluyen los 7 tipos de trabajo en
 *                      caliente/flama que también disparan permiso de sitio: son portátiles de
 *                      usar-y-guardar → por_evento (su permiso de sitio lo maneja la capa de
 *                      permisos, no la vigencia del tipo).
 *   · por_evento     = todo lo demás (el default; no exige nada).
 *
 * IDEMPOTENTE: resetea todo a por_evento y re-fija las excepciones, así re-correrlo da el mismo
 * estado. El régimen es atributo del TIPO; el owner ajusta excepciones editando aquí.
 *
 * Correr:  php artisan db:seed --class=ToolInspectionRegimeSeeder
 */
class ToolInspectionRegimeSeeder extends Seeder
{
    /** Los 6 estructurales confirmados (andamio, aparejos, grúa de cámara, car mount). */
    private const COLOCACION = ['HER-029', 'HER-053', 'HER-054', 'HER-055', 'HER-067', 'HER-069'];

    public function run()
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('tools', 'inspection_regime')) {
            if ($this->command) $this->command->warn('ToolInspectionRegimeSeeder: falta tools.inspection_regime; aplica el delta #43 primero.');
            return;
        }

        // 1) todo a por_evento (default explícito → idempotencia total).
        DB::table('tools')->update(['inspection_regime' => 'por_evento']);

        // 2) por_jornada = operador designado (derivado, no a mano).
        $jornada = DB::table('tools')->where('requires_designated_operator', 1)
            ->update(['inspection_regime' => 'por_jornada']);

        // 3) por_colocacion = los 6 estructurales confirmados.
        $coloc = DB::table('tools')->whereIn('code', self::COLOCACION)
            ->update(['inspection_regime' => 'por_colocacion']);

        $evento = DB::table('tools')->where('inspection_regime', 'por_evento')->count();
        if ($this->command) {
            $this->command->info("ToolInspectionRegimeSeeder: por_jornada={$jornada}  por_colocacion={$coloc}  por_evento={$evento}");
        }
    }
}
