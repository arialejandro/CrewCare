<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * SUBSISTEMA SYNC / API (Módulo 12) — RETIRADO (2026-08-29).
 *
 * POR QUÉ SE RETIRÓ: `up()` recibía un lote de reportes offline y hacía un UPSERT
 * directo por 'uuid' a las columnas $fillable de cada modelo. Ese camino se SALTABA
 * la validación (Form Request), el ensamblado (imágenes, testigos, normas) y —lo más
 * grave— `signDocument`: los documentos así creados quedaban SIN VALIDAR y SIN SELLAR,
 * a diferencia del store() interactivo. Era una asimetría peligrosa (un documento de
 * cumplimiento sin sello no es válido) y, aunque iba bajo 'auth', cualquier sesión podía
 * inyectar reportes sin sellar. Nunca debió conectarse así.
 *
 * QUÉ LO REEMPLAZA: el ENVÍO DIFERIDO (Camino A). El borrador capturado sin red se
 * reproduce, al reconectar, por la MISMA ruta store() del formulario en línea — la
 * validación, el ensamblado, las imágenes y el SELLO corren UNA SOLA VEZ, por el único
 * camino que existe. La no-duplicación la garantiza el middleware 'idempotent'
 * (App\Http\Middleware\IdempotentReplay) con la cabecera X-Idempotency-Key. Cliente:
 * public/js/cc-drafts.js (cola + reenvío). La ruta POST /api/sync/up quedó ELIMINADA de
 * routes/web.php; este stub solo existe por si alguien la re-cablea por error: responde
 * 410 Gone en vez de crear documentos sin sellar.
 */
class SyncController extends Controller
{
    /**
     * Retirado. No crea nada: contesta 410 Gone y apunta al reemplazo.
     */
    public function up(Request $request)
    {
        abort(410, 'Endpoint retirado: el envío offline se hace por la ruta store() normal (envío diferido idempotente).');
    }
}
