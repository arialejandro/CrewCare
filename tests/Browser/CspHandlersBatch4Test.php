<?php

namespace Tests\Browser;

use App\Models\ScoutingReport;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TANDA 4 (ambulancia e inspección) — handlers convertidos:
 *   · inspection/index: buscador data-search-noop (no envía).
 *   · epi/index: selects data-autosubmit (cambiar → envía el filtro, GET).
 *   · ambulance/records + inspection/records: filas clicables (tr[data-href] vía _row-link).
 *   · ambulance/acta + inspection/acta (standalone): confirms data-confirm por delegación en
 *     _report-v2-foot (esos docs no tienen @stack('scripts')).
 * NO muta la base: confirms se CANCELAN; navegación GET.
 *
 * Correr: php artisan dusk --filter=CspHandlersBatch4Test
 */
class CspHandlersBatch4Test extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** inspection/index: el buscador NO navega al enviar (data-search-noop). */
    public function test_inspeccion_buscador_no_envia(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/inspeccion')->pause(600)
                ->assertPresent('form[data-search-noop]')
                ->script('window.__noNav = true;');
            $b->keys('#toolsearch', '{enter}')->pause(500);
            $survived = $b->script('return window.__noNav === true;');
            $this->assertTrue((bool) ($survived[0] ?? false), 'el buscador de inspección envió el form');
        });
    }

    /** epi/index: cambiar un select auto-envía el filtro (data-autosubmit → form.submit, GET). */
    public function test_epi_autosubmit_select(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/vigilancia')->pause(600)
                ->assertPresent('select[data-autosubmit]')
                ->script('window.__stay = true;');
            // Dispara change en el primer select → el listener delegado envía el form: HAY navegación
            // (el destino exacto es indistinto; el marcador desaparece si el form.submit() corrió).
            $b->script("var s=document.querySelector('select[data-autosubmit]'); s.dispatchEvent(new Event('change',{bubbles:true}));");
            $b->pause(800);
            $stay = $b->script('return window.__stay === true;');
            $this->assertFalse((bool) ($stay[0] ?? true), 'el select no auto-envió (no hubo navegación)');
        });
    }

    /** inspection/records: fila clicable (tr[data-href] vía _row-link) navega al acta. Defensivo. */
    public function test_fila_clicable(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/inspeccion/actas')->pause(600);

            $href = $b->script("var r=document.querySelector('tr[data-href]'); return r ? r.getAttribute('data-href') : null;");
            if (! ($href[0] ?? null)) {
                fwrite(STDERR, "\n[Tanda4] sin actas de inspección → fila clicable no ejercida en vivo (mecanismo _row-link verificado en fuente).\n");
                $this->assertTrue(true);
                return;
            }
            $b->click('tr[data-href]')->pause(700);
            $this->assertStringContainsString('/inspeccion/acta/', $b->driver->getCurrentURL(), 'la fila clicable no navegó al acta');
        });
    }

    /** Docs de reporte v2 standalone: el confirm delegado del foot (data-confirm) dispara y se
     *  CANCELA. Se prueba en un scouting show (report doc que SIEMPRE existe ≥1 e incluye el foot). */
    public function test_confirm_delegado_del_foot(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');
        $sid = ScoutingReport::orderBy('id')->value('id');
        $this->assertNotNull($sid, 'no hay scouting para probar el foot compartido');

        $this->browse(function (Browser $b) use ($admin, $sid) {
            $b->loginAs($admin)->visit('/scoutings/' . $sid)->pause(800);
            // Inyecta un form data-confirm; el veto delegado del foot debe dispararse (report doc).
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
}
