<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · 2c — SIEMBRA del campo de ALCANCE ADMINISTRATIVO. Sólo el campo; NO se cablea ninguna rama.
 *
 * Catálogo, contratos, padrón (payees), contabilidad y expediente clínico son GLOBALES de la producción, y
 * el owner nunca ha necesitado separarlos por unidad — pero quiere que el día que haga falta sea un clic.
 * Este campo es ESE seed. Default **'global'**.
 *
 * 🔴 NO se cablea la rama por unidad de ninguno: un interruptor que no hace nada es PEOR que no tenerlo, así
 * que tampoco hay UI. Se cablea el día que alguien lo pida, y eso es trabajo de un MÓDULO, no una reescritura.
 *
 * ── CÓMO CABLEAR UNO (para el delta) ─────────────────────────────────────────────────────────────────────
 * El día que, p. ej., el CATÁLOGO deba ser por unidad:
 *   1) Modelo de datos: la entidad del catálogo gana `unit_id` nullable (null = global/principal), como las
 *      operativas en 2b; migración de columna + índice (y unicidad ampliada si tiene llaves únicas).
 *   2) Lectura: sus consultas se acotan con `CurrentUnit::applyTo(...)` (o el helper del dominio), guardado
 *      por `CurrentUnit::hasMultiple()` para que con una sola unidad NO cambie nada.
 *   3) Escritura: al crear se estampa `CurrentUnit::id()`.
 *   4) Este campo: la rama sólo se ENCIENDE si `admin_scope` de esa superficie dice 'unit' (hoy, todo
 *      'global' → la rama nunca corre). Si se quiere granularidad por superficie, este VARCHAR pasa a JSON
 *      con una clave por superficie; hoy es un modo único de producción (decisión abierta del owner).
 *   5) UI: recién entonces aparece el interruptor (no antes).
 * `users`/`production_user` NUNCA entran en esto: la pertenencia es la pivote unit_members (2c).
 *
 * ⚠ `php artisan migrate --path=database/migrations/2026_09_07_000004_seed_admin_scope_field.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('productions') || Schema::hasColumn('productions', 'admin_scope')) {
            return;
        }
        DB::statement("ALTER TABLE `productions` ADD COLUMN `admin_scope` VARCHAR(20) NOT NULL DEFAULT 'global'");
    }

    public function down(): void
    {
        if (Schema::hasTable('productions') && Schema::hasColumn('productions', 'admin_scope')) {
            DB::statement('ALTER TABLE `productions` DROP COLUMN `admin_scope`');
        }
    }
};
