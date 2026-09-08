<?php

namespace App\Support;

use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * CurrentUnit — FUENTE ÚNICA de "¿en qué UNIDAD se está trabajando?" (2026-09-07 · Unidades 2b).
 *
 * Hermano de CurrentProduction. La unidad VIGENTE es una preferencia POR SESIÓN (opción (a) del owner):
 * un selector en el topbar la fija; el default es la PRINCIPAL (null). NO vive en users ni en
 * production_user —los HOD compartidos viven en las dos unidades—, vive en la sesión.
 *
 * 🔑 NULL = unidad PRINCIPAL (no hay fila). Las operativas estampan y filtran con este valor.
 *
 * REGLA DE ORO (para que "una sola unidad = idéntico a hoy"): si NO hay unidades adicionales,
 * hasMultiple() es false y applyTo() NO toca la consulta — cero cambio de comportamiento. El selector y
 * la franja tampoco se pintan. El eje de unidad sólo existe cuando de verdad hay más de una unidad.
 *
 * VALIDACIÓN: el id guardado en sesión debe ser una unidad ACTIVA de la producción vigente; si se
 * desactivó o cambió la producción, id() cae a principal (null) en silencio (falla seguro).
 */
class CurrentUnit
{
    /** Clave de sesión de la unidad vigente. */
    const SESSION_KEY = 'cc_active_unit_id';

    /** Memo por petición. `false` = sin resolver (null SÍ es respuesta válida = principal). */
    private static $memoId = false;

    /** Memo de las unidades activas de la producción vigente. */
    private static $memoUnits = null;

    /**
     * Unidades ADICIONALES activas de la producción vigente (la principal es implícita, sin fila).
     *
     * @return Collection<int,\App\Models\Unit>
     */
    public static function activeUnits(): Collection
    {
        if (self::$memoUnits !== null) {
            return self::$memoUnits;
        }
        if (! Schema::hasTable('units')) {
            return self::$memoUnits = collect();
        }
        try {
            self::$memoUnits = Unit::query()->forProduction(CurrentProduction::id())->active()->get();
        } catch (\Throwable $e) {
            self::$memoUnits = collect();
        }

        return self::$memoUnits;
    }

    /**
     * ¿Hay MÁS DE UNA unidad (principal + ≥1 adicional)? El selector, la franja y el eje de filtro sólo
     * existen si esto es true. Con una sola unidad, todo es idéntico a hoy.
     */
    public static function hasMultiple(): bool
    {
        return self::activeUnits()->isNotEmpty();
    }

    /** Id de la unidad VIGENTE. NULL = principal. Validada contra las unidades activas (falla seguro). */
    public static function id(): ?int
    {
        if (self::$memoId !== false) {
            return self::$memoId;
        }

        $raw = null;
        try {
            $raw = session(self::SESSION_KEY);
        } catch (\Throwable $e) {
            $raw = null; // sin sesión (consola/seeder/arnés) → principal
        }
        if ($raw === null || $raw === '') {
            return self::$memoId = null;
        }

        $rawId = (int) $raw;
        // Debe seguir siendo una unidad ACTIVA de la producción vigente; si no, principal.
        $ok = self::activeUnits()->contains(fn ($u) => (int) $u->id === $rawId);

        return self::$memoId = ($ok ? $rawId : null);
    }

    /** ¿La unidad vigente es la principal? */
    public static function isPrincipal(): bool
    {
        return self::id() === null;
    }

    /** El modelo Unit vigente, o null (principal). */
    public static function current(): ?Unit
    {
        $id = self::id();

        return $id === null ? null : self::activeUnits()->firstWhere('id', $id);
    }

    /** Nombre a MOSTRAR de la unidad vigente (principal si es null). */
    public static function label(): string
    {
        return Unit::displayName(self::id());
    }

    /**
     * Fija la unidad vigente en la sesión (null / id inválido → principal). Valida contra las activas
     * para que no quede pegada una unidad ajena o desactivada.
     */
    public static function set(?int $unitId): void
    {
        self::$memoId = false; // invalida el memo

        if ($unitId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }
        $ok = self::activeUnits()->contains(fn ($u) => (int) $u->id === (int) $unitId);
        $ok ? session()->put(self::SESSION_KEY, (int) $unitId)
            : session()->forget(self::SESSION_KEY);
    }

    /** Olvida los memos (seeders/arnés que mueven unidades dentro del mismo proceso). */
    public static function forget(): void
    {
        self::$memoId = false;
        self::$memoUnits = null;
    }

    /**
     * Apila el EJE DE UNIDAD sobre una consulta, PERO sólo cuando hay más de una unidad: con una sola no
     * toca nada → la consulta queda idéntica a hoy. NULL vigente = principal (whereNull); un id = esa
     * unidad. Es el mismo criterio en todos los dominios operativos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    public static function applyTo($query, string $column = 'unit_id')
    {
        if (! self::hasMultiple()) {
            return $query; // una sola unidad → sin filtro → idéntico a hoy
        }
        $id = self::id();

        return $id === null ? $query->whereNull($column) : $query->where($column, $id);
    }
}
