<?php

namespace App\Support;

use App\Models\CallPlace;
use App\Models\Department;
use App\Models\TransportAddress;
use App\Models\TransportEquipment;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Transportación · Bloque 2 (§1/§4) — SNAPSHOT + DIFF de la orden.
 *
 * La orden se CONGELA (no se sella): `build()` arma el documento resuelto (etiquetas ya
 * materializadas para que el congelado no dependa del catálogo vivo). `diff()` compara contra la
 * versión inmediata anterior EMPAREJANDO POR `run_key` (identidad estable): una corrida que sólo
 * movió el pick up sale MODIFICADA con el campo marcado, no como baja+alta.
 */
class TransportOrderSnapshot
{
    /** Campos comparables de una corrida (orden = orden de despliegue). */
    public const RUN_FIELDS = [
        'type_label'      => 'Tipo',
        'vehicle_label'   => 'Vehículo',
        'driver_label'    => 'Conductor',
        'pickup'          => 'Pick up',
        'dest'            => 'Destino',
        'equipment_label' => 'Equipo',
        'notes'           => 'Notas',
        'occupants_label' => 'Ocupantes',
    ];

    /**
     * Documento resuelto de la orden: runs (fila por corrida) + notas + leyenda derivada.
     * En el EDITOR transpo ve el rótulo real de las direcciones privadas; el enmascarado 'CASA'
     * es cosa del PDF/no-transpo (se aplica al renderizar, no aquí).
     */
    public static function build(TransportOrder $order): array
    {
        $order->loadMissing(['runs.occupants']);

        $callById  = CallPlace::where('production_id', $order->production_id)->pluck('name', 'id');
        $privById  = TransportAddress::where('production_id', $order->production_id)->get()->keyBy('id');
        $equipByCode = TransportEquipment::pluck('name_es', 'code');
        $deptById  = Department::pluck('name', 'id');

        $typeLabels = [
            TransportOrderRun::TYPE_NORMAL     => 'Normal',
            TransportOrderRun::TYPE_AEROPUERTO => 'Aeropuerto',
            TransportOrderRun::TYPE_APLICACION => 'Transporte de aplicación',
        ];

        $placeLabel = function ($kind, $id, $text) use ($callById, $privById) {
            if ($kind === 'text')    return (string) $text;
            if ($kind === 'call')    return (string) ($callById[$id] ?? '');
            if ($kind === 'private') return (string) (optional($privById->get($id))->label ?? '');
            return '';
        };

        $rows = [];
        $usedEquipment = [];
        $usedTypes = [];
        foreach ($order->runs as $run) {
            $veh = $run->vehicle_id ? Vehicle::find($run->vehicle_id) : null;
            $vehLabel = $veh ? trim(($veh->make ?: '') . ' ' . ($veh->model ?: '') . ($veh->plate ? ' · ' . $veh->plate : '')) : '';

            $eqCodes = array_values($run->equipment ?? []);
            foreach ($eqCodes as $c) { $usedEquipment[$c] = (string) ($equipByCode[$c] ?? $c); }
            $usedTypes[$run->run_type] = $typeLabels[$run->run_type] ?? $run->run_type;

            $pickup = trim(($run->pickup_literal ?: '') . ' ' . $placeLabel($run->pickup_place_kind, $run->pickup_place_id, $run->pickup_place_text));

            $occ = [];
            foreach ($run->occupants as $o) {
                $line = $o->displayName();
                if ($o->department_id && isset($deptById[$o->department_id])) $line .= ' · ' . $deptById[$o->department_id];
                if ($o->load_note) $line .= ' · ' . $o->load_note;
                $occ[] = $line;
            }

            $rows[] = [
                'run_key'         => (string) $run->run_key,
                'run_type'        => $run->run_type,
                'type_label'      => $typeLabels[$run->run_type] ?? $run->run_type,
                'vehicle_label'   => $vehLabel,
                'driver_label'    => $run->driver_user_id ? (User::displayName(User::find($run->driver_user_id)) ?? '') : '',
                'pickup'          => trim($pickup),
                'dest'            => $placeLabel($run->dest_place_kind, $run->dest_place_id, $run->dest_text),
                'equipment'       => $eqCodes,
                'equipment_label' => implode(', ', array_map(fn ($c) => (string) ($equipByCode[$c] ?? $c), $eqCodes)),
                'notes'           => (string) ($run->notes ?? ''),
                'occupants'       => $occ,
                'occupants_label' => implode(' | ', $occ),
            ];
        }

        // LEYENDA = sólo las claves USADAS ese día (tipos con clave + equipamiento presente).
        ksort($usedEquipment);
        $legend = [
            'types'     => $usedTypes,       // p. ej. aeropuerto → 'Aeropuerto'
            'equipment' => $usedEquipment,   // code → nombre
        ];

        return [
            'version'       => (int) $order->version,
            'order_date'    => optional($order->order_date)->toDateString(),
            'uuid'          => $order->uuid,
            'notes_general' => (string) ($order->notes_general ?? ''),
            'runs'          => $rows,
            'legend'        => $legend,
        ];
    }

    /**
     * Diff del documento actual contra el de la versión inmediata anterior, por `run_key`.
     *
     * @return array{summary: array, byKey: array} — byKey[run_key] = ['status','changed'];
     *   status ∈ nueva|modificada|sin_cambio; y una lista 'dropped' con las bajas (rows previas).
     */
    public static function diff(array $current, ?array $prev): array
    {
        $byKey = [];
        $dropped = [];
        $counts = ['nueva' => 0, 'modificada' => 0, 'baja' => 0];

        if (! $prev || empty($prev['runs'])) {
            // Sin anterior: nada que resaltar (la primera versión no compara).
            return ['summary' => ['has_prev' => (bool) $prev, 'counts' => $counts, 'dropped' => []], 'byKey' => $byKey];
        }

        $prevByKey = [];
        foreach ($prev['runs'] as $r) { $prevByKey[$r['run_key']] = $r; }

        $seen = [];
        foreach ($current['runs'] as $cur) {
            $key = $cur['run_key'];
            $seen[$key] = true;
            if (! isset($prevByKey[$key])) {
                $byKey[$key] = ['status' => 'nueva', 'changed' => []];
                $counts['nueva']++;
                continue;
            }
            $changed = [];
            foreach (array_keys(self::RUN_FIELDS) as $f) {
                if ((string) ($cur[$f] ?? '') !== (string) ($prevByKey[$key][$f] ?? '')) {
                    $changed[] = $f;
                }
            }
            $byKey[$key] = ['status' => $changed ? 'modificada' : 'sin_cambio', 'changed' => $changed];
            if ($changed) $counts['modificada']++;
        }

        // Bajas: corridas que estaban en la anterior y ya no.
        foreach ($prev['runs'] as $r) {
            if (empty($seen[$r['run_key']])) {
                $dropped[] = $r;
                $counts['baja']++;
            }
        }

        return ['summary' => ['has_prev' => true, 'prev_version' => $prev['version'] ?? null, 'counts' => $counts, 'dropped' => $dropped], 'byKey' => $byKey];
    }
}
