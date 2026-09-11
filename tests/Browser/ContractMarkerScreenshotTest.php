<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CAPTURA del MARCADOR DE CONTRATO en el Crew List (/usuarioscrud): la barra de chips filtro+conteo
 * ("Sin contrato N" / "Contrato incompleto N") y el marcador junto a cada persona; más la vista ya
 * FILTRADA a "les falta contrato". NETO-CERO: no crea ni borra nada, sólo navega (solo lectura).
 * Corre contra la base real (corpus demo).
 *
 * Correr: php artisan dusk --filter=ContractMarkerScreenshotTest
 * Capturas: tests/Browser/screenshots/crew-marcador-contrato.png · crew-marcador-filtrado.png
 */
class ContractMarkerScreenshotTest extends DuskTestCase
{
    public function test_captura_marcador_y_filtro(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();

        // (1) Crew List completo: chips de filtro con conteo + marcador por persona.
        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->resize(1360, 980)
                ->visit('/usuarioscrud')
                ->pause(700)
                ->screenshot('crew-marcador-contrato')   // captura ANTES de asertar (queda pase o falle)
                ->assertSee('miembros activos')
                ->assertSee('Contrato incompleto');       // chip de filtro (no va en mayúsculas)
        });

        // (2) Filtrado a los que les falta capturar el trato.
        $this->browse(function (Browser $browser) {
            $browser->resize(1360, 980)
                ->visit('/usuarioscrud?contract=incompleto')
                ->pause(600)
                ->screenshot('crew-marcador-filtrado')
                ->assertSee('miembros activos');
        });
    }
}
