<?php

namespace Tests\Feature\Security;

use Tests\QaTestCase;

/**
 * Cabeceras de seguridad + CSP (modo reporte) + rate-limit de recuperación. En testing (no
 * producción) el transporte NO se toca: sin redirect a https, sin HSTS — el local sigue en http.
 */
class SecurityHeadersTest extends QaTestCase
{
    public function test_cabeceras_presentes_y_sin_redirect_en_no_produccion(): void
    {
        $res = $this->get('/login');
        $res->assertOk();   // no redirige (local/testing sigue en http)

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // La CSP se emite en UNO de dos modos según `csp_enforce`, nunca en ambos. Este test no
        // fija cuál: fija que la política sea la MISMA y esté completa en cualquiera de los dos.
        //
        // 🪤 Antes daba por hecho el modo REPORTE, así que en cuanto el owner activó el bloqueo
        // en su .env la prueba se puso en rojo y ahí se quedó. Una prueba de seguridad que lleva
        // días fallando por una razón conocida deja de leerse — y el día que el bloqueo mató el
        // motor geo en producción (connect-src cerrado, 2026-09-14), la suite ya no avisaba de
        // nada. Un guardia que grita por lo de siempre es un guardia apagado.
        $enforcing = (bool) config('crewcare.security.csp_enforce');
        $csp       = $res->headers->get($enforcing ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only');
        $otro      = $res->headers->get($enforcing ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy');

        $this->assertNotNull($csp, $enforcing
            ? 'con csp_enforce=true la política debe ir en BLOQUEO (Content-Security-Policy).'
            : 'con csp_enforce=false la política debe ir en REPORTE (Content-Security-Policy-Report-Only).');
        $this->assertNull($otro, 'la CSP se emite en UN solo modo: nunca las dos cabeceras a la vez.');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString('report-uri /csp-report', $csp);

        // Fuera de producción NO hay HSTS (el transporte no se toca en local/testing).
        $this->assertNull($res->headers->get('Strict-Transport-Security'));
    }

    /**
     * REGRESIÓN · el motor geo necesita salir a tres servicios de OpenStreetMap.
     *
     * `connect-src 'self'` a secas los bloquea y deja MUERTO todo lo que depende de la ubicación
     * (dirección del scouting y del DSR, hospitales cercanos del PAE y del MEDEVAC, ETA). Y el fallo
     * no se ve como fallo: el navegador SÍ obtiene las coordenadas, sólo se bloquea la llamada que
     * las traduce, y el código degrada a "escríbela a mano" — su conducta correcta sin internet. En
     * pantalla parece un GPS roto, y así se reportó desde producción: "la geolocalización nunca
     * funciona, ya probé en varios dispositivos". Costó una jornada encontrarlo.
     *
     * Si alguien cierra connect-src otra vez, que lo diga la suite y no el set.
     */
    public function test_la_csp_deja_salir_a_los_servicios_del_motor_geo(): void
    {
        $res = $this->get('/login');
        $csp = $res->headers->get('Content-Security-Policy')
            ?: $res->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotNull($csp, 'sin CSP emitida no hay nada que comprobar.');

        // Los mismos hosts que usa public/js/crewcare-geo.js.
        foreach ([
            'https://nominatim.openstreetmap.org',   // dirección ⇄ coordenadas
            'https://overpass-api.de',               // hospitales cercanos
            'https://router.project-osrm.org',       // ETA por carretera
        ] as $host) {
            $this->assertStringContainsString($host, $csp,
                "connect-src debe permitir $host o el módulo geo queda muerto EN SILENCIO.");
        }
    }

    public function test_sumidero_csp_responde(): void
    {
        $this->post('/csp-report', ['csp-report' => ['violated-directive' => 'script-src']])
            ->assertNoContent();
    }

    public function test_recuperacion_de_contrasena_tiene_rate_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('password/email', ['email' => 'nadie@example.com']);
        }
        // La 7ª supera throttle:6,1 → 429 (antes NO había límite alguno).
        $this->post('password/email', ['email' => 'nadie@example.com'])->assertStatus(429);
    }
}
