<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * MedicRolePermissionsSeeder — PASO A: cimiento de "quién es médico". 2026-07-19.
 *
 * Hace DOS cosas:
 *   1. Habilita al rol `medic` sobre la gestión H&S (hazards.manage + hazards.create).
 *   2. Migra al rol `medic` a quienes hoy ocupan los puestos "Doctor" del catálogo.
 *
 * POR QUÉ EXISTE: RolesAndPermissionsSeeder tiene una guarda anti-sobreescritura
 * ("si algún rol ya tiene permisos → return", RolesAndPermissionsSeeder.php:104) que
 * protege la matriz personalizada en vivo desde la pantalla "Permisos por rol".
 * Efecto colateral: sobre una BD viva ese seeder REGISTRA permisos pero NO los
 * REPARTE. Este seeder cubre ese hueco para `medic`.
 *
 * QUÉ ABRE hazards.manage:
 *   - InjuryReportPolicy::viewMedical() (app/Policies/InjuryReportPolicy.php:60): el
 *     SILO MÉDICO sensible del reporte de lesión (tratamiento, EPP, causa raíz,
 *     teléfono y declaración de testigos) de un reporte que el médico NO capturó.
 *   - Cerrar/mover action_status de actos y condiciones inseguras (routes/web.php:223-224).
 *   - Cerrar/reabrir acciones correctivas del motor PDCA (routes/web.php:228-229).
 *   - Editar la carga de compliance de actos/condiciones inseguras (routes/web.php:558-563).
 *
 * POR QUÉ TAMBIÉN hazards.create — NO es un extra, es evitar una REGRESIÓN:
 *   `hazards.create` ("Reportar condiciones inseguras", routes/web.php:208-213) es el
 *   ÚNICO permiso que el rol `crew` tiene y `medic` NO. Como la app usa syncRoles
 *   (un rol por usuario, RoleAssignmentController.php:114), migrar de `crew` a `medic`
 *   QUITA el rol crew y con él la capacidad de LEVANTAR un acto/condición insegura —
 *   justo lo que el Paso A exige que el médico pueda hacer. Sin este grant la
 *   migración sería una regresión silenciosa: el menú simplemente desaparecería.
 *
 * OJO CON EL NOMBRE: es `hazards.manage` (REPORTES de condiciones inseguras), NO
 * `hazardevents.manage` (CATÁLOGO de eventos posibles). Escribir el equivocado no
 * truena — firstOrCreate lo crearía como permiso huérfano y el gate seguiría cerrado.
 *
 * ADITIVO E IDEMPOTENTE: usa givePermissionTo() (NUNCA syncPermissions(), que borraría
 * los 16 grants que `medic` ya tiene, incluidos medical.view/create/update/materials y
 * documents.view/sign). Re-correrlo no cambia nada. Solo toca roles/usuarios que
 * EXISTAN; los ausentes se omiten sin tronar.
 *
 * Correr:  php artisan db:seed --class=MedicRolePermissionsSeeder
 *          (en prod, después:  php artisan cache:clear  — el store de permisos es
 *           `file` con expiración de 24 h, config/permission.php)
 *
 * NO registrar en DatabaseSeeder: correría en cada db:seed completo, incluida
 * cualquier instancia nueva donde los puestos 176/177 tengan otros titulares.
 */
class MedicRolePermissionsSeeder extends Seeder
{
    /**
     * Puestos del catálogo que se usan UNA VEZ para sembrar el rol (ambos en el
     * departamento 31, "Salud y Seguridad"): 176 = Doctor en Set, 177 = Doctor de
     * Construcción.
     *
     * IMPORTANTE: esto NO convierte al puesto en fuente de identidad médica. La
     * fuente autoritativa es el rol Spatie `medic` (User::isMedic()); el puesto solo
     * sirve aquí como criterio de SIEMBRA inicial, porque es el único rastro fiable
     * de quiénes son los médicos reales en la BD de hoy.
     */
    private const MEDIC_POSITION_IDS = [176, 177];

