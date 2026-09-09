<?php

namespace App\Jobs;

use App\Http\Controllers\ContractSignController;
use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Support\PendingSignatures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EL CONTRATO · PASO C · FASE 4 — AVISO "TE TOCA". Cuando la ruta AVANZA y un destinatario entra en
 * turno (al enviar el sobre, al firmar el anterior, o al reenviar), se le avisa por correo con el
 * enlace para revisar y firmar + los datos que sí mira (contraprestación, situación fiscal, vigencia).
 * Antes NO existía ningún aviso y la página de firma prometía uno que nunca llegaba.
 *
 * DEFENSIVO: solo avisa si SIGUE siendo su turno (el sobre en firma y el actual es este destinatario);
 * si ya cambió (firmó, se reenvió a otro), es un no-op. Un correo que falla nunca rompe la firma.
 * $tries=1. Recibe el ID (no el modelo) para serializar barato.
 */
class NotifyRecipientTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $recipientId)
    {
    }

    /** Enlace firmado para revisar y firmar (sirve al contratado con 2º factor y al interno logueado). */
    public static function signLink(ContractEnvelopeRecipient $recipient): string
    {
        return ContractSignController::signUrl($recipient);
    }

    public function handle(): void
    {
        try {
            $r = ContractEnvelopeRecipient::with('envelope.contract.payee', 'user')->find($this->recipientId);
            if (! $r || $r->isSigned()) {
                return;
            }
            $envelope = $r->envelope;
            // Solo si SIGUE abierto para esta persona (secuencial: su turno; paralelo: firmante abierto).
            if (! $envelope || ! $envelope->isSent() || ! \App\Support\ContractSigning::isOpenTurn($envelope, $r)) {
                return;
            }

            $to = $r->email ?: optional($r->user)->email;
            if (! $to) {
                Log::warning('NotifyRecipientTurn: destinatario sin correo', ['recipient' => $r->id]);
                return;
            }

            $contract = $envelope->contract;
            $payee    = optional($contract)->payee;
            $data = [
                'toName'       => $r->name,
                'cargo'        => $r->cargo ?: $r->roleLabel(),
                'payeeName'    => optional($payee)->name,
                'key'          => PendingSignatures::keyData($contract),
                'conceptLabel' => PendingSignatures::conceptLabel(optional($contract)->concept),
                'natureLabel'  => PendingSignatures::natureLabel(optional($payee)->legal_nature),
                'signUrl'      => self::signLink($r),
            ];
            $subject = __('Es tu turno de firmar').' — '.(optional($payee)->name ?: 'CrewCare');

            Mail::send('correos.contract-turn', $data, function ($m) use ($to, $r, $subject) {
                $m->from('noreply@crewcare.mx', 'CrewCare');
                $m->to($to, $r->name ?: null);
                $m->subject($subject);
            });
        } catch (\Throwable $e) {
            // El aviso es secundario: nunca romper la firma.
            Log::error('NotifyRecipientTurn: fallo — '.$e->getMessage());
        }
    }
}
