<?php

namespace App\Support;

use App\Models\CallPersonSchedule;
use App\Models\TransportAddress;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportRunOccupant;

/**
 * Transportación · Bloque 2 (Fase 5) — LA VUELTA AL BACK.
 *
 * El back MANDA (siembra la orden en la precarga); la orden DEVUELVE: cuando transpo ajusta o cierra,
 * el pick up refinado —HORA y LUGAR— vuelve a `call_person_schedules`. La orden es un SEGUNDO escritor
 * de las MISMAS columnas del back; no cambia cómo el back guarda (`savePeople` intacto, borrado disperso
 * incluido) ni cómo muestra (`CallSheetEngine::resolvePickup` ya consume `pickup_place_text`).
 *
 *   HORA   → `pickup_offset_minutes` (SET, contra el general) o `pickup_literal` (FUERA, fija).
 *   LUGAR  → `pickup_place_id` (call place) · `pickup_place_text` (privada→'CASA' SIN dirección real,
 *            punto de set, o texto).
 *   SIN PICKUP (al cerrar) → `pickup_literal = 'N/A'` explícito (no blanco = "falta capturarlo").
 *
 * ⚠ El VEHÍCULO NO se siembra (no hay columna y el back no lo lleva). Vive sólo en la orden.
 */
class TransportBackSync
{
    /** Siembra el pick up de UNA corrida en el back para sus ocupantes CREW. Best-effort. */
    public static function seedRun(TransportOrder $order, TransportOrderRun $run): void
    {
        if ($run->run_class === TransportOrderRun::CLASS_EVENTO) {
            return; // un evento no lleva ocupantes
        }
        $run->loadMissing('occupants');
        $pid = (int) $order->production_id;
        [$offset, $literal, $placeId, $placeText] = self::pickupFields($run, TransportPickupDeriver::context($order));

        if ($offset === null && $literal === null && $placeId === null && $placeText === null) {
            return; // la corrida aún no tiene pick up que sembrar (no borres lo del back)
        }
        foreach ($run->occupants as $o) {
            if ($o->source === TransportRunOccupant::SOURCE_CREW && $o->user_id) {
                self::write($pid, (int) $o->user_id, $offset, $literal, $placeId, $placeText);
            }
        }
    }

    /**
     * Al CERRAR: siembra todas las corridas y marca 'N/A' a los EFECTIVOS que no quedaron en ninguna
     * (el back marcó que llevan pick up, pero transpo decidió que no → explícito, no blanco).
     */
    public static function seedClose(TransportOrder $order): void
    {
        $order->loadMissing('runs.occupants');
        foreach ($order->runs as $run) {
            self::seedRun($order, $run);
        }
        $pid     = (int) $order->production_id;
        $inOrder = array_flip(TransportPreload::usersInOrder($order));
        foreach (TransportPreload::effectiveUserIds($pid) as $uid) {
            if (! isset($inOrder[$uid])) {
                self::write($pid, (int) $uid, null, 'N/A', null, null);
            }
        }
    }

    /**
     * TRASLAPES (§3): pares (corrida FUERA, corrida SET) que comparten DRIVER o VEHÍCULO. Sólo eso
     * amerita confirmación bilateral (un aeropuerto con una van que nadie más toca no notifica a nadie).
     * @return array<int,array{fuera:TransportOrderRun,set:TransportOrderRun,by:string}>
     */
    public static function overlaps(TransportOrder $order): array
    {
        $order->loadMissing('runs');
        $fuera = $order->runs->where('run_class', TransportOrderRun::CLASS_FUERA);
        $set   = $order->runs->where('run_class', TransportOrderRun::CLASS_SET);

        $out = [];
        foreach ($fuera as $f) {
            foreach ($set as $s) {
                $sameVeh = $f->vehicle_id && (int) $f->vehicle_id === (int) $s->vehicle_id;
                $sameDrv = $f->driver_user_id && (int) $f->driver_user_id === (int) $s->driver_user_id;
                if ($sameVeh || $sameDrv) {
                    $out[] = ['fuera' => $f, 'set' => $s, 'by' => $sameVeh ? 'vehicle' : 'driver'];
                }
            }
        }
        return $out;
    }

    /** [offset, literal, placeId, placeText] del pick up de la corrida, listo para el back. */
    private static function pickupFields(TransportOrderRun $run, array $ctx): array
    {
        if ($run->run_class === TransportOrderRun::CLASS_SET) {
            $d = TransportPickupDeriver::derive($run, $ctx);
            if (empty($d['ok'])) {
                return [null, null, null, null]; // sin ancla → no siembra hora
            }
            $point = $run->pickup_point_id ? ($ctx['points'][$run->pickup_point_id] ?? null) : null;
            return [(int) $d['offset'], null, null, $point ?: null]; // origen de set = punto (texto)
        }

        // FUERA: hora literal + lugar según su tipo.
        $literal   = $run->pickup_literal ?: null;
        $placeId   = null;
        $placeText = null;
        if ($run->pickup_place_kind === 'call') {
            $placeId = $run->pickup_place_id;
        } elseif ($run->pickup_place_kind === 'private') {
            $a = TransportAddress::find($run->pickup_place_id);
            $placeText = $a ? $a->publicLabel() : 'CASA';   // etiqueta pública, SIN dirección real
        } elseif ($run->pickup_place_kind === 'text') {
            $placeText = $run->pickup_place_text;
        }
        return [null, $literal, $placeId, $placeText];
    }

    /**
     * Escribe SÓLO las columnas de pick up (preserva horario/hotel/comida). updateOrCreate → la orden
     * es un escritor más de las mismas columnas; `savePeople` no cambia.
     */
    private static function write(int $pid, int $userId, ?int $offset, ?string $literal, ?int $placeId, ?string $placeText): void
    {
        CallPersonSchedule::updateOrCreate(
            ['production_id' => $pid, 'user_id' => $userId],
            [
                'pickup_offset_minutes' => $literal === null ? $offset : null,
                'pickup_literal'        => $literal,
                'pickup_place_id'       => $placeId,
                'pickup_place_text'     => $placeText,
            ]
        );
    }
}
