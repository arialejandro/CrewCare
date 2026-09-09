<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF(s) ADICIONAL(es) tras el back — algunas producciones adjuntan documentos extra al llamado
 * (guiones de escena, mapas, avisos). Detrás del flag `callsheet_extra_docs`. Se unen DESPUÉS del
 * back: [front → back → adicionales]. Aditivo: columna nueva nullable, no toca nada existente.
 */
class AddExtraDocsToCallPackages extends Migration
{
    public function up()
    {
        if (Schema::hasTable('call_packages') && ! Schema::hasColumn('call_packages', 'extra_docs')) {
            Schema::table('call_packages', function (Blueprint $t) {
                $t->longText('extra_docs')->nullable()->after('frozen_schedule');   // JSON: [{path,name,pages}]
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('call_packages') && Schema::hasColumn('call_packages', 'extra_docs')) {
            Schema::table('call_packages', function (Blueprint $t) {
                $t->dropColumn('extra_docs');
            });
        }
    }
}
