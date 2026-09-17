<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · PASO 2A — la entidad UNIDAD (2ª unidad de rodaje y siguientes). Lo MÍNIMO: nombre, orden y
 * si está activa (+ production_id para acotar a la producción vigente).
 *
 * 🔑 NULL = LA UNIDAD PRINCIPAL (la unidad uno). NO hay fila para ella: todo lo existente lleva unit_id
 * NULL y por eso queda FUERA del hash (ver [[unidades-paso1-hash-column]]). Una 2ª unidad SÍ lleva id, y
 * por eso queda sellada en sus documentos. Baja por DESACTIVACIÓN (is_active=0), NUNCA borrado — las FK
 * a units son ON DELETE RESTRICT justo para impedir borrar una unidad con documentos.
 *
 * ⚠ Aditivo. `php artisan migrate --path=database/migrations/2026_09_06_000002_create_units_table.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('units')) {
            return;
        }
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id')->nullable()->index();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
