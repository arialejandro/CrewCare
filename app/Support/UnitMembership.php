<?php

namespace App\Support;

use App\Models\UnitMember;
use Illuminate\Support\Facades\Schema;

/**
 * UnitMembership — resuelve QUIÉN está en QUÉ unidad a partir de la pivote `unit_members` (2c).
 *
 * REGLAS (ver la migración): sin fila → la persona vive en la PRINCIPAL. Fila (user,U) → está en la unidad
 * U. `exclusive=1` → sólo en U (sale de la principal); `exclusive=0` → compartida (U + principal). Un HOD
 * compartido está en las dos. `users`/`production_user` NUNCA llevan unidad.
 *
 * Con una sola unidad (pivote vacía / sin unidades adicionales) NADA se filtra → todo idéntico a hoy.
 */
class UnitMembership
{
    /** ids de usuario que pertenecen a la unidad ADICIONAL $unitId. */
    public static function userIdsIn(int $unitId): array
    {
        if (! self::supported()) {
            return [];
        }

        return UnitMember::where('unit_id', $unitId)->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }

    /** ids de usuario asignados EN EXCLUSIVA a alguna unidad adicional → NO están en la principal. */
    public static function exclusiveUserIds(): array
    {
        if (! self::supported()) {
            return [];
        }

        return UnitMember::where('exclusive', 1)->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Acota una consulta de CREW a la UNIDAD VIGENTE (CurrentUnit). SÓLO cuando hay más de una unidad: con
     * una sola no toca la consulta → CrewList/roster/llamado idénticos a hoy.
     *  - Vigente = unidad adicional U → sólo sus miembros.
     *  - Vigente = principal → todo el crew MENOS los exclusivos de otra unidad.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    public static function applyToCrew($query, string $userColumn = 'users.id')
    {
        if (! CurrentUnit::hasMultiple() || ! self::supported()) {
            return $query;
        }
        $unitId = CurrentUnit::id();
        if ($unitId !== null) {
            return $query->whereIn($userColumn, self::userIdsIn($unitId) ?: [-1]);
        }

        // Principal: quita a los exclusivos de una unidad adicional (los compartidos SÍ quedan).
        $excl = self::exclusiveUserIds();

        return empty($excl) ? $query : $query->whereNotIn($userColumn, $excl);
    }

    /**
     * Estado de una persona respecto a una unidad adicional $unitId (para el constructor):
     *  'principal' = no está en esta unidad · 'solo' = sólo aquí (exclusiva) · 'ambas' = aquí y en la principal.
     */
    public static function stateFor(int $unitId, int $userId): string
    {
        if (! self::supported()) {
            return 'principal';
        }
        $row = UnitMember::where('unit_id', $unitId)->where('user_id', $userId)->first();
        if (! $row) {
            return 'principal';
        }

        return $row->exclusive ? 'solo' : 'ambas';
    }

    /** Estado de MUCHAS personas de una unidad, de un jalón: [user_id => 'solo'|'ambas']. */
    public static function statesFor(int $unitId): array
    {
        if (! self::supported()) {
            return [];
        }
        $out = [];
        foreach (UnitMember::where('unit_id', $unitId)->get(['user_id', 'exclusive']) as $r) {
            $out[(int) $r->user_id] = $r->exclusive ? 'solo' : 'ambas';
        }

        return $out;
    }

    /**
     * Fija el estado de una persona en una unidad adicional. NO duplica ni pide nada: sólo marca presencia.
     *  - 'principal' → borra su fila (vuelve a la principal, default).
     *  - 'solo'      → fila exclusive=1 (sale de la principal).
     *  - 'ambas'     → fila exclusive=0 (aquí y en la principal).
     */
    public static function setState(int $unitId, int $userId, string $state, ?int $byId = null): void
    {
        if (! self::supported()) {
            return;
        }
        if ($state === 'principal') {
            UnitMember::where('unit_id', $unitId)->where('user_id', $userId)->delete();

            return;
        }
        UnitMember::updateOrCreate(
            ['unit_id' => $unitId, 'user_id' => $userId],
            ['exclusive' => $state === 'solo', 'created_by_id' => $byId]
        );
    }

    private static function supported(): bool
    {
        static $memo = null;
        if ($memo === null) {
            try {
                $memo = Schema::hasTable('unit_members');
            } catch (\Throwable $e) {
                $memo = false;
            }
        }

        return $memo;
    }
}
