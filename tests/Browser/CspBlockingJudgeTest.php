<?php

namespace Tests\Browser;

use App\Http\Controllers\ContractSignController;
use App\Models\ContractEnvelopeRecipient;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * CSP · TEST JUEZ DEL BLOQUEO — con la CSP COMPLETA en ENFORCE recorre las vistas principales y AFIRMA
 * CERO violaciones de las directivas bloqueadas: `script-src`, `img-src`, `font-src` y `style-src`.
 * (La CSP ya no deja NADA en modo reporte: enforce O nada.)
 *
 * Sólo tiene sentido con el bloqueo activo: .env.dusk.local lleva CREWCARE_CSP_ENFORCE=true. El
 * primer test es una GUARDA que falla si no está activo (para no dar un verde vacío).
 *
 * NO muta: sólo visita (GET) y lee la consola. La ceremonia se abre con URL firmada para un
 * destinatario PROPIO del admin (salta el 2º factor) y NO se firma.
 *
 * El filtro se queda con los mensajes que nombran la directiva en cuestión. `style-src` sigue en
 * REPORTE (no se bloquea aún) y NO se afirma aquí: sus reportes nombran «style-src» y se ignoran.
 *
 * CONTROLES NEGATIVOS: sin ellos un verde no prueba nada. Se inyecta una imagen y una fuente de un
 * origen externo y se exige que el enforce las BLOQUEE (mensaje sin «[Report Only]»). Igual que el
 * <base> externo para base-uri.
 *
 * Correr: php artisan dusk --filter=CspBlockingJudgeTest
 */
class CspBlockingJudgeTest extends DuskTestCase
{
    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /** Drena y devuelve TODOS los mensajes de consola como strings (getLog vacía el buffer: leer 1 vez). */
    private function browserMessages(Browser $b): array
    {
        $out = [];
        foreach ($b->driver->manage()->getLog('browser') as $entry) {
            $out[] = (string) ($entry['message'] ?? '');
        }
        return $out;
    }

    /** De un lote ya leído, las entradas que son violación CSP de $directive (enforce «Refused…» o reporte). */
    private function directiveViolations(array $messages, string $directive): array
    {
        $hits = [];
        foreach ($messages as $msg) {
            $isCsp = stripos($msg, 'Content Security Policy') !== false || stripos($msg, 'CSP') !== false;
            if ($isCsp && stripos($msg, $directive) !== false) {
                $hits[] = $msg;
            }
        }
        return $hits;
    }

    private function assertNoDirective(array $messages, string $directive, string $label): void
    {
        $v = $this->directiveViolations($messages, $directive);
        $this->assertSame(
            [],
            $v,
            "BLOQUEO: violación(es) de {$directive} en «{$label}» (algo quedó fuera de la política → roto):\n"
            . implode("\n", array_slice($v, 0, 6))
        );
    }

    /** ¿Hay un BLOQUEO enforce (no un simple report-only) que nombre $directive? */
    private function hasEnforceBlock(array $messages, string $directive): bool
    {
        foreach ($messages as $m) {
            if (stripos($m, $directive) === false) {
                continue;
            }
            $isBlock = stripos($m, 'has been blocked') !== false
                || stripos($m, 'Refused to load') !== false
                || stripos($m, 'Refused to apply') !== false;
            $isReportOnly = stripos($m, 'report only') !== false || stripos($m, 'report-only') !== false;
            if ($isBlock && ! $isReportOnly) {
                return true;
            }
        }
        return false;
    }

    /** Guarda: el juez sólo vale con el bloqueo encendido. */
    public function test_el_bloqueo_esta_activo(): void
    {
        $this->assertTrue(
            (bool) config('crewcare.security.csp_enforce'),
            'El juez requiere CREWCARE_CSP_ENFORCE=true en .env.dusk.local (CSP en bloqueo).'
        );
    }

