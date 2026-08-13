<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractEmitException;
use App\Models\ContractClause;
use App\Models\PayeeContract;
use App\Support\ContractEmitter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO B — EMITIR el documento + servir la carátula. La emisión reusa la MISMA
 * guarda del payee (PayeePolicy `capture` = "quien contrata puede capturar"); cero permisos nuevos.
 * La captura rica (panel del contrato) es la superficie GRANDE que llega en su propio paso; aquí
 * queda el endpoint de emisión, ya usable y probado.
 */
class ContractController extends Controller
{
    /** Emite el contrato: congela y genera la carátula. Si falta el contratante, lo dice claro. */
    public function emit(Request $request, PayeeContract $contract)
    {
        abort_unless($contract->payee, 404);
        $this->authorize('capture', $contract->payee);

        $data = $request->validate([
            'clause_id' => 'required|integer|exists:contract_clauses,id',
            'language'  => 'nullable|in:' . implode(',', array_keys(ContractClause::languages())),
        ]);

        $clause = ContractClause::findOrFail($data['clause_id']);

        try {
            ContractEmitter::emit($contract, $clause, $data['language'] ?? null, $request->user());
        } catch (ContractEmitException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Contrato emitido.'));
    }

    /** Sirve el PDF de la carátula generada (privado, gateado por la visibilidad del payee). */
    public function caratula(Request $request, PayeeContract $contract)
    {
        abort_unless($contract->payee, 404);
        $this->authorize('view', $contract->payee);

        abort_unless($contract->caratula_path && Storage::disk('local')->exists($contract->caratula_path), 404);

        return Storage::disk('local')->response(
            $contract->caratula_path,
            'caratula-' . $contract->id . '.pdf',
            ['Content-Type' => 'application/pdf'],
            'inline'
        );
    }
}
