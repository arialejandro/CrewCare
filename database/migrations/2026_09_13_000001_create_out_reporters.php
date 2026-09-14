<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * out_reporters — DESIGNADOS para reportar la salida de un DEPARTAMENTO (2026-09-13).
 *
 * Corrección del modelo: la autoridad para reportar por un departamento NO es `is_hod` ni un puesto
 * fijo — es una persona DESIGNADA (por producción o por el propio departamento) según quién sea la
 * más apta; puede ser cualquiera del equipo, y la designación se declara y se puede cambiar. Cada fila
 * = "esta persona puede reportar la salida de este depto en esta producción". Puede haber varios por
 * depto.
 *
 * ⚠ Esta autoridad es SÓLO para reportar POR EL DEPARTAMENTO (el caso WhatsApp / pegar mensaje).
 * Marcar la PROPIA salida NO requiere estar aquí: cada quien puede con la suya. Aditivo, no se sella.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('out_reporters')) {
            return;
        }
        Schema::create('out_reporters', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('production_id')->index();
            $t->unsignedBigInteger('department_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('designated_by_id')->nullable();   // quién lo designó (producción o el depto)
            $t->timestamps();
            $t->unique(['production_id', 'department_id', 'user_id'], 'out_reporters_unique');
            $t->index(['production_id', 'user_id'], 'out_reporters_pid_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_reporters');
    }
};