    /**
     * Permisos que se AGREGAN al rol medic.
     *   hazards.manage → objetivo del Paso A (silo clínico + gestión H&S).
     *   hazards.create → compensa lo que se pierde al dejar de ser `crew` (ver docblock).
     */
    private const MEDIC_GRANTS = ['hazards.manage', 'hazards.create'];

    public function run()
    {
        // Cache de spatie (config/permission.php, store `file`, 24 h): limpiar ANTES
        // (para leer el estado real) y DESPUÉS (para publicar los grants).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // ---- 1. Alta defensiva de los permisos (por si esta BD nunca corrió el principal) ----
        foreach (self::MEDIC_GRANTS as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // ---- 2. Grant ADITIVO al rol medic ----
        $role = Role::where('name', User::MEDIC_ROLE)->where('guard_name', $guard)->first();
        if (!$role) {
            if ($this->command) {
                $this->command->warn('MedicRolePermissionsSeeder: el rol `'.User::MEDIC_ROLE.'` no existe en esta BD; se omite el reparto.');
            }
        } else {
            $role->givePermissionTo(self::MEDIC_GRANTS); // aditivo: respeta lo que el rol ya tenía
            if ($this->command) {
                $this->command->info('MedicRolePermissionsSeeder: '.User::MEDIC_ROLE.' ← '.implode(', ', self::MEDIC_GRANTS));
            }
        }

        // ---- 3. Titulares de los puestos 176/177 → rol medic ----
        // Espeja RoleAssignmentController::update() (líneas 112-141): syncRoles global +
        // production_user.role alineado. DIFERENCIA DELIBERADA: NO tocamos department_id
        // ni position_id. El controlador anula position_id cuando cambia el depto
        // (RoleAssignmentController.php:122-128); aquí el puesto ES el criterio de
        // selección — borrarlo rompería la idempotencia (la 2ª corrida no hallaría a nadie).
        $rows = DB::table('production_user')
            ->whereIn('position_id', self::MEDIC_POSITION_IDS)
            ->get();

        if ($rows->isEmpty() && $this->command) {
            $this->command->warn('MedicRolePermissionsSeeder: ningún usuario ocupa los puestos '.implode('/', self::MEDIC_POSITION_IDS).'; nada que migrar.');
        }

        foreach ($rows as $row) {
            $user = User::find($row->user_id);

            if (!$user) {
                if ($this->command) {
                    $this->command->warn('MedicRolePermissionsSeeder: user_id '.$row->user_id.' del pivote no existe; se omite.');
                }
                continue;
            }

            // Salvaguarda: nunca degradar a un super-admin. syncRoles lo dejaría sin
            // god-mode (Gate::before de AuthServiceProvider.php:34 depende de ese rol).
            if ($user->hasRole('super-admin')) {
                if ($this->command) {
                    $this->command->warn('MedicRolePermissionsSeeder: '.$user->name.' (id '.$user->id.') es super-admin; se omite para no degradarlo.');
                }
                continue;
            }

            $alreadyMedic = $user->isMedic();
            $previous     = $user->getRoleNames()->implode(', ');

            DB::transaction(function () use ($user, $row, $alreadyMedic) {
                if (!$alreadyMedic) {
                    // syncRoles deja EXACTAMENTE este rol (patrón un-rol-por-usuario de la app).
                    $user->syncRoles([User::MEDIC_ROLE]);
                }

                // Pivote contextual alineado con el rol global. is_lead solo es true para
                // 'hod' (RoleAssignmentController.php:136) → aquí 0.
                DB::table('production_user')
                    ->where('id', $row->id)
                    ->update([
                        'role'       => User::MEDIC_ROLE,
                        'is_lead'    => 0,
                        'updated_at' => now(),
                    ]);
            });

            if ($this->command) {
                $this->command->info(
                    'MedicRolePermissionsSeeder: '.$user->name.' (id '.$user->id.', puesto '.$row->position_id.') '
                    .($alreadyMedic ? 'ya era medic; pivote sincronizado.' : '['.$previous.'] → medic.')
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
