<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SALIDAS (OUTS) + TURNAROUND · esquema (2026-09-12).
 *
 * "OUT" = la hora en que un DEPARTAMENTO abandona la locación (no el wrap, no el fin del trabajo:
 * irse físicamente). Puede haber además SALIDAS INDIVIDUALES cuando alguien sale a otra hora que su
 * equipo.
 *
 * ⚠ NO confundir con:
 *   · la columna `out` del back del llamado (plantilla gringa, hoy vacía) — otra cosa, no se toca.
 *   · el estado de roster `PayeeContract::ROSTER_OUT` (crew inactivo) — otra cosa, no se toca.
 * Por eso el módulo se llama SALIDAS y sus tablas `*_outs` con su propio dominio.
 *
 * A QUÉ DÍA PERTENECE: un out se ancla a `shoot_date` (el día de RODAJE), NO a la fecha de pared del
 * reloj. Un out de las 02:30 pertenece al rodaje que terminó. La resolución vive en App\Support\OutWindow
 * (ancla = call_days.general_call de la unidad + 20 h hacia delante como máximo; fuera de eso NO se
 * asigna a ciegas). `unit_id` nullable (null = principal), como shoot_days/call_days: cada unidad tiene
 * su propio llamado, así que su ventana es la suya.
 *
 * TURNAROUND no se guarda como columna: se DERIVA al consultar/exportar (salida × siguiente llamado).
 * Guardarlo quedaría stale al mover el llamado siguiente. Sin salida no hay turnaround.
 *
 * NADA de esto se sella → no toca ningún hash. Aditivo.
 * Aplicar: `php artisan migrate --path=database/migrations/2026_09_12_000001_create_outs_schema.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Salida de un DEPARTAMENTO en un día de rodaje.
        if (! Schema::hasTable('department_outs')) {
            Schema::create('department_outs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('production_id')->index();
                $t->unsignedBigInteger('unit_id')->nullable();      // null = principal
                $t->date('shoot_date');                             // el DÍA DE RODAJE (resuelto por la ventana)
                $t->unsignedBigInteger('department_id');
                $t->dateTime('out_at');                             // hora real de salida (pared; puede ser de madrugada)
                $t->string('source', 20)->default('app');           // 'app' | 'whatsapp' | ...
                $t->string('note', 191)->nullable();
                $t->unsignedBigInteger('registered_by_id')->nullable();
                $t->timestamps();
                // Una salida por depto/día/unidad. MySQL trata NULL como distinto, así que la unicidad
                // efectiva la garantiza el updateOrCreate de OutRegistrar (única vía de escritura); aquí,
                // índice de consulta. Corregir una hora = update de esta fila.
                $t->index(['production_id', 'unit_id', 'shoot_date'], 'dept_outs_pid_unit_date_idx');
                $t->index(['production_id', 'department_id'], 'dept_outs_pid_dept_idx');
            });
        }

        // Salida INDIVIDUAL: alguien que salió a otra hora que su equipo.
        if (! Schema::hasTable('individual_outs')) {
            Schema::create('individual_outs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('production_id')->index();
                $t->unsignedBigInteger('unit_id')->nullable();
                $t->date('shoot_date');
                $t->unsignedBigInteger('user_id');                  // la persona
                $t->unsignedBigInteger('department_id')->nullable(); // su depto (scoping + turnaround)
                $t->dateTime('out_at');
                $t->string('source', 20)->default('app');
                $t->string('note', 191)->nullable();
                $t->unsignedBigInteger('registered_by_id')->nullable();
                $t->timestamps();
                $t->index(['production_id', 'unit_id', 'shoot_date'], 'ind_outs_pid_unit_date_idx');
                $t->index(['production_id', 'user_id'], 'ind_outs_pid_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_outs');
        Schema::dropIfExists('department_outs');
    }
};