    /** Recorre las vistas principales en BLOQUEO y exige cero violaciones de script/img/font-src. */
    public function test_vistas_principales_cero_violaciones(): void
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
                '/profile'                => 'perfil (Cropper + avatar data:)',
            ];

            foreach ($routes as $url => $label) {
                $b->driver->manage()->getLog('browser'); // drena antes de la visita
                $b->visit($url)->pause(900);
                $msgs = $this->browserMessages($b);       // lee UNA sola vez (getLog vacía el buffer)
                $this->assertNoDirective($msgs, 'script-src', $label);
                $this->assertNoDirective($msgs, 'img-src', $label);
                $this->assertNoDirective($msgs, 'font-src', $label);
                $this->assertNoDirective($msgs, 'style-src', $label);
            }

            // Ceremonia de firma (pdf.js + canvas + fuentes de firma de Google) — el flujo de más peso.
            if ($signUrl) {
                $b->driver->manage()->getLog('browser');
                $b->visit($signUrl)->pause(1400);
                $msgs = $this->browserMessages($b);
                $this->assertNoDirective($msgs, 'script-src', 'ceremonia de firma (pdf.js)');
                $this->assertNoDirective($msgs, 'img-src', 'ceremonia de firma (pdf.js)');
                $this->assertNoDirective($msgs, 'font-src', 'ceremonia de firma (pdf.js)');
                $this->assertNoDirective($msgs, 'style-src', 'ceremonia de firma (pdf.js)');
            }
        });
    }

    /** Control negativo de `img-src`: una imagen de otro origen debe quedar BLOQUEADA (no sólo reportada). */
    public function test_img_src_bloquea_una_imagen_externa(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/home')->pause(600);
            $b->driver->manage()->getLog('browser'); // drena

            $b->script(
                "var i=document.createElement('img'); i.id='evilimg';"
                . " i.src='https://evil.example/x.png'; document.body.appendChild(i);"
            );
            $b->pause(900);

            // Funcional: la imagen NO cargó (complete && naturalWidth===0 = fetch bloqueado/fallido).
            $blockedFn = $b->script(
                "var i=document.getElementById('evilimg'); return !!(i && i.complete && i.naturalWidth===0);"
            )[0] ?? false;
            $this->assertTrue((bool) $blockedFn, 'la imagen externa cargó (img-src no la bloqueó)');

            // Consola: bloqueo enforce (no report-only) que nombra img-src.
            $msgs = $this->browserMessages($b);
            $this->assertTrue(
                $this->hasEnforceBlock($msgs, 'img-src'),
                'no se registró el bloqueo enforce de img-src en la consola'
            );
        });
    }

    /** Control negativo de `font-src`: una fuente de otro origen debe quedar BLOQUEADA (no sólo reportada). */
    public function test_font_src_bloquea_una_fuente_externa(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/home')->pause(600);
            $b->driver->manage()->getLog('browser'); // drena

            // FontFace API: load() dispara la descarga de inmediato (sin depender de layout). CSP la corta.
            $b->script(
                "window.__evilFont='pending';"
                . "try{var f=new FontFace('evilf', \"url('https://evil.example/x.woff2')\");"
                . "f.load().then(function(){window.__evilFont='loaded';})"
                . ".catch(function(){window.__evilFont='blocked';});}catch(e){window.__evilFont='blocked';}"
            );
            $b->pause(1200);

            // Funcional: la promesa de carga NO resolvió a 'loaded' (el fetch fue cortado).
            $state = $b->script('return window.__evilFont;')[0] ?? null;
            $this->assertNotSame('loaded', $state, "la fuente externa cargó (font-src no la bloqueó; estado={$state})");

            // Consola: bloqueo enforce (no report-only) que nombra font-src.
            $msgs = $this->browserMessages($b);
            $this->assertTrue(
                $this->hasEnforceBlock($msgs, 'font-src'),
                'no se registró el bloqueo enforce de font-src en la consola'
            );
        });
    }

    /**
     * Control negativo de `style-src`: un <style> en línea y una hoja EXTERNA deben quedar BLOQUEADOS
     * (el vector serio de inyección de CSS), pero un `style=` en ATRIBUTO legítimo SIGUE aplicando
     * (`style-src-attr 'unsafe-inline'`). Sin esto, un verde no probaría que la separación funciona.
     */
    public function test_style_src_bloquea_estilo_y_hoja_pero_permite_atributo(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/home')->pause(600);
            $b->driver->manage()->getLog('browser'); // drena

            // 1) <style> INYECTADO sin nonce → sus reglas NO deben aplicar.
            $b->script(
                "var d=document.createElement('div'); d.id='cssprobe'; d.textContent='x';"
                . " document.body.appendChild(d);"
                . "var s=document.createElement('style'); s.textContent='#cssprobe{color:rgb(9,8,7)}';"
                . " document.head.appendChild(s);"
            );
            // 2) hoja EXTERNA → debe quedar bloqueada.
            $b->script(
                "var l=document.createElement('link'); l.rel='stylesheet';"
                . " l.href='https://evil.example/x.css'; document.head.appendChild(l);"
            );
            // 3) style= LEGÍTIMO en atributo → debe aplicar (style-src-attr 'unsafe-inline').
            $b->script(
                "var a=document.createElement('div'); a.id='attrprobe';"
                . " a.setAttribute('style','color:rgb(1,2,3)'); document.body.appendChild(a);"
            );
            $b->pause(900);

            // Funcional: el <style> NO aplicó su color, pero el style= SÍ.
            $styleColor = $b->script("var e=document.getElementById('cssprobe'); return e?getComputedStyle(e).color:'';")[0] ?? '';
            $attrColor  = $b->script("var e=document.getElementById('attrprobe'); return e?getComputedStyle(e).color:'';")[0] ?? '';
            $this->assertNotSame('rgb(9, 8, 7)', $styleColor, 'el <style> inyectado aplicó (style-src no lo bloqueó)');
            $this->assertSame('rgb(1, 2, 3)', $attrColor, "el style= legítimo NO aplicó (color={$attrColor}); style-src-attr debería permitirlo");

            // Consola: bloqueo enforce (no report-only) de style-src (por el <style> y/o la hoja externa).
            $msgs = $this->browserMessages($b);
            $this->assertTrue(
                $this->hasEnforceBlock($msgs, 'style-src'),
                'no se registró el bloqueo enforce de style-src en la consola'
            );
        });
    }

    /**
     * Control negativo de `base-uri 'self'`: un <base> hacia otro origen (que reapuntaría las rutas
     * relativas —y los scripts de la app— a otro host CON el nonce intacto) debe quedar BLOQUEADO.
     */
    public function test_base_uri_bloquea_un_base_externo(): void
    {
        $admin = $this->findUser('admin@127.0.0.1');
        $this->assertNotNull($admin, 'falta el super-admin de prueba');

        $this->browse(function (Browser $b) use ($admin) {
            $b->loginAs($admin)->visit('/home')->pause(600);
            $b->driver->manage()->getLog('browser'); // drena

            $before = $b->script('return document.baseURI;')[0] ?? null;
            $b->script("var el=document.createElement('base'); el.href='https://evil.example/'; document.head.appendChild(el);");
            $after = $b->script('return document.baseURI;')[0] ?? null;

            // Si base-uri lo bloqueó, el baseURI NO cambió a evil.example.
            $this->assertSame($before, $after, "base-uri no bloqueó el <base> inyectado (baseURI cambió a {$after})");

            // Y la consola registra el BLOQUEO enforce (no un simple report-only).
            $msgs = $this->browserMessages($b);
            $this->assertTrue(
                $this->hasEnforceBlock($msgs, 'base-uri'),
                'no se registró el bloqueo enforce de base-uri en la consola'
            );
        });
    }
}
