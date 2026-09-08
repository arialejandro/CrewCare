<?php

namespace App\Support;

use App\Models\Unit;
use App\Models\UnitMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

    // ---------------------------------------------------------------------------------------
    // CICLO DE VIDA — apagar / encender a la gente CON la unidad (§1 desactivación en cascada)
    // ---------------------------------------------------------------------------------------
    //
    // 🔑 Las unidades NO se combinan. Desactivar una unidad NO devuelve a nadie a la principal: a sus
    // EXCLUSIVOS los apaga de verdad (users.activo=0, el MISMO mecanismo que el crew), para que no queden
    // en limbo. Los COMPARTIDOS (exclusive=0) NUNCA se tocan: siguen trabajando en la principal. Reactivar
    // la unidad devuelve EXACTAMENTE a los que ella apagó (marcador deactivated_with_unit), sin resucitar a
    // quien ya estaba de baja por su cuenta. Estas operaciones son independientes de CurrentUnit/hasMultiple:
    // actúan sobre la pivote de la unidad dada, se llame como se llame el contexto vigente.

    /**
     * user_ids EXCLUSIVOS de $unitId que están ACTIVOS hoy y que, por tanto, la desactivación apagará.
     * Se excluye a quien además sea exclusivo de OTRA unidad todavía activa (caso raro pero posible: dos
     * constructores marcan a la misma persona 'solo'): esa unidad aún lo necesita, no se apaga por ésta.
     * Es la fuente ÚNICA del número que ve el aviso y de a quién toca deactivateExclusivesOf().
     *
     * @return int[]
     */
    private static function activeExclusiveTargets(int $unitId): array
    {
        if (! self::supported()) {
            return [];
        }
        $members = UnitMember::where('unit_id', $unitId)->where('exclusive', 1)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        if (empty($members)) {
            return [];
        }
        // Sólo los que hoy están activos: a quien ya estaba de baja no lo "apaga" esta unidad.
        $active = User::whereIn('id', $members)->where('activo', 1)
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (empty($active)) {
            return [];
        }
        // Protege a quien sea exclusivo de otra unidad ACTIVA (≠ ésta): esa unidad aún lo tiene trabajando.
        $otherActiveUnitIds = Unit::forProduction(CurrentProduction::id())->active()
            ->where('id', '!=', $unitId)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $protected = empty($otherActiveUnitIds) ? [] : UnitMember::where('exclusive', 1)
            ->whereIn('unit_id', $otherActiveUnitIds)->whereIn('user_id', $active)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        return array_values(array_diff($active, $protected));
    }

    /** Cuántas personas exclusivas ACTIVAS apagaría desactivar $unitId (para el aviso previo). */
    public static function countActiveExclusivesOf(int $unitId): int
    {
        return count(self::activeExclusiveTargets($unitId));
    }

    /**
     * DESACTIVAR EN CASCADA: apaga (users.activo=0) a los exclusivos activos de $unitId y los marca como
     * "apagados con la unidad" para poder devolverlos exactos al reactivar. Los compartidos no se tocan.
     * Devuelve cuántas personas apagó. No-op si falta el marcador (degrade-safe) o no hay a quién apagar.
     */
    public static function deactivateExclusivesOf(int $unitId): int
    {
        if (! self::markerSupported()) {
            return 0;
        }
        $targets = self::activeExclusiveTargets($unitId);
        if (empty($targets)) {
            return 0;
        }
        DB::transaction(function () use ($unitId, $targets) {
            User::whereIn('id', $targets)->update(['activo' => 0]);
            UnitMember::where('unit_id', $unitId)->whereIn('user_id', $targets)
                ->update(['deactivated_with_unit' => 1]);
        });

        return count($targets);
    }

    /**
     * REACTIVAR: devuelve (users.activo=1) SÓLO a los que esta unidad apagó (marcador=1) y limpia la marca.
     * No resucita a quien se dio de baja por su cuenta. Devuelve cuántas personas devolvió.
     */
    public static function reactivateAutoDeactivatedOf(int $unitId): int
    {
        if (! self::markerSupported()) {
            return 0;
        }
        $ids = UnitMember::where('unit_id', $unitId)->where('deactivated_with_unit', 1)
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        if (empty($ids)) {
            return 0;
        }
        DB::transaction(function () use ($unitId, $ids) {
            User::whereIn('id', $ids)->update(['activo' => 1]);
            UnitMember::where('unit_id', $unitId)->whereIn('user_id', $ids)
                ->update(['deactivated_with_unit' => 0]);
        });

        return count($ids);
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

    /** La cascada necesita además la columna marcador; sin ella no apaga a nadie (degrade-safe). */
    private static function markerSupported(): bool
    {
        static $memo = null;
        if ($memo === null) {
            try {
                $memo = self::supported() && Schema::hasColumn('unit_members', 'deactivated_with_unit');
            } catch (\Throwable $e) {
                $memo = false;
            }
        }

        return $memo;
    }
}
