<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * TÁCTIL · ANTI-REGRESIÓN — falla si una vista cablea `mousedown`/`mouseup` SIN un equivalente
 * táctil o de puntero en el mismo archivo.
 *
 * ============================ POR QUÉ EXISTE ============================
 * En iOS los eventos de RATÓN son SINTETIZADOS a partir del toque, y no llegan de forma fiable a
 * elementos que no son controles de formulario (un <li> de una lista de opciones, por ejemplo).
 * Encima, un `preventDefault()` sobre ese ratón sintético interfiere con la secuencia del toque.
 *
 * Esto no es teórico y no es cosmético: el 2026-09-08, con la primera producción REAL ya rodando
 * (flor.crewcare.mx), el typeahead —el componente que usa TODO select largo de personas o
 * catálogo— tenía sus opciones cableadas SÓLO a 'mousedown'. En iPad, tocar una opción no corría
 * `setVal()`, el <select> se quedaba vacío, y el formulario respondía "el campo es obligatorio"
 * aunque el usuario SÍ había elegido. En Android y en escritorio funcionaba, así que el fallo
 * parecía un problema de subida de archivos y costó horas de producción encontrarlo.
 *
 * La app se usa EN SET, sobre iPads. Un manejador que sólo entiende de ratón está roto para el
 * usuario real, aunque pase todas las pruebas de escritorio.
 *
 * ============================ LA REGLA ============================
 * Si un archivo escucha 'mousedown' o 'mouseup', debe escuchar también 'pointerdown'/'pointerup'
 * (que cubre ratón, dedo y lápiz con un solo manejador; Safari 13+) o, en su defecto, los
 * 'touchstart'/'touchend' correspondientes. Los canvas de firma son un ejemplo del patrón CORRECTO:
 * cablean ratón Y tacto, y por eso siempre se pudo firmar en iPad.
 *
 * El test mira por ARCHIVO, no por línea: un archivo que ya trae el par táctil está bien resuelto,
 * y afinar más produciría falsos positivos sin ganar señal.
 */
class NoMouseOnlyHandlersTest extends TestCase
{
    public function test_ninguna_vista_escucha_solo_eventos_de_raton(): void
    {
        $base      = resource_path('views');
        $offenders = [];

        foreach (File::allFiles($base) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getRealPath();
            if (str_contains($path, '-legacy.blade.php')) {
                continue; // vistas MUERTAS (misma exclusión que NoInlineHandlersTest)
            }

            $src = (string) file_get_contents($path);

            // Fuera TODOS los comentarios antes de mirar: Blade, /* */ y // hasta fin de línea.
            //
            // 🪤 Quitar sólo los de Blade NO alcanza, y esto casi cuela una versión inútil de este
            // test: el arreglo del typeahead dejó la palabra "pointerdown" explicada en un comentario
            // JS, así que al reintroducir el bug a propósito el archivo SEGUÍA mencionándola y el test
            // pasaba en verde. Un guardia que no falla cuando debe es peor que no tenerlo, porque da
            // confianza falsa. Se verifica reintroduciendo el bug y comprobando que ROMPE.
            //
            // El (?<!:) evita destrozar las URLs (https://…) al quitar los comentarios de línea.
            $src = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $src);
            $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);
            $src = (string) preg_replace('#(?<!:)//[^\n]*#', '', $src);

            $mouse = preg_match('/addEventListener\s*\(\s*[\'"]mouse(down|up)[\'"]/i', $src);
            if (! $mouse) {
                continue;
            }

            $touch = preg_match('/addEventListener\s*\(\s*[\'"](pointer(down|up)|touch(start|end))[\'"]/i', $src)
                // Forma indirecta: el evento se elige en una variable (p. ej.
                // `var PICK_EVENT = window.PointerEvent ? 'pointerdown' : 'mousedown';`).
                || preg_match('/[\'"]pointer(down|up)[\'"]/i', $src);

            if (! $touch) {
                $offenders[] = str_replace($base . DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame([], $offenders, "Estas vistas escuchan eventos de RATÓN sin equivalente táctil.\n"
            . "En iPad —que es donde se usa la app en set— el toque no dispara 'mousedown' de forma fiable,\n"
            . "así que ese control está MUERTO para el usuario real aunque funcione en tu escritorio.\n"
            . "Usa 'pointerdown' (cubre ratón, dedo y lápiz) o añade el 'touchstart' correspondiente:\n  - "
            . implode("\n  - ", $offenders));
    }
}
