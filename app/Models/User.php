<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Nombre canónico del rol médico. Es `medic` (inglés), NO `medico`.
     * Único lugar donde debe escribirse ese literal — ver isMedic().
     */
    const MEDIC_ROLE = 'medic';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'lname',
        'lname2',
        'borndate',
        'sex',
        'email',
        'phone',
        'zone',
        'puestodepartamento',
        'ncreditos',
        'age',
        // SEGURIDAD: 'admin' NO es mass-assignable (era escalada de privilegios — cualquiera
        // podía mandar admin=1 en un update de perfil). Se asigna SOLO explícitamente vía
        // activaradmin/desactivaradmin (rutas gated por permission:users.assign-role).
        'encuestadiaria',
        'activo',
        'imgperfil',
        'password',
        'lastwr',
        'workstation',
        'device_token',
        'daytest',
        'labn',
        // EL INFOSHEET · no-crew como usuario ÚNICO (bandera de la PERSONA; NO otorga ni gatea
        // acceso — solo distingue el tipo). El token de acceso (external_access_*) se asigna SÓLO
        // en servidor: a propósito NO es mass-assignable (token de enlace = no debe elegirse).
        'is_external',
        'crewlist_visible',
        // COVID desacoplado (2026-07-07): lastpcr/enfermo/ultimatemperatura/inline/resultpcr/tested
        // salieron del $fillable y del schema (owner-apply 2026-07-07-users-covid-cleanup.sql). Nunca
        // se leían; solo se inicializaban en el alta. DIFERIDOS (siguen vivos): daytest (rol legacy →
        // refactor UI de grupos), labn ("Jerarquía"), age (→has_badge_photo), zone (→department_name).
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'borndate' => 'date', // Añade esta línea para convertir a fecha
        // EL INFOSHEET · no-crew
        'is_external'                => 'boolean',
        'crewlist_visible'           => 'boolean',
        'external_access_used_at'    => 'datetime',
        'external_access_expires_at' => 'datetime',
    ];

    /**
     * RBAC foundation (additive): productions this user belongs to, with their
     * per-production role/department/position. Spatie roles (HasRoles) are global;
     * this pivot carries the contextual role per production.
     */
    public function productions()
    {
        return $this->belongsToMany(Production::class, 'production_user')
            ->withPivot(['department_id', 'position_id', 'role', 'is_lead'])
            ->withTimestamps();
    }

    /**
     * Registro de impresión del gafete (credencial). PRESENCIA = impreso. Reemplaza el uso
     * de la columna sin sentido `users.age`. Ver App\Models\BadgePrint.
     */
    public function badgePrint()
    {
        return $this->hasOne(BadgePrint::class, 'user_id', 'id');
    }

    /**
     * Vehículos donde este usuario (crew) es el conductor asignado (Transportación, aditivo).
     * Cierra el lado INVERSO de Vehicle::driver() para que la corrida pueda "proponer el otro"
     * al elegir uno de los dos (§2 Bloque 2). Referencia BLANDA (sin FK dura).
     */
    public function drivenVehicles()
    {
        return $this->hasMany(Vehicle::class, 'driver_user_id', 'id')
            ->where('vehicles.is_active', 1);
    }

    /**
     * IDs de departamento a los que pertenece este usuario (vía el pivote production_user).
     * Es la FUENTE DE VERDAD de "su departamento" para el scope del HOD. La etiqueta
     * desnormalizada users.puestodepartamento NO es una llave estable; el pivote sí.
     */
    public function ownDepartmentIds()
    {
        return $this->productions()
            ->wherePivotNotNull('department_id')
            ->pluck('production_user.department_id')
            ->unique()
            ->filter()
            ->values();
    }

    /**
     * NOMBRE del departamento cuando el usuario tiene UN SOLO lente (un HOD de un depto): así el
     * menú se personaliza ("Arte", "Transpo") en vez de un genérico "Crew". Devuelve null para
     * quien ve TODOS los departamentos (line-producer/coordinator/super-admin → etiqueta genérica)
     * o para quien no tiene exactamente un departamento. No otorga accesos: solo personaliza el título.
     */
    public function soleDepartmentName(): ?string
    {
        if ($this->can('crew.view.all-departments')) {
            return null;   // ve todo → etiqueta genérica
        }
        $ids = $this->ownDepartmentIds();
        if ($ids->count() !== 1) {
            return null;
        }
        return optional(\App\Models\Department::find($ids->first()))->name;
    }

    /**
     * NOMBRE del departamento y del puesto para MOSTRAR — leídos de la FUENTE DE VERDAD (el
     * pivote production_user de la producción vigente), con fallback a la etiqueta legacy
     * (`users.zone` / `users.puestodepartamento`) SÓLO cuando no hay producción, no hay fila
     * pivote, o la fila no trae el FK (filas viejas nunca reasignadas). Antes las vistas leían
     * directo las columnas legacy, hoy sucias (p.ej. zone="Constrcción"); ahora ven el nombre
     * canónico del catálogo y sólo caen al legacy si no hay nada mejor. La ESCRITURA ya derivaba
     * ambas columnas del FK (CrewController), así que las filas nuevas quedan idénticas.
     */
    public function departmentName(): ?string
    {
        return self::departmentNameFor($this->id, $this->zone);
    }

    public function positionName(): ?string
    {
        return self::positionNameFor($this->id, $this->puestodepartamento);
    }

    /**
     * Versión ESTÁTICA por id — para las vistas que iteran filas CRUDAS de `DB::table` (stdClass:
     * el buscador `SearchController` y las tarjetas médicas), donde NO hay un modelo con métodos,
     * sólo columnas. Recibe el id del usuario y el valor legacy como fallback. La versión de
     * instancia de arriba delega aquí, así que ambos caminos comparten el mismo caché por-petición.
     */
    public static function departmentNameFor($userId, ?string $legacy = null): ?string
    {
        $id = self::currentPivotValueFor((int) $userId, 'department_id');
        if ($id) {
            $name = self::catalogName('departments', (int) $id);
            if ($name !== null && $name !== '') {
                return $name;
            }
        }
        return ($legacy !== null && $legacy !== '') ? $legacy : null; // legacy (puede venir sucio)
    }

    public static function positionNameFor($userId, ?string $legacy = null): ?string
    {
        $id = self::currentPivotValueFor((int) $userId, 'position_id');
        if ($id) {
            $name = self::catalogName('positions', (int) $id);
            if ($name !== null && $name !== '') {
                return $name;
            }
        }
        return ($legacy !== null && $legacy !== '') ? $legacy : null; // legacy (fallback ya era depto)
    }

    /**
     * NOMBRE CORTO de un integrante de crew: 1ª palabra del `name` + 1er apellido (`lname`).
     * P.ej. "Hugo Jacobo" + "Baus" → "Hugo Baus". Como `name`/`lname` están poblados en el 100%
     * del crew, siempre da un resultado usable. NUNCA inventa: usa lo que hay. Si por alguna razón
     * no hay apellido, cae al `name` tal cual.
     *
     * Acepta un modelo Eloquent o una fila cruda (stdClass): la lista itera `DB::table('users')`.
     */
    public static function shortName($user): string
    {
        $name  = trim((string) ($user->name ?? ''));
        $first = $name === '' ? '' : preg_split('/\s+/', $name)[0];
        $short = trim($first . ' ' . trim((string) ($user->lname ?? '')));

        return $short !== '' ? $short : $name;
    }

    /**
     * NOMBRE A MOSTRAR de un integrante de crew. Fuente única para las superficies que enseñan
     * "cómo se llama" esta persona SIN un campo de crédito aparte (Crew List, gafete, organigrama
     * del PAE…). Regla (owner 2026-08-07):
     *   1) el **Nombre en Créditos** (`ncreditos`) cuando existe DE VERDAD —tiene al menos una
     *      letra, para ignorar basura de semilla tipo "0" o "-"—;
     *   2) si no, el **nombre corto** ([[shortName]]).
     *
     * ⚠ En superficies que YA muestran el crédito por separado (p. ej. la lista de gafetes lo pone
     * de subtítulo) usar [[shortName]] para el nombre PRINCIPAL, no este método, o se duplicaría.
     *
     * NO usar en expedientes clínicos ni documentos sellados: ahí va el nombre COMPLETO/legal (o
     * no va ningún nombre). Solo lee `ncreditos`, `name`, `lname`.
     */
    public static function displayName($user): string
    {
        $cred = trim((string) ($user->ncreditos ?? ''));
        if ($cred !== '' && preg_match('/\p{L}/u', $cred)) {
            return $cred;
        }

        return self::shortName($user);
    }

    /**
     * Nombre para la MARCA DE AGUA (y cualquier crédito COMPACTO): el Nombre en Créditos si existe
     * de verdad; si no, **PRIMER nombre + PRIMER apellido** (regla owner 2026-08-24 — el nombre
     * completo "se ve largo" en la marca de agua diagonal). Difiere de [[shortName]], que arrastra el
     * apellido COMPLETO ("Mariana Arismendi Castro" vs. aquí "Mariana Arismendi"). Solo lee
     * `ncreditos`, `name`, `lname`.
     */
    public static function creditShortName($user): string
    {
        $cred = trim((string) ($user->ncreditos ?? ''));
        if ($cred !== '' && preg_match('/\p{L}/u', $cred)) {
            return $cred;
        }

        $name  = trim((string) ($user->name ?? ''));
        $lname = trim((string) ($user->lname ?? ''));
        $first = $name  === '' ? '' : preg_split('/\s+/', $name)[0];
        $ap1   = $lname === '' ? '' : preg_split('/\s+/', $lname)[0];
        $short = trim($first . ' ' . $ap1);

        return $short !== '' ? $short : self::shortName($user);
    }

    /**
     * Valor de una columna del pivote de la producción VIGENTE para un usuario (por id), o null.
     * Espeja la lectura del write-path (CrewController::useredit). Cachea TODO el pivote de la
     * producción una sola vez por petición → evita el N+1 cuando una lista (usuarioscrud,
     * resultados de búsqueda, gafetes masivos) pide el depto/puesto de muchos usuarios.
     */
    private static function currentPivotValueFor(int $userId, string $col)
    {
        $productionId = \App\Support\CurrentProduction::id();
        if (! $productionId) {
            return null;
        }

        static $pivotByProduction = [];
        if (! array_key_exists($productionId, $pivotByProduction)) {
            $pivotByProduction[$productionId] = DB::table('production_user')
                ->where('production_id', $productionId)
                ->get(['user_id', 'department_id', 'position_id'])
                ->keyBy('user_id');
        }

        $row = $pivotByProduction[$productionId]->get($userId);
        return $row->{$col} ?? null;
    }

    /**
     * Nombre de un renglón de catálogo por id, cacheando el catálogo completo una vez por
     * petición (departments ~21 filas, positions ~231). Evita un find() por usuario en listas.
     */
    private static function catalogName(string $table, int $id): ?string
    {
        static $catalogs = [];
        if (! isset($catalogs[$table])) {
            $catalogs[$table] = DB::table($table)->pluck('name', 'id')->all();
        }
        return $catalogs[$table][$id] ?? null;
    }

    /**
     * Scope de departamento — UNA sola fuente de verdad (la usan usuarioscrud y
     * SearchController). Restringe la consulta de usuarios a los VISIBLES para $viewer:
     *   - viewer con `crew.view.all-departments`            → sin restricción (ve a todos).
     *   - viewer sin ese permiso, con depto(s) conocido(s)  → solo usuarios que comparten
     *     alguno de SUS departamentos (vía production_user).
     *   - viewer sin ese permiso y SIN depto determinable   → NO devuelve nada (err
     *     restrictivo: nunca degrada a "ver todo" por accidente).
     *
     * Acepta tanto un Eloquent\Builder (User::query()) como un Query\Builder
     * (DB::table('users')): whereExists/whereRaw/whereColumn existen en ambos y la tabla
     * base es `users` en los dos casos. Devuelve el mismo $query (encadenable).
     */
    public static function applyDepartmentScope($query, self $viewer)
    {
        if ($viewer->can('crew.view.all-departments')) {
            return $query;
        }

        $ownDeptIds = $viewer->ownDepartmentIds();

        if ($ownDeptIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($sub) use ($ownDeptIds) {
            $sub->select(DB::raw(1))
                ->from('production_user')
                ->whereColumn('production_user.user_id', 'users.id')
                ->whereIn('production_user.department_id', $ownDeptIds->all());
        });
    }

    /**
     * ORDEN JERÁRQUICO del listado de crew (2026-08-21): departamento por el `sort_order`
     * del catálogo y, dentro de cada uno, el puesto por su `sort_order` (HOD primero) — el
     * MISMO criterio del documento de export (CrewRosterBuilder), ahora también en las
     * pantallas (antes: users.id DESC). Se resuelve con subconsultas ESCALARES en el ORDER
     * BY (no JOINs) para NO multiplicar filas ni alterar el conteo de la paginación. Fuente
     * de verdad: el pivote production_user de la producción vigente; fallback a las etiquetas
     * legacy zone/puestodepartamento sólo cuando no hay fila pivote (igual que CrewRosterBuilder).
     * Sin orden resoluble → al final (999999), desempate por apellido y luego id.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyRosterOrder($query, ?int $productionId = null)
    {
        $pid = $productionId ?? \App\Support\CurrentProduction::id();
        $prodFilterPivot = $pid ? ' AND pu.production_id = ' . (int) $pid : '';   // (int) → sin inyección
        $prodFilterPivot2 = $pid ? ' AND pu2.production_id = ' . (int) $pid : '';

        $deptSort = '(COALESCE('
            . '(SELECT d.sort_order FROM production_user pu JOIN departments d ON d.id = pu.department_id'
            . ' WHERE pu.user_id = users.id' . $prodFilterPivot . ' LIMIT 1),'
            . '(SELECT d2.sort_order FROM departments d2 WHERE d2.name = users.zone LIMIT 1),'
            . '999999))';

        $posSort = '(COALESCE('
            . '(SELECT p.sort_order FROM production_user pu2 JOIN positions p ON p.id = pu2.position_id'
            . ' WHERE pu2.user_id = users.id' . $prodFilterPivot2 . ' LIMIT 1),'
            . '(SELECT p2.sort_order FROM positions p2 WHERE p2.name = users.puestodepartamento LIMIT 1),'
            . '999999))';

        return $query
            ->orderByRaw($deptSort . ' asc')
            ->orderByRaw($posSort . ' asc')
            ->orderBy('users.lname', 'asc')
            ->orderBy('users.id', 'desc');
    }

    /**
     * HERMANO de applyDepartmentScope para la BASE ÚNICA DE QUIEN COBRA. La original NO se
     * toca (tiene 8 call sites: CrewList, bitácora médica, gafete, export…). Este usa el MISMO
     * whereExists sobre production_user, pero correlacionado con la PARTE CONTRATANTE en vez de
     * users.id: conserva solo las filas cuyo contratante comparte departamento con el viewer.
     * "Quién contrata es quien ve" (Paso 4) se implementa enchufando esto en los listados de
     * payees/contratos; aquí solo queda la capacidad del modelo, sin call sites todavía.
     *
     * OJO: el bypass reusa `crew.view.all-departments` por ahora; el Paso 4 decide el permiso
     * apagable definitivo (p.ej. exclusividad de ambulancias para producción/safety).
     *
     * @param  string  $contractingUserIdColumn  columna a correlacionar (default: la del contrato).
     */
    public static function applyContractingScope($query, self $viewer, string $contractingUserIdColumn = 'payee_contracts.contracted_by_user_id')
    {
        if ($viewer->can('crew.view.all-departments')) {
            return $query;
        }

        $ownDeptIds = $viewer->ownDepartmentIds();

        if ($ownDeptIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($sub) use ($ownDeptIds, $contractingUserIdColumn) {
            $sub->select(DB::raw(1))
                ->from('production_user')
                ->whereColumn('production_user.user_id', $contractingUserIdColumn)
                ->whereIn('production_user.department_id', $ownDeptIds->all());
        });
    }

    /**
     * ¿Puede ESTE usuario (viewer) gestionar a $target según el scope de departamento?
     * Es el equivalente "de un solo objetivo" de applyDepartmentScope() (que opera sobre
     * una consulta). MISMO criterio → una sola regla de negocio para listar Y para mutar:
     *   - viewer con `crew.view.all-departments`  → true (super-admin/coordinador/etc.).
     *   - viewer sin ese permiso                  → true solo si comparten al menos un
     *     departamento (intersección de pivotes production_user); false en cualquier otro
     *     caso (incluido viewer sin depto determinable → err restrictivo).
     *
     * Cierra H2 (IDOR de scope) en las acciones admin sobre {id}: la vista solo enlaza a
     * usuarios visibles, pero esto bloquea además los {id} manipulados a mano en la URL.
     */
    public function canManageCrewMember(self $target): bool
    {
        if ($this->can('crew.view.all-departments')) {
            return true;
        }

        return $this->ownDepartmentIds()
            ->intersect($target->ownDepartmentIds())
            ->isNotEmpty();
    }

    /**
     * ¿Es médico? — FUENTE AUTORITATIVA ÚNICA (PASO A, 2026-07-19).
     *
     * "Ser médico" = tener el rol Spatie `medic`. Punto. Todo lo demás que antes
     * parecía marcar identidad médica queda DEGRADADO a dato descriptivo:
     *
     *   - Puestos 176 "Doctor en Set" / 177 "Doctor de Construcción" (production_user.
     *     position_id): describen QUÉ hace en la producción, no acreditan identidad
     *     médica. Son el criterio con el que se SEMBRÓ el rol una vez
     *     (MedicRolePermissionsSeeder), no el que se consulta en caliente.
     *   - users.puestodepartamento / users.zone: etiquetas legacy de texto libre.
     *   - users.daytest = 2: marcador MUERTO. Su botón ("Convertir a Médico"), su ruta
     *     (/putmed) y su método (CrewStatusController@putgb) fueron eliminados, y el
     *     dato se neutralizó con database/owner-apply/2026-07-19-daytest-neutralizar-valor-2.sql.
     *     Nunca marcó a un médico: los 13 usuarios que lo tenían eran de producción.
     *
     * OJO CON EL NOMBRE: el rol es `medic` (inglés), NO `medico`. Ese typo vivió en
     * InjuryReportPolicy y dejó la rama muerta en silencio (Spatie devuelve false ante
     * un rol inexistente, no lanza). Por eso la comprobación vive AQUÍ y en un solo
     * sitio: para que no vuelva a escribirse el literal a mano.
     *
     * Defensivo: si el rol aún no existe en la BD destino, devuelve false sin reventar.
     *
     * ⚠ ALCANCE: isMedic() = MÉDICO. Gatea el accidente completo (InjuryReportPolicy),
     * los addendums de accidente (AddendumPolicy) y el anexo al EXPEDIENTE CLÍNICO DEL CREW
     * (HealthRecordAddendumController). (2026-07-25) Retirados los roles beta, isClinician()
     * DELEGA en este método —"clínico" y "médico" volvieron a ser lo mismo—; se conserva
     * isClinician() sólo como alias legible ("autor clínico que registra una consulta").
     */
    public function isMedic(): bool
    {
        try {
            return $this->hasRole(self::MEDIC_ROLE);
        } catch (\Throwable $e) {
            // Rol inexistente / registrar de Spatie no disponible → no es médico.
            return false;
        }
    }

    /**
     * ¿Es AUTOR CLÍNICO? — históricamente `medic` O `medicbeta`.
     *
     * (2026-07-25) Retirados los roles beta, "clínico" == "médico": este método DELEGA en
     * isMedic() para que el rol `medic` siga siendo la ÚNICA fuente del literal. Se conserva
     * como alias por intención semántica —los sitios que preguntan "¿puede registrar una
     * consulta?" siguen legibles y no se tocó ni un gate médico en el barrido—; hoy es idéntico
     * a isMedic() e inlinearlo a isMedic() en cada sitio es un follow-up seguro pero opcional.
     */
    public function isClinician(): bool
    {
        return $this->isMedic();
    }

    /**
     * (2026-07-24) PERMISOS QUE ABREN EL PANEL — unión de los gates de PRIMER NIVEL del sidebar.
     *
     * BUG QUE ARREGLA: `layouts/app` decidía pintar el sidebar con
     * `auth()->user()->admin || can('users.view')`. Ese OR nació como puente de la migración a
     * RBAC (el flag legacy `admin`, o el permiso que lo "espejaba"), y funcionó mientras todos
     * los roles de oficina tenían `users.view`. El rol `medic` NO lo tiene —un médico no
     * administra el directorio de crew— así que un médico con `admin=0` se quedaba SIN sidebar:
     * pasaba todos los gates del menú médico y jamás llegaba a verlo. Se veía como "no tiene
     * permisos" cuando en realidad los tenía todos.
     *
     * La lista NO incluye `injury.create` ni `hazards.create` a propósito: las tiene el rol
     * `crew`, y esto decide quién ve el PANEL de trabajo, no quién puede levantar un reporte
     * desde su portal. Al agregar una sección al sidebar, agrega aquí su permiso.
     */
    const PANEL_PERMISSIONS = [
        'users.view', 'users.create', 'users.assign-role', 'roles.manage-permissions',
        'locations.view', 'locations.create',
        'dsr.view', 'dsr.create',
        'hazards.view', 'hazards.manage',
        'injury.view', 'injury.manage',
        'sds.view',
        'medical.view', 'medical.materials',
        'catalogs.view', 'standards.view', 'hazardevents.view',
        'reports.view', 'badge.design', 'settings.manage',
    ];

    /**
     * ¿Este usuario ve el panel (sidebar) o sólo su portal de autoservicio?
     *
     * Se conserva el flag legacy `admin` en el OR mientras existan instancias que aún dependan
     * de él; el criterio real es tener AL MENOS UN permiso de panel.
     *
     * @return bool
     */
    public function canSeePanel(): bool
    {
        try {
            if (! empty($this->admin)) {
                return true;
            }
            foreach (self::PANEL_PERMISSIONS as $p) {
                if ($this->can($p)) {
                    return true;
                }
            }
            // Consulta de contratos: contabilidad / oficina de producción / producción (los que "ven
            // todo") entran al panel aunque no tengan otro permiso. Los HOD de cada depto ya entran por
            // sus permisos y ven lo suyo. Regla en App\Support\ContractVisibility.
            if (\App\Support\ContractVisibility::seesAll($this)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Permiso inexistente en una instancia sin sembrar → no reventar el layout.
        }
        return false;
    }

    /**
     * Nombre completo (nombre + los dos apellidos), normalizado.
     *
     * Existía copiado a mano en varios sitios (p.ej. InjuryReportController:541). Se
     * centraliza aquí porque el PASO B lo usa para el COTEJO ANTISUPLANTACIÓN contra el
     * nombre que devuelve el registro de la SEP: si cada llamador arma el nombre a su
     * manera, el cotejo compara cosas distintas según desde dónde se invoque.
     *
     * `trim` + colapso de espacios: `lname2` suele venir vacío y dejaría un espacio doble
     * que rompería la comparación por palabras.
     */
    public function fullName(): string
    {
        $parts = trim($this->name . ' ' . $this->lname . ' ' . $this->lname2);

        return preg_replace('/\s+/u', ' ', $parts);
    }

    /**
     * Cédula profesional (PASO B). Relación 1:1 — UNIQUE(user_id) en la tabla.
     *
     * SOLO tiene sentido para usuarios con rol `medic`; la invariante la sostiene
     * MedicCredentialController, no el esquema (la BD no sabe de roles de Spatie).
     * Para un no-médico esto devuelve null y toda la UI del módulo desaparece.
     *
     * OJO al usarla en listados: es una consulta por fila. Carga con ->with('medicCredential')
     * o resuelve un mapa aparte (ver cmedicController::historialWR).
     */
    public function medicCredential()
    {
        return $this->hasOne(MedicCredential::class, 'user_id');
    }
}
