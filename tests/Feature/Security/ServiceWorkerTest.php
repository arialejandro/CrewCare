<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * SERVICE WORKER · ANTI-REGRESIÓN — el SW no puede tocar nada que no sea GET.
 *
 * ============================ POR QUÉ EXISTE ============================
 * El 2026-09-16 el owner no podía guardar NINGUNA nota del Tech Scout, y cualquier PUT devolvía
 * 405. Dos síntomas que parecían no tener nada que ver, y una tarde de diagnóstico.
 *
 * La causa era el `fetch` del service worker: corría para TODAS las peticiones —POST y PUT
 * incluidos— y las reemitía con `fetch(req)`. En ese viaje el CUERPO se pierde. El servidor
 * recibía la cabecera `multipart/form-data` con su boundary y CERO campos:
 *
 *     {"campos":[],"note_len":0,"tiene_file":false,"tipo":"multipart/form-data; boundary=..."}
 *
 * De ahí los dos síntomas: los formularios llegaban vacíos, y Laravel —que lee el método real del
 * campo `_method`, que va EN EL CUERPO— se quedaba en POST y devolvía 405 en las rutas PUT.
 *
 * ⚠ Afectaba a TODA la app, no sólo al módulo nuevo: cualquier formulario, en cualquier
 * dispositivo con el service worker registrado (se registra solo al visitar). Se descubrió por el
 * Tech Scout únicamente porque fue el primero que dejó registrado en el log QUÉ había llegado.
 *
 * La Cache API no soporta peticiones que no sean GET, así que interceptarlas nunca aportó nada.
 */
class ServiceWorkerTest extends TestCase
{
    /**
     * 🪤 Se quitan los COMENTARIOS antes de mirar. La primera versión de este test buscaba
     * "respondWith" en el archivo crudo y lo encontraba… dentro del comentario que explica el bug,
     * justo encima de la guarda. El test fallaba con el código correcto. Misma lección que el
     * guardia del `mousedown`: comparar contra el texto que EJECUTA, no contra el que explica.
     * El (?<!:) evita destrozar las URLs al quitar los comentarios de línea.
     */
    private function sw(): string
    {
        $path = public_path('serviceworker.js');
        $this->assertFileExists($path, 'el service worker debe existir: la app se instala como PWA.');

        $src = (string) file_get_contents($path);
        $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);
        $src = (string) preg_replace('#(?<!:)//[^\n]*#', '', $src);

        return $src;
    }

    public function test_el_service_worker_no_intercepta_peticiones_que_no_sean_get(): void
    {
        $sw = $this->sw();

        $pos = strpos($sw, "addEventListener(\"fetch\"");
        if ($pos === false) {
            $pos = strpos($sw, "addEventListener('fetch'");
        }
        $this->assertNotFalse($pos, 'no se encontró el manejador de fetch.');

        // La guarda tiene que estar DENTRO del manejador y ANTES del primer respondWith: si va
        // después, el cuerpo ya se perdió.
        $cuerpo      = substr($sw, $pos);
        $posGuarda   = strpos($cuerpo, "req.method !== 'GET'");
        $posResponde = strpos($cuerpo, 'respondWith');

        $this->assertNotFalse($posGuarda,
            "El service worker DEBE dejar pasar de largo todo lo que no sea GET.\n"
            . "Sin esa guarda, reemite los POST/PUT con fetch(req) y SE PIERDE EL CUERPO:\n"
            . "los formularios llegan vacíos al servidor y los PUT dan 405 (el `_method` viaja\n"
            . "en el cuerpo). Rompe TODOS los formularios de la app, no sólo uno.");

        $this->assertLessThan($posResponde, $posGuarda,
            'la guarda debe ir ANTES del primer respondWith, o el cuerpo ya se perdió.');
    }

    /**
     * Sin `clients.claim()`, un arreglo del service worker no llega a las pestañas ya abiertas
     * hasta que se cierran todas — y en un iPad de set eso puede ser nunca. Justo este arreglo es
     * el que devuelve el contenido a los formularios: tiene que llegar el mismo día.
     */
    public function test_el_service_worker_toma_control_de_las_pestanas_abiertas(): void
    {
        $this->assertStringContainsString('clients.claim()', $this->sw(),
            'sin clients.claim() una corrección del SW puede tardar días en llegar al dispositivo.');
    }
}
