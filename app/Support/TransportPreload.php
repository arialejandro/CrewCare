<?php

namespace App\Support;

use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportRunOccupant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TransportPreload — Fase 3. Precarga la orden del día desde el conjunto EFECTIVO de pick up,
 * agrupado por VEHÍCULO de la asignación fija. El MODO es consecuencia de las marcas, no un botón.
 *
 *   conjunto efectivo = (gente en puestos `always_pickup` = la BASE, el piso) ∪ (gente MARCADA)
 *
 * Una corrida por vehículo con su grupo; quien no tiene vehículo fijo → corrida de uno (day player,
 * recién entrado). NO inventa pick up/destino (se derivan o capturan). SNAPSHOT: no guarda vínculo
 * corrida→asignación, así cambiar la asignación después no toca un borrador ya creado.
 */
class TransportPreload
{
    /** user_id (únicos) del conjunto efectivo: base (always_pickup) ∪ marcados. */
    public static function effectiveUserIds(?int $pid): array
    {
        if (! $pid) {
            return [];
        }
        $ids = [];

        $basePos = TransportCrew::alwaysPickupPositionIds($pid);
        if ($basePos) {
            foreach (DB::table('production_user')->where('production_id', $pid)->whereIn('position_id', $basePos)->pluck('user_id') as $u) {
                $ids[(int) $u] = true;
            }
        }
        foreach (DB::table('transport_pickup_marks')->where('production_id', $pid)->where('is_marked', 1)->pluck('user_id') as $u) {
            $ids[(int) $u] = true;
        }

        return array_keys($ids);
    }

    /**
     * Agrupa el conjunto por vehículo de su asignación fija (persona tiene prioridad sobre puesto).
     * @return array{byVehicle: array<int,int[]>, loose: int[]}
     */
    public static function groupByVehicle(?int $pid, array $userIds): array
    {
        if (! $pid || ! $userIds) {
            return ['byVehicle' => [], 'loose' => []];
        }

        $byUser = [];
        $byPos  = [];
        foreach (DB::table('transport_vehicle_assignments')->where('production_id', $pid)->where('is_active', 1)
                     ->get(['vehicle_id', 'position_id', 'user_id']) as $a) {
            if ($a->user_id) {
                $byUser[(int) $a->user_id] = (int) $a->vehicle_id;
            } elseif ($a->position_id) {
                $byPos[(int) $a->position_id] = (int) $a->vehicle_id;
            }
        }
        $posOf = [];
        foreach (DB::table('production_user')->where('production_id', $pid)->whereIn('user_id', $userIds)
                     ->get(['user_id', 'position_id']) as $r) {
            $posOf[(int) $r->user_id] = $r->position_id ? (int) $r->position_id : null;
        }

        $byVehicle = [];
        $loose = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $veh = $byUser[$uid] ?? (isset($posOf[$uid]) && $posOf[$uid] !== null ? ($byPos[$posOf[$uid]] ?? null) : null);
            if ($veh) {
                $byVehicle[$veh][] = $uid;
            } else {
                $loose[] = $uid;
            }
        }

        return ['byVehicle' => $byVehicle, 'loose' => $loose];
    }

    /** Crea las corridas precargadas en el borrador. Devuelve cuántas creó. */
    public static function into(TransportOrder $order): int
    {
        $pid = (int) $order->production_id;
        $eff = self::effectiveUserIds($pid);
        if (! $eff) {
            return 0;
        }
        $grp   = self::groupByVehicle($pid, $eff);
        $names = User::whereIn('id', $eff)->get()->keyBy('id');
        $sort  = (int) $order->runs()->max('sort_order');
        $made  = 0;

        $addRun = function (?int $vehicleId, array $uids) use ($order, &$sort, &$made, $names) {
            $run = new TransportOrderRun([
                'run_class'  => TransportOrderRun::CLASS_SET,   // pick up a set → deriva del llamado
                'run_type'   => TransportOrderRun::TYPE_NORMAL,
                'vehicle_id' => $vehicleId,
                'is_active'  => 1,
            ]);
            $run->transport_order_id = $order->id;
            $run->sort_order = ++$sort;
            $run->save();

            $i = 0;
            foreach ($uids as $uid) {
                $run->occupants()->create([
                    'source'        => TransportRunOccupant::SOURCE_CREW,
                    'user_id'       => $uid,
                    'name_snapshot' => isset($names[$uid]) ? User::displayName($names[$uid]) : null,
                    'sort_order'    => $i++,
                ]);
            }
            $made++;
        };

        foreach ($grp['byVehicle'] as $vehicleId => $uids) {
            $addRun((int) $vehicleId, $uids);
        }
        foreach ($grp['loose'] as $uid) {
            $addRun(null, [$uid]);   // corrida de uno, sin vehículo (transpo le asigna)
        }

        return $made;
    }
}
