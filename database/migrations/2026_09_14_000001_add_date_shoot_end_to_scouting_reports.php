<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RANGO DE RODAJE EN EL SCOUTING — una locación puede ocupar varios días.
 *
 * `date_shoot` guardaba UN día. En campo eso es la excepción, no la regla: una locación se ocupa
 * "del 14 al 17" y declarar un solo día obliga a mentir o a duplicar el scouting. Esta columna es
 * el FIN del rango; `date_shoot` pasa a ser el inicio. Vacía = un solo día, que es como se
 * comporta todo lo ya capturado.
 *
 * ── POR QUÉ ES SEGURO HOY, Y POR QUÉ NO LO SERÁ MAÑANA ──────────────────────────────────────
 * `scouting_reports` usa HasDigitalSignatures: el sello se calcula sobre la fila ENTERA, así que
 * una columna nueva mueve el hash de TODO lo ya firmado y lo vuelve "ALTERADO". Se verificó contra
 * producción antes de escribir esto: en flor.crewcare.mx hay UNA sola firma en toda la instancia y
 * es de un DailyReport — CERO scoutings sellados. La ventana está abierta.
 *
 * Aun así el modelo la declara en NULLABLE_HASH_EXCLUDES, que es el cinturón además del tirante:
 * mientras valga null queda FUERA del payload, así que aunque mañana existan scoutings sellados
 * sin rango, su hash no se mueve. En cuanto la columna lleva valor entra al hash y el rango queda
 * sellado como cualquier otro dato. Ver la doctrina completa en HasDigitalSignatures.
 *
 * ADITIVA e IDEMPOTENTE: no toca datos, no reescribe nada, y correrla dos veces no falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('scouting_reports', 'date_shoot_end')) {
            return;
        }

        Schema::table('scouting_reports', function (Blueprint $table) {
            $table->date('date_shoot_end')->nullable()->after('date_shoot');
        });
    }

    public function down(): void
    {
        // ⛔ A PROPÓSITO NO HACE NADA. Quitar una columna de una tabla SELLADA vuelve "ALTERADO"
        // todo lo que ya se firmó CON ella — exactamente igual que añadirla. Si esta migración
        // llegó a producción y alguien selló un scouting con rango, el rollback destruiría esa
        // prueba en silencio. Revertir es una decisión del owner, a mano y con los sellos a la
        // vista; no algo que ejecute un `migrate:rollback` de paso.
    }
};
