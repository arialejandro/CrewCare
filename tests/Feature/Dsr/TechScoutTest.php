<?php

namespace Tests\Feature\Dsr;

use App\Models\TechScout;
use App\Models\TechScoutNote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * TECH SCOUT — recorrido técnico de Locaciones (foto + qué hay que resolver).
 *
 * Sustituye lo que hoy se hace a mano: fotografiar, anotar en libreta, y luego descargar las
 * fotos, pegarlas en Word y reescribir las notas.
 *
 * Lo que estas pruebas fijan es lo que NO se puede romper sin que el módulo pierda su razón de
 * ser: que dos personas trabajen a la vez sin pisarse, que editar deje marca, y que el autor sea
 * dato interno.
 */
class TechScoutTest extends QaTestCase
{
    private function nuevoRecorrido(array $over = []): TechScout
    {
        $this->post(route('techscout.store'), array_merge([
            'location_name'    => 'Bodega ' . Str::random(6),
            'location_address' => 'Calle Falsa 123',
        ], $over))->assertSessionHasNoErrors();

        return TechScout::latest('id')->first();
    }

    public function test_se_inicia_un_recorrido_solo_con_donde_estamos(): void
    {
        $user = $this->actingAsRole('safety-officer');

        $scout = $this->nuevoRecorrido(['location_name' => 'Bodega Vallejo']);

        $this->assertSame('Bodega Vallejo', $scout->location_name);
        $this->assertSame($user->id, (int) $scout->created_by_id);
        $this->assertNotEmpty($scout->uuid, 'el documento necesita identidad pública para el pie del PDF.');
    }

    public function test_una_nota_es_foto_mas_texto_y_etiqueta_opcional(): void
    {
        Storage::fake('public');
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->post(route('techscout.note.store', $scout->id), [
            'note'        => 'Quitar las cortinas de esta ventana',
            'story_label' => 'Depa Pablo',
            'photo'       => UploadedFile::fake()->image('ventana.jpg'),
        ])->assertSessionHasNoErrors();

        $n = TechScoutNote::latest('id')->first();
        $this->assertSame('Quitar las cortinas de esta ventana', $n->note);
        $this->assertSame('Depa Pablo', $n->story_label);
        $this->assertNotNull($n->photo_path);
        $this->assertNull($n->edited_at, 'una nota recién capturada NO está editada.');
    }

    /**
     * La etiqueta se arrastra: en una locación única no se captura nunca, y en multilocación se
     * escribe UNA vez por espacio en lugar de en cada nota.
     */
    public function test_la_etiqueta_de_la_historia_se_prellena_con_la_ultima_usada(): void
    {
        Storage::fake('public');
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->post(route('techscout.note.store', $scout->id), [
            'note' => 'Primera', 'story_label' => 'Depa Pablo',
        ])->assertSessionHasNoErrors();

        $this->get(route('techscout.show', $scout->id))
            ->assertOk()
            ->assertSee('value="Depa Pablo"', false);
    }

