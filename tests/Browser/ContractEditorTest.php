<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * VERIFICADOR del editor de plantillas de contrato (ruta contracts.templates.create,
 * canvas #tplCanvas) tras el rediseño de 3 columnas. Foco en el BUG recién arreglado:
 * negrita sobre selección con CLIC FÍSICO en el botón (fix = mousedown preventDefault
 * en la barra para no perder la selección). NO resetea BD, NO usa RefreshDatabase.
 *
 * Correr: php artisan dusk --filter=ContractEditor
 */
class ContractEditorTest extends DuskTestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@127.0.0.1')->firstOrFail();
    }

    public function test_editor_carga_y_negrita_por_clic_fisico(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin())
                ->visitRoute('contracts.templates.create')
                ->waitFor('#tplCanvas')
                ->pause(500);

            // (1) Sin errores SEVERE de consola al cargar. Se guardan en el reporte.
            $severe = collect($browser->driver->manage()->getLog('browser'))
                ->filter(fn ($e) => ($e['level'] ?? '') === 'SEVERE')
                ->map(fn ($e) => $e['message'] ?? '')
                ->values()->all();

            // (2) Presencia de los tres bloques del editor.
            $browser->assertPresent('.cc-ins-list')
                ->assertPresent('.cc-ins-list .cc-ins-row[data-field]')
                ->assertPresent('#tplFormatbar')
                ->assertPresent('#tplArch')
                ->assertPresent('#tplPageSize')
                ->assertPresent('.cc-mark-btn[data-cmd="bold"]');

            $browser->screenshot('cc-editor');

            // (3) BUG CLAVE — negrita sobre selección con clic FÍSICO en el botón.
            // Poner contenido y SELECCIONARLO por script.
            $browser->script(
                "var c=document.getElementById('tplCanvas');" .
                "c.focus();" .
                "c.innerHTML='<p id=cctest>hola mundo</p>';" .
                "var el=document.getElementById('cctest');" .
                "var r=document.createRange();" .
                "r.selectNodeContents(el);" .
                "var s=window.getSelection();" .
                "s.removeAllRanges();" .
                "s.addRange(r);"
            );

            // Clic FÍSICO real en el botón de negrita (no execCommand por script).
            $browser->click('.cc-mark-btn[data-cmd="bold"]')->pause(300);

            $boldHtml = $browser->script(
                "return document.getElementById('tplCanvas').innerHTML;"
            )[0];

            $hasBold = (stripos($boldHtml, '<b>') !== false)
                || (stripos($boldHtml, '<b ') !== false)
                || (stripos($boldHtml, '<strong') !== false);

            // (4) Insertar: clic en la 1ª fila data-field inserta una pastilla .cc-tok.
            // Recolocar el caret dentro del canvas antes de insertar.
            $browser->script(
                "var c=document.getElementById('tplCanvas');" .
                "c.focus();" .
                "var r=document.createRange();" .
                "r.selectNodeContents(c);" .
                "r.collapse(false);" .
                "var s=window.getSelection();" .
                "s.removeAllRanges();" .
                "s.addRange(r);"
            );
            $browser->click('.cc-ins-list .cc-ins-row[data-field]')->pause(300);

            $afterInsertHtml = $browser->script(
                "return document.getElementById('tplCanvas').innerHTML;"
            )[0];
            $tokCount = (int) $browser->script(
                "return document.querySelectorAll('#tplCanvas span.cc-tok').length;"
            )[0];

            // (5) Screenshot final del editor con contenido.
            $browser->screenshot('cc-editor-final');

            // ── Aserciones + evidencia (se vuelca al final del test via fail message) ──
            $this->assertEmpty(
                $severe,
                "Errores SEVERE de consola:\n" . implode("\n---\n", $severe)
            );
            $this->assertTrue(
                $hasBold,
                "El FIX de negrita FALLÓ: el innerHTML tras el clic NO contiene <b>/<strong>.\n" .
                "innerHTML = " . $boldHtml
            );
            $this->assertGreaterThan(
                0,
                $tokCount,
                "La inserción FALLÓ: no apareció ningún span.cc-tok.\n" .
                "innerHTML = " . $afterInsertHtml
            );
        });
    }
}
