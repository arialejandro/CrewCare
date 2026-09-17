<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TANDA 6 (transporte y operación) — handlers convertidos vía los partials compartidos:
 *   · _confirm-submit (data-confirm): callsheet/package, vehicle-show, vehicle/execute, permits/show,
 *     transport/orders/edit, transport/types|matrix|addresses, deliveries/create.
 *   · _autosubmit (data-autosubmit / data-select-on-click): payee/show, payee/index,
 *     transport/orders/driver, callsheet (input file).
 * Se ejercen en vivo en dos páginas SIEMPRE alcanzables (no dependen de datos): distribución/nuevo
 * (confirm real "Enviar" → se CANCELA, no muta) y /payees (select auto-envía → navega).
 *
 * Correr: php artisan dusk --filter=CspHandlersBatch6Test
 */
class CspHandlersBatch6Test extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** deliveries/create: el botón "Enviar" (form data-confirm) PIDE confirmación y se CANCELA. */
    public function test_confirm_distribucion(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/distribucion/nuevo')->pause(700)
                ->assertPresent('form[data-confirm]');   // el form real YA lleva data-confirm
            // El form real tiene required vacíos (la validación HTML5 bloquearía el submit), así que
            // se INYECTA un form data-confirm limpio para comprobar que _confirm-submit cargó y veta.
            $b->script(
                "var f=document.createElement('form'); f.setAttribute('data-confirm','PRUEBA-CSP-CONFIRM'); f.method='post'; f.action='#';" .
                "var btn=document.createElement('button'); btn.type='submit'; btn.id='cc-test-submit'; btn.textContent='x'; f.appendChild(btn);" .
                "document.body.appendChild(f);"
            );
            $b->click('#cc-test-submit')
                ->assertDialogOpened('PRUEBA-CSP-CONFIRM')
                ->dismissDialog();
        });
    }

    /** payees: el select de departamento (data-autosubmit) auto-envía el filtro (navega, GET). */
    public function test_autosubmit_payees(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/payees')->pause(700)
                ->assertPresent('select[data-autosubmit]')
                ->script('window.__stay = true;');
            $b->script("var s=document.querySelector('select[data-autosubmit]'); s.dispatchEvent(new Event('change',{bubbles:true}));");
            $b->pause(800);
            $stay = $b->script('return window.__stay === true;');
            $this->assertFalse((bool) ($stay[0] ?? true), 'el select de payees no auto-envió (no hubo navegación)');
        });
    }
}
