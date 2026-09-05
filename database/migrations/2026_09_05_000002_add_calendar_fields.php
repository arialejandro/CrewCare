<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * CALENDARIO DE PAGOS — dos campos ADITIVOS:
 *  - `production_document_settings.period_label_template`: PLANTILLA configurable de la nomenclatura
 *    de la semana (tokens de fecha, p.ej. `SEM{DD}{MM}{YY}` → SEM060926). Configurable porque cada
 *    productora escribe distinto; el `label` tecleado a mano de cada periodo sigue ganando como escape.
 *  - `payment_periods.announced_at`: marca de que YA se envió el correo de apertura de ESA semana
 *    (idempotencia: el comando diario `periods:announce` no reenvía). NULL = aún no avisado.
 *
 * Guardas hasColumn → idempotente.
 */
class AddCalendarFields extends Migration
{
    public function up()
    {
        if (Schema::hasTable('production_document_settings')
            && ! Schema::hasColumn('production_document_settings', 'period_label_template')) {
            Schema::table('production_document_settings', function (Blueprint $t) {
                $t->string('period_label_template', 160)->nullable()->after('equipment_threshold');
            });
        }

        if (Schema::hasTable('payment_periods')
            && ! Schema::hasColumn('payment_periods', 'announced_at')) {
            Schema::table('payment_periods', function (Blueprint $t) {
                $t->dateTime('announced_at')->nullable()->after('reopened_by_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('production_document_settings', 'period_label_template')) {
            Schema::table('production_document_settings', fn (Blueprint $t) => $t->dropColumn('period_label_template'));
        }
        if (Schema::hasColumn('payment_periods', 'announced_at')) {
            Schema::table('payment_periods', fn (Blueprint $t) => $t->dropColumn('announced_at'));
        }
    }
}
