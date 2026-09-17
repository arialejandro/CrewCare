<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRATO · PASO C · B1 — destinatarios de COPIA / entrega-certificada. Espeja el owner-apply
 * 2026-08-16-contract-recipient-copy.sql (la BD real se toca con ESE). Aquí solo para la suite.
 *
 *   delivery_mode — 'sign' (o NULL = firmante) | 'copy' (solo recibe, no firma ni bloquea la ruta).
 *   delivered_at  — cuándo se entregó la copia certificada (al completarse el sobre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_envelope_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_envelope_recipients', 'delivery_mode')) {
                $table->string('delivery_mode', 10)->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('contract_envelope_recipients', 'delivered_at')) {
                $table->dateTime('delivered_at')->nullable()->after('signed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_envelope_recipients', function (Blueprint $table) {
            foreach (['delivery_mode', 'delivered_at'] as $col) {
                if (Schema::hasColumn('contract_envelope_recipients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
