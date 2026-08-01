<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SafetyAlertRecipients — resuelve QUIÉN recibe el aviso de seguridad de riesgo
 * Alto/Extremo. Es la UNIÓN, deduplicada por correo, de tres orígenes:
 *
 *   1. Contactos manuales EXTERNOS activos  → tabla `usuariosnotificaciones` (CRUD legacy).
 *   2. Usuarios por ROL Spatie              → medic, line-producer, safety-officer, coordinator.
 *   3. Usuarios por PUESTO                   → production_user.position_id ∈ {13,14,16}
 *      (Gerente de Producción / Gerente de Unidad / Coordinador de Producción — el ROL no
 *      los distingue, por eso van por puesto).
 *
 * DEFENSIVO por diseño: cada origen se salta si su tabla no existe (deploy sin el SQL) y todo
 * el método está envuelto para que NUNCA lance — un problema al resolver destinatarios no debe
 * romper el guardado del reporte que disparó el aviso. Devuelve [] en el peor caso.
 *
 * (2026-07-19) Creado para el Listener SendHighRiskSafetyAlert.
 */
class SafetyAlertRecipients
{
    /** Roles Spatie que reciben el aviso. */
    const ROLES = ['medic', 'line-producer', 'safety-officer', 'coordinator'];

    /**
     * Puestos que reciben el aviso (production_user.position_id). El ROL no los distingue:
     *   13 = Gerente de Producción · 14 = Gerente de Unidad · 16 = Coordinador de Producción.
     */
    const POSITION_IDS = [13, 14, 16];

    /**
     * Lista deduplicada de destinatarios: [['email' => ..., 'name' => ...], ...].
     *
     * @return array
     */
    public static function resolve(): array
    {
        $rows = []; // clave = correo en minúsculas → ['email','name'] (primero gana el nombre)

        // 1) Contactos manuales externos activos.
        try {
            if (Schema::hasTable('usuariosnotificaciones')) {
                $manual = DB::table('usuariosnotificaciones')
                    ->where('activo', 1)
                    ->get(['nombre', 'correo']);
                foreach ($manual as $u) {
                    self::add($rows, isset($u->correo) ? $u->correo : null, isset($u->nombre) ? $u->nombre : null);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SafetyAlertRecipients: fallo leyendo usuariosnotificaciones — ' . $e->getMessage());
        }

        // 2) Usuarios por ROL Spatie. Se usa el scope role() del modelo User (maneja el
        //    guard/morph internamente → inmune al valor exacto de model_has_roles.model_type).
        try {
            if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
                $byRole = \App\Models\User::role(self::ROLES)->get(['id', 'name', 'email']);
                foreach ($byRole as $u) {
                    self::add($rows, isset($u->email) ? $u->email : null, isset($u->name) ? $u->name : null);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SafetyAlertRecipients: fallo resolviendo por rol — ' . $e->getMessage());
        }

        // 3) Usuarios por PUESTO (pivote production_user).
        try {
            if (Schema::hasTable('production_user') && Schema::hasTable('users')) {
                $byPosition = DB::table('users')
                    ->join('production_user as pu', 'pu.user_id', '=', 'users.id')
                    ->whereIn('pu.position_id', self::POSITION_IDS)
                    ->distinct()
                    ->get(['users.name', 'users.email']);
                foreach ($byPosition as $u) {
                    self::add($rows, isset($u->email) ? $u->email : null, isset($u->name) ? $u->name : null);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SafetyAlertRecipients: fallo resolviendo por puesto — ' . $e->getMessage());
        }

        return array_values($rows);
    }

    /**
     * Agrega un destinatario si el correo es válido y no está repetido (dedup por minúsculas).
     * El primer nombre visto para un correo se conserva.
     */
    private static function add(array &$rows, $email, $name): void
    {
        $email = trim((string) $email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $key = mb_strtolower($email);
        if (isset($rows[$key])) {
            return;
        }
        $rows[$key] = [
            'email' => $email,
            'name'  => trim((string) $name) !== '' ? trim((string) $name) : $email,
        ];
    }
}
