<?php

namespace App\Console\Commands;

use App\Models\ContractEnvelope;
use App\Support\ContractSigning;
use Illuminate\Console\Command;

/**
 * EL CONTRATO · PASO C · FASE 2 — VENCER sobres olvidados. Barre los sobres EN FIRMA (sent) cuyo
 * plazo ya pasó y los marca 'expired' (evento en la bitácora, turno liberado). Nada vence solo: este
 * barrido es MANUAL o programable por el owner (task scheduler / cron). Idempotente: un sobre ya
 * vencido/cerrado no se vuelve a tocar.
 *
 * Un sobre vence si su fecha límite (`expires_at`, fijada al enviar) ya pasó, O —para sobres viejos
 * sin fecha límite— si su envío (`sent_at`) rebasó la ventana `--days` (por defecto config).
 *
 *   php artisan contracts:expire-stale            # vence según config crewcare.contracts.expire_days
 *   php artisan contracts:expire-stale --days=30  # ventana explícita para el rezago
 *   php artisan contracts:expire-stale --dry      # solo lista, no cambia nada
 */
class ExpireStaleContracts extends Command
{
    protected $signature = 'contracts:expire-stale {--days= : Ventana de vigencia en días (default config)} {--dry : Solo lista, no vence nada}';

    protected $description = 'Vence (expired) los sobres en firma cuyo plazo ya pasó. Manual/programable.';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?: config('crewcare.contracts.expire_days', 45));
        $dry    = (bool) $this->option('dry');
        $cutoff = now()->subDays(max(1, $days));

        $stale = ContractEnvelope::where('status', ContractEnvelope::STATUS_SENT)
            ->where(function ($q) use ($cutoff) {
                // Fecha límite explícita ya pasada…
                $q->where(function ($q2) {
                    $q2->whereNotNull('expires_at')->where('expires_at', '<', now());
                })
                // …o sobre viejo sin fecha límite, más antiguo que la ventana.
                ->orWhere(function ($q2) use ($cutoff) {
                    $q2->whereNull('expires_at')->whereNotNull('sent_at')->where('sent_at', '<', $cutoff);
                });
            })
            ->with('contract.payee')
            ->orderBy('id')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No hay sobres vencidos por barrer.');
            return self::SUCCESS;
        }

        $this->line(($dry ? '[dry] ' : '') . $stale->count() . ' sobre(s) vencido(s):');
        foreach ($stale as $env) {
            $who = optional(optional($env->contract)->payee)->name ?: '—';
            $ref = $env->expires_at ? ('vencía ' . $env->expires_at->format('d/m/Y')) : ('enviado ' . optional($env->sent_at)->format('d/m/Y'));
            $this->line("  #{$env->id}  {$who}  ({$ref})");

            if (! $dry) {
                ContractSigning::expire($env);
            }
        }

        $this->info($dry
            ? 'Dry-run: no se venció nada. Quita --dry para aplicar.'
            : $stale->count() . ' sobre(s) marcados como vencidos.');

        return self::SUCCESS;
    }
}
