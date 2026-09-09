<?php

namespace App\Support;

use App\Models\TransportOrder;
use App\Models\User;

/**
 * Transportación · Bloque 2 (Fase 5) — CONTADOR DE ATENCIÓN del topbar (patrón de {@see PendingSignatures}).
 *
 * Cuenta lo que pende de MIRAR en la orden ABIERTA del día para la producción actual:
 *   - PROPUESTA: gente marcada en el back que aún NO está en la orden (§2).
 *   - TRASLAPES: corridas FUERA y de SET que comparten driver o vehículo (§3).
 *
 * Llega a transpo y a producción EN GENERAL (cualquiera con acceso lite). Avisa, no bloquea, ninguno
 * gana automáticamente. Cacheado por-request (el header/partial lo piden más de una vez).
 */
class TransportAttention
{
    /** @var array<string,int> */
    private static array $cache = [];

    public static function countForUser(int $userId, ?int $prodId = null): int
    {
        $prodId = $prodId ?? CurrentProduction::id();
        $key = $userId . ':' . ($prodId ?? '0');
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $count = 0;
        $user  = User::find($userId);
        if ($prodId && $user && TransportAccess::canLite($user)) {
            $order = self::currentDraft((int) $prodId);
            if ($order) {
                $count = TransportPreload::proposal($order)['count'] + count(TransportBackSync::overlaps($order));
            }
        }

        return self::$cache[$key] = $count;
    }

    /** Borrador ABIERTO de HOY para la producción (la orden en la que se está trabajando). */
    private static function currentDraft(int $pid): ?TransportOrder
    {
        return TransportOrder::where('production_id', $pid)
            ->whereDate('order_date', now()->toDateString())
            ->where('status', TransportOrder::STATUS_DRAFT)
            ->where('is_active', 1)
            ->orderByDesc('version')
            ->first();
    }
}
