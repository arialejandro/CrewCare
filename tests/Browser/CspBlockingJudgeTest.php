<?php

namespace Tests\Browser;

use App\Http\Controllers\ContractSignController;
use App\Models\ContractEnvelopeRecipient;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TEST JUEZ DEL BLOQUEO — con la CSP en ENFORCE (script-src 'self' 'nonce-…'), recorre las
 * vistas principales y AFIRMA CERO violaciones de script-src en la consola del navegador.
 *
 * Sólo tiene sentido con el bloqueo activo: .env.dusk.local lleva CREWCARE_CSP_ENFORCE=true. El
 * primer test es una GUARDA que falla si no está activo (para no dar un verde vacío).
 *
 * NO muta: sólo visita (GET) y lee la consola. La ceremonia se abre con URL firmada para un
 * destinatario PROPIO del admin (salta el 2º factor) y NO se firma.
 *
 * El filtro se queda con los mensajes que nombran «script-src» (la directiva que se bloquea);
 * las violaciones de font-src/style-src/img-src (que siguen en REPORTE) nombran su propia
 * directiva y NO cuentan. Así, un font-src en reporte no ensucia el juez.
 *
 * Correr: php artisan dusk --filter=CspBlockingJudgeTest
 */
class CspBlockingJudgeTest extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** Entradas de consola que son violación de script-src (enforce «Refused …» o reporte «violates …»). */
    private function scriptSrcViolations(Browser $b): array
    {
        $logs = $b->driver->manage()->getLog('browser');
        $hits = [];
        foreach ($logs as $entry) {
            $msg = (string) ($entry['message'] ?? '');
            $isCsp = stripos($msg, 'Content Security Policy') !== false || stripos($msg, 'CSP') !== false;
            if ($isCsp && stripos($msg, 'script-src') !== false) {
                $hits[] = $msg;
            }
        }
        return $hits;
    }

    private function assertNoScriptSrc(Browser $b, string $label): void
    {
        $v = $this->scriptSrcViolations($b);
        $this->assertSame(
            [],
            $v,
            "BLOQUEO: violación(es) de script-src en «{$label}» (un script quedó fuera del nonce/'self' → muerto):\n"
            . implode("\n", array_slice($v, 0, 6))
        );
    }

    /** Guarda: el juez sólo vale con el bloqueo encendido. */
    public function test_el_bloqueo_esta_activo(): void
    {
        $this->assertTrue(
            (bool) config('crewcare.security.csp_enforce'),
            'El juez requiere CREWCARE_CSP_ENFORCE=true en .env.dusk.local (CSP en bloqueo).'
        );
    }

    /** Recorre las vistas principales en BLOQUEO y exige cero violaciones de script-src. */
    public function test_vistas_principales_cero_violaciones_script_src(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        // Ceremonia: destinatario PROPIO del admin (interno logueado → salta el 2º factor). Preferir
        // uno aún no firmado (muestra el asistente con pdf.js); si no, cualquiera renderiza los scripts.
        $own = ContractEnvelopeRecipient::where('user_id', $admin->id)
            ->whereIn('status', ['sent', 'viewed'])->first()
            ?? ContractEnvelopeRecipient::where('user_id', $admin->id)->first();
        $signUrl = $own ? ContractSignController::signUrl($own, 7) : null;

        $this->browse(function (Browser $b) use ($admin, $signUrl) {
            $b->loginAs($admin);

            // (scouting · llamado · orden de transportación · documentos standalone impresos)
            $routes = [
                '/home'                   => 'dashboard (Chart.js + inline)',
                '/scoutings'              => 'scouting (lista)',
                '/scoutings/create'       => 'scouting (captura)',
                '/scoutings/1/amazon'     => 'documento standalone impreso (amazon)',
                '/historialWR/1?print=1'  => 'documento standalone impreso (historial)',
                '/llamado'                => 'llamado',
                '/transportacion/orden/1' => 'orden de transportación',
            ];

            foreach ($routes as $url => $label) {
                $b->driver->manage()->getLog('browser'); // drena antes de la visita
                $b->visit($url)->pause(900);
                $this->assertNoScriptSrc($b, $label);
            }

            // Ceremonia de firma (pdf.js + canvas) — el flujo de más peso.
            if ($signUrl) {
                $b->driver->manage()->getLog('browser');
                $b->visit($signUrl)->pause(1400);
                $this->assertNoScriptSrc($b, 'ceremonia de firma (pdf.js)');
            }
        });
    }
}
