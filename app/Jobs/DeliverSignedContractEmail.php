<?php

namespace App\Jobs;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
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

    public function __construct(public ContractEnvelope $envelope)
    {
    }

    public function handle(): void
    {
        try {
            $envelope = $this->envelope;
            if (! $envelope || ! $envelope->isCompleted()) {
                return;
            }

            $payee = optional($envelope->contract)->payee;

            // El contratado es a quien se le entrega el paquete firmado.
            $contracted = $envelope->recipients()
                ->where('role', ContractEnvelopeRecipient::ROLE_CONTRACTED)->first();
            $to = optional($contracted)->email ?: optional(optional($payee)->user)->email;
            if (! $to) {
                Log::warning('DeliverSignedContractEmail: sin correo del contratado', ['envelope' => $envelope->id]);
                return;
            }
            $toName = optional($contracted)->name ?: optional($payee)->name;

            // Certificado: quién firmó, cuándo, con qué integridad (hash del sello, recortado).
            $signers = $envelope->orderedRecipients()->get()->map(function ($r) {
                $sig = $r->signatures()->latest('id')->first();
                return [
                    'name'      => $r->name,
                    'role'      => $r->cargo ?: $r->roleLabel(),
                    'signed_at' => optional($r->signed_at)->format('d/m/Y H:i'),
                    'method'    => $r->sign_method,
                    'hash'      => $sig ? substr($sig->document_hash, 0, 24) : null,
                ];
            })->all();

            $data = [
                'toName'      => $toName,
                'payeeName'   => optional($payee)->name,
                'signers'     => $signers,
                'completedAt' => optional($envelope->completed_at)->format('d/m/Y H:i'),
            ];

            $docs    = $envelope->documents ?? [];
            $subject = __('Tu contrato firmado').' — '.(optional($payee)->name ?: 'CrewCare');

            Mail::send('correos.contract-signed', $data, function ($m) use ($to, $toName, $subject, $docs) {
                $m->from('noreply@crewcare.mx', 'CrewCare');
                $m->to($to, $toName ?: null);
                $m->subject($subject);
                foreach ($docs as $doc) {
                    $path = $doc['path'] ?? null;
                    if ($path && Storage::disk('local')->exists($path)) {
                        $name = ($doc['name'] ?? 'documento').'.pdf';
                        $m->attachData(Storage::disk('local')->get($path), $name, ['mime' => 'application/pdf']);
                    }
                }
            });
        } catch (\Throwable $e) {
            // Nunca romper la firma: el correo es secundario.
            Log::error('DeliverSignedContractEmail: fallo — '.$e->getMessage());
        }
    }
}
