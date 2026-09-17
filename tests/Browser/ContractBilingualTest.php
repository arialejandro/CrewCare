<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * VERIFICADOR de los 3 formatos de contrato BILINGÜES (doble columna EN|ES) del
 * editor de plantillas (ruta contracts.templates.create, query ?arch=). Confirma que
 * el andamiaje se hidrata en #tplCanvas como TABLA (2 columnas) y contiene los
 * marcadores bilingües esperados. NO resetea BD, NO usa RefreshDatabase.
 *
 * Correr: php artisan dusk --filter=ContractBilingual
 */
class ContractBilingualTest extends DuskTestCase
{
    /** arch => [marcador EN, marcador ES] */
    private const ARCHS = [
        'bilingual_crew'       => ['FRONT PAGE', 'CARÁTULA'],
        'bilingual_vendor'     => ['GOODS AND SERVICES', 'SUMINISTRO DE BIENES'],
        'bilingual_main_terms' => ['MAIN TERMS', 'TÉRMINOS PRINCIPALES'],
    ];

    private function admin(): User
    {
        return User::where('email', 'admin@127.0.0.1')->firstOrFail();
    }

    public function test_andamiaje_bilingue_carga_a_dos_columnas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->admin());

            $severeAll = [];
            $report    = [];

            foreach (self::ARCHS as $arch => $markers) {
                [$en, $es] = $markers;

                $browser->visitRoute('contracts.templates.create', ['arch' => $arch])
                    ->waitFor('#tplCanvas')
                    ->pause(600);

                // (1) Andamiaje a 2 columnas => hay una <table> dentro del canvas.
                $browser->assertPresent('#tplCanvas table');

                // (2) Texto del canvas contiene los marcadores bilingües.
                $text = $browser->script(
                    "return document.getElementById('tplCanvas').innerText;"
                )[0];

                $hasEn = stripos($text, $en) !== false;
                $hasEs = stripos($text, $es) !== false;

                // (3) Screenshot del editor.
                $shot = 'bili-' . str_replace('bilingual_', '', $arch);
                $browser->screenshot($shot);

                // (4) Vista con datos (iframe relleno a 2 columnas). No bloqueante.
                $previewOk = false;
                try {
                    if ($browser->element('#tplTogglePreview')) {
                        $browser->click('#tplTogglePreview')->pause(1200);
                        $browser->screenshot($shot . '-preview');
                        $previewOk = true;
                        // Cerrar/volver a la vista de edición si el toggle lo permite.
                        $browser->click('#tplTogglePreview')->pause(300);
                    }
                } catch (\Throwable $e) {
                    // El iframe puede complicar el screenshot; se omite sin fallar.
                }

                // (5) Errores SEVERE de consola tras cargar este arch.
                $severe = collect($browser->driver->manage()->getLog('browser'))
                    ->filter(fn ($e) => ($e['level'] ?? '') === 'SEVERE')
                    ->map(fn ($e) => '[' . $arch . '] ' . ($e['message'] ?? ''))
                    ->values()->all();
                $severeAll = array_merge($severeAll, $severe);

                $report[$arch] = compact('hasEn', 'hasEs', 'previewOk')
                    + ['en' => $en, 'es' => $es];

                // Aserción por-arch de los marcadores (falla claro si falta uno).
                $this->assertTrue(
                    $hasEn && $hasEs,
                    "Arch '$arch': faltan marcadores bilingües. " .
                    "EN '$en'=" . ($hasEn ? 'OK' : 'FALTA') . ", " .
                    "ES '$es'=" . ($hasEs ? 'OK' : 'FALTA') . ".\n" .
                    "innerText (recorte)=\n" . mb_substr($text, 0, 800)
                );
            }

            // (6) Sin errores SEVERE en ninguno de los 3 archs.
            $this->assertEmpty(
                $severeAll,
                "Errores SEVERE de consola:\n" . implode("\n---\n", $severeAll)
            );
        });
    }
}
