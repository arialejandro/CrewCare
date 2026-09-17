<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TANDA 1 — verifica que los handlers convertidos de `on*=` a `addEventListener` SIGUEN
 * disparando (el listener quedó enganchado). NO muta la base: sólo comprueba el efecto de UI
 * (veta el submit, abre el alta, agrega/quita fila, pide confirmación y se CANCELA). La lógica
 * de negocio ya la cubre PHPUnit.
 *
 * Cubre: componentes/_consulta-campos (agregar/quitar medicamento), componentes/_medical-directory
 * (botón "registrar" re-inyectado por AJAX → ccOpenRegister), admin/medicocrud (buscador que no
 * envía) y perfil/sesiones (confirmar antes de cerrar sesiones).
 *
 * Correr: php artisan dusk --filter=CspHandlersBatch1Test
 */
class CspHandlersBatch1Test extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** medicocrud: el form del buscador NO navega al enviar (data-search-noop veta el submit). */
    public function test_buscador_medico_no_envia(): void
    {
        $medic = $this->findUser('test.medico@crewcare.test');
        $this->assertNotNull($medic, 'falta el usuario medic de prueba');

        $this->browse(function (Browser $browser) use ($medic) {
            $browser->loginAs($medic)->visit('/medicocrud')->pause(600)
                ->assertPresent('form[data-search-noop]')
                ->script('window.__noNav = true;');
            // Un marcador en window: si el form ENVIARA, la página recargaría/​navegaría y lo borraría.
            $browser->type('#search', 'zzz')
                ->keys('#search', '{enter}')
                ->pause(600);
            $survived = $browser->script('return window.__noNav === true;');
            $this->assertTrue((bool) ($survived[0] ?? false), 'el buscador envió el form (navegó): el veto no corrió');
        });
    }

    /** _medical-directory: el botón "registrar" del estado vacío (inyectado por AJAX) abre #reg-nocrew. */
    public function test_boton_registrar_abre_alta(): void
    {
        $medic = $this->findUser('test.medico@crewcare.test');
        $this->assertNotNull($medic, 'falta el usuario medic de prueba');

        $this->browse(function (Browser $browser) use ($medic) {
            $browser->loginAs($medic)->visit('/medicocrud')->pause(600)
                ->type('#search', 'zzznadiexyz999')
                ->pause(1000)                              // debounce 300ms + AJAX
                ->waitFor('[data-cc-open-register]', 6)
                ->click('[data-cc-open-register]')
                ->pause(400);
            $open = $browser->script("return !!(document.getElementById('reg-nocrew') && document.getElementById('reg-nocrew').open);");
            $this->assertTrue((bool) ($open[0] ?? false), 'ccOpenRegister no abrió #reg-nocrew');
        });
    }

    /** _consulta-campos: agregar y quitar fila de medicamento (delegación data-med-add/-del). */
    public function test_agregar_y_quitar_fila_medicamento(): void
    {
        $medic = $this->findUser('test.medico@crewcare.test');
        $this->assertNotNull($medic, 'falta el usuario medic de prueba');

        $this->browse(function (Browser $browser) use ($medic) {
            $browser->loginAs($medic)->visit('/medicocrud')->pause(600)
                ->waitFor('a[href*="/consulta/"]', 6)
                ->click('a[href*="/consulta/"]')
                ->waitFor('#medRows', 6)
                ->pause(300);

            $before = count($browser->elements('#medRows tr'));
            // scrollIntoView: la barra de acción sticky (bottom:0) puede tapar el botón según el scroll.
            $browser->scrollIntoView('[data-med-add]')->pause(250)->click('[data-med-add]')->pause(300);
            $this->assertSame($before + 1, count($browser->elements('#medRows tr')), 'data-med-add no agregó fila');

            $browser->scrollIntoView('#medRows tr:last-child [data-med-del]')->pause(250)
                ->click('#medRows tr:last-child [data-med-del]')->pause(300);
            $this->assertSame($before, count($browser->elements('#medRows tr')), 'data-med-del no quitó fila');
        });
    }

    /** perfil/sesiones: cerrar otras sesiones PIDE confirmación (data-confirm) y se puede CANCELAR sin mutar. */
    public function test_cerrar_sesiones_pide_confirmacion(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/profile/sesiones')->pause(500)
                ->assertPresent('form[data-confirm]')
                ->press('Cerrar las demás sesiones')
                ->assertDialogOpened('¿Cerrar todas las demás sesiones? Tendrás que volver a iniciar sesión en esos dispositivos.')
                ->dismissDialog()                          // CANCELA → no muta nada
                ->pause(300)
                ->assertPathIs('/profile/sesiones');
        });
    }
}
