<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractEnvelope;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\ContractEnvelopeBuilder;
use App\Support\ContractSigning;
use App\Support\CurrentProduction;
use App\Support\SignaturePositions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO C — EL SOBRE. Crear (congela la ruta), enviar, ver, cancelar. Misma guarda del
 * payee (PayeePolicy capture/view) — SIN permiso nuevo. La CONFIG de puestos de la ruta va por
 * `settings.manage` (config de la productora).
 */
class ContractEnvelopeController extends Controller
{
    public function store(Request $request, PayeeContract $contract)
    {
        abort_unless($contract->payee, 404);
        $this->authorize('capture', $contract->payee);

        $data = $request->validate(['contracted_email' => 'nullable|email|max:191']);

        try {
            $envelope = ContractEnvelopeBuilder::build($contract, $request->user(), $data['contracted_email'] ?? null);
        } catch (ContractEnvelopeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('contracts.envelope.show', $envelope)->with('status', __('Sobre creado.'));
    }

    public function send(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('capture', $envelope->contract->payee);

        ContractSigning::send($envelope);
        return back()->with('status', __('Sobre enviado a firma.'));
    }

    public function show(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('view', $envelope->contract->payee);

        $envelope->load('recipients', 'contract.payee');
        return view('contracts.envelope.show', compact('envelope'));
    }

    public function cancel(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('capture', $envelope->contract->payee);

        if (! $envelope->isCompleted()) {
            $envelope->update(['status' => ContractEnvelope::STATUS_CANCELLED, 'cancelled_at' => now()]);
        }
        return back()->with('status', __('Sobre cancelado.'));
    }

    /** Servir un documento del paquete (byte-intact) a un viewer autenticado con alcance. */
    public function document(Request $request, ContractEnvelope $envelope, int $index)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('view', $envelope->contract->payee);

        return self::serveDocument($envelope, $index);
    }

    /** Compartido: sirve documents[$index] byte-intact del disco privado. */
    public static function serveDocument(ContractEnvelope $envelope, int $index)
    {
        $doc = ($envelope->documents ?? [])[$index] ?? null;
        abort_unless($doc && ! empty($doc['path']) && Storage::disk('local')->exists($doc['path']), 404);
        $nice = ($doc['name'] ?? 'documento') . '.pdf';
        return Storage::disk('local')->response($doc['path'], $nice, ['Content-Type' => 'application/pdf'], 'inline');
    }

    // ── CONFIG de la ruta (puestos + orden) — settings.manage ─────────────────
    public function editConfig(Request $request)
    {
        $prodId = CurrentProduction::id();
        $positions = Position::query()
            ->where(fn ($q) => $q->whereNull('production_id')->orWhere('production_id', $prodId))
            ->where('active', 1)->orderBy('sort_order')->orderBy('name')->get();

        return view('contracts.route-config', [
            'positions'   => $positions,
            'preparerId'  => SignaturePositions::preparerPositionId(),
            'binderId'    => SignaturePositions::binderPositionId(),
            'routeOrder'  => implode(',', SignaturePositions::routeOrder()),
        ]);
    }

    public function updateConfig(Request $request)
    {
        $data = $request->validate([
            'preparer_position_id' => 'nullable|integer|exists:positions,id',
            'binder_position_id'   => 'nullable|integer|exists:positions,id',
            'route_order'          => 'nullable|string|max:120',
        ]);

        Setting::updateOrCreate(['key' => SignaturePositions::KEY_PREPARER], ['value' => $data['preparer_position_id'] ?? '']);
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_BINDER],   ['value' => $data['binder_position_id'] ?? '']);
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_ORDER],    ['value' => $data['route_order'] ?? '']);
        Branding::forget();

        return back()->with('status', __('Ruta de firma actualizada.'));
    }
}
