<?php

namespace App\Policies;

use App\Models\InjuryReport;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Schema;

/**
 * InjuryReportPolicy — RBAC / silos médicos (MÓDULO 13).
 *
 * Distingue DOS niveles de acceso sobre un reporte de lesión:
 *
 *   - view()        acceso a la FICHA general del reporte. Laxo: cualquier
 *                   usuario autenticado que ya pasó el gate de ruta
 *                   (permission:injury.view) puede consultarla.
 *
 *   - viewMedical() acceso al SILO MÉDICO/sensible (nivel de atención, causa
 *                   raíz, EPP, tratamiento/hospital, teléfono y declaración de
 *                   testigos). Reservado a: el capturador original, el personal
 *                   H&S (permiso hazards.manage) y el médico (rol Spatie `medic`,
 *                   comprobado vía User::isMedic() — NO escribir el literal aquí).
 *
 * El super-admin NO se maneja aquí: pasa por el Gate::before global
 * (AuthServiceProvider) que corta a true antes de llegar a la policy.
 */
class InjuryReportPolicy
{
    use HandlesAuthorization;

    /**
     * Ver la ficha general del reporte (no sensible).
     */
    public function view(User $user, InjuryReport $report): bool
    {
        // El acceso base ya lo controla el middleware permission:injury.view en la
        // ruta; a nivel de fila no restringimos la ficha general.
        return true;
    }

    /**
     * Ver la información médica sensible del reporte.
     */
    public function viewMedical(User $user, InjuryReport $report): bool
    {
        // 1) Capturador original. El task pide comparar tanto user_id (persona
        //    ligada al reporte) como created_by_id (autor server-side, si existe).
        if ($report->user_id !== null && (int) $report->user_id === (int) $user->id) {
            return true;
        }
        if (Schema::hasColumn('injury_reports', 'created_by_id')
            && $report->created_by_id !== null
            && (int) $report->created_by_id === (int) $user->id) {
            return true;
        }

        // 2) Personal H&S (permiso Spatie). Defensivo: si el permiso aún no existe
        //    en prod, can() no debe reventar la vista.
        try {
            if ($user->can('hazards.manage')) {
                return true;
            }
        } catch (\Throwable $e) {
            // permiso inexistente / registrar no disponible → se ignora.
        }

        // 3) Rol médico — FUENTE AUTORITATIVA ÚNICA: User::isMedic() (rol Spatie `medic`).
        //
        //    BUG CORREGIDO (PASO A, 2026-07-19): aquí se comparaba contra el literal
        //    'medico', un rol que NO EXISTE en la BD (el sembrado es 'medic'). Spatie
        //    devuelve false ante un nombre de rol inexistente en vez de lanzar, y el
        //    try/catch de abajo lo habría tapado igualmente, así que la rama llevaba
        //    muerta EN SILENCIO: un médico real nunca podía abrir el silo clínico de un
        //    reporte que no capturó él mismo. Encima el super-admin corta antes por
        //    Gate::before, de modo que probando con la cuenta del owner el bug era
        //    invisible. Ahora la comprobación vive en User::isMedic() (que ya es
        //    defensivo internamente) para que el literal no vuelva a escribirse a mano.
        if ($user->isMedic()) {
            return true;
        }

        return false;
    }
}
