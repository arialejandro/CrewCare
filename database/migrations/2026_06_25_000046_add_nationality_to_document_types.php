<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · DELTA DE EXTRANJERO — a qué nacionalidad aplica un tipo de documento
 * ('mexicana' | 'extranjera' | NULL = ambas). Deja alternar el paquete para el extranjero
 * (pasaporte/visa/residencia fiscal en vez de INE/CSF/32-D). Espejo de owner-apply.
 */
class AddNationalityToDocumentTypes extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE `document_types`
  ADD COLUMN `nationality` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `legal_nature`
SQL);
    }

    public function down()
    {
        Schema::table('document_types', function ($t) {
            $t->dropColumn('nationality');
        });
    }
}
