<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * CSP · ANTI-REGRESIÓN — falla si REAPARECE cualquier manejador on*= en línea en las vistas.
 *
 * Recorre TODAS las vistas Blade (incluidas las standalone servidas al navegador). Antes de buscar,
 * QUITA los comentarios Blade {{-- --}} (no llegan al HTML renderizado) → el ejemplo del patrón malo
 * en componentes/_confirm-submit no cuenta como falso positivo, igual que si escaneáramos el render.
 *
 * Excluye las vistas `*-legacy` (MUERTAS: ni enrutadas ni incluidas desde vista viva; su exclusión
 * fue decisión del owner). Si alguna vuelve a la vida, quítala de la exclusión y conviértela.
 *
 * Cuando la CSP pase a BLOQUEO (script-src 'self' 'nonce-…'), un on*= en atributo NO se puede cubrir
 * con nonce y quedaría muerto → este test lo caza antes de que llegue a set.
 */
class NoInlineHandlersTest extends TestCase
{
    public function test_sin_manejadores_on_en_linea_en_las_vistas(): void
    {
        $base = resource_path('views');

        // Eventos DOM comunes en atributo. \b evita casar "person", "monge", etc.
        $pattern = '/\bon(click|submit|change|input|keyup|keydown|keypress|blur|focus|load|error'
            . '|mouse[a-z]+|pointer[a-z]+|scroll|drop|drag[a-z]*|paste|copy|cut|wheel|contextmenu'
            . '|toggle|reset|select|invalid|abort|beforeunload|hashchange|resize)\s*=\s*["\']/i';

        $offenders = [];

        foreach (File::allFiles($base) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getRealPath();
            if (str_contains($path, '-legacy.blade.php')) {
                continue; // vistas MUERTAS (excluidas por decisión del owner)
            }

            $src = file_get_contents($path);
            // Quita comentarios Blade {{-- ... --}} (no se renderizan). ?? $src: si el regex fallara,
            // NO se pierde el escaneo (nunca se reescribe el archivo; sólo se lee en memoria).
            $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $src) ?? $src;

            if (preg_match_all($pattern, $stripped, $m)) {
                $rel = ltrim(str_replace($base, '', $path), '\\/');
                $offenders[] = $rel . '  →  ' . implode(', ', array_values(array_unique($m[0])));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Reaparecieron manejadores on*= EN LÍNEA (la CSP en bloqueo NO los cubre con nonce y quedarían\n"
            . "muertos). Conviértelos a addEventListener con data-* + delegación, como en las tandas CSP\n"
            . "(usa los partials _confirm-submit / _autosubmit / _row-link o el foot _report-v2-foot):\n\n"
            . implode("\n", $offenders)
        );
    }
}
