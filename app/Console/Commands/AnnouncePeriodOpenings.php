<?php

namespace App\Console\Commands;

use App\Jobs\AnnouncePeriodOpening;
use App\Models\PayeeContract;
use App\Models\PaymentPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * CALENDARIO · AVISO DE APERTURA — corre A DIARIO y avisa por correo de los periodos cuya ventana ABRE
 * HOY (opens_on = hoy), UNA sola vez (`announced_at` marca el envío → idempotente). Así el aviso sale
 * "semana a semana" cuando toca, NO al crear el lote (20 semanas futuras no disparan 20 correos).
 *
 * "DOBLE en cambio de mes": si el periodo que abre es el PRIMERO de su mes (el periodo previo de la misma
 * frecuencia cerró en OTRO mes), se dispara además una 2ª variante con el recordatorio de los documentos
 * mensuales. Best-effort: un fallo no bloquea nada.
 */
class AnnouncePeriodOpenings extends Command
{
    protected $signature = 'periods:announce {--date= : Fecha a evaluar (YYYY-MM-DD); por defecto hoy}';

    protected $description = 'Avisa por correo los periodos cuya ventana abre hoy (idempotente).';

    public function handle(): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : Carbon::today();

        $opening = PaymentPeriod::query()
            ->where('status', PaymentPeriod::STATUS_OPEN)
            ->where('frequency', '!=', PayeeContract::FREQ_DAY_PLAYER)
            ->whereNull('announced_at')
            ->whereDate('opens_on', $today->toDateString())
            ->get();

        $sent = 0;
        foreach ($opening as $period) {
            $monthChange = $this->isFirstOfMonth($period);

            AnnouncePeriodOpening::dispatch($period->id, false);
            if ($monthChange) {
                AnnouncePeriodOpening::dispatch($period->id, true);   // el "doble" del cambio de mes
            }

            $period->forceFill(['announced_at' => now()])->save();
            $sent++;
        }

        $this->info("periods:announce — {$sent} periodo(s) avisado(s) para {$today->toDateString()}.");

        return self::SUCCESS;
    }

    /** ¿Es el primer periodo de su mes? (el previo de la misma frecuencia/producción cerró en otro mes) */
    private function isFirstOfMonth(PaymentPeriod $period): bool
    {
        $prev = PaymentPeriod::query()
            ->forProduction($period->production_id)
            ->where('frequency', $period->frequency)
            ->whereDate('closes_on', '<', optional($period->opens_on)->toDateString() ?: $period->closes_on)
            ->orderByDesc('closes_on')
            ->first();

        if (! $prev || ! $prev->closes_on || ! $period->opens_on) {
            return false;   // sin previo no afirmamos "cambio de mes"
        }

        return $prev->closes_on->month !== $period->opens_on->month
            || $prev->closes_on->year !== $period->opens_on->year;
    }
}
