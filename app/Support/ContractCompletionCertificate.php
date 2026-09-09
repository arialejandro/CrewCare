<?php

namespace App\Support;

use App\Models\ContractEnvelope;

/**
 * EL CONTRATO · PASO C · FASE 3 — CERTIFICADO DE CIERRE (estilo DocuSign "Certificate of Completion").
 * Es una VISTA de datos ya sellados: quién firmó, cuándo, desde qué IP, con qué método y con qué hash;
 * la integridad de la cadena de eventos (bitácora, Fase 1); el hash del documento firmado (Fase 3b); y
 * el QR al verificador público. NO se sella por sí mismo (es reproducible de las fuentes selladas).
 *
 * Reutilizable por la descarga (controlador) y por el correo de cierre (adjunto). El HTML es autónomo
 * (listo para PDF) → se pasa a {@see ContractPdf}.
 */
class ContractCompletionCertificate
{
    /** El certificado como HTML autónomo (listo para PDF o para servir en pantalla). */
    public static function html(ContractEnvelope $envelope): string
    {
        $envelope->loadMissing('recipients', 'contract.payee');

        $parties = $envelope->orderedRecipients()->get()->map(function ($r) {
            $sig = $r->signatures()->latest('id')->first();
            return [
                'name'      => $r->name ?: '—',
                'role'      => $r->cargo ?: $r->roleLabel(),
                'email'     => $r->email,
                'sent_at'   => optional($r->sent_at)->format('d/m/Y H:i'),
                'viewed_at' => optional($r->viewed_at)->format('d/m/Y H:i'),
                'signed_at' => optional($r->signed_at)->format('d/m/Y H:i'),
                'ip'        => $r->ip_address,
                'method'    => $r->sign_method,
                'signed'    => $r->isSigned(),
                'hash'      => $sig ? $sig->document_hash : null,
            ];
        })->all();

        $events = ContractEventLog::forEnvelope($envelope)->map(fn ($e) => [
            'label' => ContractEventLog::label($e->event),
            'at'    => optional($e->occurred_at)->format('d/m/Y H:i:s'),
            'tz'    => $e->display_timezone,
            'actor' => $e->actor_label ?: __('Sistema'),
            'ip'    => $e->ip_address,
        ])->all();

        $verifyUrl = SealVerifier::urlFor($envelope);

        return view('contracts.certificate', [
            'envelope'  => $envelope,
            'folio'     => $envelope->folio(),
            'parties'   => $parties,
            'events'    => $events,
            'chain'     => ContractEventLog::verifyChain($envelope),
            'signed'    => $envelope->signed_document,
            'verifyUrl' => $verifyUrl,
            'qr'        => $verifyUrl ? SealVerifier::qrSvg($verifyUrl, 128) : null,
        ])->render();
    }
}
