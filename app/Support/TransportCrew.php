<?php

namespace App\Support;

use App\Models\Position;
use App\Models\TransportOrderRun;
use App\Models\TransportPositionConfig;
use App\Models\TransportRunOccupant;
use Illuminate\Support\Facades\DB;

/**
 * Config de crew para Transportación (Fase 2), TODA configurable por producción, NADA en código.
 *   - JEFATURA (discreto): `transport_position_config.is_leadership`, con DEFAULT = `positions.is_hod`
 *     (jefe de departamento) cuando no hay fila de config → arranca sensato, editable por producción.
 *   - "LLEVA PICK UP SIEMPRE" (define el modo LIGERO): `always_pickup`, sólo config explícita.
 */
class TransportCrew
{
    /** Puestos de JEFATURA efectivos: config.is_leadership; default `is_hod` si no hay config. */
    public static function leadershipPositionIds(?int $pid): array
    {
        $cfg = TransportPositionConfig::where('production_id', $pid)->get()->keyBy('position_id');
        $ids = [];
        // Default: los HOD son jefatura salvo que la config los apague.
        foreach (Position::where('is_hod', 1)->pluck('id') as $posId) {
            $posId = (int) $posId;
            if (! isset($cfg[$posId]) || $cfg[$posId]->is_leadership) {
                $ids[$posId] = true;
            }
        }
        // Overrides de la config que encienden jefatura en puestos NO-HOD.
        foreach ($cfg as $posId => $row) {
            if ($row->is_leadership) {
                $ids[(int) $posId] = true;
            }
        }
        return array_keys($ids);
    }

    /** Puestos "lleva pick up siempre" (conjunto del modo LIGERO). Sólo config explícita. */
    public static function alwaysPickupPositionIds(?int $pid): array
    {
        return TransportPositionConfig::where('production_id', $pid)
            ->where('always_pickup', 1)
            ->pluck('position_id')->map(fn ($x) => (int) $x)->all();
    }

    /** position_id de un usuario en la producción (pivote production_user). */
    public static function userPositionId(?int $userId, ?int $pid): ?int
    {
        if (! $userId || ! $pid) {
            return null;
        }
        $v = DB::table('production_user')->where('user_id', $userId)->where('production_id', $pid)->value('position_id');

        return $v ? (int) $v : null;
    }

    /**
     * ¿Esta corrida PUEDE marcarse discreta? SÓLO tres casos (el resto del crew se ve siempre):
     *   run_type = aplicacion · un ocupante cast · un ocupante crew en puesto de jefatura.
     */
    public static function discreetEligible(TransportOrderRun $run, ?int $pid): bool
    {
        if ($run->run_type === TransportOrderRun::TYPE_APLICACION) {
            return true;
        }
        $lead = array_flip(self::leadershipPositionIds($pid));
        foreach ($run->occupants as $o) {
            if ($o->source === TransportRunOccupant::SOURCE_CAST) {
                return true;
            }
            if ($o->source === TransportRunOccupant::SOURCE_CREW && $o->user_id) {
                $posId = self::userPositionId($o->user_id, $pid);
                if ($posId && isset($lead[$posId])) {
                    return true;
                }
            }
        }
        return false;
    }
}
