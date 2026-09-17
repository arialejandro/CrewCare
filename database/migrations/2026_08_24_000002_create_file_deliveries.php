<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ENVÍO DE ARCHIVOS CON MARCA DE AGUA — outbox reusable (no exclusivo del llamado).
 *
 * El envío masivo NO se hace en el request: se ENCOLA aquí y un comando agendado (cron ya existente,
 * `schedule:run`) lo drena en tandas — cada copia se marca al vuelo con el nombre de quien la recibe.
 * Así el envío no bloquea ni se pierde (los reintentos viven en la fila), y el mismo motor sirve para
 * el paquete del llamado y para cualquier documento suelto (avisos, etc.).
 *
 *  - file_deliveries            un "envío" (lote): el PDF base + de quién viene + asunto/cuerpo.
 *  - file_delivery_recipients   una fila por persona = una unidad de trabajo del cron (marca + correo).
 */
class CreateFileDeliveries extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('file_deliveries')) {
            Schema::create('file_deliveries', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('production_id')->nullable()->index();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->string('source_type', 40)->default('manual');    // call_package | manual
                $t->unsignedBigInteger('source_id')->nullable();      // p.ej. call_packages.id
                $t->string('title', 180);                             // asunto del correo
                $t->text('body')->nullable();                         // cuerpo (texto libre, opcional)
                $t->string('base_path', 255);                         // PDF base a marcar (disco local)
                $t->string('base_name', 180);                         // nombre visible del adjunto
                $t->boolean('watermark')->default(true);              // marca de agua por destinatario
                $t->timestamps();
                $t->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('file_delivery_recipients')) {
            Schema::create('file_delivery_recipients', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('file_delivery_id')->index();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('name', 160)->nullable();
                $t->string('email', 190);
                $t->string('watermark_text', 160)->nullable();        // lo que se estampa en diagonal
                $t->string('status', 12)->default('pending');         // pending|sent|failed
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->text('error')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('last_attempt_at')->nullable();
                $t->timestamps();
                $t->index(['status', 'attempts']);   // el cron busca pendientes con reintentos restantes
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('file_delivery_recipients');
        Schema::dropIfExists('file_deliveries');
    }
}
