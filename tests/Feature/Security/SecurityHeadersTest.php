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

        $csp = $res->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($csp, 'la CSP va en modo REPORTE (no bloqueo)');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString('report-uri /csp-report', $csp);

        // Fuera de producción NO hay HSTS ni CSP de bloqueo.
        $this->assertNull($res->headers->get('Strict-Transport-Security'));
        $this->assertNull($res->headers->get('Content-Security-Policy'));
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
