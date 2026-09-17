<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clinical_read_logs — BITÁCORA DE LECTURA CLÍNICA (quién abrió qué expediente y cuándo).
 *
 * POR QUÉ: por decisión del owner, PRODUCCIÓN puede LEER expedientes clínicos (HOD de su depto,
 * line-producer y coordinador de cualquiera). Eso deja gente NO clínica leyendo datos clínicos
 * sin rastro. Esta bitácora deja el rastro — es INVISIBLE para el usuario (no pide nada, no
 * interrumpe) y append-only. `reader_is_clinical` distingue al médico de quien sólo observa.
 *
 * Retención: hasta 3 años (el cron `clinical-log:prune` borra lo más viejo). Al concluir el
 * contrato el contenido baja del VPS y queda solo en la nube (operativo, no en esta tabla).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinical_read_logs')) {
            return;
        }

        Schema::create('clinical_read_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reader_id')->nullable();       // quién leyó (users.id)
            $table->boolean('reader_is_clinical')->default(false);     // ¿es médico? (isMedic)
            $table->string('record_type', 24);                         // expediente | consulta | expediente_lite | injury_completo
            $table->unsignedBigInteger('record_id')->nullable();       // id del documento leído
            $table->unsignedBigInteger('patient_ref')->nullable();     // paciente (users.id o lite_patients.id)
            $table->string('route_name', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('opened_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['record_type', 'record_id']);
            $table->index('reader_id');
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_read_logs');
    }
};
