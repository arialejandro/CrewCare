<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · MINI-TANDA IMÁGENES — verifica el mecanismo ÚNICO que reemplaza los 3
 * onerror="this.style.display='none'" (en _doc-hero-logo, historiamr-print, medevac/show):
 * un listener de CAPTURA a nivel window (public/js/img-fallback.js, same-origin, en <head>) que
 * oculta cualquier <img data-hide-on-error> que falle al cargar.
 *
 * Prueba con una imagen ROTA DE VERDAD (404) inyectada en la página — NO muta la base.
 *
 * Correr: php artisan dusk --filter=CspImgFallbackTest
 */
class CspImgFallbackTest extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    private function assertBrokenImageHides(Browser $b, string $ctx): void
    {
        // El fallback debe estar cargado en el <head> de este contexto.
        $has = $b->script('return document.querySelectorAll("script[src*=img-fallback]").length > 0;');
        $this->assertTrue((bool) ($has[0] ?? false), "img-fallback.js no se cargó en $ctx");

        // Inyecta una <img data-hide-on-error> con src 404 y espera el error real.
        $b->script(
            'var i=document.createElement("img");' .
            'i.id="cc-test-broken";' .
            'i.setAttribute("data-hide-on-error","");' .
            'i.src="/no-existe-"+Date.now()+".png";' .
            'document.body.appendChild(i);'
        );
        $b->pause(900);
        $disp = $b->script('var e=document.getElementById("cc-test-broken"); return e ? getComputedStyle(e).display : "gone";');
        $this->assertSame('none', $disp[0] ?? null, "la imagen rota no se ocultó en $ctx");
    }

    /** layouts.app (todas las páginas de la app): el fallback oculta una imagen rota. */
    public function test_imagen_rota_se_oculta_en_app(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/medicocrud')->pause(600);
            $this->assertBrokenImageHides($b, 'layouts.app (/medicocrud)');
        });
    }

    /** Reporte STANDALONE (historiamr-print, sin layouts.app): el fallback también oculta una rota. */
    public function test_imagen_rota_se_oculta_en_reporte_standalone(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');
        $patient = User::where('activo', 1)->where('id', '!=', $admin->id)->first();
        $this->assertNotNull($patient, 'no hay crew activo para el historial');

        $this->browse(function (Browser $b) use ($admin, $patient) {
            $b->loginAs($admin)->visit('/historialWR/' . $patient->id . '?print=1')->pause(900);
            $this->assertBrokenImageHides($b, 'reporte standalone (historiamr-print)');
        });
    }
}
