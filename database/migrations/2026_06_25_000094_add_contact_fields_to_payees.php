<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · datos de contacto del contratado para el CONTRATO. Espeja el owner-apply
 * 2026-08-16-payee-contact-legalrep.sql (la BD real se toca con ESE, no con migrate). Aquí solo
 * para que la suite (RefreshDatabase) tenga las columnas.
 *
 *   phone / email        — contacto propio del payee (independiente del user ligado).
 *   legal_representative — representante legal (solo aplica a persona moral).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            if (! Schema::hasColumn('payees', 'phone')) {
                $table->string('phone', 40)->nullable()->after('marital_status');
            }
            if (! Schema::hasColumn('payees', 'email')) {
                $table->string('email', 191)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('payees', 'legal_representative')) {
                $table->string('legal_representative', 200)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            foreach (['phone', 'email', 'legal_representative'] as $col) {
                if (Schema::hasColumn('payees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
