<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PARTE D · MOTOR DE HORARIOS + configuración del llamado + back (2026-08-23).
 *
 * REGLA CENTRAL: todo se guarda como OFFSET (minutos) respecto al LLAMADO GENERAL del día, nunca
 * como hora absoluta. Cambiar el general recalcula todo solo. Los offsets de depto y persona son
 * SINGLETONS por producción (persisten día con día hasta que alguien los cambie; al mover el general
 * el patrón se conserva). Los valores que NO son hora (O/C, D/C, texto libre en horario; N/A, SD, W/N
 * en pick up) se guardan como LITERAL, no como offset.
 *
 * NADIE de esto se sella → no toca ningún hash (el back se firma opcionalmente al exportar, aparte).
 *
 *  - call_places            catálogo de lugares (clave corta + nombre, SIN vigencias).
 *  - call_days              config por producción+fecha: general, jornada, contingente, ubicaciones…
 *  - call_day_meals         servicios de comida del día (offset del general + toggle + lugar + contingente).
 *  - call_dept_offsets      N2 · offset (o literal) por departamento — SINGLETON por producción.
 *  - call_person_schedules  N3 · offset/pick up/marca de comida por persona — SINGLETON por producción.
 */
class CreateCallSheetSchema extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('call_places')) {
            Schema::create('call_places', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('production_id')->index();
                $t->string('code', 24);                 // clave corta ("HRP")
                $t->string('name', 150);                // "Hotel Real de la Paz"
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->unique(['production_id', 'code']);
            });
        }

        if (! Schema::hasTable('call_days')) {
            Schema::create('call_days', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('production_id')->index();
                $t->date('call_date');
                $t->time('general_call')->nullable();          // N1 · el ancla del día
                $t->integer('journey_minutes')->nullable();    // jornada (para el wrap estimado)
                $t->boolean('wrap_estimate_enabled')->default(false);
                $t->unsignedBigInteger('location_place_id')->nullable();
                $t->string('location_text', 190)->nullable();
                $t->unsignedBigInteger('basecamp_place_id')->nullable();
                $t->string('basecamp_text', 190)->nullable();
                $t->text('notes')->nullable();
                $t->integer('cast_count')->nullable();         // contingente cast del día (default de servicios)
                $t->integer('bg_count')->nullable();           // contingente BG del día
                $t->boolean('sign_enabled')->default(false);   // firma del back (default OFF → solo PDF)
                $t->text('footer_extra')->nullable();          // JSON: hoteles, contactos de emergencia, leyendas extra
                $t->timestamps();
                $t->unique(['production_id', 'call_date']);
            });
        }

        if (! Schema::hasTable('call_day_meals')) {
            Schema::create('call_day_meals', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('call_day_id')->index();
                $t->integer('sort_order')->default(0);
                $t->string('label', 80);                       // "Café/Craft", "Comida", …
                $t->boolean('enabled')->default(true);
                $t->integer('offset_minutes')->nullable();     // offset del general
                $t->time('explicit_time')->nullable();         // o una hora fija (gana sobre el offset)
                $t->unsignedBigInteger('place_id')->nullable();
                $t->string('place_text', 120)->nullable();     // set / basecamp / locación
                $t->integer('cast_override')->nullable();      // contingente propio del servicio (si difiere del día)
                $t->integer('bg_override')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('call_dept_offsets')) {
            Schema::create('call_dept_offsets', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('production_id')->index();
                $t->unsignedBigInteger('department_id')->index();
                $t->integer('offset_minutes')->nullable();     // minutos respecto al general (negativo = precall)
                $t->string('literal_value', 40)->nullable();   // O/C, D/C, "Per GC"… (NO se recalcula)
                $t->timestamps();
                $t->unique(['production_id', 'department_id']);
            });
        }

        if (! Schema::hasTable('call_person_schedules')) {
            Schema::create('call_person_schedules', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('production_id')->index();
                $t->unsignedBigInteger('user_id')->index();
                $t->integer('schedule_offset_minutes')->nullable();  // N3 · offset propio (gana sobre el del depto)
                $t->string('schedule_literal', 40)->nullable();      // O/C, D/C, texto libre
                $t->integer('pickup_offset_minutes')->nullable();
                $t->string('pickup_literal', 40)->nullable();        // N/A, SD, W/N
                $t->unsignedBigInteger('pickup_place_id')->nullable();
                $t->string('pickup_place_text', 120)->nullable();
                $t->boolean('meal_mark')->default(true);             // el "#" del back: 1 = come
                $t->timestamps();
                $t->unique(['production_id', 'user_id']);
            });
        }
    }

    public function down()
    {
        foreach (['call_person_schedules', 'call_dept_offsets', 'call_day_meals', 'call_days', 'call_places'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
}
