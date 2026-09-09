<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthController — healthcheck de app / base / cola / cron. Público y MÍNIMO (sólo ok/estado por
 * componente, sin detalles sensibles) para que un monitor externo lo golpee y alerte cuando algo
 * se cae. 200 = sano, 503 = degradado. El cron se vigila con un latido (schedule heartbeat): si
 * `schedule:run` deja de correr, el latido se vuelve viejo y aquí sale 'stale' → 503.
 */
class HealthController extends Controller
{
    private const HEARTBEAT_KEY  = 'cron_heartbeat';
    private const STALE_SECONDS   = 600;   // 10 min sin latido → el cron está caído

    public function check()
    {
        $checks = [
            'app'   => true,
            'db'    => $this->dbOk(),
            'queue' => $this->queueOk(),
            'cron'  => $this->cronState(),
        ];

        $ok = $checks['db'] && $checks['queue'] && $checks['cron'] !== 'stale';

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'time'   => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }

    private function dbOk(): bool
    {
        try {
            DB::connection()->select('select 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function queueOk(): bool
    {
        try {
            $conn = config('queue.default');
            if ($conn === 'sync') {
                return true;   // inline: no hay worker que vigilar.
            }
            if ($conn === 'database') {
                return Schema::hasTable('jobs');
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** 'ok' (latido reciente) | 'stale' (cron caído) | 'unknown' (aún no ha latido). */
    private function cronState(): string
    {
        try {
            $ts = Cache::get(self::HEARTBEAT_KEY);
            if (! $ts) {
                return 'unknown';   // recién desplegado / cache limpia: no penaliza el overall.
            }
            return (now()->timestamp - (int) $ts) <= self::STALE_SECONDS ? 'ok' : 'stale';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }
}
