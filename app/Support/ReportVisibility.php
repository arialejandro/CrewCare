<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * AISLAMIENTO POR PROPIEDAD de los reportes de seguridad (2026-08-21 · auditoría #1).
 *
 * Los 5 índices (DSR, scouting, accidentes, actos y condiciones inseguras) NO filtraban:
 * quien alcanzaba la ruta veía TODOS los reportes. Decisión del owner: aislar a los SAFETY
 * ENTRE SÍ —"un safety no ve lo que hace otro safety", igual que los MÉDICOS entre sí— para
 * que no choquen en DSR / Injury / Actos / Condiciones. Cada quien ve SÓLO lo que capturó
 * (`created_by_id`); un rol de CONSOLIDACIÓN ve todo. Falla CERRADO.
 *
 * El BYPASS es el permiso **`safety.consolidate`** — el espejo exacto de `medical.consolidate`
 * (KEY MEDIC) del silo clínico. Lo tienen super-admin, line-producer, coordinator y auditor
 * (supervisión/compliance). **NO lo tiene el safety-officer** (aunque sí tenga los permisos de
 * gestión del módulo y `crew.view.all-departments`): por eso los safety quedan aislados a lo
 * suyo, mientras la supervisión conserva la vista consolidada.
 *
 * OJO: esto aísla el LISTADO. La ficha/edición por id (show/edit/update/status) se sigue gateando
 * por el permiso de MÓDULO, no por propiedad (un safety con hazards.manage/dsr.update aún puede
 * abrir/editar por id el reporte de otro safety). Enforcement por fila = decisión aparte del owner.
 */
class ReportVisibility
{
    /**
     * Sentinela del eje de UNIDAD: "NO filtres por unidad" (comportamiento de hoy). Es DISTINTO de null,
     * que significa la unidad PRINCIPAL (whereNull('unit_id')). Con esto apply() distingue "sin eje de
     * unidad" (hoy, nadie lo pasa) de "eje de unidad = principal".
     */
    const UNIT_UNSCOPED = '__unit_unscoped__';

    /**
     * @param  Builder    $query        consulta Eloquent del reporte (p.ej. DailyReport::query()).
     * @param  User|null  $user         el visor (auth()->user()).
     * @param  string     $ownerColumn  columna de autoría (las 5 tablas usan created_by_id).
     * @param  int|null|string $unitScope  UNIDAD vigente que se SUMA (AND) al eje de autor: UNIT_UNSCOPED
     *         (default) = no filtrar por unidad (idéntico a hoy); null = principal (whereNull); un id = esa
     *         unidad. Lo aporta el contexto de unidad vigente cuando exista (§4); hoy nadie lo pasa.
     */
    public static function apply(Builder $query, ?User $user, string $ownerColumn = 'created_by_id', $unitScope = self::UNIT_UNSCOPED): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0'); // sin sesión → nada (defensa; la ruta ya exige auth)
        }

        // Consolidación (supervisión/compliance) → ve TODO: ni el autor ni la unidad la acotan.
        if ($user->can('safety.consolidate')) {
            return $query;
        }

        // EJE AUTOR — cada quien ve sólo lo que capturó (proxy de unidad, ver canMutate).
        // created_by_id NULL → invisible (falla cerrado).
        $query->where($ownerColumn, $user->id);

        // EJE UNIDAD (2026-09-07 · 2b §3) — se SUMA (AND) al de autor, NO lo sustituye. Sólo cuando el
        // llamador aporta la unidad vigente (UNIT_UNSCOPED = no filtrar = hoy). Redundante mientras cada
        // unidad tenga su propio safety —el autor ya aísla la unidad—; deja de serlo el día que UN MISMO
        // safety cubra dos unidades. Guard de columna para instancias sin el esquema de P1.
        if ($unitScope !== self::UNIT_UNSCOPED && self::hasUnitColumn($query)) {
            $unitScope === null
                ? $query->whereNull('unit_id')
                : $query->where('unit_id', (int) $unitScope);
        }

        return $query;
    }

    /**
     * Igual que apply(), pero apilando el eje de la UNIDAD VIGENTE (2b). Con una sola unidad pasa
     * UNIT_UNSCOPED → no filtra por unidad → idéntico a hoy; con más de una, la vigente (null = principal).
     * Es el punto único que usan los 5 listados de safety.
     */
    public static function forCurrentUnit(Builder $query, ?User $user, string $ownerColumn = 'created_by_id'): Builder
    {
        $scope = CurrentUnit::hasMultiple() ? CurrentUnit::id() : self::UNIT_UNSCOPED;

        return self::apply($query, $user, $ownerColumn, $scope);
    }

    /** ¿La tabla del modelo consultado tiene columna unit_id? (defensivo para instancias sin P1). */
    private static function hasUnitColumn(Builder $query): bool
    {
        try {
            return Schema::hasColumn($query->getModel()->getTable(), 'unit_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * ¿Puede MUTAR (editar / cambiar estado / re-sellar / cerrar la acción PDCA) este reporte?
     * (2026-08-21, auditoría #1, decisión del owner). Mismo eje que la lectura del listado: SÓLO
     * el AUTOR o la CONSOLIDACIÓN (`safety.consolidate`). "Dos safeties no coexisten en la misma
     * unidad, así que un safety nunca cierra el hallazgo de otro; si el autor no está, lo cierra la
     * consolidación." La LECTURA de la ficha por id NO usa esto (es transversal). Falla CERRADO:
     * autoría NULL + sin consolidate → false.
     *
     * ── UNIDADES (2026-09-07 · 2b §3, RESUELTO) ──────────────────────────────────────────────────────
     * Los ejes producción/unidad/autor se SUMAN (AND), no se sustituyen. En apply() (el LISTADO) el eje de
     * unidad ya se puede apilar sobre el de autor vía $unitScope. Aquí (la MUTACIÓN) NO hace falta un eje
     * de unidad aparte: el AUTOR ya es proxy de la unidad —cada unidad tiene su propio safety, así que el
     * documento de un autor ES de su unidad— y `safety.consolidate` sigue viendo/pudiendo todo. El eje de
     * unidad importará el día que UN MISMO safety cubra dos unidades; ese día, quien lea/mute con una
     * unidad vigente distinta a la del documento se acota en apply() (lectura) y el enforcement por fila
     * de la mutación, si se quiere, se suma AND aquí — pero hoy sería redundante con el eje de autor.
     */
    public static function canMutate(?User $user, $model, string $ownerColumn = 'created_by_id'): bool
    {
        if (! $user || ! $model) {
            return false;
        }
        if ($user->can('safety.consolidate')) {
            return true;
        }
        $ownerId = (int) ($model->{$ownerColumn} ?? 0);
        return $ownerId !== 0 && $ownerId === (int) $user->id;
    }
}
