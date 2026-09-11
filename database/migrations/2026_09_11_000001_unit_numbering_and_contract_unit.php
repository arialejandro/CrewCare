<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · nombre compuesto en el contrato (2c, 2026-09-11).
 *
 *  · units.number — NÚMERO ESTABLE de la unidad, asignado al crearla y que NUNCA cambia (identidad,
 *    como su nombre). NO se deriva de sort_order: desactivar/reordenar una unidad NO renumera a las
 *    demás, así el "Unidad 3" de un contrato sellado sigue apuntando a la 3 aunque se apague la 2.
 *    La PRINCIPAL es la unidad 1 IMPLÍCITA (no tiene fila) → las adicionales arrancan en 2.
 *  · productions.unit_label_format — 'long' ("Unidad {n}") | 'short' ("U{n}"). Cada productora elige.
 *  · payee_contracts.unit_number — la unidad que se CONGELA en el contrato al emitirlo (NULL = principal).
 *    Es la referencia ESTRUCTURAL para comparar contra la pertenencia viva ("revisar") sin parsear el
 *    texto del título. payee_contracts NO se sella → columna aditiva sin efecto en ningún hash.
 *
 * Todo ADITIVO y nullable. El backfill numera las unidades adicionales existentes por orden de alta
 * (id asc) desde 2, por producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('units') && ! Schema::hasColumn('units', 'number')) {
            Schema::table('units', function (Blueprint $t) {
                $t->unsignedSmallInteger('number')->nullable()->after('name');
            });

            // Backfill: por producción, las adicionales existentes se numeran 2,3,4… por id asc.
            foreach (DB::table('units')->select('production_id')->distinct()->pluck('production_id') as $pid) {
                $n = 2;
                $q = DB::table('units')->orderBy('id');
                $pid === null ? $q->whereNull('production_id') : $q->where('production_id', $pid);
                foreach ($q->pluck('id') as $id) {
                    DB::table('units')->where('id', $id)->update(['number' => $n++]);
                }
            }
        }

        if (Schema::hasTable('productions') && ! Schema::hasColumn('productions', 'unit_label_format')) {
            Schema::table('productions', function (Blueprint $t) {
                $t->string('unit_label_format', 8)->default('long')->after('code');
            });
        }

        if (Schema::hasTable('payee_contracts') && ! Schema::hasColumn('payee_contracts', 'unit_number')) {
            Schema::table('payee_contracts', function (Blueprint $t) {
                $t->unsignedSmallInteger('unit_number')->nullable()->after('title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('units') && Schema::hasColumn('units', 'number')) {
            Schema::table('units', fn (Blueprint $t) => $t->dropColumn('number'));
        }
        if (Schema::hasTable('productions') && Schema::hasColumn('productions', 'unit_label_format')) {
            Schema::table('productions', fn (Blueprint $t) => $t->dropColumn('unit_label_format'));
        }
        if (Schema::hasTable('payee_contracts') && Schema::hasColumn('payee_contracts', 'unit_number')) {
            Schema::table('payee_contracts', fn (Blueprint $t) => $t->dropColumn('unit_number'));
        }
    }
};
