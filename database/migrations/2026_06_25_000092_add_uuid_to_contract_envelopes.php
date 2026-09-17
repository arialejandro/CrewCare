<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C · FASE 3 — UUID público del sobre. Identificador estable y no adivinable para
 * la superficie de verificación pública (/verificar/cenv/{uuid}). No reemplaza la PK.
 *
 * `uuid` es VOLÁTIL para el sello (HasDigitalSignatures lo excluye junto a created_at/updated_at) →
 * agregarlo NO invalida ningún sello existente. Backfill para los sobres viejos; los nuevos lo toman
 * del trait GeneratesUuidKey. Espejo del owner-apply 2026-08-15-contract-envelope-uuid.sql.
 */
class AddUuidToContractEnvelopes extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('contract_envelopes', 'uuid')) {
            Schema::table('contract_envelopes', function (Blueprint $table) {
                $table->char('uuid', 36)->nullable()->after('id');
            });
        }

        // Backfill (no-op en instalaciones frescas: la tabla nace vacía).
        DB::statement('UPDATE contract_envelopes SET uuid = (UUID()) WHERE uuid IS NULL');

        $hasIndex = collect(DB::select(
            "SHOW INDEX FROM contract_envelopes WHERE Key_name = 'contract_envelopes_uuid_unique'"
        ))->isNotEmpty();
        if (! $hasIndex) {
            Schema::table('contract_envelopes', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }
    }

    public function down()
    {
        Schema::table('contract_envelopes', function (Blueprint $table) {
            if (Schema::hasColumn('contract_envelopes', 'uuid')) {
                $table->dropUnique('contract_envelopes_uuid_unique');
                $table->dropColumn('uuid');
            }
        });
    }
}
