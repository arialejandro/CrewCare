<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * OutAuthority — quién puede REGISTRAR y VER salidas, POR PUESTO (no por persona).
 *
 * La autoridad se declara como PUESTO para que se mueva sola cuando cambie quién lo ocupa:
 *   - Producción / coordinación (permiso `crew.view.all-departments`) → ven y registran TODO.
 *   - Quien ocupa un puesto de JEFATURA (`positions.is_hod`) en un depto (vía el pivote
 *     production_user de la producción vigente) → registra las salidas de ESE depto.
 *
 * Reusa exactamente las piezas del scope de crew ([[User::applyDepartmentScope]] / is_hod): así la
 * regla de "quién manda en un depto" es una sola en toda la app. Si más adelante el coordinador con
 * autoridad NO es el jefe (is_hod) sino otro puesto, se añade una config por puesto (precedente
 * TransportPositionConfig) sin tocar esto.
 */
class OutAuthority
{
    /** Producción / coordinación: ven y registran todos los departamentos. */
    public static function seesAll(User $viewer): bool
    {
        return $viewer->can('crew.view.all-departments');
    }

    /**
     * IDs de departamento donde $viewer tiene AUTORIDAD (ocupa un puesto is_hod), para la producción
     * vigente. Vacío si no es jefe de ninguno.
     *
     * @return int[]
     */
    public static function authorityDepartmentIds(User $viewer): array
    {
        $pid = CurrentProduction::id();
        if (! $pid) {
            return [];
        }

        return DB::table('production_user as pu')
            ->join('positions as p', 'p.id', '=', 'pu.position_id')
            ->where('pu.production_id', $pid)
            ->where('pu.user_id', $viewer->id)
            ->where('p.is_hod', 1)
            ->pluck('pu.department_id')
            ->map(function ($x) {
                return (int) $x;
            })
            ->unique()
            ->values()
            ->all();
    }

    /** ¿Puede registrar salidas de ESTE departamento? */
    public static function canRegisterFor(User $viewer, int $departmentId): bool
    {
        return self::seesAll($viewer)
            || in_array((int) $departmentId, self::authorityDepartmentIds($viewer), true);
    }

    /** ¿Tiene sentido mostrarle la pantalla? (ve todo, o es jefe de al menos un depto). */
    public static function canUseScreen(User $viewer): bool
    {
        return self::seesAll($viewer) || ! empty(self::authorityDepartmentIds($viewer));
    }

    /**
     * Departamentos que $viewer puede VER en la pantalla: null = TODOS (producción/coordinación);
     * si no, la lista de sus deptos con autoridad.
     *
     * @return int[]|null
     */
    public static function visibleDepartmentIds(User $viewer): ?array
    {
        return self::seesAll($viewer) ? null : self::authorityDepartmentIds($viewer);
    }
}
