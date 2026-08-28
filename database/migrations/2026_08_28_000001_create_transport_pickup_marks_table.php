<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRANSPORTACIÓN · Fase 3 — LA MARCA "lleva pick up hoy" (delta #115).
 * Gemelo de database/owner-apply/2026-08-28-transport-pickup-marks.sql.
 *
 * Singleton por producción+persona, SIN fecha (memoria del único día abierto, como
 * call_person_schedules — que NO se toca). La declara el back; la precarga la lee.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transport_pickup_marks')) {
            return;
        }
        Schema::create('transport_pickup_marks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('production_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_marked')->default(true);
            $table->unsignedBigInteger('marked_by_id')->nullable();
            $table->timestamps();
            $table->unique(['production_id', 'user_id'], 'transport_pickup_marks_unique');
            $table->index('production_id', 'transport_pickup_marks_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_pickup_marks');
    }
};
