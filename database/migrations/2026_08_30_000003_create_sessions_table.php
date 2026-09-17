<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sessions — habilita SESIÓN EN BASE (SESSION_DRIVER=database) para poder LISTAR y REVOCAR las
 * sesiones activas de un usuario desde su perfil. Hoy el driver es `file`: cada sesión es un
 * archivo sin índice por usuario → NO se pueden enumerar ni cerrar una a una. Esta tabla + el flip
 * a `database` es el "cambio de enfoque" que exige el caso del teléfono perdido (cerrar la sesión
 * sin cambiar la contraseña).
 *
 * ⚠ ACTIVACIÓN (owner): poner SESSION_DRIVER=database en .env. Al hacerlo, las sesiones `file`
 * vigentes dejan de valer → TODOS re-inician sesión UNA vez (evento único, no fricción continua).
 * Es aditivo: mientras el driver siga en `file`, esta tabla queda vacía y no afecta nada.
 *
 * Esquema estándar de Laravel para el driver `database`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
