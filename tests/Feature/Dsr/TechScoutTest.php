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
    /**
     * En ESTE módulo, actuar como alguien implica estar en LOCACIONES.
     *
     * Desde el 2026-09-16 el Tech Scout no lo abre un permiso sino el DEPARTAMENTO (decisión del
     * owner; ver App\Support\LocationsAccess). Se sobreescribe el ayudante para que las pruebas
     * de comportamiento sigan hablando de lo suyo —notas, autoría, documento— y no de accesos.
     * El aislamiento tiene sus propias pruebas más abajo, explícitas.
     */
    protected function actingAsRole(string $role, array $attrs = []): \App\Models\User
    {
        $user = parent::actingAsRole($role, $attrs);
        $this->ponerEnLocaciones($user);

        return $user;
    }

    /** Fila de pivote que mete al usuario en Locaciones (fuente de verdad de ownDepartmentIds). */
    private function ponerEnLocaciones(\App\Models\User $user): void
    {
        $dept = \App\Models\Department::whereRaw('LOWER(name) LIKE ?', ['%locacion%'])->value('id');
        $this->assertNotNull($dept, 'La fábrica debe sembrar el departamento de Locaciones.');

        \Illuminate\Support\Facades\DB::table('production_user')->updateOrInsert(
            ['production_id' => (int) \App\Support\CurrentProduction::get()->id, 'user_id' => $user->id],
            ['department_id' => $dept, 'role' => 'crew', 'is_lead' => false,
             'created_at' => now(), 'updated_at' => now()]
        );
    }

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

    // ────────────────────────────────────────────────────────────────────────────────────────
    // AISLAMIENTO POR DEPARTAMENTO (owner, 2026-09-16)
    //
    // «Que eso sólo lo vea quien esté en el departamento de Locaciones, no importa su puesto.»
    // Es un criterio distinto al del resto de la app —permisos— y por eso se prueba por los dos
    // lados: que el de fuera NO entra aunque tenga rango, y que el de dentro SÍ aunque no tenga
    // ningún permiso de locaciones. Una prueba sola de las dos se puede pasar sin querer.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_quien_no_es_de_locaciones_no_entra_aunque_tenga_permiso(): void
    {
        // safety-officer TIENE `locations.create` — con la regla vieja entraba de sobra. Se usa
        // el ayudante de la clase base a propósito: este usuario NO pasa por Locaciones.
        $fuera = parent::actingAsRole('safety-officer');
        $this->assertTrue($fuera->can('locations.create'),
            'si este rol pierde el permiso, la prueba deja de demostrar lo que dice demostrar.');

        $this->get(route('techscout.index'))->assertForbidden();
        $this->get(route('techscout.create'))->assertForbidden();
        $this->post(route('techscout.store'), ['location_name' => 'Bodega ajena'])->assertForbidden();
    }

    public function test_ni_siquiera_el_documento_ni_las_notas_de_otro_departamento(): void
    {
        // Lo crea alguien de Locaciones…
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido(['location_name' => 'Casa Narvarte']);

        // …y lo intenta abrir alguien de fuera. Incluye el DOCUMENTO y la rejilla de notas: son
        // rutas de LECTURA, justo las que se olvidan al cerrar un módulo.
        parent::actingAsRole('line-producer');
        $this->get(route('techscout.show', $scout->id))->assertForbidden();
        $this->get(route('techscout.document', $scout->id))->assertForbidden();
        $this->get(route('techscout.notes', $scout->id))->assertForbidden();
    }

    public function test_el_departamento_basta_sin_ningun_permiso_de_locaciones(): void
    {
        // Un P.A. de Locaciones no trae permisos de nada: el puesto no importa, el departamento sí.
        $pa = $this->makeUser('crew');
        $this->actingAs($pa);
        $this->ponerEnLocaciones($pa);

        $this->assertFalse($pa->can('locations.create'),
            'el sentido de esta prueba es que entre SIN permiso; si lo tiene, no prueba nada.');

        $this->get(route('techscout.index'))->assertOk();
        $this->post(route('techscout.store'), ['location_name' => 'Depa Pablo'])
            ->assertSessionHasNoErrors();
    }

    /**
     * 🪤 EL ENLACE DEL MENÚ, que es por donde se entra de verdad.
     *
     * El 2026-09-16 se dio de alta al primer scouter (rol `crew`, departamento Locaciones) y NO
     * veía el Tech Scout: el enlace estaba bien gateado por departamento, pero vivía dentro de la
     * sección "Locaciones" del menú, que sólo se pintaba con permisos `locations.*`. Una puerta
     * buena dentro de una puerta cerrada. Poder ENTRAR por la URL no basta: si no está en el
     * menú, para quien lo usa el módulo no existe.
     */
    public function test_el_scouter_ve_la_entrada_en_el_menu(): void
    {
        $pa = $this->makeUser('crew');
        $this->actingAs($pa);
        $this->ponerEnLocaciones($pa);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('techscout.index'), false);
    }

    /**
     * 🪤 EL SCOUTER VE LO SUYO, Y NADA MÁS.
     *
     * Entra al panel por PERTENENCIA al departamento, no por permisos de oficina — así que el menú
     * no puede ofrecerle el resto de la casa. El owner lo marcó al ver el primer alta: el Panel SFX
     * («no es viable en este proyecto») y Contratos («tampoco es de su departamento»). No es sólo
     * orden: la ruta del Panel SFX en vivo NO pide permiso —la abre cualquiera con sesión— y en
     * producción el módulo de contratos ni siquiera está desplegado.
     */
    public function test_el_scouter_no_ve_el_resto_de_la_casa_en_el_menu(): void
    {
        $pa = $this->makeUser('crew');
        $this->actingAs($pa);
        $this->ponerEnLocaciones($pa);

        $home = $this->get(route('home'))->assertOk();

        $home->assertDontSee(route('sfx.index'), false);
        $home->assertDontSee('Panel SFX');
        if (\Illuminate\Support\Facades\Route::has('contracts.index')) {
            $home->assertDontSee(route('contracts.index'), false);
        }

        // Lo SUYO sigue estando: su módulo y el autoservicio de siempre (reportar lo que ve).
        $home->assertSee(route('techscout.index'), false);
    }

    /** Y a quien sí es de la casa no se le quita nada: el permiso manda como siempre. */
    public function test_a_quien_tiene_permisos_no_se_le_esconde_nada(): void
    {
        parent::actingAsRole('super-admin');

        $this->get(route('home'))->assertOk()->assertSee(route('sfx.index'), false);
    }

    /** Y quien no es de Locaciones NO lo ve en el menú, aunque la pantalla cargue igual. */
    public function test_quien_no_es_de_locaciones_no_ve_la_entrada_en_el_menu(): void
    {
        parent::actingAsRole('safety-officer');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('techscout.index'), false);
    }

    public function test_el_super_admin_conserva_la_llave(): void
    {
        // Única excepción, y deliberada: hoy en producción el owner es el único usuario y no está
        // en ningún departamento. Sin esto, el cambio lo dejaría fuera de su propio módulo.
        parent::actingAsRole('super-admin');
        $this->get(route('techscout.index'))->assertOk();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // REFRESCO PERIÓDICO — dos scouters en la misma locación
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_la_rejilla_sola_trae_las_notas_y_cambia_de_firma_al_entrar_una(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->post(route('techscout.note.store', $scout->id), ['note' => 'Cambiar la cerradura'])
            ->assertSessionHasNoErrors();

        $antes = $this->get(route('techscout.notes', $scout->id));
        $antes->assertOk()->assertSee('Cambiar la cerradura');

        // La FIRMA es lo que decide si el navegador reemplaza la rejilla. Si no cambiara al
        // entrar una nota, el refresco quedaría mudo y nadie vería lo del otro scouter — que es
        // exactamente el problema que esta pieza existe para resolver.
        $firmaAntes = $this->firmaDe($antes->getContent());
        $this->assertNotSame('', $firmaAntes, 'la rejilla debe publicar su firma.');

        $this->post(route('techscout.note.store', $scout->id), ['note' => 'El vecino ensaya batería'])
            ->assertSessionHasNoErrors();

        $despues = $this->get(route('techscout.notes', $scout->id));
        $despues->assertOk()->assertSee('El vecino ensaya batería');
        $this->assertNotSame($firmaAntes, $this->firmaDe($despues->getContent()),
            'con una nota más, la firma tiene que cambiar.');
    }

    public function test_la_nota_del_otro_scouter_aparece_en_la_rejilla_compartida(): void
    {
        $a = $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido(['location_name' => 'Bodega Vallejo']);

        $b = $this->actingAsRole('line-producer');
        $this->post(route('techscout.note.store', $scout->id), ['note' => 'Falta luz en el pasillo'])
            ->assertSessionHasNoErrors();

        // A pregunta por la rejilla y ve lo de B sin recargar la pantalla entera.
        $this->actingAs($a);
        $this->get(route('techscout.notes', $scout->id))
            ->assertOk()
            ->assertSee('Falta luz en el pasillo');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // LA NOTA NO SE PIERDE — los dos contratos del servidor de los que depende la cola
    //
    // La captura guarda la nota (con su foto) en el DISPOSITIVO antes de tocar la red, y de ahí
    // la envía. Ese envío diferido se apoya en dos comportamientos del servidor que, si cambian,
    // hacen perder notas EN SILENCIO. Por eso están fijados aquí y no sólo en el JavaScript.
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * 🔑 REINTENTAR NO DUPLICA. Si la red se corta después de que el servidor creó la nota pero
     * antes de que la respuesta llegue, el dispositivo reintenta con la MISMA llave. Sin esta
     * garantía, salir del penal con 20 notas encoladas podría dejar 40.
     */
    public function test_reenviar_la_misma_nota_no_la_duplica(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $llave = 'techscout-note-prueba-123';
        $envio = ['note' => 'Falta contacto en la cocina'];

        $this->withHeaders(['X-Idempotency-Key' => $llave])
            ->post(route('techscout.note.store', $scout->id), $envio);
        $this->withHeaders(['X-Idempotency-Key' => $llave])
            ->post(route('techscout.note.store', $scout->id), $envio);

        $this->assertSame(1, $scout->notes()->count(),
            'dos envíos con la misma llave son UNA nota, no dos.');
    }

    /**
     * 🔑 UNA NOTA VACÍA SE RECHAZA CON 422, NO CON UN 302.
     *
     * Para la cola de envío, un 3xx es ÉXITO (el store redirige al crear) → con una redirección
     * borraría el borrador dando la nota por buena, y con él la FOTO. Rechazar con 422 es lo que
     * hace que la nota se conserve en el dispositivo con su error a la vista.
     */
    public function test_la_nota_vacia_se_rechaza_con_422_para_quien_espera_json(): void
    {
        $this->actingAsRole('safety-officer');
        $scout = $this->nuevoRecorrido();

        $this->postJson(route('techscout.note.store', $scout->id), ['note' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');

        // Y al navegador de siempre se le sigue contestando con la redirección de toda la vida.
        $this->post(route('techscout.note.store', $scout->id), ['note' => ''])
            ->assertStatus(302)
            ->assertSessionHasErrors('note');
    }

    /** Extrae el valor de `data-ts-sig` del fragmento (lo que compara el refresco). */
    private function firmaDe(string $html): string
    {
        return preg_match('/data-ts-sig="([^"]*)"/', $html, $m) ? $m[1] : '';
    }
}
