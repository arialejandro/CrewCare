<?php

namespace App\Support;

use App\Models\CallPlace;
use App\Models\TransportAddress;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Transportación · Bloque 2 (Fase 4) — AGENDA POR VEHÍCULO.
 *
 * La orden vista como agenda: cada unidad con su día (corridas de set, corridas fuera de llamado y
 * eventos) en ORDEN DE HORA. Sobre esa línea calcula qué PODRÍA ADELANTARSE en la MISMA unidad —
 * SÓLO informa, NUNCA mueve.
 *
 *   disponible = end_literal de la corrida anterior [ + traslado destino→origen, si ambas geo ]
 *   holgura    = inicio actual − disponible          (si > 0, se puede adelantar hasta esa holgura)
 *
 * Cálculo HÍBRIDO (decisión del owner): usa el TRASLADO cuando el par destino→origen está
 * geolocalizado (reusa la matriz `transport_travel_times` de la Fase 1, por SIMETRÍA del par
 * punto×scouting); si no, el HUECO puro. El aviso SIEMPRE dice CUÁL usó, porque cambia cuánto
 * confiar (el hueco es optimista: el vehículo no aparece por arte de magia en el punto siguiente).
 * Como sólo informa, una estimación aproximada basta.
 *
 * Las corridas SIN vehículo (transporte por aplicación) van APARTE (`loose`), no cuelgan de nadie.
 */
class TransportAgenda
{
    /** Documento agrupado por vehículo + corridas sueltas (aplicación). */
    public static function build(TransportOrder $order, bool $includeDiscreet = true): array
    {
        $order->loadMissing('runs.occupants');
        $ctx = TransportPickupDeriver::context($order);

        $callById = CallPlace::where('production_id', $order->production_id)->pluck('name', 'id');
        $privById = TransportAddress::where('production_id', $order->production_id)->get()->keyBy('id');

        $runs = $order->runs->filter(fn (TransportOrderRun $r) => $includeDiscreet || ! $r->is_discreet)->values();

        $byVeh = [];
        $loose = [];
        foreach ($runs as $run) {
            if ($run->vehicle_id) {
                $byVeh[(int) $run->vehicle_id][] = $run;
            } else {
                $loose[] = $run;   // aplicación: sin unidad, no cuelga de ninguna
            }
        }

        $vehById  = Vehicle::whereIn('id', array_keys($byVeh))->get()->keyBy('id');
        $vehicles = [];
        foreach ($byVeh as $vid => $vruns) {
            $veh   = $vehById->get($vid);
            $items = self::timeline(collect($vruns), $ctx, $callById, $privById, true);
            $vehicles[] = [
                'vehicle_id'    => (int) $vid,
                'vehicle_label' => $veh ? self::vehLabel($veh) : ('Vehículo #' . $vid),
                'plate'         => $veh?->plate,
                'driver_label'  => $veh?->driverLabel(),
                'items'         => $items,
            ];
        }
        // Unidades ordenadas por su primera hora del día.
        usort($vehicles, fn ($a, $b) => self::firstStart($a['items']) <=> self::firstStart($b['items']));

        return [
            'vehicles' => $vehicles,
            'loose'    => self::timeline(collect($loose), $ctx, $callById, $privById, false),
        ];
    }

    /**
     * Aviso de una sola línea de qué podría adelantarse en UNA unidad (para el flash al mover/cancelar).
     * null si nada puede adelantarse (no inventamos avisos vacíos).
     */
    public static function vehicleHint(TransportOrder $order, int $vehicleId): ?string
    {
        foreach (self::build($order)['vehicles'] as $v) {
            if ($v['vehicle_id'] !== $vehicleId) {
                continue;
            }
            $lines = [];
            foreach ($v['items'] as $it) {
                if (! empty($it['opportunity'])) {
                    $lines[] = self::hintLine($v['vehicle_label'], $it['title'], $it['opportunity']);
                }
            }
            return $lines ? implode(' ', $lines) : null;
        }
        return null;
    }

    // ── Interno ──────────────────────────────────────────────────────────────
    private static function timeline(Collection $runs, array $ctx, $callById, $privById, bool $withOpportunities): array
    {
        $items = [];
        foreach ($runs as $run) {
            $items[] = [
                'run'        => $run,
                'kind'       => $run->run_class,
                'type_label' => self::typeLabel($run),
                'title'      => self::title($run, $ctx, $callById, $privById),
                'start'      => self::startInfo($run, $ctx),
                'end'        => self::endInfo($run),
                'opportunity' => null,
            ];
        }

        // Ordena por hora de inicio (sin hora → al final), desempata por sort_order.
        usort($items, function ($a, $b) {
            $am = $a['start']['min'];
            $bm = $b['start']['min'];
            if ($am === null && $bm === null) {
                return $a['run']->sort_order <=> $b['run']->sort_order;
            }
            if ($am === null) {
                return 1;
            }
            if ($bm === null) {
                return -1;
            }
            return ($am <=> $bm) ?: ($a['run']->sort_order <=> $b['run']->sort_order);
        });

        if ($withOpportunities) {
            for ($i = 1, $n = count($items); $i < $n; $i++) {
                $items[$i]['opportunity'] = self::opportunity($items[$i - 1], $items[$i], $ctx);
            }
        }

        return $items;
    }

