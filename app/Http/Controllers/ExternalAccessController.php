<?php

namespace App\Http\Controllers;

use App\Models\Payee;
use App\Support\ExternalParty;
use Illuminate\Http\Request;

/**
 * EL INFOSHEET · FASE 4 — ACCESO del contratado NO-CREW.
 *
 *  - enter()     PÚBLICO, sin sesión: consume el hash de UN SOLO USO y lleva a la persona a su firma
 *                (que sigue pidiendo su 2º factor = RFC). Enlace inválido/ya usado → página clara.
 *  - provision() PRODUCCIÓN (gated por capture del payee): crea/reusa el usuario externo lite del
 *                payee no-crew y genera su enlace de un solo uso para enviárselo.
 */
class ExternalAccessController extends Controller
{
    public function enter(string $token)
    {
        $user = ExternalParty::consume($token);
        if (! $user) {
            return response()->view('external.access-invalid', [], 410);   // 410 Gone: usado/ inválido
        }

        $rec = ExternalParty::pendingRecipientFor($user);
        if (! $rec) {
            return response()->view('external.access-none', ['name' => $user->name]);
        }

        return redirect(ContractSignController::signUrl($rec));
    }

    public function provision(Request $request, Payee $payee)
    {
        abort_unless($request->user() && $request->user()->can('capture', $payee), 403);

        $data = $request->validate([
            'email' => 'required|email',
            'name'  => 'nullable|string|max:191',
        ]);

        $user = ExternalParty::provisionForPayee($payee, $data['email'], $data['name'] ?? null);

        // Solo tiene sentido para un contratado EXTERNO: un payee ligado a un crew real inicia
        // sesión normal, no por enlace (su token nunca casaría en consume()).
        abort_unless($user->is_external, 422, 'Este registro corresponde a una persona del crew (inicia sesión normal).');

        $link = ExternalParty::mintAccessLink($user);

        return back()
            ->with('success', __('Usuario externo listo. Comparte este enlace de un solo uso para que firme.'))
            ->with('external_link', $link);
    }
}
