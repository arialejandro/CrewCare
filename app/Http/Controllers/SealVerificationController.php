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
}
