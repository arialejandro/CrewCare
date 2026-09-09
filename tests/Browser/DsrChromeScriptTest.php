<?php

namespace Tests\Browser;

use App\Models\DailyReport;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * DSR · el <script> del chrome compartido (_report-v2-foot) NO se parte. Regresión del bug donde un
 * `@stack('scripts')` literal en un comentario del foot inyectaba el stack (con un </script>) dentro del
 * bloque, matando theme/PDF/print/veto y el picker de "+ Hallazgo". Corre contra la base real EN MODO
 * ENFORCE (CREWCARE_CSP_ENFORCE=true) e incluye control NEGATIVO (un inline sin nonce debe quedar bloqueado).
 * SÓLO LECTURA + toggles de cliente: no muta nada.
 *
 * Correr: php artisan dusk --filter=DsrChromeScriptTest
 * Captura: tests/Browser/screenshots/dsr-chrome-ok.png
 */
class DsrChromeScriptTest extends DuskTestCase
{
    public function test_el_chrome_del_dsr_ejecuta_y_no_se_parte(): void
    {
        $admin = User::where('email', 'admin@127.0.0.1')->firstOrFail();
        $dsr   = DailyReport::orderBy('id')->first();   // DSR-0001
        $this->assertNotNull($dsr, 'Debe existir al menos un DSR en la base real.');

        $this->browse(function (Browser $browser) use ($admin, $dsr) {
            $browser->loginAs($admin)
                ->visit('/dsr-reports/' . $dsr->id)
                ->pause(1400)
                // (a) El comentario del foot NO se filtra como texto → el <script> está intacto.
                ->assertDontSee('en el chrome compartido')
                ->screenshot('dsr-chrome-ok');

            $r = $browser->script("
                var res = {};
                var t0 = document.documentElement.getAttribute('data-theme');
                var b = document.getElementById('themeBtn'); if (b) { b.click(); }
                res.themeChanged = document.documentElement.getAttribute('data-theme') !== t0;
                res.viewBtn = !!document.getElementById('viewBtn');
                res.pdfBtn = !!document.getElementById('pdfBtn');
                res.cctypeahead = !!window.CCTypeahead;
                var sel = document.getElementById('hazard_event_id');
                res.pickerEnhanced = !!(sel && sel.getAttribute('data-ta') === '1');
                res.hasLogTime = !!document.querySelector('input[name=\"log_time\"]');
                res.hasDesc = !!document.querySelector('textarea[name=\"description\"]');
                res.hasHazardSel = !!document.querySelector('select[name=\"hazard_event_id\"]');
                // Diagnóstico del typeahead empujado:
                res.scriptCount = document.scripts.length;
                var full = document.documentElement.innerHTML;
                res.htmlHasTASource = full.indexOf('CCTypeahead') !== -1;   // ¿el fuente del typeahead está en el DOM?
                res.htmlHasFootSource = full.indexOf('themeBtn') !== -1;
                var taScript = Array.prototype.slice.call(document.scripts).filter(function(s){return s.textContent.indexOf('CCTypeahead')!==-1;})[0];
                res.taScriptPresent = !!taScript;
                res.taScriptNonce = taScript ? (taScript.getAttribute('nonce') ? 'yes' : 'no') : 'n/a';
                res.detailsOpenNeeded = !!document.querySelector('details');
                // Control NEGATIVO de enforce: un inline SIN nonce debe quedar bloqueado por la CSP.
                try { var s = document.createElement('script'); s.text = 'window.__pf_pwned = 1;'; document.body.appendChild(s); } catch (e) {}
                res.injectedBlocked = (typeof window.__pf_pwned === 'undefined');
                return res;
            ")[0];

            // (b · PROBADO) El foot script del chrome compartido EJECUTA — theme toggle + botones vivos.
            // Son 3 de los 4 síntomas (PDF/Vista/Tema); el 4º (veto data-confirm) vive en el mismo bloque.
            // El form de "+ Hallazgo" (event-picker) va gateado por dsr.create y se verifica aparte en el
            // feature test DsrChromeRenderTest (admin@127.0.0.1 no tiene dsr.create → no lo renderiza aquí).
            $this->assertTrue((bool) ($r['themeChanged'] ?? false), 'El toggle de tema NO ejecutó → el foot script sigue roto.');
            $this->assertTrue((bool) ($r['viewBtn'] ?? false), 'Falta el botón de Vista impresión.');
            $this->assertTrue((bool) ($r['pdfBtn'] ?? false), 'Falta el botón Exportar PDF.');
            // (e) Control negativo: la CSP en ENFORCE bloquea el inline sin nonce.
            $this->assertTrue((bool) ($r['injectedBlocked'] ?? false), 'Un inline sin nonce EJECUTÓ → la CSP no está en enforce (control negativo falló).');
        });
    }
}
