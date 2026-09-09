<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES · 2c — PERTENENCIA persona↔unidad como PIVOTE (muchos-a-muchos).
 *
 * 🔴 NUNCA una columna de unidad en `users` ni en `production_user`: eso PARTICIONA a la persona a una sola
 * unidad y rompe al HOD compartido (Arte/Construcción/Decoración/Locaciones viven en las dos). Una pivote no
 * particiona — permite pertenecer a varias.
 *
 * MODELO:
 *  - `unit_id`  → la unidad ADICIONAL (units). Nunca la principal (que no tiene fila).
 *  - `exclusive` → 1 = SÓLO en esta unidad (sale de la principal); 0 = COMPARTIDA (en esta unidad Y en la
 *    principal). Default 1 porque lo NORMAL es exclusivo (el gaffer no está en dos lugares); lo compartido
 *    es la excepción que se marca.
 *  - SIN fila en la pivote → la persona vive en la PRINCIPAL (default; nada de backfill). Con una sola
 *    unidad la pivote está vacía → todo idéntico a hoy.
 *  - Pertenencia derivada: en la unidad U ⟺ existe fila (user,U); en la principal ⟺ NO tiene ninguna fila
 *    con exclusive=1.
 *
 * La pivote NO SE SELLA (no usa HasDigitalSignatures) — es estado de asignación, no documento.
 *
 * ⚠ `php artisan migrate --path=database/migrations/2026_09_07_000003_create_unit_members.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unit_members')) {
            return;
        }
        Schema::create('unit_members', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id');
            $t->unsignedBigInteger('user_id');
            $t->boolean('exclusive')->default(true);   // 1 = sólo aquí; 0 = compartida con la principal
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->timestamps();

            $t->unique(['unit_id', 'user_id'], 'unit_members_unit_user_unique');
            $t->index('user_id', 'unit_members_user_id_index');

            // La membresía es desechable: si se borra la unidad o el usuario, se limpia (units igual no se
            // borra —sólo se desactiva—, pero es defensivo).
            $t->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_members');
    }
};
