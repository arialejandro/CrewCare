<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * ENLACE DE LA 32-D — un solo campo NUEVO: `sat_folio`, DEDICADO (NO se reusa `folio`, que ya significa
 * "folio de trámite" y alimenta effectiveStatus(); sobrecargarlo muerde después). Con él, el RFC (del
 * payee), la fecha (issued_at) y el sentido (result_status) se arma el D3 del validador del SAT sin más.
 * OPCIONAL: si viene vacío el documento llega igual y contabilidad lo captura luego. Idempotente.
 */
class AddSatFolio extends Migration
{
    public function up()
    {
        if (Schema::hasTable('external_authorizations')
            && ! Schema::hasColumn('external_authorizations', 'sat_folio')) {
            Schema::table('external_authorizations', function (Blueprint $t) {
                $t->string('sat_folio', 60)->nullable()->after('folio');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('external_authorizations', 'sat_folio')) {
            Schema::table('external_authorizations', fn (Blueprint $t) => $t->dropColumn('sat_folio'));
        }
    }
}
