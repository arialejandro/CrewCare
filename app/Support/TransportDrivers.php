<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Transportación — DRIVERS (choferes) de la producción: crew del depto de Transportación con puesto
 * `transport.driver`. El selector de driver se ACOTA a ellos (regla del owner: si no pertenece al
 * departamento, no se ve; y siendo estrictos, sólo drivers). Además distingue LIBRES vs ASIGNADOS a
 * una unidad, para el selector con default-libres + toggle (FILTRO, no candado).
 */
class TransportDrivers
{
    public const DEPT_KEY   = 'transport';
    public const DRIVER_KEY = 'transport.driver';

    /** user_ids del depto de transporte con puesto de chofer, en la producción. */
    public static function candidateIds(?int $pid): array
    {
        if (! $pid) {
            return [];
        }
        return DB::table('production_user as pu')
            ->join('departments as d', 'd.id', '=', 'pu.department_id')
            ->join('positions as p', 'p.id', '=', 'pu.position_id')
            ->where('pu.production_id', $pid)
            ->where('d.catalog_key', self::DEPT_KEY)
            ->where('p.catalog_key', self::DRIVER_KEY)
            ->pluck('pu.user_id')->map(fn ($x) => (int) $x)->unique()->values()->all();
    }

    /**
     * Para el selector del vehículo: cada driver con su nombre y, si YA conduce OTRA unidad activa
     * (≠ la actual), la etiqueta de ese vehículo. Libres primero.
     * @return array<int,array{id:int,name:string,other_vehicle:?string}>
     */
    public static function forVehicleForm(?int $pid, ?int $currentVehicleId = null): array
    {
        $ids = self::candidateIds($pid);
        if (! $ids) {
            return [];
        }
        $users = User::whereIn('id', $ids)->orderBy('name')->get();

        $assigned = Vehicle::where('is_active', 1)
            ->whereIn('driver_user_id', $ids)
            ->when($currentVehicleId, fn ($q) => $q->where('id', '!=', $currentVehicleId))
            ->get(['id', 'make', 'model', 'plate', 'driver_user_id']);
        $byDriver = [];
        foreach ($assigned as $v) {
            $byDriver[(int) $v->driver_user_id] = self::vehLabel($v);
        }

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id'            => (int) $u->id,
                'name'          => User::displayName($u),
                'other_vehicle' => $byDriver[(int) $u->id] ?? null,
            ];
        }
        usort($out, fn ($a, $b) => ($a['other_vehicle'] ? 1 : 0) <=> ($b['other_vehicle'] ? 1 : 0)); // libres primero

        return $out;
    }

    /** Si $driverId ya conduce OTRA unidad activa, devuelve ese Vehicle (para liberar + avisar). */
    public static function otherVehicleOf(?int $driverId, ?int $exceptVehicleId): ?Vehicle
    {
        if (! $driverId) {
            return null;
        }
        return Vehicle::where('is_active', 1)->where('driver_user_id', $driverId)
            ->when($exceptVehicleId, fn ($q) => $q->where('id', '!=', $exceptVehicleId))
            ->first();
    }

    public static function vehLabel(Vehicle $v): string
    {
        $base = trim(($v->make ?: '') . ' ' . ($v->model ?: '')) ?: ('Vehículo #' . $v->id);
        return $v->plate ? ($base . ' · ' . $v->plate) : $base;
    }
}
