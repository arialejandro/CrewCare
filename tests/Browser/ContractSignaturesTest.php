<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * VERIFICADOR del BLOQUE DE FIRMAS rediseñado del editor de plantillas de contrato
 * (ruta contracts.templates.create, canvas #tplCanvas). Antes era una TABLA editable
 * (se rompía); ahora es una REJILLA flexible `.cc-signs` de slots PROTEGIDOS `.cc-sign`
 * (contenteditable=false) con etiqueta editable `.cc-sign-role` (contenteditable=true).
 * Verifica: baseline 2 slots, protección, agregar firmante (apila en UNA rejilla),
 * quitar con la ×, y serialización limpia (conserva cc-sign, descarta la ×).
 * NO resetea BD, NO usa RefreshDatabase.
 *
 * Correr: php artisan dusk --filter=ContractSignatures
 */
class ContractSignaturesTest extends DuskTestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@127.0.0.1')->firstOrFail();
    }

    public function test_bloque_firmas_rejilla_slots_protegidos(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin())
                ->visitRoute('contracts.templates.create')
                ->waitFor('#tplCanvas')
                ->pause(600);

            // (2) BASELINE: el andamiaje default trae 2 slots de firma.
            $n0 = (int) $browser->script(
                "return document.querySelectorAll('#tplCanvas .cc-signs .cc-sign').length;"
            )[0];
            $this->assertSame(
                2,
                $n0,
                "Baseline de slots FALLÓ: se esperaban 2 .cc-sign en la rejilla, hay $n0."
            );

            // (3) PROTECCIÓN: el slot es no editable; su etiqueta sí lo es.
            $slotCe = $browser->script(
                "return document.querySelector('#tplCanvas .cc-sign').getAttribute('contenteditable');"
            )[0];
            $roleCe = $browser->script(
                "return document.querySelector('#tplCanvas .cc-sign-role').getAttribute('contenteditable');"
            )[0];
            $this->assertSame(
                'false',
                $slotCe,
                "Protección FALLÓ: .cc-sign debería ser contenteditable='false', es '" . var_export($slotCe, true) . "'."
            );
            $this->assertSame(
                'true',
                $roleCe,
                "Etiqueta editable FALLÓ: .cc-sign-role debería ser contenteditable='true', es '" . var_export($roleCe, true) . "'."
            );

            // (4) AGREGAR FIRMANTE: desplegar la sección "Firmas" (arranca colapsada)
            // y hacer clic en el primer firmante que NO sea rúbrica.
            $browser->script(
                "var h=[].find.call(document.querySelectorAll('.cc-ins-cat'), e=>/Firmas/i.test(e.textContent)); if(h) h.click();"
            );
            $browser->pause(300);
            $browser->script(
                "var r=[].find.call(document.querySelectorAll('.cc-ins-list .cc-ins-row[data-anchor]'), e=>e.getAttribute('data-anchor')!=='rubrica'); if(r) r.click();"
            );
            $browser->pause(400);

            $n1 = (int) $browser->script(
                "return document.querySelectorAll('#tplCanvas .cc-signs .cc-sign').length;"
            )[0];
            $grids = (int) $browser->script(
                "return document.querySelectorAll('#tplCanvas .cc-signs').length;"
            )[0];
            $this->assertSame(
                $n0 + 1,
                $n1,
                "Agregar firmante FALLÓ: se esperaban " . ($n0 + 1) . " slots, hay $n1."
            );
            $this->assertSame(
                1,
                $grids,
                "La rejilla se rompió: se esperaba UNA sola .cc-signs, hay $grids."
            );

            // (5) Screenshot del editor con las 3 firmas.
            $browser->screenshot('sign-3');

            // (6) QUITAR FIRMANTE (× del primer slot): vuelve a $n0.
            $browser->script(
                "var d=document.querySelector('#tplCanvas .cc-sign .cc-sign-del'); if(d) d.click();"
            );
            $browser->pause(300);
            $n2 = (int) $browser->script(
                "return document.querySelectorAll('#tplCanvas .cc-signs .cc-sign').length;"
            )[0];
            $this->assertSame(
                $n0,
                $n2,
                "Quitar firmante FALLÓ: se esperaban $n0 slots tras la ×, hay $n2."
            );

            // (7) SERIALIZACIÓN: el body serializado conserva la estructura de firmas
            // y descarta la × (cc-sign-del). Se bloquea la navegación con preventDefault.
            $browser->script(
                "document.getElementById('tplForm').addEventListener('submit',function(e){e.preventDefault();},{once:true});"
            );
            $body = $browser->script(
                "var f=document.getElementById('tplForm'); f.dispatchEvent(new Event('submit',{cancelable:true})); return document.getElementById('tplBodyInput').value;"
            )[0];

            $hasSignClass = stripos($body, 'class="cc-sign"') !== false;
            $hasDel       = stripos($body, 'cc-sign-del') !== false;
            $this->assertTrue(
                $hasSignClass,
                "Serialización FALLÓ: el body no contiene class=\"cc-sign\".\nbody(recorte)=\n" . mb_substr($body, 0, 1200)
            );
            $this->assertFalse(
                $hasDel,
                "Serialización FALLÓ: el body NO debería contener 'cc-sign-del' (la × no se guarda).\nbody(recorte)=\n" . mb_substr($body, 0, 1200)
            );

            // (8) VISTA CON DATOS: firmas selladas a 2 columnas dentro del iframe.
            // No bloqueante.
            try {
                if ($browser->element('#tplTogglePreview')) {
                    $browser->click('#tplTogglePreview')->pause(1200);
                    $browser->screenshot('sign-preview');
                }
            } catch (\Throwable $e) {
                // El iframe puede complicar el screenshot; se omite sin fallar.
            }

            // (9) Sin errores SEVERE de consola.
            $severe = collect($browser->driver->manage()->getLog('browser'))
                ->filter(fn ($e) => ($e['level'] ?? '') === 'SEVERE')
                ->map(fn ($e) => $e['message'] ?? '')
                ->values()->all();
            $this->assertEmpty(
                $severe,
                "Errores SEVERE de consola:\n" . implode("\n---\n", $severe)
            );
        });
    }
}
