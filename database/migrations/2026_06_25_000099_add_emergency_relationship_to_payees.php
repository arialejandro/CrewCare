<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUIEN COBRA · intake · parentesco del CONTACTO DE EMERGENCIA. Espeja el owner-apply
 * 2026-08-16-payee-emergency-relationship.sql (la BD real se toca con ESE, no con migrate).
 * Aquí solo para que la suite (RefreshDatabase) tenga la columna.
 *
 *   emergency_contact_relationship — parentesco del contacto de emergencia, SEPARADO del
 *   parentesco del beneficiario (no siempre es la misma persona; se consulta por separado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            if (! Schema::hasColumn('payees', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship', 60)->nullable()->after('emergency_contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payees', function (Blueprint $table) {
            if (Schema::hasColumn('payees', 'emergency_contact_relationship')) {
                $table->dropColumn('emergency_contact_relationship');
            }
        });
    }
};
