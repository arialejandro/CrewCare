<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  Builder   $query        consulta Eloquent del reporte (p.ej. DailyReport::query()).
     * @param  User|null $user         el visor (auth()->user()).
     * @param  string    $ownerColumn  columna de autoría (las 5 tablas usan created_by_id).
     */
    public static function apply(Builder $query, ?User $user, string $ownerColumn = 'created_by_id'): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0'); // sin sesión → nada (defensa; la ruta ya exige auth)
        }

        // Consolidación (supervisión/compliance) → ve TODO, de cualquier autor.
        if ($user->can('safety.consolidate')) {
            return $query;
        }

        // El resto (incluidos los safety-officer entre sí): sólo lo que capturó.
        // created_by_id NULL → invisible (falla cerrado).
        return $query->where($ownerColumn, $user->id);
    }

    /**
     * ¿Puede MUTAR (editar / cambiar estado / re-sellar / cerrar la acción PDCA) este reporte?
     * (2026-08-21, auditoría #1, decisión del owner). Mismo eje que la lectura del listado: SÓLO
     * el AUTOR o la CONSOLIDACIÓN (`safety.consolidate`). "Dos safeties no coexisten en la misma
     * unidad, así que un safety nunca cierra el hallazgo de otro; si el autor no está, lo cierra la
     * consolidación." La LECTURA de la ficha por id NO usa esto (es transversal). Falla CERRADO:
     * autoría NULL + sin consolidate → false. (Futuro: cuando exista el modelo de UNIDADES, el
     * autor es proxy de la unidad; verificar que ambos ejes se SUMEN.)
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
