<?php

namespace App\Http\Controllers;

use App\Support\SealVerifier;
use Illuminate\Http\Request;

/**
 * SealVerificationController — VERIFICADOR PÚBLICO de sellos (2026-07-24).
 *
 * Página PÚBLICA (sin auth) a la que llega un tercero escaneando el QR impreso en el sello CFDI.
 * SIN 'signed' a propósito: el QR va impreso en un documento que se entrega, así que la URL es
 * pública por diseño; firmarla no protegería nada y rompería el papel ya emitido. La contención
 * es el throttle de la ruta + que la respuesta no contenga NADA sensible.
 *
 * ⚠ TODO EL CONTRATO DE PRIVACIDAD VIVE EN SealVerifier::resolve(): devuelve un array de cinco
 * claves y el modelo muere allí dentro. Este controlador NUNCA ve el modelo, así que no puede
 * pasárselo a la vista ni por descuido.
 *
 * RESPUESTA GENÉRICA: tipo inválido y uuid inexistente responden EXACTAMENTE lo mismo (404 + la
 * misma página). Si el "tipo desconocido" respondiera distinto, un tercero podría sondear qué
 * tipos existen; y si el "uuid inexistente" dijera "no existe" mientras uno real dice "sin sello",
 * la diferencia ya sería una filtración.
 */
class SealVerificationController extends Controller
{
    public function show(Request $request, $tipo, $uuid)
    {
        $acuse = SealVerifier::resolve($tipo, $uuid);

        if ($acuse === null) {
            return response()->view('public.verify', ['acuse' => null], 404);
        }

        return response()->view('public.verify', ['acuse' => $acuse]);
    }

    /**
     * Descarga el TOKEN de sello de tiempo (.tsr) de un documento. Público, sin sesión (igual que
     * el verificador): es la pieza que un tercero necesita para verificar el timbre por su cuenta.
     * El modelo muere dentro de SealVerifier::resolveTimbre(); aquí sólo llegan los bytes del token.
     *
     * 404 GENÉRICO: tipo inválido, uuid inexistente, documento sin sello o sin timbre → todos el
     * mismo 404 (no filtra en qué estado está un documento que no es el que pide el que descarga).
     */
    public function timbre(Request $request, $tipo, $uuid)
    {
        $t = \App\Support\SealVerifier::resolveTimbre($tipo, $uuid);

        if ($t === null || empty($t['tsr'])) {
            abort(404);
        }

        $folio = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($t['folio'] ?? 'timbre'));

        return response($t['tsr'], 200, [
            'Content-Type'        => 'application/timestamp-reply',              // RFC 3161
            'Content-Disposition' => 'attachment; filename="timbre-' . $folio . '.tsr"',
            'Content-Length'      => (string) strlen($t['tsr']),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
