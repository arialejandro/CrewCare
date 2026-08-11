<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\MedicalAccessGrant;
use App\Models\Production;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla de asignación de ROL (global, spatie) + DEPARTAMENTO (pivote production_user)
 * para cada usuario.
 *
 * Contexto de despliegue: cada cliente corre una COPIA independiente de la app en su VPS,
 * así que una instancia = una sola producción. Por eso esta pantalla NO tiene selector de
 * producción: opera sobre la única "Producción Demo" de la instancia. El día que exista
 * navegación multi-producción, se extiende.
 *
 * Protegida por `permission:users.assign-role` (la ruta). El departamento es el dato que
 * habilita el scope del HOD (#2): "su departamento" sale de production_user.department_id.
 */
class RoleAssignmentController extends Controller
{
    /**
     * Roles asignables desde la UI. `super-admin` se excluye a propósito: es god-mode y se
     * concede solo por seeder / manualmente, nunca por una pantalla (evita escaladas).
     */
    private const ASSIGNABLE = [
        'line-producer', 'coordinator', 'hod', 'medic', 'safety-officer', 'crew', 'auditor',
    ];

    /**
     * (2026-07-24) La producción vigente. Antes buscaba por el NOMBRE LITERAL 'Producción Demo';
     * el día que esa fila se renombre —o se dé de alta la producción real— esto devolvía null en
     * silencio y las asignaciones de rol dejaban de guardarse sin un solo error visible.
     * Ahora lo decide App\Support\CurrentProduction (activa + start_date más reciente).
     */
    private function demoProduction(): ?Production
    {
        return \App\Support\CurrentProduction::get();
    }

    public function index(Request $request)
    {
        // Buscador: filtra por nombre/apellidos/email. Con 92+ (o 300+) usuarios, paginar
        // fila por fila es inviable; esto deja saltar directo a la persona. Server-side
        // (GET ?q=...), misma convención que el resto de la app.
        $q = trim((string) $request->query('q', ''));

        // Eager-load de roles spatie para evitar N+1 al pintar el rol actual de cada fila.
        $users = User::query()
            ->with('roles')
            ->where('activo', 1)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . $q . '%';
                // OR agrupado en closure (no romper el filtro `activo`).
                $query->where(function ($w) use ($like) {
                    $w->where('name', 'LIKE', $like)
                      ->orWhere('lname', 'LIKE', $like)
                      ->orWhere('lname2', 'LIKE', $like)
                      ->orWhere('email', 'LIKE', $like);
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->appends(['q' => $q]); // conserva ?q= al paginar (sin arrastrar ?partial=)

        $production = $this->demoProduction();

        // Departamento actual por usuario (user_id => department_id) desde el pivote.
        $deptByUser = [];
        if ($production) {
            $deptByUser = DB::table('production_user')
                ->where('production_id', $production->id)
                ->pluck('department_id', 'user_id')
                ->all();
        }

        $departments = Department::where('active', true)->orderBy('name')->get();
        $roles = self::ASSIGNABLE;

        // (2026-07-24 · item 7) Estado del acceso clínico por usuario, para el toggle super-admin.
        //   · $directMedicalIds  = ids con permiso `medical.view` DIRECTO (Spatie model_has_permissions).
        //   · $rolesWithMedical  = nombres de rol que HOY conceden medical.view (para avisar si el
        //     acceso ya viene por rol y el otorgamiento directo sería redundante).
        //   · $isSuperAdmin      = solo el super-admin ve/usa el toggle.
        $userMorph = (new User)->getMorphClass();
        $permId    = DB::table('permissions')->where('name', 'medical.view')->value('id');
        $directMedicalIds = [];
        if ($permId) {
            $directMedicalIds = DB::table('model_has_permissions')
                ->where('permission_id', $permId)
                ->where('model_type', $userMorph)
                ->whereIn('model_id', $users->pluck('id')->all())
                ->pluck('model_id')->all();
        }
        $rolesWithMedical = $permId
            ? DB::table('role_has_permissions')
                ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
                ->where('role_has_permissions.permission_id', $permId)
                ->pluck('roles.name')->all()
            : [];
        $isSuperAdmin = (bool) $request->user()->hasRole('super-admin');

        $data = compact('users', 'departments', 'roles', 'deptByUser', 'q',
            'directMedicalIds', 'rolesWithMedical', 'isSuperAdmin');

        // Buscador en vivo: el front pide SOLO la tabla (?partial=1) y reemplaza #rolesResults
        // (mismo patrón AJAX que el Crew List).
        if ($request->boolean('partial')) {
            return view('admin.partials.roles-assign-table', $data);
        }

        return view('admin.roles-assign', $data);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'role'          => ['required', 'in:' . implode(',', self::ASSIGNABLE)],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $user = User::findOrFail($id);

        // SEGURIDAD (2026-07-06): guarda de scope por departamento, igual que useredit/acountupdate.
        // Super-admin (crew.view.all-departments) pasa; roles acotados solo dentro de su depto.
        abort_unless(auth()->user()->canManageCrewMember($user), 403);

        // Un operador no puede cambiar su PROPIO rol (evita auto-escalada/lockout).
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes cambiar tu propio rol.');
        }

        $production = $this->demoProduction();

        if (!$production) {
            return back()->with('error', 'No existe la producción de la instancia (seeder ProductionDemo).');
        }

        DB::transaction(function () use ($user, $production, $data) {
            // 1) Rol global (spatie). syncRoles deja EXACTAMENTE este rol.
            $user->syncRoles([$data['role']]);

            // 2) Pivote production_user: rol contextual + departamento (+ is_lead si es HOD).
            $existing = DB::table('production_user')
                ->where('production_id', $production->id)
                ->where('user_id', $user->id)
                ->first();

            // Si el departamento cambia, el puesto previo pertenecía a otro depto → se limpia
            // (no hay selector de puesto en esta v1; se reasigna cuando lo agreguemos).
            $newDept = ($data['department_id'] ?? null) !== null ? (int) $data['department_id'] : null; // BUG-03: la clave puede faltar (validada nullable)
            $positionId = $existing->position_id ?? null;
            if (!$existing || (int) ($existing->department_id ?? 0) !== (int) ($newDept ?? 0)) {
                $positionId = null;
            }

            DB::table('production_user')->updateOrInsert(
                ['production_id' => $production->id, 'user_id' => $user->id],
                [
                    'department_id' => $newDept,
                    'position_id'   => $positionId,
                    'role'          => $data['role'],
                    'is_lead'       => $data['role'] === 'hod',
                    'created_at'    => $existing->created_at ?? now(),
                    'updated_at'    => now(),
                ]
            );
        });

        return back()->with('status', "Actualizado: {$user->name} {$user->lname} → rol «{$data['role']}».");
    }

    /**
     * (2026-07-24 · item 7) OTORGA acceso al expediente clínico (permiso DIRECTO medical.view) a una
     * persona. Solo el super-admin. Mismo patrón que los Feature Flags: nace apagado, se enciende
     * deliberadamente y queda REGISTRADO (quién y cuándo) en medical_access_grants.
     *
     * ALCANCE: quien recibe el acceso ve a TODO el crew (medical.view no está acotado por depto como
     * el HOD) — el safety-officer es figura de control, como coordinador/line-producer.
     */
    public function grantMedical(Request $request, $id)
    {
        // Otorgar acceso a datos clínicos es MÁS sensible que asignar rol → super-admin, no el
        // users.assign-role del grupo. La puerta real está aquí (no en el middleware del grupo).
        // Se usa auth() (no $request->user()) por consistencia con update() y para no depender del
        // resolver del request.
        abort_unless(auth()->user()->hasRole('super-admin'), 403);

        $user = User::findOrFail($id);

        // Idempotente: si ya lo tiene DIRECTO, no dupliques ni el permiso ni el registro.
        if (! $user->hasDirectPermission('medical.view')) {
            $user->givePermissionTo('medical.view');
            if (MedicalAccessGrant::supportsGrants()) {
                MedicalAccessGrant::create([
                    'user_id'       => $user->id,
                    'permission'    => 'medical.view',
                    'granted_by_id' => auth()->id(),
                    'granted_at'    => now(),
                    'note'          => 'Otorgado directo desde /rolescrud',
                ]);
            }
        }

        return back()->with('status', "Acceso al expediente clínico OTORGADO a {$user->name} {$user->lname}. Ve a todo el crew.");
    }

    /**
     * (2026-07-24 · item 7) REVOCA el acceso clínico DIRECTO. Solo el super-admin. NO borra la
     * bitácora: cierra los otorgamientos activos (revoked_at + revoked_by). Si además tuviera el
     * acceso por su ROL, esto NO se lo quita (revokePermissionTo solo toca el permiso directo) y se
     * avisa en el mensaje para no dar por revocado lo que sigue vigente.
     */
    public function revokeMedical(Request $request, $id)
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);

        $user = User::findOrFail($id);

        if ($user->hasDirectPermission('medical.view')) {
            $user->revokePermissionTo('medical.view');
        }

        if (MedicalAccessGrant::supportsGrants()) {
            MedicalAccessGrant::where('user_id', $user->id)
                ->where('permission', 'medical.view')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_by_id' => auth()->id(),
                    'revoked_at'    => now(),
                    'updated_at'    => now(),
                ]);
        }

        $msg = "Acceso clínico directo REVOCADO a {$user->name} {$user->lname}.";
        // hasPermissionTo (sin Gate::before) refleja rol + directo: si sigue en true, viene del ROL.
        if ($user->hasPermissionTo('medical.view')) {
            $msg .= ' OJO: aún conserva acceso por su ROL (cambia el rol si quieres retirarlo del todo).';
        }

        return back()->with('status', $msg);
    }
}
