<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · PASO 2 — sinónimos de búsqueda en el catálogo ("Opinión SAT" = 32-D),
 * para que el usuario encuentre el documento con cualquiera de los dos nombres. Espejo de owner-apply.
 */
class AddAliasesToDocumentTypes extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE `document_types`
  ADD COLUMN `aliases` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`
SQL);
    }

    public function down()
    {
        Schema::table('document_types', function ($table) {
            $table->dropColumn('aliases');
        });
    }
}
