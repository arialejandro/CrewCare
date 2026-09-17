<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EL CONTRATO · PASO C · FASE 2 — CAMINOS DE ESCAPE del sobre. Da al sobre las salidas que "definen
 * si el sistema se usa": rechazar (el firmante se niega, con motivo), anular (con motivo), reenviar
 * (recordatorio) y vencer (barrido). Cada transición escribe su evento en la bitácora (Fase 1).
 *
 *  - `expires_at`        — fecha límite; se fija al ENVIAR. NULL en sobres viejos (el barrido cae a
 *                          sent_at + ventana).
 *  - `declined_at`       — cuándo un firmante rechazó (status='declined').
 *  - `expired_at`        — cuándo el barrido lo marcó vencido (status='expired').
 *  - `resolution_reason` — motivo humano de anular/rechazar (copia del que va en el evento).
 *
 * NINGUNA entra al hash del sello (todas en $signatureExcludes del modelo): metadato de ruta, no el
 * documento → los sobres YA sellados NO se vuelven "alterados". ADITIVO/NULLABLE.
 *
 * Espejo del owner-apply 2026-08-15-contract-envelope-escape-paths.sql (que corre FUERA de Laravel
 * en la BD real). Esta migración existe para la BD de PRUEBAS (migrate:fresh) y para paridad.
 */
class AddEscapePathsToContractEnvelopes extends Migration
{
    public function up()
    {
        Schema::table('contract_envelopes', function (Blueprint $table) {
            if (! Schema::hasColumn('contract_envelopes', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('contract_envelopes', 'declined_at')) {
                $table->dateTime('declined_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('contract_envelopes', 'expired_at')) {
                $table->dateTime('expired_at')->nullable()->after('declined_at');
            }
            if (! Schema::hasColumn('contract_envelopes', 'resolution_reason')) {
                $table->string('resolution_reason', 500)->nullable()->after('expired_at');
            }
        });
    }

    public function down()
    {
        Schema::table('contract_envelopes', function (Blueprint $table) {
            foreach (['expires_at', 'declined_at', 'expired_at', 'resolution_reason'] as $col) {
                if (Schema::hasColumn('contract_envelopes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
