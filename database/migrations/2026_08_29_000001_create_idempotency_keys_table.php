<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * idempotency_keys — llave de idempotencia HTTP para el ENVÍO DIFERIDO offline
 * (Camino A). Un borrador capturado sin red se reproduce por la RUTA NORMAL al
 * reconectar; el cliente manda su llave (X-Idempotency-Key = id del borrador) para
 * que un reintento del mismo envío NO cree un segundo documento.
 *
 * La tabla es la memoria "a lo hecho, hecho" del middleware IdempotentReplay:
 *   - se reclama la llave (INSERT único) ANTES de correr el store();
 *   - si el store() tuvo éxito (2xx/3xx) se sella con su response_status → cualquier
 *     reintento con la misma llave contesta 'duplicate' sin volver a crear/sellar;
 *   - si falló validación (4xx) o reventó, la llave se LIBERA → un reintento corregido
 *     sí puede pasar.
 *
 * Es ADITIVA y no toca ningún flujo: el middleware es un passthrough salvo que venga
 * la cabecera, así que los envíos interactivos (sin cabecera) ni la ven.
 *
 * NOTA: `idem_key` se llama así a propósito para NO chocar con la palabra reservada
 * `key` de MySQL 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idempotency_keys')) {
            return;
        }

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idem_key', 191)->unique();        // llave del cliente (id del borrador)
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('method', 8)->nullable();
            $table->string('path', 255)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable(); // se llena al completar el store()
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
