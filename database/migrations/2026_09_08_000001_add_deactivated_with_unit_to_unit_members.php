<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · desactivación en cascada — MARCADOR de "se apagó CON la unidad".
 *
 * POR QUÉ EXISTE: al desactivar una unidad, sus miembros EXCLUSIVOS quedan dados de baja de verdad
 * (users.activo=0, el MISMO mecanismo que ya usa el crew — CrewStatusController::desactivarusuario), para
 * que no queden en limbo. Al REACTIVAR la unidad hay que devolver EXACTAMENTE a los que ella apagó, sin
 * resucitar a quien ya estaba dado de baja por su cuenta. Como el flip de `activo` NO guarda quién/cuándo,
 * este marcador en la pivote es la única forma de saber a quién restaurar. Se pone al apagar, se limpia al
 * restaurar. Los COMPARTIDOS (exclusive=0) nunca se tocan, así que nunca llevan marca.
 *
 * `unit_members` NO se sella (es catálogo de asignación, no documento): la columna no mueve ningún hash.
 * Aditiva, con default 0, degrade-safe.
 *
 * ⚠ Aplicar con `php artisan migrate --path=database/migrations/2026_09_08_000001_add_deactivated_with_unit_to_unit_members.php`.
 *   NUNCA migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unit_members') || Schema::hasColumn('unit_members', 'deactivated_with_unit')) {
            return;
        }
        Schema::table('unit_members', function (Blueprint $table) {
            // TINYINT(1) NOT NULL DEFAULT 0. 1 = a esta persona la apagó la desactivación de ESTA unidad
            // (y hay que reactivarla al reactivar la unidad). 0 = no la apagó la unidad (default).
            $table->boolean('deactivated_with_unit')->default(false)->after('exclusive');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('unit_members') || ! Schema::hasColumn('unit_members', 'deactivated_with_unit')) {
            return;
        }
        Schema::table('unit_members', function (Blueprint $table) {
            $table->dropColumn('deactivated_with_unit');
        });
    }
};
