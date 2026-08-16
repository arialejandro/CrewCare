<?php

namespace App\Http\Controllers;

use App\Models\ContractEnvelopeRecipient;
use App\Support\ContractSigning;
use App\Support\Features;
use App\Support\PendingSignatures;
use Illuminate\Http\Request;

/**
 * EL CONTRATO · PASO C — FIRMAS PENDIENTES (la "cola" de firmas a escala).
 *
 *  - index()  BANDEJA personal: los contratos cuyo turno es del usuario logueado. UNO A UNO por
 *             defecto (cada uno abre su hoja de firma sellada); LOTE solo si el admin lo prende.
 *  - board()  TABLERO admin (settings.manage): pendientes por FIGURA (quién frena la cola).
 *  - batch()  FIRMA EN LOTE (detrás del flag contracts_batch_signing): aplica la firma ADOPTADA del
 *             usuario a los seleccionados, sellando cada contrato individualmente.
 *
 * Sin permiso nuevo: la bandeja se auto-limita a las firmas del propio usuario (recipient.user_id).
 */
class PendingSignatureController extends Controller
{
    public function index()
    {
        $userId = (int) auth()->id();

        return view('contracts.pending.index', [
            'pending'    => PendingSignatures::forUser($userId),
            'batchOn'    => Features::enabled('contracts_batch_signing'),
            'hasAdopted' => (bool) optional(auth()->user())->adopted_signature,
        ]);
    }

    public function board()
    {
        return view('contracts.pending.board', [
            'groups' => PendingSignatures::byFigure(),
        ]);
    }

    public function batch(Request $request)
    {
        abort_unless(Features::enabled('contracts_batch_signing'), 403);

        $user  = auth()->user();
        $image = $user->adopted_signature;
        if (! $image) {
            return back()->with('error', __('Primero firma un contrato de forma individual para adoptar tu firma; después podrás firmar en lote.'));
        }

        $ids = (array) $request->input('recipient_ids', []);
        $signed = 0;
        $skipped = 0;

        foreach ($ids as $rid) {
            $r = ContractEnvelopeRecipient::find((int) $rid);
            // Candados: solo firmas PROPIAS (mismo usuario) y solo si es su TURNO ahora.
            if (! $r || (int) $r->user_id !== (int) $user->id) {
                $skipped++;
                continue;
            }
            $env = $r->envelope;
            // Turno unificado (B4): secuencial = puntero; paralelo = firmante abierto.
            if (! $env || ! $env->isSent() || ! ContractSigning::isOpenTurn($env, $r)) {
                $skipped++;
                continue;
            }
            try {
                ContractSigning::recordConsent($r, $request->ip());
                ContractSigning::sign($r, 'authenticated_batch', $request->ip(), $image);
                $signed++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $msg = __(':n contrato(s) firmado(s).', ['n' => $signed]);
        if ($skipped) {
            $msg .= ' ' . __(':n omitido(s).', ['n' => $skipped]);
        }

        return back()->with($signed ? 'status' : 'error', $msg);
    }
}
