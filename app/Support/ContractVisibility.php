<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * VISIBILIDAD DE CONTRATOS por departamento (consulta de SOLO LECTURA). Regla definida por el owner:
 *
 *  - Cada departamento ve los contratos de SU departamento (`payee_contracts.department_id`).
 *  - "Ven TODO" las áreas que administran la contratación de punta a punta: Producción (ahí vive el
 *    Productor en Línea), Oficina de Producción (Coordinación de Producción: coordinador, APOC, PA de
 *    oficina…) y Contabilidad — más quien tenga el bypass `crew.view.all-departments`.
 *  - Super-admin ve TODO.
 *
 * La membresía es por DEPARTAMENTO (`production_user.department_id`), como el resto del panel. Los
 * NOMBRES son la clave estable del catálogo (los ids no son estables entre instancias), mismo idioma
 * que {@see SignaturePositions::SIGNER_DEPARTMENTS}. Ajustar la lista de abajo si cambia la política.
 */
class ContractVisibility
{
    /** Departamentos que ven TODOS los contratos (nombre = clave estable del catálogo `departments`). */
    public const SEES_ALL_DEPARTMENTS = ['Producción', 'Oficina de Producción', 'Contabilidad'];

    /** Ids de los departamentos "ve todo" (consulta ligera, indexada por nombre). */
    private static function seesAllDepartmentIds(): array
    {
        return Department::whereIn('name', self::SEES_ALL_DEPARTMENTS)
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    private static function ownDeptIds(?User $u): array
    {
        if (! $u) {
            return [];
        }
        $ids = $u->ownDepartmentIds();               // Collection (o array)
        $ids = $ids instanceof \Illuminate\Support\Collection ? $ids->all() : (array) $ids;

        return array_map('intval', $ids);
    }

    /** ¿Este usuario ve TODOS los contratos (super-admin, bypass, o depto de administración)? */
    public static function seesAll(?User $u): bool
    {
        if (! $u) {
            return false;
        }
        if ($u->hasRole('super-admin') || $u->can('crew.view.all-departments')) {
            return true;
        }

        return (bool) array_intersect(self::ownDeptIds($u), self::seesAllDepartmentIds());
    }

    /** ¿Ve al menos ALGO (para pintar la entrada del panel)? Ve todo, o pertenece a algún departamento. */
    public static function seesAny(?User $u): bool
    {
        if (! $u) {
            return false;
        }

        return self::seesAll($u) || count(self::ownDeptIds($u)) > 0;
    }

    /** Acota una consulta de SOBRES a lo VISIBLE para el usuario (todo, o solo su(s) departamento(s)). */
    public static function scopeVisible(Builder $q, ?User $u): Builder
    {
        if (self::seesAll($u)) {
            return $q;
        }
        $deptIds = self::ownDeptIds($u);

        return $q->whereHas('contract', fn ($c) => $c->whereIn('department_id', $deptIds ?: [-1]));
    }

    /** ¿Puede VER (consultar/descargar) ESTE sobre? Ve todo, o el contrato es de su departamento. */
    public static function canView(?User $u, ContractEnvelope $envelope): bool
    {
        if (self::seesAll($u)) {
            return true;
        }
        if (! $u) {
            return false;
        }
        $dept = (int) optional($envelope->contract)->department_id;

        return $dept > 0 && in_array($dept, self::ownDeptIds($u), true);
    }
}
