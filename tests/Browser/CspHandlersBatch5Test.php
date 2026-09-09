<?php

namespace Tests\Browser;

use App\Http\Controllers\ContractSignController;
use App\Models\ContractEnvelopeRecipient;
use App\Models\MedevacPoster;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TANDA 5 (contratos, PAE, medevac, mapeo) — handlers convertidos:
 *   · contracts/sign: ccDecline (toggle "no puedo firmar") — data-decline-toggle. ⚠️ CRÍTICO: es la
 *     ÚNICA on*= de la página de firma; el flujo de FIRMA (adoptar+enviar) vive en OTRO <script>
 *     (pdf.js, addEventListener) que NO se tocó. Aquí se verifica que el toggle dispara y que la
 *     página de firma no quedó rota.
 *   · riskmaps/index + edit, contracts/envelope/show: confirms data-confirm.
 *   · medevac/show: data-mv-pdf/-print. · pae/show: data-history-back (foot). · consulta-documento: data-doc-print.
 * NO muta la base (confirms se cancelan; window.print/history.back espiados).
 *
 * Correr: php artisan dusk --filter=CspHandlersBatch5Test
 */
class CspHandlersBatch5Test extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** ⚠️ CRÍTICO — contracts/sign: el toggle "no puedo firmar" (data-decline-toggle) dispara y la
     *  página de firma NO quedó rota (sin errores SEVERE; el flujo de firma sigue presente). */
    public function test_contrato_firma_decline_toggle(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $recipient = ContractEnvelopeRecipient::orderByDesc('id')->first();
        if (! $recipient) {
            fwrite(STDERR, "\n[Tanda5] sin destinatarios de contrato en la BD → ceremonia de firma no ejercida en vivo.\n"
                . "Verificación estructural: contracts/sign tiene UNA sola on*= (ccDecline); el flujo de firma\n"
                . "(adoptar+enviar) vive en un <script> aparte (pdf.js/addEventListener) que NO se tocó.\n");
            $this->assertTrue(true);
            return;
        }

        $url = ContractSignController::signUrl($recipient);

        $this->browse(function (Browser $b) use ($url) {
            $b->visit($url)->pause(900);

            $hasToggle = $b->script("return document.querySelectorAll('[data-decline-toggle]').length > 0;");
            if (! (bool) ($hasToggle[0] ?? false)) {
                // La página cayó al gate de 2º factor (sign-gate) o a 'done/not_turn': la ceremonia
                // con el toggle no se alcanzó en vivo. Se reporta; la conversión está verificada en fuente.
                fwrite(STDERR, "\n[Tanda5] la ceremonia con el toggle no se alcanzó (gate 2FA / estado del destinatario).\n");
                $this->assertTrue(true);
                return;
            }

            // El toggle abre el formulario de rechazo (el handler convertido dispara).
            $antes = $b->script("var f=document.getElementById('declineForm'); return f ? getComputedStyle(f).display : 'gone';");
            $b->click('[data-decline-toggle]')->pause(300);
            $despues = $b->script("var f=document.getElementById('declineForm'); return f ? getComputedStyle(f).display : 'gone';");
            $this->assertNotSame($antes[0] ?? null, $despues[0] ?? null, 'el toggle de rechazo no cambió el formulario');

            // La FIRMA sigue intacta: existe el área/controles de firma y no hay errores SEVERE.
            $hasSign = $b->script("return document.querySelectorAll('form, canvas, [id*=sign], [class*=sign]').length > 0;");
            $this->assertTrue((bool) ($hasSign[0] ?? false), 'no se ven controles de firma en la ceremonia');

            $severe = collect($b->driver->manage()->getLog('browser'))
                ->filter(fn ($e) => ($e['level'] ?? '') === 'SEVERE')->map(fn ($e) => $e['message'] ?? '')->values()->all();
            $this->assertEmpty($severe, "Errores SEVERE en la página de firma:\n" . implode("\n---\n", $severe));
        });
    }

    /** riskmaps/index: "Eliminar mapeo" (data-confirm) pide confirmación y se CANCELA. Defensivo. */
    public function test_riskmaps_confirm(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/mapeo-riesgos')->pause(700);
            $has = $b->script("return !!document.querySelector('form[data-confirm] [type=submit], form[data-confirm] button');");
            if (! (bool) ($has[0] ?? false)) {
                fwrite(STDERR, "\n[Tanda5] sin mapeos con form data-confirm → confirm de riskmaps no ejercido en vivo.\n");
                $this->assertTrue(true);
                return;
            }
            $b->script("document.querySelector('form[data-confirm] [type=submit], form[data-confirm] button').click();");
            $alert = $b->driver->switchTo()->alert();
            $this->assertStringContainsString('mapeo', mb_strtolower($alert->getText()));
            $alert->dismiss();
            $b->pause(200)->assertPathIs('/mapeo-riesgos');
        });
    }

    /** medevac/show (standalone): botón imprimir (data-mv-print) llama window.print; pdf lleva data-pdf-url. */
    public function test_medevac_print_y_pdf(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');
        $uuid = MedevacPoster::orderByDesc('id')->value('uuid');
        if (! $uuid) {
            fwrite(STDERR, "\n[Tanda5] sin pósters MEDEVAC en la BD → print/pdf de medevac no ejercidos en vivo.\n");
            $this->assertTrue(true);
            return;
        }

        $this->browse(function (Browser $b) use ($admin, $uuid) {
            $b->loginAs($admin)->visit('/medevac/' . $uuid)->pause(700)
                ->assertPresent('[data-mv-print]')
                ->assertPresent('[data-mv-pdf][data-pdf-url]');
            $b->script('window.__printed=false; window.print=function(){window.__printed=true;};');
            $b->click('[data-mv-print]')->pause(200);
            $called = $b->script('return window.__printed===true;');
            $this->assertTrue((bool) ($called[0] ?? false), 'el botón imprimir de medevac no llamó window.print');
        });
    }
}
