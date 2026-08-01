<?php

namespace App\Http\Controllers;

use App\Models\PrivacyConsent;
use App\Support\PrivacyNotice;
use Illuminate\Http\Request;

/**
 * Aviso de privacidad: mostrarlo y registrar su aceptación.
 * (2026-07-24 · PIEZA 3, corrida 2/2)
 */
class PrivacyConsentController extends Controller
{
    /** El aviso. Si ya lo aceptó, no se le vuelve a poner enfrente. */
    public function show()
    {
        if (PrivacyConsent::aceptadoPor(auth()->user())) {
            return redirect()->intended('/home');
        }

        return view('avisos.privacidad', [
            'version'    => PrivacyNotice::VERSION,
            'esBorrador' => PrivacyNotice::esBorrador(),
        ]);
    }

    /**
     * Registra la aceptación y devuelve a la persona a donde iba.
     *
     * `acepto` se valida como aceptado explícito: la casilla tiene que venir marcada. Un
     * consentimiento que se otorga por omisión —casilla premarcada, o botón sin casilla— no es
     * expreso, y expreso es justo lo que exige un dato personal sensible.
     */
    public function store(Request $request)
    {
        $request->validate(
            ['acepto' => 'accepted'],
            ['acepto.accepted' => __('health.v_privacy_accept_required')]
        );

        PrivacyConsent::registrar($request->user(), $request);

        return redirect()->intended('/dailyreport')->with('success', __('health.privacy_recorded'));
    }
}