    public function test_una_nota_vacia_se_rechaza(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->post(route('techscout.note.store', $scout->id), ['note' => '', 'story_label' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(0, $scout->notes()->count());
    }

    public function test_una_nota_puede_ser_solo_texto_sin_foto(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        // No todo se puede fotografiar: «el vecino ensaya batería por las tardes».
        $this->post(route('techscout.note.store', $scout->id), [
            'note' => 'El vecino ensaya batería por las tardes',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $scout->notes()->count());
        $this->assertNull(TechScoutNote::latest('id')->first()->photo_path);
    }

    // =====================================================================
    //  LO QUE SOSTIENE EL MÓDULO
    // =====================================================================

    /**
     * 🔑 DOS SCOUTERS A LA VEZ, SIN PISARSE. Es la razón de que el documento sea una LISTA y no un
     * formulario: si ambos guardaran un formulario entero, el segundo borraría el trabajo del
     * primero sin error y sin aviso — la misma pérdida silenciosa que ya costó una jornada de fotos.
     */
    public function test_dos_personas_capturan_a_la_vez_sin_pisarse(): void
    {
        $a = $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();
        $this->post(route('techscout.note.store', $scout->id), ['note' => 'Nota de A'])->assertSessionHasNoErrors();

        $b = $this->actingAsRole('line-producer');
        $this->post(route('techscout.note.store', $scout->id), ['note' => 'Nota de B'])->assertSessionHasNoErrors();

        $notas = $scout->fresh()->notes;
        $this->assertCount(2, $notas, 'ninguna de las dos puede desaparecer.');
        $this->assertSame(['Nota de A', 'Nota de B'], $notas->pluck('note')->all(),
            'y el orden es el del recorrido (cronológico).');
        $this->assertSame([$a->id, $b->id], $notas->pluck('created_by_id')->map('intval')->all());
    }

    /**
     * 🪤 EDITAR DEJA MARCA. El owner usa este documento para zanjar discusiones: «si no está en las
     * notas, no se pidió». Ese argumento se voltea si una nota se puede reescribir en silencio
     * después del recorrido. No se sella —sería pesado para algo que se edita a diario—, pero la
     * edición queda a la vista.
     */
    public function test_editar_una_nota_deja_marca_y_reguardar_lo_mismo_no(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();
        $this->post(route('techscout.note.store', $scout->id), ['note' => 'Original'])->assertSessionHasNoErrors();
        $n = TechScoutNote::latest('id')->first();

        // Reguardar lo MISMO no es editar.
        $this->put(route('techscout.note.update', [$scout->id, $n->id]), ['note' => 'Original'])
            ->assertSessionHasNoErrors();
        $this->assertNull($n->fresh()->edited_at, 'guardar sin cambiar nada no debe marcarla.');

        // Cambiar el contenido sí.
        $this->put(route('techscout.note.update', [$scout->id, $n->id]), ['note' => 'Cambiada después'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($n->fresh()->edited_at, 'cambiar el texto DEBE quedar marcado.');
    }

    public function test_nadie_edita_la_nota_de_otra_persona(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();
        $this->post(route('techscout.note.store', $scout->id), ['note' => 'De A'])->assertSessionHasNoErrors();
        $n = TechScoutNote::latest('id')->first();

        $this->actingAsRole('line-producer');
        $this->put(route('techscout.note.update', [$scout->id, $n->id]), ['note' => 'Secuestrada'])
            ->assertForbidden();

        $this->assertSame('De A', $n->fresh()->note);
    }

    /** El autor se ve DENTRO de la app (para que entre ellos se entiendan). En el PDF, no. */
    public function test_la_vista_interna_muestra_quien_reporto_cada_nota(): void
    {
        $user = $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();
        $this->post(route('techscout.note.store', $scout->id), ['note' => 'Una nota'])->assertSessionHasNoErrors();

        $this->get(route('techscout.show', $scout->id))
            ->assertOk()
            ->assertSee(\App\Models\User::displayName($user));
    }

    // =====================================================================
    //  EL DOCUMENTO — lo que sustituye al Word
    // =====================================================================

    /**
     * El pie lo firma **Locaciones**, el DEPARTAMENTO. Y el autor de cada nota NO se imprime: es
     * dato interno para que los scouters se entiendan entre ellos; a arte le llega el documento
     * del departamento, no quién anotó qué. Decisión del owner, y es lo que hay que vigilar —
     * imprimir el nombre por descuido cambiaría a quién señala el documento.
     */
    public function test_el_documento_lo_firma_locaciones_y_no_imprime_al_autor(): void
    {
        $user  = $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido(['location_name' => 'Bodega Vallejo']);
        $this->post(route('techscout.note.store', $scout->id), [
            'note' => 'Quitar las cortinas', 'story_label' => 'Depa Pablo',
        ])->assertSessionHasNoErrors();

        $res = $this->get(route('techscout.document', $scout->id));
        $res->assertOk();

        $res->assertSee('Locaciones');
        $res->assertSee('Quitar las cortinas');
        $res->assertSee('Depa Pablo');
        $res->assertSee('Bodega Vallejo');
        $res->assertSee($scout->uuid);   // identidad del documento en el pie

        // Sigue siendo un documento generado EN CrewCare y debe verse. El pie parte la marca en
        // dos <span> (Crew/Care) y la etiqueta es traducible, así que se asserta lo que de verdad
        // emite el parcial, no el texto en inglés que uno esperaría leer.
        $res->assertSee(__('reports.label_powered_by'));
        $res->assertSee('<span class="crew">Crew</span><span class="care">Care</span>', false);

        $res->assertDontSee(\App\Models\User::displayName($user),
            'el autor de la nota es dato INTERNO: no se imprime en el documento que va a arte.');
    }

    public function test_el_documento_ordena_las_notas_como_se_camino(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();
        foreach (['Primera parada', 'Segunda parada', 'Tercera parada'] as $t) {
            $this->post(route('techscout.note.store', $scout->id), ['note' => $t])->assertSessionHasNoErrors();
        }

        $html = $this->get(route('techscout.document', $scout->id))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Segunda parada'), strpos($html, 'Primera parada'));
        $this->assertLessThan(strpos($html, 'Tercera parada'), strpos($html, 'Segunda parada'));
    }

    // =====================================================================
    //  PANEL GENERAL · viabilidad · acuerdos
    // =====================================================================

    public function test_el_panel_general_se_guarda_y_sale_en_el_documento(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido(['location_name' => 'Casa Pantalla']);

        $this->put(route('techscout.update', $scout->id), [
            'location_name'  => 'Casa Pantalla',
            'loc_setting'    => 'Interior',
            'shoot_time'     => 'Día',
            'date_shoot'     => '2026-10-14',
            'date_shoot_end' => '2026-10-17',
            'viability'      => [['item' => 'Permiso de filmación', 'detail' => 'Lo tramita producción']],
            'agreements'     => [['item' => 'Arte', 'detail' => 'Se retiran las cortinas el día 13']],
        ])->assertSessionHasNoErrors();

        $s = $scout->fresh();
        $this->assertSame('Interior', $s->loc_setting);
        $this->assertSame('Día', $s->shoot_time);
        $this->assertTrue($s->hasShootRange());
        $this->assertCount(1, $s->rows('viability_checklist'));
        $this->assertCount(1, $s->rows('agreements'));

        $doc = $this->get(route('techscout.document', $scout->id))->assertOk();
        $doc->assertSee('Permiso de filmación');
        $doc->assertSee('Se retiran las cortinas el día 13');
        $doc->assertSee('14/10/2026 – 17/10/2026');   // el rango, no sólo el primer día
    }

    /**
     * Las filas se auto-agregan en pantalla, así que llegan vacías casi siempre. Guardarlas
     * ensuciaría el documento que va a arte y haría ruido en la revisión.
     */
    public function test_las_filas_vacias_no_se_guardan(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->put(route('techscout.update', $scout->id), [
            'location_name' => $scout->location_name,
            'viability'     => [
                ['item' => 'Permiso', 'detail' => 'Sí'],
                ['item' => '', 'detail' => ''],
                ['item' => '  ', 'detail' => '   '],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $scout->fresh()->rows('viability_checklist'));
    }

    /**
     * 🪤 Guardar el panel SIN tocar el archivo no puede borrar la portada que ya había. Es el
     * tropiezo clásico de los campos de imagen en formularios que se reguardan seguido.
     */
    public function test_guardar_el_panel_no_borra_la_portada_existente(): void
    {
        Storage::fake('public');
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->put(route('techscout.update', $scout->id), [
            'location_name' => $scout->location_name,
            'hero_image'    => UploadedFile::fake()->image('portada.jpg'),
        ])->assertSessionHasNoErrors();

        $portada = $scout->fresh()->hero_image_path;
        $this->assertNotNull($portada);

        // Segundo guardado SIN archivo: la portada debe seguir ahí.
        $this->put(route('techscout.update', $scout->id), [
            'location_name' => $scout->location_name,
            'shoot_time'    => 'Noche',
        ])->assertSessionHasNoErrors();

        $this->assertSame($portada, $scout->fresh()->hero_image_path,
            'reguardar el panel NO puede llevarse la portada por delante.');
    }

    public function test_el_fin_de_rodaje_no_puede_ser_anterior_al_inicio(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->put(route('techscout.update', $scout->id), [
            'location_name'  => $scout->location_name,
            'date_shoot'     => '2026-10-14',
            'date_shoot_end' => '2026-10-09',
        ])->assertSessionHasErrors('date_shoot_end');
    }

    /**
     * UNA SOLA PANTALLA — crear abre el panel COMPLETO, no un formulario mínimo previo.
     *
     * 🪤 Antes eran dos pantallas y el owner lo marcó: «son 2 pantallas nuevamente». Partir la
     * captura obliga a decidir qué es "lo mínimo" antes de dejar trabajar, y en campo eso es
     * fricción. Lo único que no está hasta guardar son las notas, porque una nota necesita un
     * documento al que pertenecer — y eso se dice en pantalla, no se esconde.
     */
    public function test_crear_abre_el_panel_completo_en_una_sola_pantalla(): void
    {
        $this->actingAsRole('safety-officer');

        $res = $this->get(route('techscout.create'));
        $res->assertOk();

        $res->assertSee('name="location_name"', false);
        $res->assertSee('name="loc_setting"', false);
        $res->assertSee('name="shoot_time"', false);
        $res->assertSee('name="date_shoot"', false);
        $res->assertSee('name="hero_image"', false);
        $res->assertSee('name="viability[0][item]"', false);
        $res->assertSee('name="agreements[0][item]"', false);

        // Lo que el owner retiró NO puede reaparecer.
        $res->assertDontSee('name="production_type"', false);
        $res->assertDontSee('name="manager_name"', false);

        // Y la PRIMERA NOTA se puede escribir ya, sin guardar antes: es el punto de todo esto.
        $res->assertSee('name="note"', false);
        $res->assertSee('name="story_label"', false);
        $res->assertSee('Primera nota');
    }

    /**
     * 🪤 LA REGLA DE ORO DEL MÓDULO: lo único obligatorio es la LOCACIÓN.
     *
     * El owner lo marcó — «se llena mucha información antes de poder emitir notas». En campo se
     * llega, se ve algo que resolver y se anota; permisos y fechas se rellenan después, sentado.
     * Ese primer acto tiene que dejar el scouting guardado, como un borrador: esa capa no se pierde.
     */
    public function test_se_puede_crear_con_la_primera_nota_y_nada_mas(): void
    {
        Storage::fake('public');
        $this->actingAsRole('safety-officer');

        $this->post(route('techscout.store'), [
            'location_name' => 'Casa Pantalla',
            'note'          => 'Quitar las cortinas de esta ventana',
            'photo'         => UploadedFile::fake()->image('ventana.jpg'),
        ])->assertSessionHasNoErrors();

        $s = TechScout::latest('id')->first();
        $this->assertSame('Casa Pantalla', $s->location_name);
        $this->assertCount(1, $s->notes, 'la primera nota debe guardarse EN EL MISMO acto que crea el scouting.');
        $this->assertSame('Quitar las cortinas de esta ventana', $s->notes->first()->note);
        $this->assertNotNull($s->notes->first()->photo_path);
    }

    public function test_crear_sin_nota_sigue_funcionando(): void
    {
        $this->actingAsRole('safety-officer');

        $this->post(route('techscout.store'), ['location_name' => 'Bodega'])->assertSessionHasNoErrors();

        $s = TechScout::latest('id')->first();
        $this->assertSame('Bodega', $s->location_name);
        $this->assertCount(0, $s->notes, 'sin nota no se inventa una vacía.');
    }

    public function test_se_crea_con_todo_el_panel_de_un_solo_envio(): void
    {
        $this->actingAsRole('safety-officer');

        $this->post(route('techscout.store'), [
            'location_name' => 'Bodega Vallejo',
            'loc_setting'   => 'Interior',
            'shoot_time'    => 'Día',
            'date_shoot'    => '2026-11-02',
            'viability'     => [['item' => 'Permiso', 'detail' => 'En trámite']],
        ])->assertSessionHasNoErrors();

        $s = TechScout::latest('id')->first();
        $this->assertSame('Bodega Vallejo', $s->location_name);
        $this->assertSame('Interior', $s->loc_setting);
        $this->assertCount(1, $s->rows('viability_checklist'),
            'el panel entero debe guardarse en el MISMO envío que crea el documento.');
    }

    public function test_el_modulo_exige_sesion(): void
    {
        $this->post(route('techscout.store'), ['location_name' => 'X'])->assertRedirect();
    }
}
