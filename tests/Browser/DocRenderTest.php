<?php

namespace Tests\Browser;

use App\Models\hazardnotification;
use App\Models\ScoutingReport;
use App\Models\ToolInspection;
use App\Models\unsafecond;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Render de verificación (temporal): abre documentos en VISTA IMPRESIÓN (data-view=print,
 * fondo blanco como el PDF) y saca screenshots de las zonas retocadas (firmas + pie) para
 * cotejar los arreglos visuales del upgrade. No crea contenido, no resetea BD.
 *
 * Correr: php artisan dusk --filter=DocRenderTest
 */
class DocRenderTest extends DuskTestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@127.0.0.1')->firstOrFail();
    }

    /** Fuerza vista de impresión (blanca) + tema claro y da tiempo al auto-ajuste del hero. */
    private function printView(Browser $b): void
    {
        $b->script("document.documentElement.setAttribute('data-theme','light');document.documentElement.setAttribute('data-view','print');");
        $b->pause(600);
    }

    public function test_render_scouting(): void
    {
        $s = ScoutingReport::where('status', 'final')->latest('id')->first()
            ?: ScoutingReport::latest('id')->firstOrFail();

        $this->browse(function (Browser $browser) use ($s) {
            $browser->loginAs($this->admin())
                ->visit('/scoutings/' . $s->id)
                ->pause(1200)
                ->resize(1300, 2400);
            $this->printView($browser);
            $browser->scrollIntoView('.sign')->pause(400)->screenshot('rev-scouting-firmas');
            $browser->script("window.scrollTo(0, document.body.scrollHeight);");
            $browser->pause(400)->screenshot('rev-scouting-foot');
        });
        $this->assertTrue(true);
    }

    public function test_render_hazard(): void
    {
        $h = hazardnotification::latest('id')->firstOrFail();
        $this->browse(function (Browser $browser) use ($h) {
            $browser->loginAs($this->admin())->visit('/unsafeact/' . $h->id)->pause(1200)->resize(1300, 2400);
            $this->printView($browser);
            $browser->scrollIntoView('.sign')->pause(400)->screenshot('rev-hazard-firmas');
        });
        $this->assertTrue(true);
    }

    public function test_render_unsafe(): void
    {
        $u = unsafecond::latest('id')->firstOrFail();
        $this->browse(function (Browser $browser) use ($u) {
            $browser->loginAs($this->admin())->visit('/unsafecond/' . $u->id)->pause(1200)->resize(1300, 2400);
            $this->printView($browser);
            $browser->scrollIntoView('.sign')->pause(400)->screenshot('rev-unsafe-firmas');
        });
        $this->assertTrue(true);
    }

    public function test_render_inspeccion(): void
    {
        $i = ToolInspection::latest('id')->firstOrFail();

        $this->browse(function (Browser $browser) use ($i) {
            $browser->loginAs($this->admin())
                ->visit('/inspeccion/acta/' . $i->uuid)
                ->pause(1200)
                ->resize(1300, 2400);
            $this->printView($browser);
            $browser->script("window.scrollTo(0, document.body.scrollHeight);");
            $browser->pause(400)->screenshot('rev-inspeccion-foot');
        });
        $this->assertTrue(true);
    }
}
