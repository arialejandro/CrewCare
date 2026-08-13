<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Mail;

/**
 * CrewController — SEGUNDO CORTE del God Object AdminController (strangler, 2026-06-28).
 *
 * ESCRITURAS de crew. Por ahora: ALTA de miembro (formulario `adduser` + persistencia
 * `newuser`). Movimiento PURO desde AdminController: misma lógica, mismas vistas, mismos
 * nombres de método y de ruta → cero cambio de comportamiento (verificable).
 *
 * Estas dos acciones YA eran código moderno (no legacy): resuelven contra el catálogo real
 * `departments`/`positions`, escriben el pivote `production_user`, asignan rol spatie `crew`
 * y acotan el alta al departamento del HOD (#2b) cuando no tiene `crew.view.all-departments`.
 * Gateadas en routes/web.php con `permission:users.create`.
 *
 * El perfil de crew (useredit/acountupdate, paso #4) se sumará aquí — cierra H2 (scope) y
 * H4 (mass-assignment) con whitelist validada; es el corte de MÁS valor de seguridad.
 */
class CrewController extends Controller
{
    /**
     * (2026-07-24) Roles asignables desde el ALTA. MISMA lista blanca que RoleAssignmentController,
     * y por el mismo motivo: `super-admin` se excluye a propósito — es god-mode y se concede sólo
     * por seeder o a mano, nunca desde una pantalla (evita escaladas).
     *
     * Sólo la respeta quien tenga `users.assign-role`; para el resto el alta sigue creando `crew`.
     */
    private const ASSIGNABLE_ROLES = [
        'line-producer', 'coordinator', 'hod', 'medic', 'safety-officer', 'crew', 'auditor',
    ];

    public function adduser()
    {
        $viewer = auth()->user();

        // HOD (sin all-departments): el alta queda FIJA a su departamento, o BLOQUEADA si aún
        // no tiene un departamento asignado (no se le muestra la lista completa → evita captura
        // en el departamento equivocado). Otros roles eligen del catálogo.
        $restricted = ! $viewer->can('crew.view.all-departments');
        $lockedDeptId = null;
        $lockedDept = null;
        if ($restricted) {
            $lockedDeptId = $viewer->ownDepartmentIds()->first();
            $lockedDept = $lockedDeptId ? optional(\App\Models\Department::find($lockedDeptId))->name : null;
        }

        // Catálogo REAL: departamentos activos + puestos del catálogo global (production_id null).
        // Si el alta está acotada (HOD), solo se cargan ese depto y sus puestos.
        $departments = \App\Models\Department::where('active', 1)
            ->when($lockedDeptId, function ($q) use ($lockedDeptId) { $q->where('id', $lockedDeptId); })
            ->orderBy('name')->get(['id', 'name']);

        $positions = \App\Models\Position::whereNull('production_id')->where('active', 1)
            ->when($lockedDeptId, function ($q) use ($lockedDeptId) { $q->where('department_id', $lockedDeptId); })
            ->orderBy('name')->get(['id', 'name', 'department_id']);

        // (2026-07-24) Selector de rol: sólo se pinta a quien pueda asignar roles. Quien no,
        // no ve el campo Y aunque lo mande por POST el store lo ignora (ver newuser()).
        $assignableRoles = auth()->user()->can('users.assign-role') ? self::ASSIGNABLE_ROLES : [];

        return view('admin.newuser', compact('lockedDept', 'lockedDeptId', 'restricted', 'departments', 'positions', 'assignableRoles'));
    }

