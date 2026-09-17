<?php

namespace Tests\Feature\Dsr;

use App\Models\ScoutingDraft;
use App\Models\ScoutingReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\QaTestCase;

/**
 * BORRADOR DE SCOUTING EN SERVIDOR — las fotos suben en cuanto se capturan.
 *
 * ============================ POR QUÉ EXISTE ============================
 * El 2026-09-14, con la producción REAL rodando, se perdió un scouting con más de 20 fotos al
 * cerrarse la vista por error. El borrador local (cc-drafts.js) guarda el texto en IndexedDB, pero
 * su propia cabecera lo advierte: «el borrador NO captura <input type=file>». Las fotos vivían
 * únicamente en la memoria de esa pestaña.
 *
 * Esto no es una comodidad: un scouting se llena CAMINANDO una locación, y rehacerlo significa
 * volver al sitio. Las pruebas de abajo cubren las dos mitades del contrato — que el trabajo
 * SOBREVIVA, y que nadie pueda colar una foto que no subió.
 */
class ScoutingDraftTest extends QaTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'location_name'    => 'Locación Draft ' . Str::random(6),
            'location_address' => 'Calle Falsa 123',
            'production_type'  => 'Film',
            'manager_name'     => 'PM QA',
            'safety_rep_name'  => 'Safety QA',
            'nearest_hospital' => 'Hospital QA Norte',
            'status'           => 'draft',
        ], $overrides);
    }

    // =====================================================================
    //  LA RED: las fotos viajan al capturarlas, no al guardar
    // =====================================================================

    public function test_las_fotos_suben_en_lote_y_quedan_en_el_borrador(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        // EN LOTE a propósito: una petición por foto multiplica la espera con la red de un set.
        $res = $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-test-1',
            'photos'     => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
                UploadedFile::fake()->image('c.jpg', 800, 600),
            ],
        ]);

        $res->assertOk();
        $res->assertJson(['ok' => true, 'failed' => 0, 'total' => 3]);

        $draft = ScoutingDraft::forAuthor('sd-test-1', $user->id);
        $this->assertNotNull($draft, 'la primera foto debe CREAR el borrador, sin pedirlo aparte.');
        $this->assertCount(3, $draft->photoList());
    }

    public function test_el_scouting_se_guarda_con_las_fotos_del_borrador_sin_re_subirlas(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-test-2',
            'photos'     => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertOk();

        $rutas = array_column(ScoutingDraft::forAuthor('sd-test-2', $user->id)->photoList(), 'path');

        // El envío final NO lleva ni un archivo: sólo rutas. Eso es lo que vuelve instantáneo el
        // guardado de un scouting con 20 fotos, que antes tardaba un minuto mandando 30 MB.
        $payload = $this->payload([
            'draft_key'              => 'sd-test-2',
            'draft_photos'           => $rutas,
            'draft_photos_captions'  => ['Grieta en muro', ''],
            'draft_photos_riskmap'   => ['1', '0'],
        ]);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $this->assertNotNull($scout);

        $imgs = $scout->additionalImagesList();
        $this->assertCount(2, $imgs, 'las fotos ya subidas deben quedar en el scouting.');
        $this->assertSame($rutas[0], $imgs[0]['path']);
        $this->assertSame('Grieta en muro', $imgs[0]['caption'], 'el pie de foto viaja con la ruta.');
        $this->assertTrue((bool) ($imgs[0]['risk_map'] ?? false), 'y el flag de mapeo también.');
    }

    public function test_el_borrador_se_retira_cuando_el_scouting_ya_existe(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-test-3',
            'photos'     => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk();

        $rutas = array_column(ScoutingDraft::forAuthor('sd-test-3', $user->id)->photoList(), 'path');
        $this->post(route('scoutings.store'), $this->payload([
            'draft_key' => 'sd-test-3', 'draft_photos' => $rutas,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(
            ScoutingDraft::forAuthor('sd-test-3', $user->id),
            'cumplida su función, el borrador se retira: si no, reaparecería al abrir el siguiente.'
        );
    }

    // =====================================================================
    //  EL CARRIL: sólo entran rutas que ESTE autor subió en ESTE borrador
    // =====================================================================

    /**
     * 🔒 La ruta la propone el NAVEGADOR. Sin comprobarla contra el borrador del autor, cualquiera
     * podría mandar una ruta cualquiera y colar como evidencia un archivo que no subió — o apuntar
     * a uno ajeno. En un documento sellable eso no es aceptable, así que se descarta en silencio.
     */
    public function test_una_ruta_que_no_esta_en_el_borrador_se_descarta(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        // 🪤 El borrador tiene que EXISTIR y tener una foto suya. La primera versión de este test
        // mandaba una ruta inventada con una clave SIN borrador: la búsqueda devolvía null, el
        // bloque entero se saltaba y `owns()` no se llamaba nunca. Pasaba en verde con el guardián
        // DESACTIVADO — una prueba de seguridad que no falla cuando debe es peor que no tenerla.
        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-test-4',
            'photos'     => [UploadedFile::fake()->image('mia.jpg')],
        ])->assertOk();

        $mia = array_column(ScoutingDraft::forAuthor('sd-test-4', $user->id)->photoList(), 'path')[0];

        // Se manda la SUYA y una INVENTADA juntas: sólo debe entrar la suya.
        $payload = $this->payload([
            'draft_key'    => 'sd-test-4',
            'draft_photos' => [$mia, '/storage/scouting_images/inventada.jpg'],
        ]);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $imgs  = $scout->additionalImagesList();

        $this->assertCount(1, $imgs, 'la ruta inventada NO puede entrar al documento.');
        $this->assertSame($mia, $imgs[0]['path'], 'y la legítima sí debe entrar.');
    }

    public function test_no_se_pueden_usar_las_fotos_del_borrador_de_otra_persona(): void
    {
        Storage::fake('public');

        // Persona A captura sus fotos.
        $a = $this->actingAsRole('safety-officer');
        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'clave-compartida',
            'photos'     => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk();
        $rutasDeA = array_column(ScoutingDraft::forAuthor('clave-compartida', $a->id)->photoList(), 'path');
        $this->assertNotEmpty($rutasDeA);

        // Persona B usa LA MISMA clave (adivinarla es trivial). Y tiene borrador PROPIO con esa
        // clave y foto propia, para que la búsqueda por autor SÍ encuentre algo y el guardián
        // tenga que decidir de verdad — si no, esto pasaría por la vía muerta del null.
        $b = $this->actingAsRole('line-producer');
        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'clave-compartida',
            'photos'     => [UploadedFile::fake()->image('b.jpg')],
        ])->assertOk();
        $rutasDeB = array_column(ScoutingDraft::forAuthor('clave-compartida', $b->id)->photoList(), 'path');

        $payload = $this->payload([
            'draft_key'    => 'clave-compartida',
            'draft_photos' => array_merge($rutasDeA, $rutasDeB),   // las de A y las suyas
        ]);
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();

        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();
        $rutasFinales = array_column($scout->additionalImagesList(), 'path');

        $this->assertSame($rutasDeB, $rutasFinales,
            'el borrador se acota SIEMPRE por autor: sólo deben entrar SUS fotos, no las de A.');
        foreach ($rutasDeA as $ajena) {
            $this->assertNotContains($ajena, $rutasFinales, 'no se puede usar la foto de otra persona.');
        }
    }

    /**
     * CABLEADO — el servidor puede estar perfecto y no servir de nada si la vista no carga el
     * módulo que sube las fotos. Esta comprobación es barata y caza justo el error que las pruebas
     * de endpoint no ven: que todo funcione "en el backend" mientras el usuario sigue perdiendo
     * su trabajo. Al EDITAR no debe aparecer: ahí las imágenes ya viven en el reporte.
     */
    public function test_la_vista_de_captura_carga_el_modulo_de_borrador(): void
    {
        $this->actingAsRole('safety-officer');

        $res = $this->get(route('scoutings.create'));
        $res->assertOk();
        $res->assertSee('js/cc-scouting-draft.js', false);
        $res->assertSee('id="sd-saved-grid"', false);

        $payload = $this->payload();
        $this->post(route('scoutings.store'), $payload)->assertSessionHasNoErrors();
        $scout = ScoutingReport::where('location_name', $payload['location_name'])->latest('id')->first();

        $edit = $this->get(route('scoutings.edit', ['id' => $scout->id]));
        $edit->assertOk();
        $edit->assertDontSee('js/cc-scouting-draft.js', false);
    }

    /**
     * DESPLIEGUE SIN MIGRAR — la ventana entre copiar los archivos y correr `migrate`.
     *
     * Es un orden perfectamente normal (Plesk copia, la migración va después), y durante esos
     * minutos la tabla no existe. Sin tolerancia, el módulo que existe para NO perder trabajo sería
     * justo el que impide capturarlo: 500 en la pantalla de captura, y —peor— un "No se pudo
     * guardar el reporte" DESPUÉS de haberlo creado, porque la limpieza del borrador reventaba
     * dentro del try. El owner lo daría por perdido y lo capturaría otra vez.
     *
     * Sin tabla, todo debe comportarse como antes de existir esto.
     */
    public function test_sin_la_tabla_todo_sigue_funcionando_como_antes(): void
    {
        Storage::fake('public');
        $this->actingAsRole('safety-officer');
        \Illuminate\Support\Facades\Schema::drop('scouting_drafts');

        $this->get(route('scoutings.create'))->assertOk();   // la captura NO puede caerse

        // Los endpoints responden sin romper; el navegador conserva sus fotos y las manda al guardar.
        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sin-tabla',
            'photos'     => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk()->assertJson(['ok' => false]);

        // Y guardar debe funcionar Y reportarse como éxito, aunque el borrador no exista.
        $payload = $this->payload(['draft_key' => 'sin-tabla']);
        $res = $this->post(route('scoutings.store'), $payload);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success');

        $this->assertNotNull(
            ScoutingReport::where('location_name', $payload['location_name'])->first(),
            'el scouting se guarda igual: la tabla del borrador no es un requisito para capturar.'
        );
    }

    // =====================================================================
    //  RECUPERAR — la mitad que faltaba, y sin la cual todo lo demás es una trampa
    // =====================================================================

    /**
     * 🔥 EL BUG DEL 2026-09-16. Las fotos subían bien… y no había NINGUNA forma de que volvieran a
     * un scouting: `create()` calculaba los borradores y la vista los ignoraba. El owner capturó 27
     * fotos, guardó, y el scouting salió VACÍO — con las 27 vivas en disco y sin dueño.
     *
     * Guardar sin poder recuperar es peor que no guardar: promete una red que no existe. Un
     * mecanismo de recuperación sólo está probado cuando se prueba RECUPERANDO.
     */
    public function test_al_volver_a_entrar_las_fotos_del_borrador_se_recuperan(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-recuperar',
            'photos'     => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertOk();

        // Se cierra la pestaña. Al volver, el navegador pide lo suyo con su clave.
        $res = $this->getJson(route('scoutings.draft.show', ['client_key' => 'sd-recuperar']));
        $res->assertOk();
        $res->assertJsonCount(2, 'photos');

        $rutas = array_column($res->json('photos'), 'path');
        $this->assertSame(
            array_column(ScoutingDraft::forAuthor('sd-recuperar', $user->id)->photoList(), 'path'),
            $rutas,
            'deben volver EXACTAMENTE las que se subieron.'
        );
    }

    public function test_no_se_recuperan_las_fotos_del_borrador_de_otra_persona(): void
    {
        Storage::fake('public');

        $this->actingAsRole('safety-officer');
        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'misma-clave',
            'photos'     => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk();

        $this->actingAsRole('line-producer');
        $this->getJson(route('scoutings.draft.show', ['client_key' => 'misma-clave']))
            ->assertOk()
            ->assertJsonCount(0, 'photos');
    }

    /**
     * 🪤 EL SEGUNDO ESLABÓN del mismo desastre: al guardar, el borrador se borraba A CIEGAS. Como
     * el formulario no mandó sus fotos —no había cómo—, el guardado destruyó la ÚNICA referencia
     * que quedaba a esos archivos y los dejó huérfanos para siempre.
     *
     * Ahora, si el borrador conserva fotos que NADIE usó, sobrevive al guardado. Que reaparezca un
     * borrador molesta; perder una jornada de fotos, no.
     */
    public function test_un_borrador_con_fotos_sin_usar_sobrevive_al_guardado(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-sin-usar',
            'photos'     => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk();

        // Se guarda un scouting mandando la clave pero NINGUNA foto (el caso que ocurrió).
        $this->post(route('scoutings.store'), $this->payload(['draft_key' => 'sd-sin-usar']))
            ->assertSessionHasNoErrors();

        $vivo = ScoutingDraft::forAuthor('sd-sin-usar', $user->id);
        $this->assertNotNull($vivo, 'el borrador NO puede llevarse por delante fotos que nadie usó.');
        $this->assertCount(1, $vivo->photoList());
    }

    public function test_un_borrador_cuyas_fotos_si_se_usaron_si_se_retira(): void
    {
        Storage::fake('public');
        $user = $this->actingAsRole('safety-officer');

        $this->post(route('scoutings.draft.photos'), [
            'client_key' => 'sd-usado',
            'photos'     => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk();

        $rutas = array_column(ScoutingDraft::forAuthor('sd-usado', $user->id)->photoList(), 'path');
        $this->post(route('scoutings.store'), $this->payload([
            'draft_key' => 'sd-usado', 'draft_photos' => $rutas,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(
            ScoutingDraft::forAuthor('sd-usado', $user->id),
            'cumplida su función, se retira: si no, reaparecería en el siguiente scouting.'
        );
    }

    public function test_el_borrador_exige_sesion(): void
    {
        $this->post(route('scoutings.draft.photos'), ['client_key' => 'x', 'photos' => []])
            ->assertRedirect();   // a login: no hay borradores anónimos
    }
}
