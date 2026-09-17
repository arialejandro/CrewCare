<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TECH SCOUT · panel general + viabilidad y acuerdos.
 *
 * El módulo nació como "foto + nota". Al verlo en pantalla, el owner pidió que fuera lo que
 * siempre dijo que era: una versión LITE del Scouting H&S. Mismos datos de cabecera que aquél
 * —para que quien lee los dos no tenga que traducir— menos lo que aquí no aporta.
 *
 * ── QUÉ SE TOMA DEL SCOUTING H&S, CON LOS MISMOS NOMBRES ───────────────────────────────────
 * `production_type`, `manager_name`, `date_prep`/`date_shoot`/`date_shoot_end`/`date_wrap`,
 * `loc_setting` (tipo de locación) y `shoot_time` (horario). Mismos nombres A PROPÓSITO: son el
 * mismo dato del mundo real y llamarlo distinto en cada documento es cómo empiezan a divergir.
 *
 * ── QUÉ NO SE TRAE, Y POR QUÉ ──────────────────────────────────────────────────────────────
 * · `scene` — la escena es del llamado, no del recorrido técnico.
 * · `complexity` — es un juicio de riesgo y ese juicio vive en el Scouting H&S, que se sella.
 *   Duplicarlo aquí, en un documento editable, invitaría a que los dos digan cosas distintas.
 * · `safety_rep_name` — este documento lo firma Locaciones como departamento.
 *
 * ── LO PROPIO ──────────────────────────────────────────────────────────────────────────────
 * `viability_checklist` y `agreements`: permisos, solicitudes especiales y lo pactado entre
 * departamentos y locaciones. Mismo formato de filas JSON que el Scouting H&S.
 *
 * ADITIVA y segura: la tabla NO está sellada (es documento de trabajo), así que ampliarla no
 * mueve ningún hash. Todas las columnas nullable — lo ya capturado sigue válido sin tocarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tech_scouts')) {
            return;
        }

        Schema::table('tech_scouts', function (Blueprint $table) {
            if (! Schema::hasColumn('tech_scouts', 'production_type')) {
                $table->string('production_type', 60)->nullable()->after('location_address');
            }
            if (! Schema::hasColumn('tech_scouts', 'manager_name')) {
                $table->string('manager_name', 255)->nullable()->after('production_type');
            }
            if (! Schema::hasColumn('tech_scouts', 'loc_setting')) {
                $table->string('loc_setting', 60)->nullable()->after('manager_name');
            }
            if (! Schema::hasColumn('tech_scouts', 'shoot_time')) {
                $table->string('shoot_time', 60)->nullable()->after('loc_setting');
            }
            if (! Schema::hasColumn('tech_scouts', 'date_prep')) {
                $table->date('date_prep')->nullable()->after('shoot_time');
            }
            if (! Schema::hasColumn('tech_scouts', 'date_shoot')) {
                $table->date('date_shoot')->nullable()->after('date_prep');
            }
            if (! Schema::hasColumn('tech_scouts', 'date_shoot_end')) {
                $table->date('date_shoot_end')->nullable()->after('date_shoot');
            }
            if (! Schema::hasColumn('tech_scouts', 'date_wrap')) {
                $table->date('date_wrap')->nullable()->after('date_shoot_end');
            }
            if (! Schema::hasColumn('tech_scouts', 'viability_checklist')) {
                $table->json('viability_checklist')->nullable()->after('date_wrap');
            }
            if (! Schema::hasColumn('tech_scouts', 'agreements')) {
                $table->json('agreements')->nullable()->after('viability_checklist');
            }
            // Imagen HERO del documento — la línea editorial de CrewCare: todos los documentos
            // abren con una foto de la locación bajo la marca. Se ELIGE a mano (no es la primera
            // nota) porque la banda es ancha y baja: una foto se recorta a una franja, así que
            // funciona un plano general y no un detalle. Quien la elige debe poder decidirlo.
            if (! Schema::hasColumn('tech_scouts', 'hero_image_path')) {
                $table->string('hero_image_path', 500)->nullable()->after('location_address');
            }
        });
    }

    public function down(): void
    {
        // Seguro: la tabla no está sellada y ningún documento probatorio la referencia.
        if (! Schema::hasTable('tech_scouts')) {
            return;
        }

        Schema::table('tech_scouts', function (Blueprint $table) {
            foreach ([
                'production_type', 'manager_name', 'loc_setting', 'shoot_time',
                'date_prep', 'date_shoot', 'date_shoot_end', 'date_wrap',
                'viability_checklist', 'agreements', 'hero_image_path',
            ] as $col) {
                if (Schema::hasColumn('tech_scouts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