    public function newuser(Request $request)
    {
        // VALIDACIÓN (2026-06-28): antes el alta NO validaba nada (asumía datos correctos) → se
        // podían crear usuarios con email inválido/duplicado o password débil. Whitelist alineada
        // al formulario admin.newuser (que ya trae password_confirmation y repoblado con old()).
        // `unique:users,email` evita altas duplicadas; `min:8|confirmed` da fuerza mínima de password.
        $request->validate([
            'name'          => 'required|string|max:255',
            'lname'         => 'required|string|max:255',
            'lname2'        => 'nullable|string|max:255',
            'ncreditos'     => 'required|string|max:255',
            'borndate'      => 'required|date|before:today',
            'sex'           => 'nullable|in:M,F',
            'labn'          => 'required|integer', // labn es orden numerico (col int) → validar entero evita el 500 con texto (BUG-02)
            'phone'         => 'required|string|max:50',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
            'department_id' => 'nullable|integer',
            'position_id'   => 'nullable|integer',
            // (2026-07-24) Rol del nuevo miembro. Se valida contra la MISMA lista blanca que
            // /rolescrud (super-admin excluido a propósito: god-mode nunca desde una pantalla).
            // Que llegue el campo NO basta: abajo se exige `users.assign-role` para respetarlo.
            'role'          => 'nullable|in:' . implode(',', self::ASSIGNABLE_ROLES),
        ]);

        $viewer = auth()->user();

        // --- Departamento + puesto del nuevo miembro (CATÁLOGO real + #2b acotado) ---
        // HOD (sin all-departments): se FUERZA su propio departamento (no se confía en el form).
        // Otros roles: usan el department_id elegido del catálogo (tabla `departments`).
        if (! $viewer->can('crew.view.all-departments')) {
            $departmentId = $viewer->ownDepartmentIds()->first();
            if (! $departmentId) {
                return back()
                    ->with('error', 'No tienes un departamento asignado; pide a un coordinador que te asigne uno antes de dar de alta crew.')
                    ->withInput();
            }
        } else {
            $departmentId = (int) $request->department_id;
        }

        // Resolver contra el CATÁLOGO (no se confía en el form): el departamento debe existir;
        // el puesto (opcional) debe pertenecer a ESE departamento.
        $dept = \App\Models\Department::find($departmentId);
        if (! $dept) {
            return back()->with('error', 'Selecciona un departamento válido.')->withInput();
        }
        $position = null;
        if ($request->filled('position_id')) {
            $position = \App\Models\Position::whereNull('production_id')
                ->where('active', 1)
                ->where('department_id', $dept->id)
                ->find($request->position_id);
        }

        // Etiquetas legacy consistentes (varias vistas aún leen estas columnas):
        //   zone = nombre del depto (compat con profile); puestodepartamento = SOLO el puesto.
        //
        // PASO A (2026-07-19), decisión del owner: antes era "Depto-Puesto". Se cambió a solo el
        // puesto porque esta columna es la que se IMPRIME en el gafete (admin/badge/_card.blade.php)
        // y una cadena como "Producción-Coordinador de Producción" desborda el diseño. Además 88
        // de 92 filas ya guardaban solo el puesto ("Scouter"), así que este formato es el que
        // coincide con los datos reales. Misma regla en acountupdate() → alta y edición simétricas.
        $deptName = $dept->name;
        $puestodepartamento = $position ? $position->name : $deptName;

        $user = User::create([
            'name' => $request->name,
            'lname' => $request->lname,
            'lname2'=> $request->lname2,
            'email' => $request->email,
            'phone' => $request->phone,
            'sex' => $request->sex,
            'labn' => $request->labn,
            'zone' => $deptName,
            'ncreditos' => $request->ncreditos,
            'borndate' => $request->borndate,
            // COVID desacoplado (2026-07-07): ya NO se inicializan lastpcr/enfermo/inline/tested/
            // resultpcr — columnas eliminadas del schema (nunca se leían). `lastwr` SÍ se conserva:
            // es el timing del cuestionario clínico (expediente), no COVID.
            'lastwr'  => Carbon::now(),
            'puestodepartamento' => $puestodepartamento,
            'password' => Hash::make($request->password)
        ]);

        // --- Rol base + pivote production_user ---
        // Cierra DOS huecos del alta vieja: el nuevo usuario no recibía rol spatie, y
        // newuser NUNCA escribía el pivote (por eso no aparecía en listas acotadas por depto).
        //
        // (2026-07-24) EL PUESTO NO OTORGA PRIVILEGIOS. Antes esto era `syncRoles(['crew'])` fijo,
        // así que dar de alta a alguien como "Doctor en Set" lo dejaba con rol `crew` y sin acceso
        // al panel médico: el puesto es una etiqueta del catálogo y el rol Spatie es lo que abre
        // puertas, y nada los conectaba. Ahora el rol se ELIGE en el alta — pero sólo lo respeta
        // quien puede asignar roles; para los demás sigue naciendo `crew`.
        //
        // POR QUÉ NO SE DERIVA DEL PUESTO: el catálogo de puestos lo edita cualquiera con
        // `catalogs.manage`. Si "Doctor en Set" concediera acceso clínico por su nombre, renombrar
        // un puesto —o crear uno nuevo parecido— repartiría expedientes médicos en silencio.
        $rolAsignado = 'crew';
        if ($viewer->can('users.assign-role') && $request->filled('role')
            && in_array($request->input('role'), self::ASSIGNABLE_ROLES, true)) {
            $rolAsignado = $request->input('role');
        }
        $user->syncRoles([$rolAsignado]);
        $production = \App\Support\CurrentProduction::get();
        if ($production) {
            DB::table('production_user')->updateOrInsert(
                ['production_id' => $production->id, 'user_id' => $user->id],
                [
                    'department_id' => $dept->id,
                    'position_id'   => $position ? $position->id : null,
                    // El pivote refleja el MISMO rol que Spatie (si divergen, el alcance por
                    // departamento y el rol efectivo cuentan historias distintas). is_lead sigue
                    // atado al HOD, igual que en /rolescrud.
                    'role'          => $rolAsignado,
                    'is_lead'       => $rolAsignado === 'hod',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]
            );
        }

        // (PASO 3 · quien cobra) DISPARA LA INVITACIÓN AL INTAKE: link firmado y expirable para que
        // la persona llene ella misma sus datos fiscales y documentos (solo ella tiene su CLABE/INE
        // y su domicilio). Se comparte por el mismo canal de llamados (correo del alta / WhatsApp);
        // el alta sigue CORTA (identidad/rol/depto/contacto), no se vuelve un asistente largo.
        $intakeUrl = \App\Http\Controllers\IntakeController::invitationUrl($user);

        // Correo de bienvenida. El alta NO se rompe si el correo falla, pero YA NO falla en silencio.
        //
        // BUG que esto corrige (2026-07-25): este bloque pasaba `password` (que la plantilla
        // welcomeuser NI usa) y NO pasaba `resetUrl` (que la plantilla SÍ exige, welcomeuser:132).
        // Resultado: cada alta lanzaba "Undefined variable $resetUrl" al renderizar, el catch se lo
        // tragaba, y el correo de bienvenida NUNCA salía — sólo funcionaba el reenvío por Artisan.
        // Ahora se genera el ENLACE de restablecimiento igual que crew:welcome-resend (nunca se
        // envía contraseña en claro), y si el envío falla se DEJA RASTRO: log con contexto + aviso
        // visible a quien dio de alta (un envío que falla callado es peor que uno que falla fuerte).
        $mailWarning = null;
        try {
            $token = Password::broker()->createToken($user);
            $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $user->email], false));
            $subject = 'Bienvenido a CrewCare';
            $data = [
                'nombre'    => $user->name,
                'email'     => $user->email,
                'resetUrl'  => $resetUrl,
                'intakeUrl' => $intakeUrl, // invitación al intake (la plantilla puede incluirla)
            ];
            $for = $user->email;
            Mail::send('correos.welcomeuser', $data, function ($msj) use ($subject, $for) {
                $msj->from('noreply@crewcare.mx', 'CrewCare');
                $msj->subject($subject);
                $msj->to($for);
            });
        } catch (\Throwable $e) {
            // El alta ya quedó; el correo es secundario, pero el fallo se REGISTRA y se AVISA.
            Log::error('Alta de crew: falló el correo de bienvenida', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
            $mailWarning = 'El miembro se dio de alta, pero el correo de bienvenida NO se pudo enviar. '
                .'Reenvíalo desde consola con:  php artisan crew:welcome-resend '.$user->id;
        }

        $redirect = redirect('/adduser')
            ->with('status', 'Miembro de crew dado de alta: '.$user->name.' '.$user->lname.'.')
            ->with('intake_url', $intakeUrl); // el coordinador puede compartir la invitación (WhatsApp)
        if ($mailWarning) {
            $redirect->with('error', $mailWarning);
        }
        return $redirect;
    }

    /**
     * PASO #4 (2026-06-28) — perfil/edición de crew, extraído de AdminController y endurecido.
     * Form de edición. Cambios vs. el original (que hacía solo `User::find($id)` sin guarda):
     *   - findOrFail($id): 404 limpio si el id no existe (antes: null-deref latente).
     *   - Guarda de scope (H2): un HOD solo abre la ficha de crew de SU departamento; un {id}
     *     manipulado a mano (fuera de scope) recibe 403 en vez de exponer datos de otra área.
     */
    public function useredit($id)
    {
        $users = User::findOrFail($id);

        abort_unless(auth()->user()->canManageCrewMember($users), 403);

        // PASO A (2026-07-19) — SIMETRÍA CON EL ALTA. Antes esta pantalla editaba el puesto
        // con un <input type=text> libre y el departamento con un <select> de 21 opciones
        // HARDCODEADAS que ni siquiera coincidían con el catálogo real (6 no existían como
        // departments.name — "Oficina Producción" vs "Oficina de Producción", "Útileria" vs
        // "Utilería"… — y faltaban 23 departamentos). Además NUNCA escribía production_user,
        // así que editar a un usuario NO cambiaba su puesto real: solo reescribía etiquetas.
        // Ahora usa el MISMO catálogo que adduser() y escribe el pivote.
        //
        // NO se replica el `when($lockedDeptId, ...)` del alta: /useredit está gateada por
        // `permission:users.update`, que solo tienen super-admin, line-producer y coordinator
        // — los tres con crew.view.all-departments. Acotar aquí sería código muerto.
        $departments = \App\Models\Department::where('active', 1)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'sort_order']);

        $positions = \App\Models\Position::whereNull('production_id')->where('active', 1)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'department_id', 'sort_order']);

        // Selección actual: la FUENTE DE VERDAD es el pivote. users.zone / users.puestodepartamento
        // son etiquetas legacy desnormalizadas y hoy están sucias (hay zones con errata como
        // "Constrcción" o "Licaciones" que no existen en el catálogo), así que no sirven para
        // preseleccionar. Efecto lateral deseado: abrir y guardar una ficha con zone huérfana la
        // reescribe al nombre canónico del catálogo.
        $currentDeptId = null;
        $currentPosId  = null;
        $production = \App\Support\CurrentProduction::get();
        if ($production) {
            $pivot = DB::table('production_user')
                ->where('production_id', $production->id)
                ->where('user_id', $users->id)
                ->first();
            if ($pivot) {
                $currentDeptId = $pivot->department_id;
                $currentPosId  = $pivot->position_id;
            }
        }

        // ¿Está editando su propia ficha? La vista oculta los selects en ese caso (ver
        // acountupdate: un operador no reasigna su propio departamento).
        $isSelf = ((int) $users->id === (int) auth()->id());

        // PASO B (2026-07-19) — CÉDULA PROFESIONAL. Solo se resuelve para médicos: para el
        // resto del crew el bloque no existe. Va detrás de supportsCredentials() (memo de
        // Schema::hasTable) para que la pantalla siga funcionando sin el SQL aplicado.
        $credential = null;
        if ($users->isClinician() && \App\Models\MedicCredential::supportsCredentials()) {
            $credential = \App\Models\MedicCredential::where('user_id', $users->id)->first();
        }
        $canManageCredential = auth()->user()->can('medic.credential.manage');

        return view("admin/useredit", compact(
            'users', 'departments', 'positions', 'currentDeptId', 'currentPosId', 'isSelf',
            'credential', 'canManageCredential'
        ));
    }

    /**
     * Persistencia del perfil. Cierra DOS hallazgos del método viejo:
     *   - H2 (scope): misma guarda canManageCrewMember que useredit().
     *   - H4 (mass-assignment): el original hacía `$request->except('password')` → CUALQUIER
     *     campo posteado entraba a fill() (admin ya no es fillable, pero `activo`/`encuestadiaria`
     *     SÍ lo son → un POST manipulado podía auto-activarse o silenciar el cuestionario).
     *     Ahora se valida una WHITELIST explícita = exactamente los campos del formulario
     *     useredit.blade.php; nada fuera de esa lista llega al modelo. `password` se maneja
     *     aparte (nunca por mass-assignment) y solo se cambia si viene con valor.
     *
     * Reglas permisivas (nullable) a propósito: el objetivo de seguridad es ACOTAR qué columnas
     * se escriben, no rechazar capturas que antes pasaban; no se endurecen formatos legacy.
     * EXCEPCIÓN (2026-07-25): el email SÍ lleva `unique` con la salvedad del PROPIO id
     * (`Rule::unique(...)->ignore`), así editar la ficha sin cambiar el correo no choca consigo
     * misma, pero editarla a un correo YA existente da un error de validación amable en vez del
     * QueryException 500 crudo que reventaba contra el índice único de la BD.
     */
    public function acountupdate(Request $request, $id)
    {
        $user = User::findOrFail($id);

        abort_unless(auth()->user()->canManageCrewMember($user), 403);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'lname'              => 'nullable|string|max:255',
            'lname2'             => 'nullable|string|max:255',
            'ncreditos'          => 'nullable',
            'phone'              => 'nullable|string|max:50',
            'borndate'           => 'nullable|date',
            'labn'               => 'nullable|integer', // col int → evita 500 con texto (BUG-02)
            'email'              => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'sex'                => 'nullable|string|max:10',
            // PASO A (2026-07-19): `zone` y `puestodepartamento` SALEN de la whitelist. Dejan de
            // ser texto libre posteado y pasan a DERIVARSE del catálogo, igual que en el alta.
            // Si siguieran aquí, un POST manipulado podría ganarle a la derivación.
            'department_id'      => 'nullable|integer',
            'position_id'        => 'nullable|integer',
        ]);

        // --- Guarda de auto-edición (espeja RoleAssignmentController.php:102-104) ---
        // Un operador no reasigna su propio departamento/puesto: reescribiría su propio scope.
        // Sí puede editar el resto de SU ficha (nombre, teléfono, contraseña…).
        // EXCEPCIÓN: el super-admin (el owner) SÍ puede asignarse un puesto — su scope ya es
        // total (Gate::before lo deja pasar todo), así que no hay nada que "reescribir".
        $isSelf = ((int) $user->id === (int) auth()->id());
        if ($isSelf && ! auth()->user()->hasRole('super-admin')
            && ($request->filled('department_id') || $request->filled('position_id'))) {
            return back()
                ->with('error', 'No puedes cambiar tu propio departamento o puesto; pídelo a un coordinador.')
                ->withInput();
        }

        // --- Re-resolución SERVER-SIDE contra el catálogo (espejo de newuser 95-107) ---
        // No se confía en el form: el departamento debe existir y estar activo, y el puesto
        // (opcional) debe pertenecer a ESE departamento.
        $dept = null;
        if ($request->filled('department_id')) {
            $dept = \App\Models\Department::where('active', 1)->find($request->department_id);
            if (! $dept) {
                return back()->with('error', 'Selecciona un departamento válido.')->withInput();
            }
        }
        $position = null;
        if ($dept && $request->filled('position_id')) {
            $position = \App\Models\Position::whereNull('production_id')
                ->where('active', 1)
                ->where('department_id', $dept->id)
                ->find($request->position_id);
        }

        // No son columnas de `users` → fuera del fill().
        unset($data['department_id'], $data['position_id']);

        // Etiquetas legacy derivadas del catálogo (misma regla que newuser).
        if ($dept) {
            $data['zone'] = $dept->name;
            $data['puestodepartamento'] = $position ? $position->name : $dept->name;
        }

        $user->fill($data);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // --- Pivote production_user: aquí es donde el puesto se vuelve REAL ---
        // Si no se eligió departamento, el pivote NO se toca (no se borra la asignación previa).
        if ($dept) {
            $production = \App\Support\CurrentProduction::get();
            if (! $production) {
                // El alta falla en SILENCIO en este caso (`if ($production)` sin else). Aquí se
                // avisa: el usuario ya se guardó, pero su puesto no se habría asignado.
                return redirect('/usuarioscrud')
                    ->with('error', 'Datos guardados, pero NO se pudo asignar el puesto: no existe la producción de la instancia (seeder ProductionDemo).');
            }

            $existing = DB::table('production_user')
                ->where('production_id', $production->id)
                ->where('user_id', $user->id)
                ->first();

            DB::table('production_user')->updateOrInsert(
                ['production_id' => $production->id, 'user_id' => $user->id],
                [
                    'department_id' => $dept->id,
                    'position_id'   => $position ? $position->id : null,
                    // CRÍTICO — `role` e `is_lead` se PRESERVAN, no se copian del alta.
                    // Son propiedad de RoleAssignmentController: hoy el pivote tiene 2 filas
                    // role='super-admin', 1 role='hod' y 1 is_lead=1. Escribir 'crew'/false
                    // como hace newuser() degradaría a esas personas en silencio con solo
                    // editarles el teléfono.
                    'role'          => $existing ? $existing->role : 'crew',
                    'is_lead'       => $existing ? $existing->is_lead : false,
                    'created_at'    => $existing ? $existing->created_at : now(),
                    'updated_at'    => now(),
                ]
            );
        }

        return redirect('/usuarioscrud');
    }
}
