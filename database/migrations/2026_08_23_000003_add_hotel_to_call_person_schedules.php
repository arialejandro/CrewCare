<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel/hospedaje por PERSONA para el back (columna HOTEL de los formatos internacionales tipo
 * PSI/LU). Aditivo: clave corta nullable (p. ej. "FS", "SH") que casa con la leyenda de hoteles del
 * pie; vacío = columna en blanco (rellenable). Singleton por producción, igual que el resto de la fila.
 */
class AddHotelToCallPersonSchedules extends Migration
{
    public function up()
    {
        if (Schema::hasTable('call_person_schedules') && ! Schema::hasColumn('call_person_schedules', 'hotel_code')) {
            Schema::table('call_person_schedules', function (Blueprint $t) {
                $t->string('hotel_code', 24)->nullable()->after('pickup_place_text');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('call_person_schedules') && Schema::hasColumn('call_person_schedules', 'hotel_code')) {
            Schema::table('call_person_schedules', function (Blueprint $t) {
                $t->dropColumn('hotel_code');
            });
        }
    }
}
