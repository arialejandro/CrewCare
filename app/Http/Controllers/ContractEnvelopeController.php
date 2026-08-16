<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractTemplate;
use App\Models\PayeeContract;
use App\Models\Position;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\ContractEnvelopeBuilder;
use App\Support\ContractEventLog;
use App\Support\ContractSigning;
use App\Support\ContractTemplateRenderer;
use App\Support\CurrentProduction;
use App\Support\SignaturePositions;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // FASE 1c — ¿hay plantilla activa para este subtipo? Si la hay, el sobre puede mostrar el
        // documento ARMADO con la plantilla + las firmas reales (link solo si existe → nada vacío).
        $template = ContractTemplate::activeFor($envelope->production_id, optional($envelope->contract)->concept);

        return view('contracts.envelope.show', ['envelope' => $envelope, 'hasTemplate' => (bool) $template]);
    }

    /**
     * FASE 1c — el CONTRATO ARMADO con la plantilla activa del subtipo + las firmas REALES del sobre.
     * Cada `[[firma:...]]` se estampa con la autógrafa CONGELADA de su destinatario (o "pendiente" si
     * aún no firma). Prueba viva del lazo plantilla → firma, sin tocar el paquete byte-intact.
     */
    public function templateDocument(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('view', $envelope->contract->payee);

        $contract = $envelope->contract;
        $template = ContractTemplate::activeFor($envelope->production_id, $contract->concept);
        abort_unless($template, 404);

        $envelope->load('recipients');
        $inner = ContractTemplateRenderer::render(
            $template,
            ContractTemplateRenderer::valuesFor($contract),
            ContractTemplateRenderer::sigMapForEnvelope($envelope)
        );

        // La "rúbrica en cada página" (initials_each_page) se coloca por coordenadas en inc.3c-2
        // (excluyendo la hoja de Firmas); el render base ya no la pinta.
        return response(ContractTemplateRenderer::page(
            $inner, $template->architecture, $template->page_size, null, $template->font_family, $template->font_size
        ));
    }

    /** ANULAR (Fase 2) — con MOTIVO obligatorio. Estado terminal; queda el motivo + el evento. */
    public function cancel(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('capture', $envelope->contract->payee);

        $data = $request->validate(['reason' => 'required|string|max:500']);

        if ($envelope->isStopped()) {
            return back()->with('error', __('Este sobre ya está cerrado; no se puede anular.'));
        }

        $reason = trim($data['reason']);
        $envelope->update([
            'status'               => ContractEnvelope::STATUS_CANCELLED,
            'cancelled_at'         => now(),
            'resolution_reason'    => $reason,
            'current_recipient_id' => null,
        ]);
        ContractEventLog::record($envelope, ContractEnvelopeEvent::CANCELLED, [
            'payload' => ['reason' => $reason],
        ]);

        return back()->with('status', __('Sobre anulado.'));
    }

    /** REENVIAR (Fase 2) — recordatorio manual al turno actual: sella resent_at + registra el evento. */
    public function resend(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('capture', $envelope->contract->payee);

        try {
            ContractSigning::resend($envelope, $request->user());
        } catch (ContractEnvelopeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Recordatorio de firma registrado.'));
    }

    /**
     * FASE 3c — CERTIFICADO DE CIERRE (constancia del proceso de firma). Es una vista de datos ya
     * sellados; en pantalla (HTML) o como PDF (`?pdf=1`, Chrome headless).
     */
    public function certificate(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('view', $envelope->contract->payee);

        $html = \App\Support\ContractCompletionCertificate::html($envelope);

        if ($request->boolean('pdf')) {
            $bytes = \App\Support\ContractPdf::render($html);
            return response($bytes, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Certificado-' . $envelope->folio() . '.pdf"',
            ]);
        }

        return response($html);
    }

    /** FASE 3 — sirve el CONTRATO FIRMADO congelado (PDF con autógrafas) del disco privado. */
    public function signedDocument(Request $request, ContractEnvelope $envelope)
    {
        abort_unless($envelope->contract && $envelope->contract->payee, 404);
        $this->authorize('view', $envelope->contract->payee);

        $meta = $envelope->signed_document ?? [];
        abort_unless(! empty($meta['path']) && Storage::disk('local')->exists($meta['path']), 404);

        $nice = 'Contrato-firmado-' . $envelope->folio() . '.pdf';
        return Storage::disk('local')->response($meta['path'], $nice, ['Content-Type' => 'application/pdf'], 'inline');
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

        // Elegibles para el picker: SOLO cabezas de producción (no 200+ puestos). El nombre de una
        // entrada YA configurada se resuelve contra TODOS los puestos (por si quedó una legacy).
        $eligible = SignaturePositions::signerEligiblePositions($prodId);
        $allById  = $positions->keyBy('id');
        $mapEntries = fn (array $entries) => collect($entries)->map(function ($e) use ($allById) {
            if ($e === SignaturePositions::DEPT_HOD) {
                return ['id' => SignaturePositions::DEPT_HOD, 'name' => __('HOD del departamento del contrato')];
            }
            return ['id' => (int) $e, 'name' => optional($allById->get((int) $e))->name ?: ('#' . $e)];
        })->values()->all();

        $authorizers = $mapEntries(SignaturePositions::authorizerEntries());
        $signers     = $mapEntries(SignaturePositions::signerEntries());

        // OCUPANTE resuelto por puesto (para el preview de la ruta, estilo Signus): quién ocupa HOY
        // cada puesto en esta producción, o si está vacante / duplicado. El token dinámico dept_hod
        // se resuelve por el departamento de CADA contrato, así que aquí no tiene ocupante fijo.
        $needIds = $eligible->pluck('id')
            ->merge(collect($authorizers)->pluck('id'))
            ->merge(collect($signers)->pluck('id'))
            ->reject(fn ($id) => $id === SignaturePositions::DEPT_HOD)
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $occupants = [];
        foreach ($needIds as $pid) {
            $occupants[$pid] = self::occupantFor($prodId, $pid);
        }

        return view('contracts.route-config', [
            'positions'   => $positions,
            'eligible'    => $eligible,
            'preparerId'  => SignaturePositions::preparerPositionId(),
            'binderId'    => SignaturePositions::binderPositionId(),
            'routeOrder'  => implode(',', SignaturePositions::routeOrder()),
            'authorizers' => $authorizers,
            'signers'     => $signers,
            'occupants'   => $occupants,
        ]);
    }

    /** Ocupante ÚNICO de un puesto en la producción (sin excepción): ok / vacante / duplicado. */
    private static function occupantFor(int $prodId, int $positionId): array
    {
        $userIds = DB::table('production_user')
            ->where('production_id', $prodId)
            ->where('position_id', $positionId)
            ->pluck('user_id');

        if ($userIds->count() === 0) {
            return ['state' => 'vacant', 'name' => null];
        }
        if ($userIds->count() > 1) {
            return ['state' => 'duplicate', 'name' => null];
        }

        return ['state' => 'ok', 'name' => optional(User::find($userIds->first()))->name];
    }

    public function updateConfig(Request $request)
    {
        $request->validate([
            'authorizer_position_ids'   => 'nullable|array',
            'authorizer_position_ids.*' => 'string|max:20',
            'signer_position_ids'       => 'nullable|array',
            'signer_position_ids.*'     => 'string|max:20',
        ]);

        // Cada entrada es 'dept_hod' o un id de puesto existente; se sanea (descarta lo inválido).
        $sanitize = fn ($arr) => collect($arr ?? [])->map(function ($v) {
            if ($v === SignaturePositions::DEPT_HOD) {
                return SignaturePositions::DEPT_HOD;
            }
            $id = (int) $v;
            return ($id > 0 && Position::whereKey($id)->exists()) ? $id : null;
        })->filter(fn ($v) => $v !== null)->values()->all();

        // MÓDULO DE FIRMA · las dos listas (autorizadores del paso 2, firmantes del paso 4). La ruta
        // clásica (preparador/obliga) se conserva SOLO como fallback en código; ya no se edita aquí.
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_AUTHORIZERS], ['value' => json_encode($sanitize($request->input('authorizer_position_ids')))]);
        Setting::updateOrCreate(['key' => SignaturePositions::KEY_SIGNERS],     ['value' => json_encode($sanitize($request->input('signer_position_ids')))]);
        Branding::forget();

        return back()->with('status', __('Ruta de firma actualizada.'));
    }
}
