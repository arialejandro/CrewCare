<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * CONCEPTOS DEL CFDI — una sola factura cubre VARIAS semanas (p.ej. 2 SEM + 2 CA de dos semanas). Se
 * guardan TODOS los conceptos parseados (prefijo + semana + descripción cruda) para poder responder
 * "qué se pagó de la semana X" LEYENDO las descripciones, no la fecha del documento. JSON aditivo.
 */
class AddCfdiConceptos extends Migration
{
    public function up()
    {
        if (Schema::hasTable('external_authorizations')
            && ! Schema::hasColumn('external_authorizations', 'cfdi_conceptos')) {
            Schema::table('external_authorizations', function (Blueprint $t) {
                $t->json('cfdi_conceptos')->nullable()->after('xml_path');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('external_authorizations', 'cfdi_conceptos')) {
            Schema::table('external_authorizations', fn (Blueprint $t) => $t->dropColumn('cfdi_conceptos'));
        }
    }
}