    /**
     * Holgura de la corrida B respecto de la anterior A en la misma unidad. Un evento cuenta IGUAL
     * que una corrida (aporta su fin como ancla y su inicio puede adelantarse).
     */
    private static function opportunity(array $prev, array $cur, array $ctx): ?array
    {
        $prevEnd  = $prev['end']['min'];
        $curStart = $cur['start']['min'];
        if ($prevEnd === null || $curStart === null) {
            return null;   // sin fin de la anterior o sin inicio actual → no hay dato fiable
        }

        [$travel, $method] = self::travelBetween($prev['run'], $cur['run'], $ctx);
        $available = $prevEnd + $travel;
        $slack     = $curStart - $available;

        return $slack > 0 ? ['minutes' => $slack, 'method' => $method] : null;
    }

    /**
     * Traslado destino(A)→origen(B) por la matriz, SI ambas están geolocalizadas (A destino scouting,
     * B origen punto). Reusa `transport_travel_times` (par punto×scouting, simétrico). Si no hay dato,
     * HUECO puro (traslado 0). Devuelve [minutos, 'travel'|'gap'].
     */
    private static function travelBetween(TransportOrderRun $a, TransportOrderRun $b, array $ctx): array
    {
        if ($a->run_class === TransportOrderRun::CLASS_SET && $a->dest_location_ref
            && $b->run_class === TransportOrderRun::CLASS_SET && $b->pickup_point_id) {
            $row = $ctx['travel']->get($b->pickup_point_id . '-' . $a->dest_location_ref);
            if ($row) {
                return [(int) $row->minutes, 'travel'];
            }
        }
        return [0, 'gap'];
    }

    private static function startInfo(TransportOrderRun $run, array $ctx): array
    {
        if ($run->run_class === TransportOrderRun::CLASS_SET) {
            $d = TransportPickupDeriver::derive($run, $ctx);
            $str = ! empty($d['ok']) ? $d['time'] : null;
            return ['min' => self::toMin($str), 'str' => $str];
        }
        // FUERA y EVENTO: el inicio es literal (pickup_literal).
        return ['min' => self::toMin($run->pickup_literal), 'str' => $run->pickup_literal];
    }

    private static function endInfo(TransportOrderRun $run): array
    {
        return ['min' => self::toMin($run->end_literal), 'str' => $run->end_literal];
    }

    private static function title(TransportOrderRun $run, array $ctx, $callById, $privById): string
    {
        if ($run->run_class === TransportOrderRun::CLASS_EVENTO) {
            return (string) ($run->dest_text ?: __('Evento'));
        }
        if ($run->run_class === TransportOrderRun::CLASS_SET) {
            $pt = $run->pickup_point_id ? ($ctx['points'][$run->pickup_point_id] ?? '') : '';
            $ds = $run->dest_location_ref ? ($ctx['destloc'][$run->dest_location_ref] ?? '') : '';
            return trim($pt . ($ds ? ' → ' . $ds : '')) ?: __('Corrida');
        }
        $pl = self::placePublic($run->pickup_place_kind, $run->pickup_place_id, $run->pickup_place_text, $callById, $privById);
        $dl = self::placePublic($run->dest_place_kind, $run->dest_place_id, $run->dest_text, $callById, $privById);
        return trim($pl . ($dl ? ' → ' . $dl : '')) ?: __('Corrida');
    }

    private static function placePublic($kind, $id, $text, $callById, $privById): string
    {
        if ($kind === 'text')    return (string) $text;
        if ($kind === 'call')    return (string) ($callById[$id] ?? '');
        if ($kind === 'private') { $a = $privById->get($id); return $a ? $a->publicLabel() : 'CASA'; }
        return '';
    }

    private static function typeLabel(TransportOrderRun $run): string
    {
        if ($run->run_class === TransportOrderRun::CLASS_EVENTO) return __('Evento');
        if ($run->run_class === TransportOrderRun::CLASS_SET)    return 'SET';
        return __('Fuera');
    }

    private static function hintLine(string $vehLabel, string $title, array $op): string
    {
        if ($op['method'] === 'travel') {
            return __(':veh: :title podría adelantarse :n min.', ['veh' => $vehLabel, 'title' => $title, 'n' => $op['minutes']]);
        }
        return __(':veh: :title podría adelantarse hasta :n min (sin considerar el traslado).', ['veh' => $vehLabel, 'title' => $title, 'n' => $op['minutes']]);
    }

    private static function vehLabel(Vehicle $v): string
    {
        return trim(($v->make ?: '') . ' ' . ($v->model ?: '')) ?: ('Vehículo #' . $v->id);
    }

    private static function firstStart(array $items): int
    {
        $min = PHP_INT_MAX;
        foreach ($items as $it) {
            if ($it['start']['min'] !== null) {
                $min = min($min, $it['start']['min']);
            }
        }
        return $min;
    }

    /** "HH:MM" → minutos desde medianoche, o null si no es una hora válida (p. ej. 'O/C'). */
    private static function toMin(?string $s): ?int
    {
        $s = trim((string) $s);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $s, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }
        return $h * 60 + $min;
    }
}
