<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractConsent;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\ContractTemplate;
use App\Support\ContractPdfStamper;
use App\Support\ContractSigning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * EL CONTRATO · PASO C — FIRMAR. El CONTRATADO firma desde el teléfono SIN sesión, por un enlace
 * firmado POR DESTINATARIO, con su segundo factor (fecha de nacimiento si es crew; RFC si es
 * no-crew). Los internos (preparador/obliga) llegan por el mismo enlace pero, logueados y siendo
 * su propio destinatario, SALTAN el factor. Antes de firmar puede VER los documentos del sobre.
 */
class ContractSignController extends Controller
{
    const SF_MAX = 8;   // intentos del segundo factor antes de enfriar

    /** Enlace firmado por destinatario (14 días). Lo dispara el envío del sobre / el hub. */
    public static function signUrl(ContractEnvelopeRecipient $recipient, int $days = 14): string
    {
        return URL::temporarySignedRoute('contracts.sign.show', now()->addDays($days), ['recipient' => $recipient->id]);
    }

    public function show(Request $request, ContractEnvelopeRecipient $recipient)
    {
        $envelope = $recipient->envelope;
        abort_unless($envelope, 404);

        // Estado de la ruta. Cualquier estado TERMINAL (completado/anulado/rechazado/vencido) o el
        // propio destinatario ya firmado → nada que hacer aquí (la vista adapta el texto al estado).
        if ($envelope->isStopped() || $recipient->isSigned()) {
            return view('contracts.sign', ['recipient' => $recipient, 'envelope' => $envelope, 'stage' => 'done']);
        }
        if (! ContractSigning::isOpenTurn($envelope, $recipient)) {
            // Aún no es su turno (secuencial); en paralelo cualquier firmante abierto pasa.
            return view('contracts.sign', ['recipient' => $recipient, 'envelope' => $envelope, 'stage' => 'not_turn']);
        }

        // ¿Necesita segundo factor? Interno logueado que es su propio destinatario → no.
        if ($this->needsFactor($request, $recipient) && ! $this->factorPassed($recipient)) {
            return view('contracts.sign-gate', [
                'recipient' => $recipient,
                'factor'    => ContractSigning::factorType($envelope),   // borndate | rfc
                'verifyUrl' => URL::temporarySignedRoute('contracts.sign.verify', now()->addHours(3), ['recipient' => $recipient->id]),
                'error'     => null,
                'locked'    => false,
            ]);
        }

        // Puede ver documentos + consentir + firmar.
        ContractSigning::markViewed($recipient);
        [$type, $id] = $this->consenterKey($recipient);
        $needsConsent = $id !== null && ! ContractConsent::has($type, $id);

        $fill = $this->fillableSignData($envelope, $recipient);

        return view('contracts.sign', [
            'recipient'    => $recipient,
            'envelope'     => $envelope,
            'stage'        => 'sign',
            'needsConsent' => $needsConsent,
            'signUrl'      => URL::temporarySignedRoute('contracts.sign.do', now()->addHours(3), ['recipient' => $recipient->id]),
            'declineUrl'   => URL::temporarySignedRoute('contracts.sign.decline', now()->addHours(3), ['recipient' => $recipient->id]),
            'docUrl'       => fn ($i) => URL::temporarySignedRoute('contracts.sign.document', now()->addHours(3), ['recipient' => $recipient->id, 'index' => $i]),
            // DocuSign-like (solo modo PDF-fillable): el contrato ARMADO + los lugares de firma del destinatario.
            'fillable'     => $fill['fillable'],
            'signFields'   => $fill['signFields'],
            'contractUrl'  => $fill['contractUrl'],
        ]);
    }

