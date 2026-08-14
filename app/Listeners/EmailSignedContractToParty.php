<?php

namespace App\Listeners;

use App\Events\ContractEnvelopeCompleted;
use App\Models\ContractEnvelopeRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * EL INFOSHEET · FASE 3.4 — al COMPLETARSE la ruta de firma (todas las partes firmaron), entrega al
 * CONTRATADO su paquete: los PDF del sobre ADJUNTOS + un certificado (quién firmó, cuándo, con qué
 * integridad). Cierra el pipeline: Infosheet → Autorización → Contrato → Firmas → **Correo**.
 *
 * DEFENSIVO de punta a punta: el evento se dispara best-effort dentro de la firma; este listener
 * jamás propaga un error (un correo que falla no debe deshacer una firma ya registrada). Sin
 * correo del contratado → se registra y se sale.
 */
class EmailSignedContractToParty
{
    public function handle(ContractEnvelopeCompleted $event): void
    {
        try {
            $envelope = $event->envelope;
            if (! $envelope || ! $envelope->isCompleted()) {
                return;
            }

            $payee = optional($envelope->contract)->payee;

            // El contratado es a quien se le entrega el paquete firmado.
            $contracted = $envelope->recipients()
                ->where('role', ContractEnvelopeRecipient::ROLE_CONTRACTED)->first();
            $to = optional($contracted)->email ?: optional(optional($payee)->user)->email;
            if (! $to) {
                Log::warning('EmailSignedContractToParty: sin correo del contratado', ['envelope' => $envelope->id]);
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
            Log::error('EmailSignedContractToParty: fallo — '.$e->getMessage());
        }
    }
}
