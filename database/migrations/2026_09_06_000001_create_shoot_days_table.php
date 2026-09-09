<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CALENDARIO DE RODAJE DINÁMICO · PASO 1 — la TABLA DE DÍAS. Una fila por fecha de la producción
 * (decisión owner: días explícitos, no marcas de fin de semana con excepciones apiladas). El
 * calendario MANDA: `shootDates()` pasa a leer de aquí (con fallback a daily_reports mientras esté
 * vacía), así el contador avanza SIN que exista un DSR. `shoot_day` sellado NO se toca — sigue
 * congelado por fila; esta tabla es planeación, NO se sella.
 *
 * Columnas:
 *   is_shoot_day — true = día de rodaje; false = descanso/festivo marcado explícito.
 *   slug_time    — luz del día PLANEADA (mismo vocabulario que el DSR: DÍA/NOCHE/AMANECER/ATARDECER/
 *                  MIXTO). Default DÍA (null = DÍA). El DSR CONFIRMA; si difiere, se muestra.
 *   week_no      — semana planeada que lo generó (desde el fin de semana marcado).
 *   is_manual    — excepción marcada A MANO: gana sobre la regeneración (la regla propone, la persona manda).
 *
 * ⚠ Aditivo. Aplicar con `php artisan migrate --path=database/migrations/2026_09_06_000001_create_shoot_days_table.php`.
 * (Unidades NO entra aquí: sin `unit_id` a propósito — es un bloque posterior.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shoot_days')) {
            return;
        }
        Schema::create('shoot_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id')->nullable()->index();
            $table->date('shoot_date');
            $table->boolean('is_shoot_day')->default(true);
            $table->string('slug_time', 20)->nullable();   // null = DÍA
            $table->unsignedInteger('week_no')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->string('note', 191)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->unique(['production_id', 'shoot_date']);   // una fila por fecha por producción
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shoot_days');
    }
};
