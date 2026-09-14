<?php

namespace App\Support;

use App\Models\OutReporter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OutAuthority — quién puede REPORTAR la salida de un DEPARTAMENTO y VER el tablero.
 *
 * 🔴 Autoridad POR DESIGNACIÓN, NO por puesto (corrección 2026-09-13). No es el jefe (`is_hod`) ni el
 * coordinador: es una persona DESIGNADA (por producción o por el propio departamento) según quién sea
 * la más apta — puede ser cualquiera del equipo, y la designación se declara y se cambia (tabla
 * `out_reporters`).
 *   - Producción / coordinación (permiso `crew.view.all-departments`) → ven y reportan TODO, y designan.
 *   - Un DESIGNADO reporta la salida de SU(S) depto(s) y puede gestionar los designados de ESE depto
 *     (dept auto-gestionado).
 *
 * ⚠ Esta autoridad es SÓLO para reportar POR EL DEPARTAMENTO (WhatsApp / pegar mensaje). Marcar la
 * PROPIA salida (individual) NO requiere autoridad alguna: cada quien puede con la suya (ver
 * OutController::myOut). Sin `is_hod` en ningún lado.
 */
class OutAuthority
{
    /** Producción / coordinación: ven, reportan y designan en todos los departamentos. */
    public static function seesAll(User $viewer): bool
    {
        return $viewer->can('crew.view.all-departments');
    }

    /**
     * IDs de departamento donde $viewer está DESIGNADO como reportero, en la producción vigente.
     *
     * @return int[]
     */
    public static function authorityDepartmentIds(User $viewer): array
    {
        $pid = CurrentProduction::id();
        if (! $pid || ! Schema::hasTable('out_reporters')) {
            return [];
        }

        return OutReporter::where('production_id', $pid)
            ->where('user_id', $viewer->id)
            ->pluck('department_id')
            ->map(fn ($x) => (int) $x)
            ->unique()->values()->all();
    }

    /** ¿Puede reportar salidas de ESTE departamento? (producción, o designado del depto). */
    public static function canRegisterFor(User $viewer, int $departmentId): bool
    {
        return self::seesAll($viewer)
            || in_array((int) $departmentId, self::authorityDepartmentIds($viewer), true);
    }

    /** ¿Puede gestionar (designar/retirar) reporteros de ESTE depto? Producción, o un designado del depto. */
    public static function canDesignateFor(User $viewer, int $departmentId): bool
    {
        return self::canRegisterFor($viewer, $departmentId);
    }

    /** ¿Tiene sentido mostrarle el tablero de salidas? (ve todo, o es designado de ≥1 depto). */
    public static function canUseScreen(User $viewer): bool
    {
        return self::seesAll($viewer) || ! empty(self::authorityDepartmentIds($viewer));
    }

    /**
     * Departamentos que $viewer puede VER/REPORTAR en el tablero: null = TODOS (producción); si no, sus
     * deptos designados.
     *
     * @return int[]|null
     */
    public static function visibleDepartmentIds(User $viewer): ?array
    {
        return self::seesAll($viewer) ? null : self::authorityDepartmentIds($viewer);
    }
}