    public function verify(Request $request, ContractEnvelopeRecipient $recipient)
    {
        $envelope = $recipient->envelope;
        abort_unless($envelope, 404);

        $key = 'sign2fa:' . $recipient->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, self::SF_MAX)) {
            return view('contracts.sign-gate', [
                'recipient' => $recipient, 'factor' => ContractSigning::factorType($envelope),
                'verifyUrl' => URL::temporarySignedRoute('contracts.sign.verify', now()->addHours(3), ['recipient' => $recipient->id]),
                'error' => __('Demasiados intentos por ahora. Espera un momento e inténtalo de nuevo, o avísale a producción.'), 'locked' => true,
            ]);
        }
        RateLimiter::hit($key, 3600);

        if (ContractSigning::verifyFactor($recipient, (string) $request->input('factor_value'))) {
            RateLimiter::clear($key);
            session()->put($this->sessionKey($recipient), true);
            return redirect(self::signUrl($recipient, 14));
        }

        return view('contracts.sign-gate', [
            'recipient' => $recipient, 'factor' => ContractSigning::factorType($envelope),
            'verifyUrl' => URL::temporarySignedRoute('contracts.sign.verify', now()->addHours(3), ['recipient' => $recipient->id]),
            'error' => __('El dato no coincide. Revísalo e inténtalo de nuevo; si sigue sin coincidir, avísale a producción.'), 'locked' => false,
        ]);
    }

    public function sign(Request $request, ContractEnvelopeRecipient $recipient)
    {
        $envelope = $recipient->envelope;
        abort_unless($envelope, 404);

        // Guarda del factor (salvo interno logueado que es su propio destinatario).
        if ($this->needsFactor($request, $recipient) && ! $this->factorPassed($recipient)) {
            abort(403);
        }

        // FIRMA AUTÓGRAFA obligatoria (DocuSign): la imagen dibujada es la representación física de
        // la autorización, más allá del sello. Sin ella no se firma.
        $request->validate(['signature_image' => 'required|string|min:100']);
        $image = (string) $request->input('signature_image');

        // Consentimiento electrónico (aparte, una vez por persona).
        if ($request->boolean('consent')) {
            ContractSigning::recordConsent($recipient, $request->ip());
        }

        // Adopción de firma para reúso (solo el firmante interno logueado sobre su propio
        // destinatario; el contratado no-crew no tiene usuario donde guardarla).
        $u = $request->user();
        if ($request->boolean('save_signature') && $u && (int) $u->id === (int) $recipient->user_id) {
            $u->adopted_signature = $image;
            $u->save();
        }

        $method = $this->needsFactor($request, $recipient) ? 'signed_link_2fa' : 'authenticated';
        try {
            ContractSigning::sign($recipient, $method, $request->ip(), $image);
        } catch (ContractEnvelopeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(self::signUrl($recipient, 14))->with('status', __('Firma registrada.'));
    }

    /**
     * RECHAZAR (Fase 2) — el firmante en turno se niega, con MOTIVO obligatorio. Misma guarda del
     * factor que firmar (solo quien pasó el 2º factor, o el interno logueado, puede rechazar). Detiene
     * el sobre. Vuelve a la misma página, que ahora muestra el estado "rechazado".
     */
    public function decline(Request $request, ContractEnvelopeRecipient $recipient)
    {
        $envelope = $recipient->envelope;
        abort_unless($envelope, 404);

        if ($this->needsFactor($request, $recipient) && ! $this->factorPassed($recipient)) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:500']);

        try {
            ContractSigning::decline($recipient, $request->ip(), $data['reason']);
        } catch (ContractEnvelopeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(self::signUrl($recipient, 14))->with('status', __('Registramos que no firmarás este contrato.'));
    }

    public function document(Request $request, ContractEnvelopeRecipient $recipient, int $index)
    {
        $envelope = $recipient->envelope;
        abort_unless($envelope, 404);
        // Debe poder VER solo quien pasó el factor (o interno logueado).
        if ($this->needsFactor($request, $recipient) && ! $this->factorPassed($recipient)) {
            abort(403);
        }
        return ContractEnvelopeController::serveDocument($envelope, $index);
    }

    /**
     * DocuSign-like — sirve el CONTRATO ARMADO (la plantilla PDF-fillable estampada con datos +
     * firmas previas; las pendientes en blanco) al firmante, para renderizarlo inline y superponer
     * SUS lugares de firma. Misma guarda del factor que ver documentos. Si el estampado falla, cae al
     * PDF ORIGINAL (mismas coordenadas de página → los marcadores igual alinean). Byte-intact aparte:
     * esto es SOLO para leer/firmar en pantalla; el paquete sellado no se toca.
     */
    public function contractDocument(Request $request, ContractEnvelopeRecipient $recipient)
    {
        $envelope = $recipient->envelope;
        abort_unless($envelope, 404);
        if ($this->needsFactor($request, $recipient) && ! $this->factorPassed($recipient)) {
            abort(403);
        }

        $contract = $envelope->contract;
        abort_unless($contract, 404);
        $template = ContractTemplate::activeFor($envelope->production_id, $contract->concept);
        abort_unless($template && $template->isPdfSource(), 404);

        try {
            $bytes = ContractPdfStamper::stampForEnvelope($envelope, $template);
        } catch (\Throwable $e) {
            $path = $template->pdf_path;
            abort_unless($path && Storage::disk('local')->exists($path), 404);
            $bytes = Storage::disk('local')->get($path);
        }

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato.pdf"',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    /**
     * Datos de la ceremonia DocuSign-like para ESTE destinatario. Solo aplica al modo PDF-fillable
     * (el contrato es un PDF con `field_map`); en el modo generado no hay coordenadas → se cae a la
     * lectura inline (6A). Devuelve MIS lugares de firma (campos 'sign' cuya ancla == mi `anchor_key`)
     * en % de página + la URL firmada del contrato armado. Sin lugares → no hay ceremonia.
     *
     * @return array{fillable: bool, signFields: array, contractUrl: ?string}
     */
    private function fillableSignData(ContractEnvelope $envelope, ContractEnvelopeRecipient $recipient): array
    {
        $none = ['fillable' => false, 'signFields' => [], 'contractUrl' => null];

        $contract = $envelope->contract;
        if (! $contract) {
            return $none;
        }
        $template = ContractTemplate::activeFor($envelope->production_id, $contract->concept);
        if (! $template || ! $template->isPdfSource()) {
            return $none;
        }

        // MIS lugares: campos de FIRMA cuya ancla es la de este destinatario (misma clave que estampa
        // el motor). El resto (datos, firmas de otros) ya vienen dibujados en el PDF armado.
        $mine = array_values(array_filter(
            $template->signFields(),
            fn ($f) => (string) ($f['key'] ?? '') === (string) $recipient->anchor_key
        ));
        if (empty($mine)) {
            return $none;
        }

        $signFields = array_map(fn ($f) => [
            'page'  => (int) $f['page'],
            'x_pct' => (float) $f['x_pct'],
            'y_pct' => (float) $f['y_pct'],
            'w_pct' => (float) ($f['w_pct'] ?? 24),
        ], $mine);

        return [
            'fillable'    => true,
            'signFields'  => $signFields,
            'contractUrl' => URL::temporarySignedRoute('contracts.sign.contract', now()->addHours(3), ['recipient' => $recipient->id]),
        ];
    }

    /** Necesita factor salvo que el auth user sea ESTE destinatario (interno con sesión). */
    private function needsFactor(Request $request, ContractEnvelopeRecipient $recipient): bool
    {
        $u = $request->user();
        return ! ($u && $recipient->user_id && (int) $u->id === (int) $recipient->user_id);
    }

    private function factorPassed(ContractEnvelopeRecipient $recipient): bool
    {
        return (bool) session($this->sessionKey($recipient));
    }

    private function sessionKey(ContractEnvelopeRecipient $recipient): string
    {
        return "sign_2fa_ok.{$recipient->id}";
    }

    private function consenterKey(ContractEnvelopeRecipient $recipient): array
    {
        if ($recipient->user_id)  { return [ContractConsent::TYPE_USER,  (int) $recipient->user_id]; }
        if ($recipient->payee_id) { return [ContractConsent::TYPE_PAYEE, (int) $recipient->payee_id]; }
        return [ContractConsent::TYPE_USER, null];
    }
}
