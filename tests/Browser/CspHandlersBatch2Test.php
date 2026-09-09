<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TANDA 2 (CRUDs admin) — verifica los handlers convertidos de `on*=` a addEventListener:
 *   · catalogo: toggle "+Departamento" (data-cc-toggle → ccToggle delegado) muestra el panel.
 *   · catalogo: "Desactivar" (data-confirm → _confirm-submit) PIDE confirmación; se CANCELA.
 *   · usuarioscrud: el buscador (data-search-noop) NO envía el form.
 * NO muta la base: los confirms se cancelan; el buscador solo busca (GET).
 *
 * Correr: php artisan dusk --filter=CspHandlersBatch2Test
 */
class CspHandlersBatch2Test extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** catalogo: el botón "+Departamento" (data-cc-toggle) muestra el panel de alta. */
    public function test_catalogo_toggle_muestra_panel(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/catalogo')->pause(600);

            $before = $browser->script("return getComputedStyle(document.getElementById('cc-new-dept')).display;");
            $this->assertSame('none', $before[0] ?? null, 'el panel debería iniciar oculto');

            $browser->click('[data-cc-toggle="cc-new-dept"]')->pause(300);
            $after = $browser->script("return getComputedStyle(document.getElementById('cc-new-dept')).display;");
            $this->assertNotSame('none', $after[0] ?? 'none', 'el toggle no mostró el panel');
        });
    }

    /** catalogo: "Desactivar" (data-confirm) pide confirmación; se CANCELA (no muta). */
    public function test_catalogo_desactivar_pide_confirmacion(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/catalogo')->pause(600)
                ->scrollIntoView('.cc-mini--danger')
                ->click('.cc-mini--danger')->pause(300);

            // El submit se vetó en captura por _confirm-submit → hay diálogo. Lo leemos y CANCELAMOS.
            $alert = $browser->driver->switchTo()->alert();
            $this->assertStringContainsString('Desactivar', $alert->getText());
            $alert->dismiss();

            $browser->pause(200)->assertPathIs('/catalogo');   // canceló → no navegó, no mutó
        });
    }

    /** usuarioscrud: el form del buscador NO navega al enviar (data-search-noop veta el submit). */
    public function test_buscador_usuarios_no_envia(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/usuarioscrud')->pause(600)
                ->assertPresent('form[data-search-noop]')
                ->script('window.__noNav = true;');
            $browser->type('#search', 'zzz')->keys('#search', '{enter}')->pause(500);
            $survived = $browser->script('return window.__noNav === true;');
            $this->assertTrue((bool) ($survived[0] ?? false), 'el buscador envió el form (navegó): el veto no corrió');
        });
    }
}
