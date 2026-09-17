<?php

namespace App\Support;

use App\Models\ScoutingReport;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportPickupPoint;
use App\Models\TransportRunOccupant;
use App\Models\TransportTravelTime;

/**
 * Deriva el pick up de una corrida de SET (Fase 2), reutilizando el motor del llamado:
 *
 *     pick up = llamado más TEMPRANO de sus ocupantes − traslado del par ± ajuste manual
 *
 * El llamado sale de {@see CallSheetEngine} contra el general del día. El traslado sale de la matriz
 * de la Fase 1 (par pickup_point × scouting); si no hay par, del valor tecleado en la corrida. En
 * BORRADOR se recalcula en vivo (mover el general o corregir la matriz mueve el pick up); al EMITIR
 * el snapshot lo materializa. Las corridas FUERA no derivan (todo va a mano).
 */
class TransportPickupDeriver
{
    /** Contexto del día (una vez por orden): general + offsets + matriz + puntos + destinos. */
    public static function context(TransportOrder $order): array
    {
        $pid  = (int) $order->production_id;
        $callDay = $pid ? CallSheetEngine::callDay($pid, $order->order_date) : null;

        return [
            'pid'     => $pid,
            'general' => $callDay ? $callDay->generalHHMM() : null,
            'dept'    => $pid ? CallSheetEngine::deptOffsets($pid) : [],
            'persons' => $pid ? CallSheetEngine::personSchedules($pid) : [],
            'travel'  => $pid
                ? TransportTravelTime::where('production_id', $pid)->get()->keyBy(fn ($t) => $t->pickup_point_id . '-' . $t->scouting_id)
                : collect(),
            'points'  => TransportPickupPoint::where('production_id', $pid)->pluck('name', 'id'),
            'destloc' => $pid ? ScoutingReport::where('production_id', $pid)->pluck('location_name', 'id') : collect(),
        ];
    }

    /**
     * Deriva el pick up de una corrida de SET.
     * @return array{ok:bool, time:?string, offset:?int, anchor_user_id:?int, travel:?int,
     *   travel_source:string, point:?string, dest:?string, reason:?string}
     */
    public static function derive(TransportOrderRun $run, array $ctx): array
    {
        $point = $run->pickup_point_id ? ($ctx['points'][$run->pickup_point_id] ?? null) : null;
        $dest  = $run->dest_location_ref ? ($ctx['destloc'][$run->dest_location_ref] ?? null) : null;

        if ($run->run_class !== TransportOrderRun::CLASS_SET) {
            return ['ok' => false, 'reason' => 'not_set', 'point' => $point, 'dest' => $dest];
        }

        // 1) Ancla = llamado más temprano de sus ocupantes.
        $earliest = null;
        $anchor   = null;
        foreach ($run->occupants as $o) {
            $offset = self::occupantCallOffset($o, $ctx);
            if ($offset === null) {
                continue;
            }
            if ($earliest === null || $offset < $earliest) {
                $earliest = $offset;
                $anchor   = $o->user_id;
            }
        }

        // 2) Traslado: matriz del par → si no, el tecleado en la corrida.
        $travel = null;
        $tsrc   = 'none';
        if ($run->pickup_point_id && $run->dest_location_ref) {
            $row = $ctx['travel']->get($run->pickup_point_id . '-' . $run->dest_location_ref);
            if ($row) {
                $travel = (int) $row->minutes;
                $tsrc   = 'matrix';
            }
        }
        if ($travel === null && $run->travel_minutes !== null) {
            $travel = (int) $run->travel_minutes;
            $tsrc   = 'typed';
        }

        if ($earliest === null) {
            return ['ok' => false, 'reason' => 'no_anchor', 'travel' => $travel, 'travel_source' => $tsrc, 'point' => $point, 'dest' => $dest];
        }

        $offset = $earliest - (int) ($travel ?? 0) + (int) ($run->travel_adjust_minutes ?? 0);
        $time   = CallSheetEngine::addMinutes($ctx['general'], $offset);

        return [
            'ok'             => true,
            'time'           => $time,
            'offset'         => $offset,
            'anchor_user_id' => $anchor,
            'travel'         => $travel,
            'travel_source'  => $tsrc,
            'point'          => $point,
            'dest'           => $dest,
        ];
    }

    /** Offset (min desde el general) del llamado de un ocupante crew; null si no es numérico. */
    private static function occupantCallOffset(TransportRunOccupant $o, array $ctx): ?int
    {
        if ($o->source !== TransportRunOccupant::SOURCE_CREW || ! $o->user_id) {
            return null;
        }
        $general = $ctx['general'];
        if (! $general) {
            return null;
        }
        $dept   = ($o->department_id && isset($ctx['dept'][$o->department_id])) ? $ctx['dept'][$o->department_id] : null;
        $person = $ctx['persons'][$o->user_id] ?? null;
        $sched  = CallSheetEngine::resolveSchedule($general, $dept, $person);
        if (empty($sched['time'])) {
            return null; // literal (O/C, D/C…) → no numérico, no ancla.
        }

        return CallSheetEngine::minutesFrom($general, $sched['time']);
    }
}
