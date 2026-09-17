<?php

namespace App\Http\Controllers;

use App\Models\ContractEnvelope;
use App\Support\ContractVisibility;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;

/**
 * CONSULTA DE CONTRATOS (SOLO LECTURA). Cada quien ve los contratos de SU departamento; producción /
 * oficina de producción / contabilidad (y super-admin) ven TODOS. Regla en {@see ContractVisibility}.
 *
 * Aquí NO hay acciones de administración (crear/enviar/cancelar): solo listar, abrir el contrato
 * armado y descargar la copia firmada + el certificado cuando el sobre está completo. Los enlaces de
 * descarga son las rutas existentes del sobre, cuya guarda de lectura ya reconoce la visibilidad por
 * departamento (ContractEnvelopeController::authorizeConsultView).
 */
class ContractConsultController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Solo entra quien tiene panel Y ve algún contrato (evita que crew sin panel adivine la URL).
        abort_unless($user && $user->canSeePanel() && ContractVisibility::seesAny($user), 403);

        $prod = CurrentProduction::id();

        $envelopes = ContractVisibility::scopeVisible(
            ContractEnvelope::query()
                ->where('production_id', $prod)
                ->with(['contract.payee', 'contract.department', 'currentRecipient']),
            $user
        )->orderByDesc('id')->paginate(30);

        return view('contracts.consult.index', [
            'envelopes' => $envelopes,
            'seesAll'   => ContractVisibility::seesAll($user),
        ]);
    }
}
