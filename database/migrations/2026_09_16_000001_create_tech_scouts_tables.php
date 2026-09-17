<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TECH SCOUT — el recorrido técnico del departamento de LOCACIONES.
 *
 * ── QUÉ ES, Y QUÉ NO ────────────────────────────────────────────────────────────────────────
 * Es el documento que hoy se hace a mano: los scouters fotografían la locación y anotan lo que
 * hay que resolver ("quitar las cortinas de esta ventana"). Después descargan las fotos, las
 * pegan en Word y reescriben lo de sus libretas. Eso es lo que este módulo elimina: la nota se
 * escribe JUNTO a la foto, en el momento, y el documento sale en PDF.
 *
 * NO es el Scouting H&S. Aquél evalúa riesgos, se sella y tiene valor probatorio; éste es un
 * documento de TRABAJO para arte y producción. Van por separado a propósito: distinto propósito,
 * distinto ciclo de vida y distinto peso. Mezclarlos habría contaminado el que sí se sella.
 *
 * ── POR QUÉ NO SE SELLA PERO SÍ GUARDA LA HORA ─────────────────────────────────────────────
 * El owner lo usa para zanjar discusiones: «si alguien dice que pidió algo y no está en las
 * notas, es que miente». Eso le da peso probatorio EN LA PRÁCTICA aunque no lleve hash. Sellarlo
 * sería pesado para un documento que se edita a diario, pero si las notas se pudieran cambiar en
 * silencio el argumento se voltearía —cualquiera podría añadir una nota después y decir que la
 * puso en el recorrido—. Por eso cada nota conserva su hora de captura y, si se edita, queda
 * marcada como editada. Cuesta un campo y hace que «no está en las notas» siga significando algo.
 *
 * ── CONCURRENCIA: RESUELTA POR EL MODELO, NO POR CANDADOS ──────────────────────────────────
 * Dos scouters recorren la misma locación a la vez. Si esto fuera UN formulario que ambos
 * guardan, el segundo pisaría al primero — sin error y sin aviso. Por eso el documento no es un
 * formulario: es una LISTA de notas, cada una de quien la puso. Colaborar es añadir. No hay nada
 * que pisar, ni bloqueos, ni versiones que fusionar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tech_scouts')) {
            Schema::create('tech_scouts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();   // identidad pública del documento (pie del PDF)

                $table->unsignedBigInteger('production_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();

                $table->string('location_name', 255);
                $table->string('location_address', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();

                $table->unsignedBigInteger('created_by_id')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tech_scout_notes')) {
            Schema::create('tech_scout_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tech_scout_id')->index();

                $table->string('photo_path', 500)->nullable();
                $table->text('note')->nullable();

                // Etiqueta del GUIÓN, no del espacio físico. Se probó nombrar por área (cocina,
                // fachada…) y no generaliza: una bodega no tiene los cuartos de una casa. Lo que sí
                // vale en ambas es cómo se llama el sitio en la historia ("Depa Pablo"), y sólo hace
                // falta en multilocación — por eso es opcional. La captura recuerda la última usada.
                $table->string('story_label', 120)->nullable();

                $table->unsignedBigInteger('created_by_id')->index();

                // `edited_at` separado de `updated_at`: sólo se marca cuando cambia el CONTENIDO de
                // la nota, no cuando se toca cualquier columna. Es lo que sostiene el "no está en
                // las notas" como argumento.
                $table->timestamp('edited_at')->nullable();

                $table->timestamps();

                // El PDF y la vista van en orden CRONOLÓGICO de captura.
                $table->index(['tech_scout_id', 'created_at'], 'tech_scout_notes_doc_time_index');
            });
        }
    }

    public function down(): void
    {
        // Seguro: ninguna de las dos está sellada ni la referencia ningún documento probatorio.
        Schema::dropIfExists('tech_scout_notes');
        Schema::dropIfExists('tech_scouts');
    }
};
