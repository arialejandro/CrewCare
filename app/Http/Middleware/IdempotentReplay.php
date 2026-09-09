<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IdempotentReplay — envío diferido offline (Camino A), capa de "a lo hecho, hecho".
 *
 * POR QUÉ: un borrador de reporte capturado SIN RED se reproduce, al reconectar, por
 * la MISMA ruta store() que usa el formulario en línea (valida, ensambla, sella una
 * sola vez). Si la red se corta justo después de que el server creó y SELLÓ el
 * documento pero antes de que la respuesta llegue al cliente, el cliente reintenta el
 * mismo POST. Sin protección eso crearía un SEGUNDO documento sellado. Este middleware
 * lo impide: el cliente manda una llave de idempotencia (cabecera X-Idempotency-Key =
 * id del borrador) y aquí garantizamos "a lo más una vez".
 *
 * ADITIVO Y NO INTRUSIVO: si la petición NO trae la cabecera (todos los envíos
 * interactivos en línea), es un passthrough puro — el flujo normal ni lo nota. Solo
 * los reenvíos diferidos, que sí traen la llave, pasan por la lógica de abajo.
 *
 * CONTRATO:
 *   - Se RECLAMA la llave (INSERT único) ANTES de correr el store().
 *   - store() con éxito (2xx/3xx)  → se SELLA la llave con su response_status →
 *     cualquier reintento contesta 'duplicate' (200) sin volver a crear/sellar.
 *   - store() con error de validación (4xx) o excepción → la llave se LIBERA →
 *     un reintento corregido sí puede pasar (el borrador se conservó con su error).
 *   - Llave ya reclamada pero aún sin resultado (en vuelo) → 409 'in_progress',
 *     el cliente reintenta más tarde.
 */
class IdempotentReplay
{
    public function handle(Request $request, Closure $next)
    {
        $key = trim((string) $request->header('X-Idempotency-Key', ''));

        // Sin llave = envío interactivo normal → no tocamos nada.
        if ($key === '') {
            return $next($request);
        }

        // Defensivo para prod: si la tabla aún no está aplicada, degradamos a passthrough
        // en vez de reventar (el gemelo owner-apply puede no haberse corrido todavía).
        if (! Schema::hasTable('idempotency_keys')) {
            return $next($request);
        }

        $userId = optional($request->user())->getAuthIdentifier();
        $now    = now();

        // 1) Reclamar la llave. El UNIQUE de idem_key hace atómico el "quién llegó primero".
        try {
            DB::table('idempotency_keys')->insert([
                'idem_key'        => $key,
                'user_id'         => $userId,
                'method'          => $request->getMethod(),
                'path'            => mb_substr($request->path(), 0, 255),
                'response_status' => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        } catch (QueryException $e) {
            // Ya estaba reclamada (en vuelo o completada).
            $row = DB::table('idempotency_keys')->where('idem_key', $key)->first();
            if ($row && $row->response_status !== null) {
                // Ya se procesó con éxito → NO repetir. Duplicado limpio.
                return response()->json(['status' => 'duplicate', 'idempotent' => true], 200);
            }
            // En vuelo (aún sin resultado) → que el cliente reintente luego.
            return response()->json(['status' => 'in_progress', 'idempotent' => true], 409);
        }

        // 2) Somos dueños de la llave: corre el store() EXACTAMENTE una vez.
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Reventó → libera la llave para que un reintento pueda pasar, y re-lanza.
            DB::table('idempotency_keys')->where('idem_key', $key)->delete();
            throw $e;
        }

        // 3) Sellar o liberar según el resultado.
        $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 200;
        if ($status >= 400) {
            // Validación (422) u otra falla → libera; el borrador se conserva con su error.
            DB::table('idempotency_keys')->where('idem_key', $key)->delete();
        } else {
            // Éxito (2xx/3xx) → sella; un reintento con la misma llave será 'duplicate'.
            DB::table('idempotency_keys')->where('idem_key', $key)->update([
                'response_status' => $status,
                'updated_at'      => now(),
            ]);
        }

        return $response;
    }
}
