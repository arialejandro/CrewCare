<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BORRADOR DE SCOUTING EN SERVIDOR — para que cerrar la pestaña no cueste una jornada.
 *
 * ── QUÉ LO MOTIVA ──────────────────────────────────────────────────────────────────────────
 * El 2026-09-14, con la producción ya rodando, se perdió un scouting con más de 20 fotos al
 * cerrarse la vista por error. El borrador local (cc-drafts.js, IndexedDB) SÍ guardaba el texto,
 * pero su propia cabecera lo dice: «el borrador NO captura <input type=file>». Las fotos vivían
 * únicamente en la memoria de esa pestaña. Al cerrarla, se fueron.
 *
 * Aquí las fotos dejan de depender de la pestaña: se suben EN CUANTO se capturan y esta tabla
 * recuerda sus rutas. Si el navegador se cierra, se recarga o se queda sin batería, el trabajo ya
 * está en el servidor.
 *
 * ── QUÉ NO ES ──────────────────────────────────────────────────────────────────────────────
 * NO es un scouting. No se sella, no entra en ningún hash, no aparece en listados ni en
 * documentos, y no tiene valor probatorio. Es guardado parcial, del AUTOR, y se BORRA en cuanto
 * el scouting real se guarda — mismo contrato que `vehicle_inspection_drafts`, que ya funciona
 * así desde agosto. Al no estar sellada, esta tabla se puede alterar y migrar con libertad.
 *
 * ── POR QUÉ `client_key` ───────────────────────────────────────────────────────────────────
 * Es el id del borrador LOCAL de cc-drafts. Sirve para emparejar las dos capas: la local (que
 * funciona sin red) y ésta (que sobrevive al dispositivo). Así una persona puede llevar VARIAS
 * locaciones capturándose a la vez en un día sin señal, y cada una encuentra sus fotos. Único por
 * autor: dos personas pueden tener borradores distintos con la misma clave sin pisarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scouting_drafts')) {
            return;
        }

        Schema::create('scouting_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_id')->index();

            // Id del borrador local (cc-drafts). Une la capa local con ésta.
            $table->string('client_key', 64);

            // Pares {n,t,v,c} del formulario, tal como los serializa cc-drafts (conserva el orden
            // del DOM y los name[] repetidos). Texto largo: un scouting con muchos peligros crece.
            $table->longText('values')->nullable();

            // Fotos YA SUBIDAS: [{path, caption, risk_map, slot}]. `path` es la ruta pública que
            // devolvió ImageCompressor, la misma que acabará en additional_images_paths — por eso
            // al guardar el scouting no se re-sube nada.
            $table->json('photos')->nullable();

            $table->timestamps();

            $table->unique(['created_by_id', 'client_key'], 'scouting_drafts_author_key_unique');
        });
    }

    public function down(): void
    {
        // Seguro de revertir: la tabla no está sellada y no la referencia ningún documento. Lo
        // único que se pierde son borradores a medio capturar (y sus fotos quedan en disco, que
        // las barre el mismo aseo que las huérfanas).
        Schema::dropIfExists('scouting_drafts');
    }
};
