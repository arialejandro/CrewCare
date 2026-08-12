<?php

namespace Tests\Browser;

use App\Models\MedevacPoster;
use App\Models\ScoutingReport;
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
}
