<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAQUETE DEL LLAMADO — ensamble front (PDF externo del 2nd AD) + back (CrewCare), firma de los 3
 * (LP · Gerente de Producción · 1er AD), congelado y envío. Aditivo, nada sella/altera lo existente.
 *
 *  - call_packages             1 por (producción, día): front subido + estado + snapshot congelado.
 *  - call_package_signatures   firma de cada figura (rúbrica) colocada sobre el front por coordenadas.
 */
class CreateCallPackages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('call_packages')) {
            Schema::create('call_packages', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('production_id')->index();
                $t->date('call_date');
                $t->string('front_path', 255)->nullable();          // PDF del front subido (disco local)
                $t->unsignedSmallInteger('front_pages')->nullable();
                $t->longText('sign_field_map')->nullable();          // JSON: etiquetas de firma en el front
                $t->longText('signers')->nullable();                 // JSON: figuras requeridas (default LP/GteProd/1stAD)
                $t->string('status', 20)->default('draft');          // draft|pending|approved|changed
                $t->timestamp('approved_at')->nullable();
                $t->string('frozen_path', 255)->nullable();          // PDF unido congelado al aprobar
                $t->longText('frozen_schedule')->nullable();         // JSON: horarios al congelar (para detectar cambios/azul)
                $t->timestamps();
                $t->unique(['production_id', 'call_date']);
            });
        }

        if (! Schema::hasTable('call_package_signatures')) {
            Schema::create('call_package_signatures', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('call_package_id')->index();
                $t->string('signer_key', 40);                        // casa con signers[].key del paquete
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('role_label', 120)->nullable();
                $t->mediumText('rubrica_image')->nullable();         // dataURI de la autógrafa
                $t->timestamp('signed_at')->nullable();
                $t->timestamps();
                $t->unique(['call_package_id', 'signer_key']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('call_package_signatures');
        Schema::dropIfExists('call_packages');
    }
}
