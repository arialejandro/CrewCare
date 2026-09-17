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

    public function test_el_modulo_exige_sesion(): void
    {
        $this->post(route('techscout.store'), ['location_name' => 'X'])->assertRedirect();
    }
}
