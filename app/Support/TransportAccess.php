<?php

namespace App\Support;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * AUTORIZACIÓN del módulo de Transportación (decisión del owner 2026-08-24: HÍBRIDO permiso+depto).
 *
 * El RBAC evalúa PERMISOS, no departamentos; el departamento vive en el pivote production_user
 * ({@see User::ownDepartmentIds()}). Por eso el acceso se compone en código, no solo con middleware:
 *
 *   - `transport.manage` (safety-officer + super-admin) → gestión completa.
 *   - `transport.view`   (line-producer + coordinator + auditor) → SOLO la vista LITE de producción.
 *   - Departamento de TRANSPORTACIÓN (por pertenencia al pivote) → puede LEVANTAR el checklist y ver
 *     el HUB, aunque no tenga el permiso. **Pero NO edita actas** (decisión del owner): como el acta
 *     es crear-y-sellar INMUTABLE, "no editar" queda satisfecho para todos — nadie edita un acta
 *     sellada. La reevaluación es un acta NUEVA (un levantamiento), no una edición.
 *
 * canFull = levantar/HUB/actas (manage O depto). canLite = vista de producción (view O canFull).
 */
class TransportAccess
{
    /** IDs del/los departamento(s) de Transportación (catálogo pequeño; sin caché para no ensuciar tests). */
    public static function transportDepartmentIds(): array
    {
        try {
            if (! Schema::hasTable('departments')) {
                return [];
            }
            return Department::whereRaw('LOWER(name) LIKE ?', ['%transport%'])
                ->pluck('id')->map(fn ($x) => (int) $x)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function isInTransport(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $dept = self::transportDepartmentIds();
        if (empty($dept)) {
            return false;
        }
        try {
            return $user->ownDepartmentIds()->intersect($dept)->isNotEmpty();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Gestión completa: levantar checklist, HUB, actas, flota, documentos. */
    public static function canFull(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        return $user->can('transport.manage') || self::isInTransport($user);
    }

    /** Vista LITE de producción (tarjeta, placas, licencia, conductor). */
    public static function canLite(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        return $user->can('transport.view') || self::canFull($user);
    }

    /**
     * ¿El usuario tiene corridas asignadas como driver? Gatea el enlace "Mis corridas" del sidebar
     * para el crew que SÓLO maneja (sin permiso de transpo). Caché por petición (el sidebar se
     * pinta en cada vista). No decide acceso a datos: la pantalla filtra por driver_user_id.
     */
    public static function isAssignedDriver(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        static $cache = [];
        if (array_key_exists($user->id, $cache)) {
            return $cache[$user->id];
        }
        try {
            if (! Schema::hasTable('transport_order_runs')) {
                return $cache[$user->id] = false;
            }
            return $cache[$user->id] = \App\Models\TransportOrderRun::where('driver_user_id', $user->id)
                ->where('is_active', 1)->exists();
        } catch (\Throwable $e) {
            return $cache[$user->id] = false;
        }
    }
}
