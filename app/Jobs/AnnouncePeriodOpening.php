<?php

namespace App\Jobs;

use App\Models\PaymentPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * CALENDARIO · AVISO DE APERTURA DE VENTANA — el correo que anuncia "abrió la semana, manda tu factura".
 * Lo dispara el comando diario {@see \App\Console\Commands\AnnouncePeriodOpenings} cuando la ventana
 * ABRE de verdad (opens_on = hoy), NUNCA al crear el periodo en lote (si no, 20 semanas futuras
 * dispararían 20 avisos de golpe).
 *
 * NO sustituye el recordatorio manual del tablero ({@see \App\Support\PeriodReminder}) — ese persigue al
 * que falta; este solo anuncia que abrió.
 *
 * DESTINATARIOS: los PAYEES cuya frecuencia coincide con el periodo (los que deben entregar esa semana),
 * por su correo de contacto. 🟡 Interpretación reportada al owner: si el aviso debe ir a contabilidad y
 * no a los payees, es cambiar SOLO recipients(). "DOBLE en cambio de mes": el comando además dispara una
 * 2ª variante `month_change` con el recordatorio de los documentos mensuales.
 *
 * DEFENSIVO: un correo que falla nunca rompe nada (best-effort, try/catch). $tries=1.
 */
class AnnouncePeriodOpening implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $periodId, public bool $monthChange = false)
    {
    }

    public function handle(): void
    {
        try {
            $period = PaymentPeriod::with('production')->find($this->periodId);
            if (! $period || ! $period->isOpen() || $period->isDayPlayer()) {
                return;
            }

            foreach ($this->recipients($period) as $to => $name) {
                $this->sendOne($period, $to, $name);
            }
        } catch (\Throwable $e) {
            Log::error('AnnouncePeriodOpening: fallo — ' . $e->getMessage());
        }
    }

    /** [email => nombre] de los payees que deben entregar en este periodo (dedupe por correo). */
    private function recipients(PaymentPeriod $period): array
    {
        $out = [];
        $contracts = $period->matchingContracts()->with('payee')->get();
        foreach ($contracts as $c) {
            $payee = $c->payee;
            if (! $payee) { continue; }
            $email = $payee->contactEmail();
            if ($email && ! isset($out[$email])) {
                $out[$email] = $payee->name;
            }
        }
        return $out;
    }

    private function sendOne(PaymentPeriod $period, string $to, ?string $name): void
    {
        $data = [
            'toName'      => $name,
            'label'       => $period->displayLabel(),
            'opensOn'     => optional($period->opens_on)->format('d/m/Y'),
            'closesOn'    => optional($period->closes_on)->format('d/m/Y'),
            'monthChange' => $this->monthChange,
        ];
        $subject = $this->monthChange
            ? __('Cambio de mes — recuerda tus documentos mensuales')
            : __('Abrió la ventana de recepción') . ' — ' . $period->displayLabel();

        Mail::send('correos.period-open', $data, function ($m) use ($to, $name, $subject) {
            $m->from('noreply@crewcare.mx', 'CrewCare');
            $m->to($to, $name ?: null);
            $m->subject($subject);
        });
    }
}
