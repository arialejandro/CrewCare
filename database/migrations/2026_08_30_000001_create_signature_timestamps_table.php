<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * signature_timestamps — SELLO DE TIEMPO RFC 3161 (TSA) sobre cada sello digital.
 *
 * POR QUÉ: el sello de integridad es HMAC con CREWCARE_SEAL_KEY. Si esa llave se filtrara,
 * alguien podría FABRICAR un sello válido y un acta inexistente pasaría el verificador — y la
 * llave NO se puede rotar con sellos vivos. Un timbre EXTERNO (freeTSA, RFC 3161) no depende de
 * nuestra llave: prueba que el HASH ya existía en un momento dado. Aunque la llave se filtre
 * mañana, nadie puede conseguir un timbre fechado en el pasado.
 *
 * DISEÑO: tabla SEPARADA keyed por digital_signatures.id → no toca el esquema del sello ni el
 * trait HasDigitalSignatures. Se llena best-effort y ASÍNCRONO por el cron `tsa:stamp` (con la
 * cola en `sync`, el sellado NUNCA debe bloquearse esperando a freeTSA). Cubre sellos NUEVOS y
 * VIEJOS: el drenador timbra en retroactivo cualquier firma sin token.
 *
 * Solo viaja el HASH a freeTSA (imprint = SHA-256 del document_hash) → confidencialidad intacta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_timestamps')) {
            return;
        }

        Schema::create('signature_timestamps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('signature_id')->unique();   // digital_signatures.id (1:1)
            $table->string('status', 12)->default('pending');       // pending | stamped | failed
            $table->string('authority', 60)->nullable();            // 'freeTSA'
            $table->char('imprint', 64)->nullable();                // SHA-256(document_hash) en hex
            $table->longText('tsr')->nullable();                    // token RFC 3161 (base64) — la prueba
            $table->dateTime('gen_time')->nullable();               // tiempo AUTORITATIVO de la TSA
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('stamped_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'attempts']);                  // el cron busca pendientes con reintentos
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_timestamps');
    }
};
