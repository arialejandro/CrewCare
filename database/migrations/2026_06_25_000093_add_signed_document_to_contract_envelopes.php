<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C · FASE 3 — documento FIRMADO real del sobre. Guarda el contrato con las
 * autógrafas estampadas, ya congelado ({ path, hash, rendered_at, engine }). Antes ese render vivía
 * solo como HTML efímero; lo sellado/entregado era el paquete byte-intact SIN firmas.
 *
 * Artefacto DERIVADO de datos ya sellados (autógrafas por destinatario + paquete) → va en
 * $signatureExcludes (no entra al hash del sobre) y NO invalida sellos existentes. Su integridad se
 * ancla en la bitácora (evento 'sealed'). Espejo del owner-apply
 * 2026-08-15-contract-envelope-signed-document.sql.
 */
class AddSignedDocumentToContractEnvelopes extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('contract_envelopes', 'signed_document')) {
            Schema::table('contract_envelopes', function (Blueprint $table) {
                $table->json('signed_document')->nullable()->after('documents');
            });
        }
    }

    public function down()
    {
        Schema::table('contract_envelopes', function (Blueprint $table) {
            if (Schema::hasColumn('contract_envelopes', 'signed_document')) {
                $table->dropColumn('signed_document');
            }
        });
    }
}
