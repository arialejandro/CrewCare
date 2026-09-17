<?php

namespace App\Jobs;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeEvent;
use App\Models\ContractEnvelopeRecipient;
use App\Support\ContractCompletionCertificate;
use App\Support\ContractEventLog;
use App\Support\ContractPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO C — entrega del paquete FIRMADO al contratado (los PDF adjuntos + certificado).
 *
 * Es un JOB para poder salir del request bajo carga (50-60 sobres completándose casi juntos): el
 * mandar SMTP con adjuntos es lo único PESADO del acto de firmar. Lo despacha
 * {@see \App\Listeners\EmailSignedContractToParty} según el flag `contracts_queue_email`:
 *  - APAGADO (default) → dispatchSync → correo INMEDIATO en el request (comportamiento histórico).
 *  - ENCENDIDO → dispatch → a la COLA (con driver real + worker, sale del request; con `sync` corre
 *    inline sin daño). En ningún caso se pierde el correo.
 *
 * DEFENSIVO: nunca propaga (un correo que falla no debe tumbar la firma ya sellada ni reintentar en
 * bucle). `$tries=1`.
 */
class DeliverSignedContractEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Best-effort: un solo intento; el correo es secundario a la firma. */
    public int $tries = 1;

    /**
     * $copyRecipientId — B1: si se pasa, entrega la COPIA CERTIFICADA a ese destinatario de copia (en
     * vez del contratado). null = entrega histórica al contratado.
     */
    public function __construct(public ContractEnvelope $envelope, public ?int $copyRecipientId = null)
    {
    }

    public function handle(): void
    {
        try {
            $envelope = $this->envelope;
            if (! $envelope || ! $envelope->isCompleted()) {
                return;
            }

            $payee       = optional($envelope->contract)->payee;
            $attachments = self::buildAttachments($envelope);
            $data        = [
                'payeeName'   => optional($payee)->name,
                'signers'     => self::signerRows($envelope),
                'completedAt' => optional($envelope->completed_at)->format('d/m/Y H:i'),
            ];
            $subjectBase = optional($payee)->name ?: 'CrewCare';

            // ── B1 · COPIA CERTIFICADA a un destinatario de copia (acuse/legal/contabilidad) ──
            if ($this->copyRecipientId !== null) {
                $copy = $envelope->copyRecipients()->whereKey($this->copyRecipientId)->first();
                if (! $copy || ! $copy->email || $copy->isDelivered()) {
                    return;   // ya entregada, sin correo, o no es copia de este sobre
                }
                self::mailTo($copy->email, $copy->name, __('Copia del contrato firmado').' — '.$subjectBase,
                    $data + ['toName' => $copy->name, 'isCopy' => true], $attachments);

                $copy->update(['delivered_at' => now()]);
                ContractEventLog::record($envelope, ContractEnvelopeEvent::COPY_DELIVERED, [
                    'recipient' => $copy, 'actor_label' => $copy->name,
                    'payload'   => ['to' => $copy->email],
                ]);
                return;
            }

            // ── Contratado (comportamiento histórico): a quien se le entrega el paquete firmado ──
            $contracted = $envelope->recipients()
                ->where('role', ContractEnvelopeRecipient::ROLE_CONTRACTED)->first();
            $to = optional($contracted)->email ?: optional(optional($payee)->user)->email;
            if (! $to) {
                Log::warning('DeliverSignedContractEmail: sin correo del contratado', ['envelope' => $envelope->id]);
                return;
            }
            $toName = optional($contracted)->name ?: optional($payee)->name;
            self::mailTo($to, $toName, __('Tu contrato firmado').' — '.$subjectBase,
                $data + ['toName' => $toName], $attachments);
        } catch (\Throwable $e) {
            // Nunca romper la firma: el correo es secundario.
            Log::error('DeliverSignedContractEmail: fallo — '.$e->getMessage());
        }
    }

    /** Filas de firmantes para el certificado del correo (quién firmó, cuándo, con qué integridad). */
    private static function signerRows(ContractEnvelope $envelope): array
    {
        return $envelope->orderedRecipients()->get()->map(function ($r) {
            $sig = $r->signatures()->latest('id')->first();
            return [
                'name'      => $r->name,
                'role'      => $r->cargo ?: $r->roleLabel(),
                'signed_at' => optional($r->signed_at)->format('d/m/Y H:i'),
                'method'    => $r->sign_method,
                'hash'      => $sig ? substr($sig->document_hash, 0, 24) : null,
            ];
        })->all();
    }

    /** Envía el correo de cierre (mismo cuerpo) a un destinatario, con los adjuntos ya armados. */
    private static function mailTo(string $to, ?string $toName, string $subject, array $data, array $attachments): void
    {
        Mail::send('correos.contract-signed', $data, function ($m) use ($to, $toName, $subject, $attachments) {
            $m->from('noreply@crewcare.mx', 'CrewCare');
            $m->to($to, $toName ?: null);
            $m->subject($subject);
            foreach ($attachments as $a) {
                $m->attachData($a['bytes'], $a['name'], ['mime' => 'application/pdf']);
            }
        });
    }

    /**
     * FASE 3d — adjuntos del correo de cierre: (1) el CONTRATO FIRMADO congelado (Fase 3b), o el
     * paquete byte-intact como respaldo si no hubo render (sin plantilla activa); (2) el CERTIFICADO
     * DE CIERRE. Defensivo POR adjunto: el que falle (p. ej. Chrome ausente para el certificado) se
     * omite sin impedir los demás.
     *
     * @return array<int, array{name:string, bytes:string}>
     */
    public static function buildAttachments(ContractEnvelope $envelope): array
    {
        $out = [];

        // El CONTRATO firmado (plantilla-contrato estampada con las autógrafas).
        $signedPath = $envelope->signed_document['path'] ?? null;
        if ($signedPath && Storage::disk('local')->exists($signedPath)) {
            $out[] = ['name' => 'Contrato-firmado-'.$envelope->folio().'.pdf', 'bytes' => Storage::disk('local')->get($signedPath)];
        }

        // Cada ANEXO firmado (plantilla-anexo estampada) va como su propio adjunto, en orden.
        foreach (($envelope->signed_annexes ?? []) as $i => $anx) {
            $path = $anx['path'] ?? null;
            if ($path && Storage::disk('local')->exists($path)) {
                $out[] = ['name' => 'Anexo-'.($i + 1).'-'.$envelope->folio().'.pdf', 'bytes' => Storage::disk('local')->get($path)];
            }
        }

        // Respaldo: si NADA firmado se congeló, se entrega el paquete byte-intact (sin autógrafas).
        if (empty($out)) {
            foreach (($envelope->documents ?? []) as $doc) {
                $path = $doc['path'] ?? null;
                if ($path && Storage::disk('local')->exists($path)) {
                    $out[] = ['name' => ($doc['name'] ?? 'documento').'.pdf', 'bytes' => Storage::disk('local')->get($path)];
                }
            }
        }

        try {
            $cert = ContractPdf::render(ContractCompletionCertificate::html($envelope));
            if ($cert !== '') {
                $out[] = ['name' => 'Certificado-'.$envelope->folio().'.pdf', 'bytes' => $cert];
            }
        } catch (\Throwable $e) {
            Log::warning('DeliverSignedContractEmail: no se pudo adjuntar el certificado — '.$e->getMessage());
        }

        return $out;
    }
}
