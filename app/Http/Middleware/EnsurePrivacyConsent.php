<?php

namespace App\Http\Middleware;

use App\Models\PrivacyConsent;
use Closure;

/**
 * PASO 0: no se recaba un dato de salud sin consentimiento registrado.
 * (2026-07-24 · PIEZA 3, corrida 2/2)
 *
 * ALCANCE DELIBERADAMENTE ESTRECHO. Este middleware NO se registra global: se cuelga sólo de
 * las rutas que RECABAN datos de salud (el cuestionario y su POST). Quien no ha aceptado el
 * aviso sigue pudiendo entrar a la app, ver su perfil y trabajar — lo único que no puede es
 * entregar datos personales sensibles sin haber leído bajo qué términos.
 *
 * Bloquear el acceso completo habría sido más fácil de programar y peor de operar: 92 usuarios
 * existentes se habrían encontrado la puerta cerrada un lunes de rodaje por un trámite que
 * nadie les anunció. El consentimiento se pide donde es exigible, no como peaje de entrada.
 *
 * El destino original se guarda en la sesión (`url.intended`) para devolver a la persona
 * exactamente a donde iba después de aceptar.
 */
class EnsurePrivacyConsent
{
    public function handle($request, Closure $next)
    {
        $usuario = $request->user();

        // Sin sesión no decide este middleware: `auth` va antes en la cadena y es quien manda
        // al login. Sin tabla tampoco bloquea (ver PrivacyConsent::supported()).
        if ($usuario && ! PrivacyConsent::aceptadoPor($usuario)) {
            // GET → se recuerda a dónde iba. En un POST no tiene sentido volver "a la URL":
            // el cuerpo del formulario se perdería igual, así que cae al cuestionario.
            session(['url.intended' => $request->isMethod('get') ? $request->fullUrl() : url('/dailyreport')]);

            return redirect()->route('privacidad.aviso');
        }

        return $next($request);
    }
}
