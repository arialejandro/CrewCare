<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · MINI-TANDA "documentos standalone" — prueba del mecanismo de FUENTE ÚNICA del nonce en
 * vistas HTML-completo que NO usan layouts.app. Convierte el on* de los 2 print docs
 * (crew-export, historiamr-print) a addEventListener y confirma que:
 *   (a) el <script> recibió el nonce por la fuente única (View::share del middleware), y
 *   (b) el botón llama a window.print (el listener quedó enganchado).
 * NO muta la base. El diálogo de impresión no se puede verificar en Dusk → se ESPÍA window.print.
 *
 * Correr: php artisan dusk --filter=CspStandaloneDocsTest
 */
class CspStandaloneDocsTest extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** crew-export (/crew/export): documento standalone; el botón llama window.print sin onclick inline. */
    public function test_crew_export_imprime_por_listener(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)->visit('/crew/export')->pause(600)
                ->assertPresent('#cc-print-btn');

            // Si $cspNonce no resolviera, la vista ni cargaría; además comprobamos que el <script>
            // trae el atributo nonce (llegó por la fuente única, no por un mecanismo aparte).
            $hasNonce = $browser->script("return document.querySelectorAll('script[nonce]').length > 0;");
            $this->assertTrue((bool) ($hasNonce[0] ?? false), 'el <script> del doc standalone no recibió nonce');

            // Espía window.print y confirma que el botón lo llama.
            $browser->script('window.__printed = false; window.print = function () { window.__printed = true; };');
            $browser->click('#cc-print-btn')->pause(250);
            $called = $browser->script('return window.__printed === true;');
            $this->assertTrue((bool) ($called[0] ?? false), 'el botón no llamó window.print');
        });
    }

    /** historiamr-print (/historialWR/{id}?print=1): documento standalone; el botón llama window.print. */
    public function test_historial_print_imprime_por_listener(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');
        $patient = User::where('activo', 1)->where('id', '!=', $admin->id)->first();
        $this->assertNotNull($patient, 'no hay crew activo para el historial');

        $this->browse(function (Browser $browser) use ($admin, $patient) {
            $browser->loginAs($admin)->visit('/historialWR/' . $patient->id . '?print=1')->pause(800)
                ->assertPresent('#hmr-print-btn');

            $hasNonce = $browser->script("return document.querySelectorAll('script[nonce]').length > 0;");
            $this->assertTrue((bool) ($hasNonce[0] ?? false), 'el <script> del historial no recibió nonce');

            // El doc auto-imprime al cargar; re-defino window.print DESPUÉS para espiar el botón.
            $browser->script('window.__printed = false; window.print = function () { window.__printed = true; };');
            $browser->click('#hmr-print-btn')->pause(250);
            $called = $browser->script('return window.__printed === true;');
            $this->assertTrue((bool) ($called[0] ?? false), 'el botón no llamó window.print');
        });
    }
}
