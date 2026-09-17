<?php

namespace Tests\Browser;

use App\Models\ScoutingReport;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TANDA 3 (captura) — handlers convertidos de on*= a addEventListener:
 *   · repetidor de imágenes (data-img-add / data-img-del) en hazard/unsafe/injury (código idéntico;
 *     se prueba hazardnotification como representante).
 *   · scouting: casilla SB132 (data + change delegado → toggleSpecial).
 *   · roster: auto-envío del filtro al cambiar la fecha (data-autosubmit).
 *   · amazon: botón imprimir (data-amz-print → window.print, espiado).
 * NO muta la base: solo DOM y navegación GET.
 *
 * Correr: php artisan dusk --filter=CspHandlersBatch3Test
 */
class CspHandlersBatch3Test extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** hazardnotification: "Agregar otra imagen" y "✕" agregan/quitan un input file (delegación). */
    public function test_repetidor_imagenes(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/hazardnotification')->pause(700)
                ->waitFor('[data-img-add]', 6);

            $before = count($b->elements('input[name="additional_images[]"]'));
            $b->scrollIntoView('[data-img-add]')->pause(150)->click('[data-img-add]')->pause(300);
            $this->assertSame($before + 1, count($b->elements('input[name="additional_images[]"]')), 'data-img-add no agregó campo');

            $b->scrollIntoView('[data-img-del]')->pause(150)->click('[data-img-del]')->pause(300);
            $this->assertSame($before, count($b->elements('input[name="additional_images[]"]')), 'data-img-del no quitó campo');
        });
    }

    /** scouting create: marcar "actividades especiales" muestra el aviso SB132 (change delegado). */
    public function test_scouting_toggle_sb132(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/scoutings/create')->pause(700)
                ->waitFor('#special_activities', 6)   // form pesado: espera a que esté interactivo
                ->assertPresent('#sb132-alert');       // presente aunque inicie display:none

            // Marca la casilla y dispara change (bubbles) → listener delegado → toggleSpecial.
            $b->script("var c=document.getElementById('special_activities'); c.checked=true; c.dispatchEvent(new Event('change',{bubbles:true}));");
            $b->pause(400);
            $disp = $b->script("var a=document.getElementById('sb132-alert'); return a ? getComputedStyle(a).display : 'gone';");
            $this->assertNotSame('gone', $disp[0] ?? 'gone', 'no se encontró #sb132-alert');
            $this->assertNotSame('none', $disp[0] ?? 'none', 'toggleSpecial no mostró el aviso SB132');
        });
    }

    // NOTA: roster (data-autosubmit por fecha) NO se prueba en vivo — /roster hace abort 404
    // salvo con el feature flag `roster_day_view` ON, que está OFF en la BD de prueba. No se
    // mutará para prenderlo. La conversión está verificada en fuente y la cubre el anti-regresión.

    /** amazon: el botón imprimir (data-amz-print) llama window.print (espiado; sin diálogo). */
    public function test_amazon_print(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');
        $sid = ScoutingReport::orderBy('id')->value('id');
        $this->assertNotNull($sid, 'no hay scouting para probar amazon');

        $this->browse(function (Browser $b) use ($admin, $sid) {
            $b->loginAs($admin)->visit('/scoutings/' . $sid . '/amazon')->pause(800)
                ->assertPresent('[data-amz-print]')
                ->assertPresent('[data-amz-pdf]');
            $b->script('window.__printed=false; window.print=function(){window.__printed=true;};');
            $b->click('[data-amz-print]')->pause(250);
            $called = $b->script('return window.__printed===true;');
            $this->assertTrue((bool) ($called[0] ?? false), 'el botón imprimir de amazon no llamó window.print');
        });
    }
}
