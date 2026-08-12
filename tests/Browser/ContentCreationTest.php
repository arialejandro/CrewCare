<?php

namespace Tests\Browser;

use App\Models\AmbulanceInspection;
use App\Models\EmergencyActionPlan;
use App\Models\hazardnotification;
use App\Models\MedevacPoster;
use App\Models\ScoutingReport;
use App\Models\ToolInspection;
use App\Models\unsafecond;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * FASE 2 del validador: crea contenido LLENANDO los formularios por la UI en un
 * navegador real (no inserts programáticos) → valida form + validación + controlador
 * + sello de punta a punta, y deja contenido demo en la BD. NO resetea la BD.
 *
 * Correr: php artisan dusk --filter=ContentCreationTest
 */
class ContentCreationTest extends DuskTestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@127.0.0.1')->firstOrFail();
    }

    /** MEDEVAC: se emite desde un scouting existente, llenando los 3 contactos por la UI. */
    public function test_emitir_medevac_desde_scouting(): void
    {
        $scouting = ScoutingReport::orderBy('id', 'desc')->firstOrFail();
        $before   = MedevacPoster::count();

        $this->browse(function (Browser $browser) use ($scouting) {
            $browser->loginAs($this->admin())
                ->visit('/medevac/emitir/' . $scouting->id)
                ->pause(700)
                ->screenshot('content-medevac-1-form');

            $contactos = [
                'set_medic'          => ['José Luis Tulio Olmo', '5534537164'],
                'risk_assessment'    => ['Ariadna Ríos Peña', '5512349876'],
                'production_manager' => ['Verónica Salas Lira', '5519518687'],
            ];
            foreach ($contactos as $k => [$name, $phone]) {
                $browser->clear("contacts[$k][name]")->type("contacts[$k][name]", $name)
                        ->clear("contacts[$k][phone]")->type("contacts[$k][phone]", $phone);
            }

            $browser->screenshot('content-medevac-2-filled')
                ->scrollIntoView('button.cc-cta')
                ->click('button.cc-cta')
                ->pause(1800)
                ->screenshot('content-medevac-3-result');
        });

        $this->assertGreaterThan($before, MedevacPoster::count(), 'No se creó el MedevacPoster al emitir por la UI.');
    }

    /** PAE: se emite eligiendo una locación (scouting) y llenando la unidad por la UI. */
    public function test_emitir_pae(): void
    {
        $scouting = ScoutingReport::orderBy('id', 'desc')->firstOrFail();
        $before   = EmergencyActionPlan::count();

        $this->browse(function (Browser $browser) use ($scouting) {
            $browser->loginAs($this->admin())
                ->visit('/pae/emitir')
                ->pause(700)
                ->screenshot('content-pae-1-form')
                ->select('scoutings[]', (string) $scouting->id)
                ->clear('unit_name')->type('unit_name', 'Main')
                ->clear('move_time')->type('move_time', '05:30')
                ->screenshot('content-pae-2-filled')
                ->scrollIntoView('button.cc-cta')
                ->click('button.cc-cta')
                ->pause(1800)
                ->screenshot('content-pae-3-result');
        });

        $this->assertGreaterThan($before, EmergencyActionPlan::count(), 'No se creó el PAE al emitir por la UI.');
    }

    /** SUBIDA DE IMAGEN por la UI: emite un MEDEVAC adjuntando un mapa (input file real). */
    public function test_subir_imagen_medevac_map(): void
    {
        $scouting = ScoutingReport::orderBy('id', 'desc')->firstOrFail();
        $fixture  = base_path('tests/Browser/fixtures/map.png');
        $before   = MedevacPoster::count();

        $this->browse(function (Browser $browser) use ($scouting, $fixture) {
            $browser->loginAs($this->admin())
                ->visit('/medevac/emitir/' . $scouting->id)
                ->pause(700)
                ->attach('map_image', $fixture)   // sube el archivo por el input real
                ->pause(1500)                      // deja correr el JS de cc-photo (compresión/HEIC)
                ->screenshot('content-medevac-img-1-attached')
                ->scrollIntoView('button.cc-cta')
                ->click('button.cc-cta')
                ->pause(2000)
                ->screenshot('content-medevac-img-2-result');
        });

        $this->assertGreaterThan($before, MedevacPoster::count(), 'No se emitió el MEDEVAC con imagen.');

        // El controlador guarda el mapa en el scouting como data URI → prueba que la imagen llegó.
        $map = (string) ScoutingReport::find($scouting->id)->hospital_map;
        $this->assertStringStartsWith('data:image', $map, 'El mapa subido no quedó guardado (data URI).');
    }

    /** ACTA DE AMBULANCIA (2 pasos): elige tipo → checklist + fotos de evidencia → sella. */
    public function test_acta_ambulancia(): void
    {
        $fixture = base_path('tests/Browser/fixtures/map.png');
        $before  = AmbulanceInspection::count();

        $this->browse(function (Browser $browser) use ($fixture) {
            // Paso 1: elegir el tipo de ambulancia (recarga con el checklist).
            $browser->loginAs($this->admin())
                ->visit('/ambulancia/verificar')
                ->pause(700)
                ->select('type_id', '3')
                ->screenshot('content-amb-1-tipo')
                ->click('button.btn-crew-accent')
                ->pause(1300)
                ->screenshot('content-amb-2-checklist');

            // Responder TODO el checklist "ok" (radios ocultos → por JS). script() devuelve
            // array, no es encadenable, así que va en su propia sentencia.
            $browser->script('document.querySelectorAll(\'input[name^="answers"][value="ok"]\').forEach(function(r){r.checked=true;});');

            // Paso 2: datos + fotos + sellar.
            $browser->type('plates', 'PDL4815')
                ->type('economic_number', '245')
                ->type('new_provider_name', 'Ambulancias Vitales')  // requerido: empresa (existente o alta)
                ->attach('unit_photo', $fixture)
                ->pause(1200)
                ->attach('evidence_photos[]', $fixture)
                ->pause(1200)
                ->screenshot('content-amb-3-lleno')
                ->scrollIntoView('button[type="submit"]')
                ->press('Cerrar verificación y sellar')
                ->pause(2200)
                ->screenshot('content-amb-4-result');
        });

        $this->assertGreaterThan($before, AmbulanceInspection::count(), 'No se creó el acta de ambulancia por la UI.');
    }

    /** INSPECCIÓN DE HERRAMIENTA: HER-001 (sierra circular) → checklist + unidad + foto → sella. */
    public function test_acta_inspeccion_herramienta(): void
    {
        $fixture = base_path('tests/Browser/fixtures/map.png');
        $before  = ToolInspection::count();

        $this->browse(function (Browser $browser) use ($fixture) {
            $browser->loginAs($this->admin())
                ->visit('/inspeccion/herramienta/1/inspeccionar')
                ->pause(800)
                ->screenshot('content-insp-1-form')
                ->type('tool_serial', 'SC-2024-0042')
                ->type('owner_name', 'Departamento de Arte')
                ->attach('tool_photo', $fixture)
                ->pause(1200);

            // Departamento (select nativo bajo el typeahead) + checklist "ok", por JS para
            // no depender de si el typeahead oculta el <select>.
            $browser->script("var s=document.querySelector('select[name=department_id]'); if(s){s.value='1'; s.dispatchEvent(new Event('change',{bubbles:true}));}");
            $browser->script('document.querySelectorAll(\'input[name^="answers"][value="ok"]\').forEach(function(r){r.checked=true;});');

            $browser->screenshot('content-insp-2-lleno')
                ->scrollIntoView('button[type="submit"]')
                ->press('Cerrar inspección y sellar')
                ->pause(2000)
                ->screenshot('content-insp-3-result');
        });

        $this->assertGreaterThan($before, ToolInspection::count(), 'No se creó el acta de inspección por la UI.');
    }

    /** ACTO INSEGURO (hazard): name_loc + fecha + descripción + foto → se sella al crearse. */
    public function test_acto_inseguro(): void
    {
        $fixture = base_path('tests/Browser/fixtures/map.png');
        $before  = hazardnotification::count();

        $this->browse(function (Browser $browser) use ($fixture) {
            $browser->loginAs($this->admin())
                ->visit('/hazardnotification')
                ->pause(900)
                ->type('name_loc', '[DEMO] Set A · Pasillo norte')
                ->value('#date_observed', '2026-08-12')
                ->value('#time_observed', '10:30')
                ->type('location_hazard_unsafe_act', 'Set A, pasillo norte junto a la mesa de catering')
                ->type('description_hazard_unsafe_act', 'Operador conectando extensión con las manos mojadas junto a la mesa de catering.')
                ->attach('main_image', $fixture)
                ->pause(1200)
                ->screenshot('content-hazard-1-lleno')
                ->scrollIntoView('button[type="submit"]')
                ->press('Enviar Notificación')
                ->pause(2000)
                ->screenshot('content-hazard-2-result');
        });

        $this->assertGreaterThan($before, hazardnotification::count(), 'No se creó el acto inseguro por la UI.');
    }

    /** CONDICIÓN INSEGURA (gemelo del acto): mismos campos, name_loc + fecha/hora + dónde + descripción + foto. */
    public function test_condicion_insegura(): void
    {
        $fixture = base_path('tests/Browser/fixtures/map.png');
        $before  = unsafecond::count();

        $this->browse(function (Browser $browser) use ($fixture) {
            $browser->loginAs($this->admin())
                ->visit('/unsafenotifications/create')
                ->pause(900)
                ->type('name_loc', '[DEMO] Bodega · Escalera de servicio')
                ->value('#date_observed', '2026-08-12')
                ->value('#time_observed', '14:15')
                ->type('location_unsafe_cond', 'Bodega, escalera de servicio hacia el foro')
                ->type('description_unsafe_cond', 'Piso mojado permanente por fuga en el lavabo de planta baja; sin señalización.')
                ->attach('main_image', $fixture)
                ->pause(1200)
                ->screenshot('content-unsafe-1-lleno')
                ->scrollIntoView('button[type="submit"]')
                ->press('Enviar Notificación')
                ->pause(2000)
                ->screenshot('content-unsafe-2-result');
        });

        $this->assertGreaterThan($before, unsafecond::count(), 'No se creó la condición insegura por la UI.');
    }
}
