# AUTH-RBAC-PLAN.md — Plan de fundación: roles reales + producciones

**Qué es esto:** el plan accionable de la **Fase 1 / Bloque 1** (la "inversión clave" de
[ROADMAP.md §1, §1.C](ROADMAP.md) y [RECIPE.md Bloque 1.C](RECIPE.md)): reemplazar el
`bool admin` + el campo `daytest` reutilizado como rol por un sistema de **roles/permisos
real** (`spatie/laravel-permission`) y por **entidades `Production`/`Department`** de primera
clase con **rol por producción** (pivote `production_user`).

**Alcance:** documento de **plan, no de implementación** — no se escribe código aún
(regla de [RECIPE.md](RECIPE.md): "No se ejecuta nada todavía"). Investigación **read-only**
verificada contra el código; referencias `archivo:línea`. El único archivo escrito es este.

**Documentos hermanos (no duplicar, mantener nombres/decisiones):**
[ARCHITECTURE.md](ARCHITECTURE.md) (§1 Auth), [SECURITY.md](SECURITY.md) (C2/C3, H5),
[DATABASE-SCHEMA.md](DATABASE-SCHEMA.md) (esquema real + objetivo), [ROADMAP.md](ROADMAP.md)
(§1, §3, §3.5, Fase 1), [RECIPE.md](RECIPE.md) (Bloques 1.A y 1.C).

---

## 0. TL;DR

- **Hoy NO hay RBAC.** La autorización es un solo booleano global `User.admin` (vía
  `AdminMiddleware`) + el campo `daytest` (0/1/2) reusado como "rol funcional" para ramificar
  el menú. **Sin Policies, sin Gates, sin spatie** (`AuthServiceProvider` vacío;
  `spatie/laravel-permission` no está en `composer.json`). `niveldos` es un middleware que
  **ni siquiera existe** ya como archivo y nunca estuvo registrado (era un stub).
- **La organización (depto/puesto) vive en un string desnormalizado** `users.puestodepartamento`
  ("Depto-Puesto"). Las junctions `userpuesto`/`departamentousuario` existen pero están **vacías**
  (0 filas, [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)). **No hay concepto de "producción".**
- **El objetivo** instala spatie con 7 roles, define permisos granulares por módulo, crea
  `productions`/`departments` + pivote `production_user` con **rol por producción**, migra los
  datos de prueba (`admin`/`daytest`/`puestodepartamento` → roles + pivote), y cierra de raíz
  **C2** (auto-promoción por mass-assignment) y **C3** (endpoints sin auth).

> **▶️ ESTADO (2026-06-25) — RBAC FINO del MENÚ + RUTAS hecho, MENÚ AGRUPADO POR SECCIONES hecho, UI DE ASIGNACIÓN DE ROL+DEPTO ✅ HECHA, #2 HOD SCOPING ✅ COMPLETO (a "ver acotado" + b "agregar acotado"), CATÁLOGOS ✅ MIGRADOS a `catalogs.view`/`catalogs.manage`; el grupo `admin` queda casi vacío (COVID + import/export + mail).**
> Ya sembrados **8 roles** y **42 permisos**. **HECHO (incremento 1 "roles visibles", 2026-06-24 — verificado `php -l` /
> `route:list --json` / `view:cache`; seeders OK):**
> 1. ✅ **Menú + rutas a RBAC FINO (ítem por ítem) — para los verticales del MENÚ.** El sidebar (`layouts/sidebar.blade.php`,
>    desktop + espejo móvil) se **reconstruyó a `@can('<permiso>')` por entrada** (mismo permiso que protege la ruta → sin
>    visible-pero-403; el flag `daytest` quedó **retirado del menú**). Las rutas del menú se **migraron de `admin` (binario) a
>    grupos `permission:` (spatie, cada uno con `auth`)** en `routes/web.php`: **users.view** (usuarioscrud/comcrud/idcardscrud/
>    idcard{id}), **users.create** (adduser/newuser), **users.update** (useredit/acountupdate/checkgft/uncheckgft/activarusuario/
>    activarencuesta), **users.deactivate** (desactivarusuario), **users.assign-role** (activaradmin/desactivaradmin/putga/putgb/
>    putgg), **users.assign-department** (selectpuesto), **crew.view** (searchusers/searchidcard), **medical.view** (medicocrud/
>    historial), **medical.create** (cmedica.create/store), **locations.view/create**, **hazards.view/create**, **dsr.view/create/
>    update**, **injury.view/create**. El flag `admin` se **retiró para esos verticales**. Además se **retiró el lock global
>    `$this->middleware('admin')` del constructor de `AdminController`** (forzaba `admin==1` en todos sus métodos → anulaba la
>    migración per-ruta; verificado con `route:list --json`: rutas migradas = solo `PermissionMiddleware`, catálogos/COVID = aún
>    `AdminMiddleware`). Los aliases spatie (`role`/`permission`/`role_or_permission`) ya estaban en `Kernel.php`. Ver Parte E paso (e).
> 2. ✅ **4 cuentas de prueba** — `database/seeders/TestAccountsSeeder.php` (registrado en `DatabaseSeeder`, corrido OK):
>    `admin=0` **a propósito** (prueban RBAC puro, no el bypass de admin), idempotente (`updateOrCreate` + `syncRoles`), password
>    `Test1234!`: `test.coordinador@crewcare.test` (`coordinator`), `test.hod@crewcare.test` (`hod`), `test.medico@crewcare.test`
>    (`medic`), `test.crew@crewcare.test` (`crew`). Para probar en incógnito.
> 3. ✅ **MENÚ AGRUPADO POR SECCIONES (#1)** — el menú plano por permiso se reorganizó en **secciones con encabezado**
>    (CREW, LOCACIONES, SEGURIDAD, MÉDICO, ADMIN) en `layouts/sidebar.blade.php` (desktop + espejo móvil); cada sección va en
>    **`@canany([...])`** (el encabezado solo aparece si hay ≥1 ítem visible) y cada ítem **sigue** con su `@can` individual.
> 4. ✅ **PANTALLA DE ASIGNACIÓN DE ROL + DEPARTAMENTO (UI) — HECHA** (antes era el PENDIENTE #2). Controller NUEVO
>    `app/Http/Controllers/RoleAssignmentController.php` (single-purpose, **NO** en `AdminController`): `index()` lista usuarios
>    activos (eager-load de roles spatie, sin N+1) con rol + depto actuales; `update($id)` valida y, en una **transacción DB**,
>    `syncRoles([$role])` + upsert del pivote `production_user` (`department_id`, `position_id`, `role`, `is_lead = role==='hod'`)
>    para la única "Producción Demo". Vista NUEVA `resources/views/admin/roles-assign.blade.php` (tabla BS5, `<select>` Rol +
>    Depto por fila, patrón `form=`, **Laravel-8-safe** con ternario `selected`, paginada 50). **Rutas** (`routes/web.php`) bajo
>    `Route::middleware(['auth','permission:users.assign-role'])`: **`GET /rolescrud` → `roles.index`**, **`POST /rolescrud/{id}`
>    → `roles.update`** (verificado vía `route:list --json`: ambas con `permission:users.assign-role`). Link ADMIN→"Asignar Roles"
>    en el sidebar (`@can('users.assign-role')` → super-admin + line-producer). Verificado `php -l` / `view:cache` / smoke test
>    (35 deptos / 92 usuarios activos). **🧱 4 DECISIONES DE DISEÑO:**
>    (1) **`super-admin` EXCLUIDO** de la lista de roles asignables — god-mode solo por seeder/manual, nunca por la UI
>    (anti-escalada); asignables = line-producer, coordinator, hod, medic, safety-officer, crew, auditor.
>    (2) **SIN selector de producción** — el despliegue es una copia independiente de la app por VPS de cliente, así que **una
>    instancia = una producción** ("Producción Demo").
>    (3) **v1 = ROL + DEPARTAMENTO únicamente** (sin selector de puesto todavía; al cambiar de departamento se limpia el
>    `position_id` viejo porque pertenecía a otro departamento).
>    (4) `coordinator` tiene `users.assign-department` pero **NO** `users.assign-role`, así que esta v1 (gateada por `assign-role`)
>    es para super-admin/line-producer; un **flujo coordinator de solo-departamento queda PENDIENTE** (refinamiento futuro).
>
> **✅ HECHO (incremento "#2(a)", 2026-06-25 — verificado `tinker` / `php -l` / `view:cache`):**
> 5. ✅ **#2(a) — HOD DEPARTMENT SCOPING ("VER ACOTADO").** Se extrajo la lógica de alcance por departamento a una **única fuente
>    de verdad** en `app/Models/User.php`: método de instancia **`ownDepartmentIds()`** (los `department_id` del usuario desde el
>    pivote `production_user`) + helper estático **`applyDepartmentScope($query, User $viewer)`**. Comportamiento: viewer **CON**
>    `crew.view.all-departments` → sin restricción; **SIN** él pero con depto(s) → solo usuarios que comparten uno de sus
>    departamentos (`whereExists` sobre `production_user`); **SIN** él y **sin** depto determinable → **no devuelve NADA**
>    (err-restrictivo, nunca degrada a "ver todo"). El helper acepta `Eloquent\Builder` **y** `Query\Builder` (`DB::table('users')`)
>    porque `whereExists`/`whereRaw`/`whereColumn` existen en ambos y la tabla base es `users`. **Aplicado** a los cuatro métodos
>    de listado de crew de `AdminController` — `usuarioscrud` (Crew List), `comcrud`, `idcardscrud` (gafetes), `medicocrud`. **Y
>    `SearchController@runSearch` se REFACTORIZÓ al mismo helper**, retirando su bloque de alcance por depto duplicado inline (DRY —
>    una sola fuente compartida por la búsqueda en vivo y la Crew List). Verificado (tinker): `test.hod` sin pivote → acotado **0**;
>    super-admin → **92**; viewer del departamento 19 → acotado **3** (= los 3 activos de ese depto). Cierra el "ver acotado" del
>    HOD como **defensa en profundidad** (los métodos siguen además gated por su permiso de ruta).
> 6. ✅ **#2(b) — HOD "AGREGAR ACOTADO" + el alta por fin POBLA el pivote RBAC** *(2026-06-25 — verificado `php -l` / render del
>    view en ambos estados / transacción rollback en `tinker` / `view:cache`)*. `AdminController@newuser` **reescrito**: para un
>    **HOD** (rol **SIN** `crew.view.all-departments`) el departamento del nuevo usuario se **fuerza** al del propio HOD vía
>    `$viewer->ownDepartmentIds()->first()` (se **ignora** el valor del form; HOD sin departamento → alta **rechazada** con flash de
>    error); para roles **all-departments** el depto viene del form y se resuelve con `Department::firstOrCreate`. **Cierra dos
>    huecos del alta que dejaban el pivote sin escribir:** (1) los nuevos usuarios reciben **rol spatie base** `$user->syncRoles(
>    ['crew'])` (antes `newuser` no asignaba ningún rol), y (2) se **escribe la fila del pivote `production_user`**
>    (`department_id`, `role='crew'`, `is_lead=false`) — antes `newuser` solo escribía el string legacy `puestodepartamento` y
>    **nunca tocaba el pivote**, así que el crew recién agregado quedaba **invisible** para las listas acotadas por departamento de
>    #2(a). Además: `Mail::send` del correo de bienvenida envuelto en **try/catch** (una falla SMTP ya no 500ea tras crear el
>    usuario) y **PRG** (`redirect('/adduser')` + flash `status`). `AdminController@adduser` pasa **`$lockedDept`** a la vista
>    (helper privado `lockedDeptName($viewer)`); la vista `admin/newuser.blade.php` muestra flashes `status`/`error` y un campo de
>    departamento **condicional** (input **readonly** fijo para el HOD, sin `name`; `<select name="zone">` para all-departments). El
>    enforcement real es del **lado del servidor** (el readonly es solo UX). **Con esto #2 (HOD scoping) queda COMPLETO** —
>    (a) ver acotado + (b) agregar acotado. *(Nota legacy registrada para el cleanup futuro: en este form `zone`=Departamento,
>    `puestodepartamento`=solo el puesto, `labn`=Jerarquía — deuda de columnas reusadas, NO bloquea #2; la verdad del scoping es el
>    pivote `production_user.department_id`.)*
> 8. ✅ **EDITOR EN VIVO DE LA MATRIZ ROL→PERMISO ("Permisos por rol")** *(2026-06-25 — verificado: el permiso existe y SOLO
>    `super-admin` lo tiene; el view renderiza ~278KB; el round-trip de `update()` persiste sin borrar los permisos de otros roles;
>    re-sembrar restaura la fábrica)*. Nuevo permiso **`roles.manage-permissions`** en el catálogo del seeder (por **DEFAULT solo
>    `super-admin`** vía `Permission::all()`; conteo **42 → 43**). Nuevo controller `app/Http/Controllers/RolePermissionController.php`
>    — `edit()` renderiza la matriz agrupada por módulo, `update()` hace `syncPermissions` por rol editable + `forgetCachedPermissions()`.
>    Nueva vista `resources/views/admin/role-permissions.blade.php` (matriz de checkboxes agrupada, aviso de "cambios en vivo", columna
>    `super-admin` bloqueada). Rutas (`routes/web.php`) bajo `permission:roles.manage-permissions`: **`GET /permisoscrud`**
>    (`roles.permissions.edit`) + **`POST /permisoscrud`** (`roles.permissions.update`). Ítem **"Permisos por rol"** en el sidebar
>    (sección Admin, desktop + móvil) gateado `@can('roles.manage-permissions')`. **🛡️ Salvaguardas:** la columna **`super-admin` es
>    de SOLO LECTURA** (god-mode, nunca editable por la UI) y un **guard anti-auto-bloqueo** impide que un no-super-admin se quite a sí
>    mismo `roles.manage-permissions`. **⚠️ "valor de fábrica" vs "estado vivo":** el seeder es el **valor de fábrica** y la BD el
>    **estado vivo**. **✅ RESUELTO (2026-06-25):** el seeder es **NO-DESTRUCTIVO** — una guarda (`Role::has('permissions')->exists()`)
>    detecta una instancia viva y **preserva** los grants editados (registra permisos nuevos del catálogo pero no re-sincroniza), con
>    override `RBAC_FORCE_RESEED=true` para un reset de fábrica intencional. Encaja con el **modelo de despliegue fresco** (cada cliente
>    se despliega en su propio subdominio VPS corriendo seeders una sola vez sobre BD vacía). **NO se hizo botón "restaurar a fábrica"**
>    (descartado como footgun de auto-destruir).
>
> 7. ✅ **CATÁLOGOS → `catalogs.view` / `catalogs.manage`** *(2026-06-25 — verificado `php -l` / `route:list --json` / `view:cache` /
>    acceso por rol en `tinker`)*. Las rutas de catálogos (departamentos/puestos/notificaciones) salieron del grupo legacy
>    `admin` a **dos grupos `permission:` (cada uno con `auth`)** en `routes/web.php`, mismo patrón strangler: **`catalogs.view`** →
>    los tres index (`departamentocrud`, `positionscrud`, `notificacioncrud`); **`catalogs.manage`** → todas las mutaciones + GET de
>    formulario de edición (`creardepartamento`, `activardepartamento`, `desactivardepartamento`, `editardepartamento`,
>    `savedepartamento`, `crearpositions`, `editpositions`, `saveposition`, `crearnotificacion`, `activarnotificacion`,
>    `desactivarnotificacion`, `editarnotificacion`, `savenotificacion`). Las copias viejas se removieron del grupo `admin` y su
>    comentario de cabecera se actualizó. **Sección "Catálogos" en el sidebar** (`layouts/sidebar.blade.php`, desktop + espejo móvil)
>    gateada `@can('catalogs.view')` con tres links (Departamentos / Puestos / Notificaciones). **Acceso por rol** (tinker):
>    `catalogs.view` = super-admin/line-producer/coordinator/hod/safety-officer/auditor; `catalogs.manage` = super-admin/line-producer
>    únicamente. Verificado además que las rutas migradas muestran `permission:catalogs.*` y **ya NO** `AdminMiddleware`, sin nombres
>    de ruta duplicados por la migración. **Con esto el grupo `admin` queda esencialmente COVID/PCR/lab/queue + import/export +
>    mail/reminders** — el flag `admin` está casi totalmente retirado. *(Polish ✅ HECHO 2026-06-25: las vistas de catálogos ya
>    envuelven sus botones de gestión en `@can('catalogs.manage')` → un rol con solo `catalogs.view` ve el listado pero no los
>    controles de crear/editar/activar/desactivar; ver PENDIENTES punto 2 abajo.)*
>
> **PENDIENTES:**
> 1. **Rutas `admin` restantes (NO migradas aún):** tras migrar catálogos, lo que queda en el grupo `admin` (`admin=1`) es
>    esencialmente **COVID/PCR/lab/queue** (se elimina con COVID-DECOMMISSION, no se "protege": desaparece) + **import/export** +
>    **mail/reminders** (estos dos podrían recibir sus propios permisos más adelante).
> 2. ✅ **Polish vistas de catálogos — HECHO Y VERIFICADO (2026-06-25).** Los botones de gestión de `admin/departamentocrud` /
>    `positionscrud` / `notificacioncrud` ya van envueltos en **`@can('catalogs.manage')`** — se gatearon tres bloques por vista:
>    la card de crear, el `<th>` de acciones y el `<td>` de acciones por fila. Un rol con solo `catalogs.view`
>    (coordinator/hod/safety-officer/auditor) ahora ve el LISTADO (id/name/correo + paginación) pero **no** los controles de crear/
>    editar/activar/desactivar → cerrado el "visible-pero-403". Verificado: `Blade::compileString` + `php -l` limpio (sin desbalance
>    `@if/@can`), render de `departamentocrud` en ambos roles (super-admin ve todo; `test.hod` con solo `catalogs.view` no ve ninguno
>    pero conserva el listado), `view:cache`. Detalle en [PROGRESS.md](PROGRESS.md) (entrada 🔘 Botones de catálogos gateados).
> 3. **Flujo coordinator de solo-departamento (UI):** la v1 de asignación está gateada por `users.assign-role` (super-admin/
>    line-producer); el `coordinator` solo tiene `users.assign-department` → su flujo de asignar SOLO departamento queda como
>    refinamiento futuro.
> **PENDIENTE owner (en vivo, incógnito):** entrar como cada una de las 4 cuentas → confirmar menú distinto y links sin 403; y que
> el super-admin del owner sigue viendo todo; entrar a `/rolescrud` y asignar a `test.hod` un departamento. Detalle operativo en
> [PROGRESS.md](PROGRESS.md) "▶️ SIGUIENTE PASO INMEDIATO".

---

# PARTE A — ESTADO ACTUAL (verificado en código)

## A.1 Cómo se decide hoy quién puede hacer qué

| Mecanismo | Dónde | Qué hace | Problema |
|---|---|---|---|
| `User.admin` (bool) | `app/Models/User.php:32` (en `$fillable`) | Único control de rol real. | En `$fillable` → mass-assignable (C2). Global, sin granularidad. |
| `AdminMiddleware` | `app/Http/Middleware/AdminMiddleware.php:17-23` | `if (auth()->check() && auth()->user()->admin) next(); else redirect('/')`. | **No verifica `activo`** (H5): un admin desactivado conserva acceso. |
| Alias `admin` | `app/Http/Kernel.php:61` | Mapea `admin` → `AdminMiddleware`. | Es el único alias de autorización propio. |
| `User.activo` (bool) | `User.php:35` | Estado de cuenta; lo togglean `activarusuario`/`desactivarusuario`. | **No se consulta en ningún control de acceso** (solo se muestra/edita). |
| `User.daytest` (0/1/2/3) | `User.php:43` | **Sobrecargado**: rol funcional (0=admin/coord, 1=supervisor/coord, 2=médico) **Y** "día de prueba" (3, residuo COVID). Lo setean `putga/putgb/putgg`. | Doble propósito (rol + día). Ramifica menús (no protege rutas). En `$fillable` (C2). |
| `niveldos` | — | **No existe como archivo** (`app/Http/Middleware/niveldos.php` ausente) y **no está registrado** en `Kernel.php:59-72`. Era un stub no-op. | Engañoso; no aplica ningún control. No confiar. |
| Sanctum | `routes/api.php:17`; `composer.json:14` (`laravel/sanctum ^2.11`) | Único endpoint `/api/user` con `auth:sanctum`. `EnsureFrontendRequestsAreStateful` comentado (`Kernel.php:46`). | Prácticamente sin usar. Tokens sin expiración (M5). |
| Policies / Gates | `app/Providers/AuthServiceProvider.php:15-29` | `$policies = []` vacío; `boot()` sin gates. | **No hay RBAC ni autorización por recurso.** |

**Setters del "rol" `daytest`** (`app/Http/Controllers/AdminController.php`):
`putga()` :110-116 → `daytest=0`; `putgb()` :117-123 → `daytest=2`; `putgg()` :124-130 → `daytest=1`.
**Setters de `admin`:** `activaradmin()` :87-93 → `admin=1`; `desactivaradmin()` :94-100 → `admin=0`
(ambos vía `Route::get`, ver H1/CSRF).

## A.2 Inventario de TODOS los puntos de decisión de acceso

### A.2.1 — Middleware en `routes/web.php`
| Bloque | Líneas | Control | Conteo aprox. de rutas |
|---|---|---|---|
| `['middleware' => 'admin']` | `web.php:44-197` | `AdminMiddleware` (`->admin`) | **~85 rutas** (todo el CRUD: usuarios, deptos, puestos, PCR, H&S, DSR, lesiones, locación, virtualqueue, búsquedas). |
| `['middleware' => ['auth']]` | `web.php:204-207` | `auth` | **1 ruta** (`/profile`, duplicada — también existe en el grupo admin `:191`). |
| **Sin middleware** | `web.php:16-41, 198-203, 208-211` | **ninguno** (C3) | **~17 rutas**: `/`, `/locale`, `/offline`, resets (`/newdayRep`:29, `/PhotoReminder`:30, `/newTD`:31, `/newWR`:32, `/Nresult`:33), `Auth::routes()`:35, `/home`:36, `/dailyreport`:37-38, `/negative-mail`:40, `/reminder-mail`:41, `/nophoto`:198, `/changepassword`:200, `/updatepassword/{id}`:201, `/formularios/registro`:202, `/formularios/medicos`:203, `/update`:208, `/pruebachedule`:209, `/crop-image*`:210-211. |

> Nota: dentro del bloque `admin` el control es **el mismo flag global** para las ~85 rutas —
> no hay segregación por rol funcional ni por recurso (un admin puede tocar datos clínicos,
> H&S, usuarios, todo). Es la causa raíz de H2 (IDOR) en [SECURITY.md](SECURITY.md).

### A.2.2 — Checks en vistas (flags de acceso)
**`@if(auth()->user()->admin)` — 4 ocurrencias en 4 archivos:**
- `resources/views/layouts/app.blade.php:60`
- `resources/views/layouts/header.blade.php:9`
- `resources/views/home.blade.php:8`
- `resources/views/inicio.blade.php:67`

**Ramificación de menú/UI por `daytest` — 20 ocurrencias en 8 archivos** (sidebar + componentes de listado):
- `resources/views/layouts/sidebar.blade.php` — **6** (`:25`, `:97`, `:139`, `:173`, `:245`, `:287`): tres ramas de menú (`daytest != 1 && != 2` = admin/coord; `== 1` = supervisor; `== 2` = médico), duplicadas para desktop y offcanvas móvil.
- `resources/views/admin/usuarioscrud.blade.php` — **3** (`:92`, `:105`, `:118`): badge de rol por fila.
- `resources/views/componentes/searchusers.blade.php` — **3** (`:72`, `:85`, `:98`).
- `resources/views/admin/comcrud.blade.php` — **2** (`:44`, `:62`); `searchcom.blade.php` — **2** (`:23`, `:41`).
- `resources/views/virtualqueue/crudtest.blade.php` — **1** (`:67`); `searchqueue.blade.php` — **1** (`:44`).
- `searchusers.bladeBKP.php` — **2** (archivo backup, a borrar en [RECIPE.md](RECIPE.md) 0.B).

**Otros flags en vistas** (no son rol pero ramifican UI): `->encuestadiaria` (home/inicio/profile),
`$user->activo` en badges (`usuarioscrud:142`, `searchusers:121`, `departamentocrud:51`, `notificacioncrud:58`, `departamentousuarios:26`).

### A.2.3 — Checks dentro de controllers
**No hay checks de autorización dentro de controllers** (no hay `if(...->admin)` ni `$this->authorize()`
ni `Gate::`). El acceso se decide 100% por el middleware de ruta. Los usos de `auth()->user()` en
controllers son **identidad** (saber quién soy), no **autorización**:
- `encuestasController.php:12,24` — gate por `->encuestadiaria` (estado, no rol).
- `PerfilController.php:13,18,23,57` y `cropimageController.php:16,34` — `User::findOrFail(auth()->user()->id)` (self).
- `FormulariosController.php:26-27,114,230,418,597` — `auth()->user()->id` para registrar autor.
- `InjuryReportController.php:19`, `queueController.php:135` — `auth()->user()` para autocompletar.

> **Conclusión del inventario:** la autorización real son exactamente **2 puntos** (el alias
> `admin` en `web.php:44` y `AdminMiddleware`); todo lo demás (`daytest` en 8 vistas, `admin`
> en 4 vistas) es **presentación** que oculta/muestra UI pero **no protege endpoints**. Quien
> conoce la URL la invoca igual si pasa `AdminMiddleware` (todo admin pasa).

## A.3 Relación usuario ↔ depto/puesto hoy

| Fuente | Modelo / archivo | Estado | Notas |
|---|---|---|---|
| **String desnormalizado** | `users.puestodepartamento` (`User.php:29`) | **El que SÍ se usa** | Guarda `"Departamento-Puesto"`. Escrito por `selectpuesto()` (`AdminController.php:273-286`) y mostrado en `inicio.blade.php:477`. Doble fuente de verdad. |
| `departamento` | `app/Models/departamento.php` | tabla `departamentos`, PK `id_departamentos`, 12 filas | `$fillable` incluye la PK (mass-assignment de PK). `$dates` deprecado. |
| `puesto` | `app/Models/puesto.php` | tabla `puestos`, PK `id_puestos`, FK `id_departamento`, 18 filas | Idem PK en `$fillable`. |
| `userpuesto` (junction) | `app/Models/userpuesto.php` | tabla `userpuesto`, **0 filas** | FK al usuario se llama **`id`** (no `user_id`). `selectpuesto` la escribe junto con el string, pero la app **lee el string**, no la junction. |
| `departamentousuario` (junction) | `app/Models/departamentousuario.php` | tabla `departamentousuario`, **0 filas** | **Bug:** `$primaryKey = 'id_departamentousuario '` (con espacio al final) apunta a columna inexistente; la columna real está limpia ([DATABASE-SCHEMA.md](DATABASE-SCHEMA.md) §mismatches). No mordió porque está vacía. |

`selectpuesto()` (`AdminController.php:273-286`), evidencia de la doble escritura:
```php
userpuesto::create(['name' => $namedepto->departamento."-".$puesto->name,
    'id_puesto' => $request->id_puesto, 'id' => $id]);       // junction (queda huérfana)
$usuario->puestodepartamento = $namedepto->departamento."-".$puesto->name; // string (el real)
```

**No existe ninguna noción de "producción/proyecto/rodaje".** Toda la app vive en un único
espacio global ([ROADMAP.md Fase 1](ROADMAP.md)). El nombre de producción aparece como **texto
libre repetido** en cada reporte H&S ([DATABASE-SCHEMA.md §objetivo](DATABASE-SCHEMA.md)).

---

# PARTE B — DISEÑO OBJETIVO

## B.1 spatie/laravel-permission — roles y permisos

Adoptar **`spatie/laravel-permission`** (estándar de facto Laravel, [RECIPE.md 1.C](RECIPE.md)).
Crea 5 tablas estándar (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
`role_has_permissions`) — todas `bigint unsigned` / `utf8mb4` por defecto.

### B.1.1 — Roles propuestos (7)
| Rol (slug spatie) | Equivale hoy a | Descripción |
|---|---|---|
| `super-admin` | `admin=1` (+ `daytest=0`) | Acceso total a la instancia. Gate `before` que concede todo. |
| `line-producer` | `daytest=0` no-admin | Productor/a de línea; gestiona producciones, crew y catálogos. |
| `coordinator` | `daytest=1` | Coordinador/a; gestiona crew y documentos dentro de su producción. |
| `hod` | (nuevo; hoy implícito en `puestodepartamento`) | Jefe/a de departamento; ve y gestiona su departamento dentro de una producción. |
| `medic` | `daytest=2` | Médico/a; acceso a reportes médicos (`cmedic`/`medical_records`). |
| `safety-officer` | (nuevo; hoy cae en admin) | Responsable de H&S (lesiones, peligros, condiciones, locación, DSR). |
| `crew` | usuario normal (`admin=0`, sin `daytest`) | Miembro de crew; self-service (perfil, sus documentos, su encuesta). |

> **Decisión de naming:** se respetan exactamente los slugs de [ROADMAP.md Fase 1](ROADMAP.md)
> y [RECIPE.md 1.C](RECIPE.md). `super-admin` recibe un Gate `before` en `AuthServiceProvider`
> que devuelve `true` para todo permiso (patrón spatie recomendado).

### B.1.2 — Permisos por módulo/acción
Convención: `modulo.accion` (kebab/dot). Lista concreta para sembrar en el seeder:

| Módulo | Permisos |
|---|---|
| **Producciones** | `productions.view` · `productions.create` · `productions.update` · `productions.delete` · `productions.manage-members` |
| **Crew / Usuarios** | `users.view` · `users.create` · `users.update` · `users.deactivate` · `users.assign-role` · `users.assign-department` |
| **Roles / Permisos** | `roles.manage-permissions` (editar EN VIVO la matriz rol→permiso — por default **solo `super-admin`**) |
| **Catálogos** (deptos/puestos/notificaciones) | `catalogs.view` · `catalogs.manage` |
| **H&S — Lesiones** | `injury.view` · `injury.create` · `injury.manage` |
| **H&S — Peligros / Condiciones inseguras** | `hazards.view` · `hazards.create` · `hazards.manage` |
| **H&S — Locación (auditoría)** | `locations.view` · `locations.create` · `locations.manage` |
| **H&S — DSR (reporte diario)** | `dsr.view` · `dsr.create` · `dsr.update` · `dsr.export` |
| **Médico** | `medical.view` · `medical.create` · `medical.update` (sensible — solo `medic`/`super-admin`) |
| **Documentos** (futuro, [ROADMAP Fase 2](ROADMAP.md)) | `documents.view` · `documents.create` · `documents.assign` · `documents.sign` · `documents.manage-templates` |
| **Perfil (self)** | `profile.update-own` (todos los autenticados) |

### B.1.3 — Tabla rol → permisos (matriz de seeder)
Leyenda: ✅ = concedido · — = no · (super-admin tiene todo vía Gate `before`).

| Permiso \ Rol | super-admin | line-producer | coordinator | hod | medic | safety-officer | crew |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| productions.view | ✅ | ✅ | ✅ | ✅ | — | ✅ | — |
| productions.create/update/delete | ✅ | ✅ | — | — | — | — | — |
| productions.manage-members | ✅ | ✅ | ✅ | — | — | — | — |
| users.view | ✅ | ✅ | ✅ | ✅ | — | — | — |
| users.create | ✅ | ✅ | ✅ | ✅ | — | — | — |
| users.update | ✅ | ✅ | ✅ | — | — | — | — |
| users.deactivate | ✅ | ✅ | — | — | — | — | — |
| users.assign-role | ✅ | ✅ | — | — | — | — | — |
| users.assign-department | ✅ | ✅ | ✅ | — | — | — | — |
| catalogs.view | ✅ | ✅ | ✅ | ✅ | — | ✅ | — |
| catalogs.manage | ✅ | ✅ | — | — | — | — | — |
| injury.* | ✅ | ✅ | view | view | — | ✅ | create (propia) |
| hazards.* | ✅ | ✅ | view | view | — | ✅ | create (propia) |
| locations.* | ✅ | ✅ | view+create | — | — | ✅ | — |
| dsr.view | ✅ | ✅ | ✅ | ✅ | — | ✅ | — |
| dsr.create/update/export | ✅ | ✅ | — | — | — | ✅ | — |
| medical.* | ✅ | view | — | — | ✅ | view | — |
| documents.manage-templates | ✅ | ✅ | — | — | — | — | — |
| documents.create/assign | ✅ | ✅ | ✅ | ✅ | — | — | — |
| documents.view/sign | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (propios) |
| profile.update-own | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

> "create (propia)" / "(propios)" = permiso global + verificación de **pertenencia** en
> Policy (cierra H2/IDOR). Los permisos `documents.*` se siembran ya pero el módulo llega en
> [ROADMAP Fase 2](ROADMAP.md); se listan aquí para fijar el vocabulario desde el día 1.

> **Actualización 2026-06-24 — alineación a la matriz del owner (perms del menú):** se alinearon
> los permisos **de cara al menú** a la matriz permiso×rol que aportó el owner. 4 grants nuevos
> (aditivos): `hod` += `users.create` (registra crew de su área); `coordinator` += `locations.view`
> + `locations.create` (ve/crea Scoutings); `line-producer` += `medical.view`; `safety-officer`
> += `medical.view`. `auditor` **sin cambios** (solo-lectura completo: conserva todos los `.view`,
> incl. `medical.view`). Conteos por rol resultantes: **line-producer 39 · coordinator 22 · hod 15 ·
> safety-officer 24** (super-admin 42 · medic 13 · crew 5 · auditor 12, sin cambio). Re-sembrado
> idempotente + verificado vía `tinker`. Archivos: `database/seeders/RolesAndPermissionsSeeder.php`.
> **🔒 Privacidad:** `medical.view` expone datos clínicos (consultas / historial médico); con esta
> alineación se extiende a `line-producer` y `safety-officer` (auditor ya lo tenía) — **decisión
> explícita del owner**. Detalle en [PROGRESS.md](PROGRESS.md) (entrada 🎚️ MATRIZ DE PERMISOS DEL MENÚ).

## B.2 Entidades `Production` y `Department` + pivote `production_user`

### B.2.1 — `productions` (NUEVA)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint unsigned` PK AI | |
| `name` | `varchar(150)` | Nombre del rodaje/proyecto. |
| `code` | `varchar(30)` nullable, unique | Código corto opcional. |
| `client_name` | `varchar(150)` nullable | Productora/estudio (modelo per-cliente, [ROADMAP §0](ROADMAP.md)). |
| `start_date` / `end_date` | `date` nullable | Ventana de rodaje (reemplaza fechas hardcoded de `HomeController`). |
| `active` | `boolean` default `true` | |
| `settings` | `json` nullable | Branding/módulos por producción ([ROADMAP §0](ROADMAP.md), [RECIPE 1.D](RECIPE.md)). |
| `timestamps` | | |

### B.2.2 — `departments` (rehecho de `departamentos`)
| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint unsigned` PK AI | Reemplaza `id_departamentos`. |
| `name` | `varchar(100)` | (hoy `departamento`). |
| `active` | `boolean` default `true` | |
| `timestamps` | | |

`positions` (rehecho de `puestos`): `id` PK, `department_id` FK→`departments.id` (`onDelete('cascade')`/`restrict`), `name varchar(100)`, `timestamps`.

### B.2.3 — `production_user` (pivote con **rol por producción**)
El corazón del diseño: un usuario puede ser HOD en una producción y crew en otra.
| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint unsigned` PK AI | |
| `production_id` | `bigint unsigned` FK→`productions.id` `onDelete('cascade')` | |
| `user_id` | `bigint unsigned` FK→`users.id` `onDelete('cascade')` | |
| `department_id` | `bigint unsigned` FK→`departments.id` nullable `onDelete('set null')` | Depto del usuario **en esa** producción. |
| `position_id` | `bigint unsigned` FK→`positions.id` nullable | Puesto en esa producción. |
| `role` | `varchar(40)` | Rol **en esa** producción (`hod`/`coordinator`/`crew`/`safety-officer`/`medic`). Espejo del rol spatie, scoped a producción. |
| `is_lead` | `boolean` default `false` | ¿Es HOD de su departamento aquí? |
| `timestamps` | | |
| — | unique(`production_id`,`user_id`) | Un registro por usuario por producción. |

> **Cómo conviven spatie y `production_user.role`:** los **roles spatie** son globales (qué
> puede hacer una persona *en general*: `super-admin`, `line-producer`, `medic`…). El campo
> `production_user.role` es el **rol contextual** dentro de un proyecto (HOD aquí, crew allá).
> El scoping por producción ([RECIPE 1.C](RECIPE.md): "un usuario solo ve lo de su producción")
> se resuelve con un *global scope* / middleware `production` que filtra por las producciones
> donde el usuario tiene fila en `production_user`. Para datos clínicos/H&S, la Policy combina
> permiso spatie **+** pertenencia a la producción del recurso.

### B.2.4 — Relaciones Eloquent objetivo
- `User belongsToMany Production` (withPivot `department_id`, `position_id`, `role`, `is_lead`).
- `Production hasMany` H&S reports (`injury_reports`, `hazardnotifications`, `unsafeconds`, `location_report`, `daily_reports`) vía `production_id` FK añadida a cada uno ([DATABASE-SCHEMA §objetivo](DATABASE-SCHEMA.md)).
- `User` usa `HasRoles` (trait de spatie) para los roles globales.

## B.3 Coherencia con DATABASE-SCHEMA.md
Este diseño es exactamente el "Esquema objetivo (post-COVID + Fase 1)" de
[DATABASE-SCHEMA.md](DATABASE-SCHEMA.md): `departments`/`positions`/`position_user`,
`productions` (NUEVA), roles spatie, FKs `user_id`/`production_id` en reportes H&S, PK a `bigint`
y charset `utf8mb4`. Se hereda de ahí el arreglo de los **mismatches**: PK con espacio de
`departamentousuario`, FK `id_usrio`/`id`→`user_id`, `tinyint→bigint` en `hazardnotifications`/`unsafeconds`.

---

# PARTE C — MIGRACIÓN DE DATOS DE PRUEBA (mapeo viejo → nuevo)

Los datos son **100% de prueba** ([DATABASE-SCHEMA.md](DATABASE-SCHEMA.md), [ROADMAP §0](ROADMAP.md)),
así que esto se ejecuta como **seeder de migración** (o factory), no como migración de datos
vivos. Mapeo explícito:

### C.1 `admin` + `daytest` → roles spatie
| Condición vieja | Rol spatie asignado |
|---|---|
| `admin = 1` | `super-admin` |
| `admin = 0` y `daytest = 0` (o null) | `line-producer` (o `crew` si no gestiona — decisión por dato; por defecto `crew`) |
| `admin = 0` y `daytest = 1` | `coordinator` |
| `admin = 0` y `daytest = 2` | `medic` |
| `daytest = 3` | **IGNORAR para rol** — es "día de prueba" (residuo COVID), NO un rol. Asignar `crew`. |
| usuario sin ninguno | `crew` |

> ⚠️ **Trampa crítica (ver Parte D/Riesgos):** `daytest` es **sobrecargado** — valor `3` = "día
> de prueba", no rol. El seeder debe mapear **solo** 0/1/2 a rol y tratar 3 (y null) como `crew`.

### C.2 `puestodepartamento` (string) → `departments`/`positions`/`production_user`
Como las junctions están vacías ([DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)), la **única** fuente
es el string `"Departamento-Puesto"`. Derivación:
1. **Crear 1 producción semilla** (`Production` "Producción Demo") para alojar a todo el crew de prueba.
2. Para cada usuario: `split('-', puestodepartamento)` → `[deptoName, puestoName]`.
3. `firstOrCreate` en `departments` por `deptoName` (cruzar con las 12 filas de `departamentos`);
   `firstOrCreate` en `positions` por `(puestoName, department_id)` (cruzar con las 18 de `puestos`).
4. Insertar fila en `production_user`: `(production_id=demo, user_id, department_id, position_id,
   role= mapeo de C.1, is_lead = (rol==hod))`.
5. Strings malformados (sin `-`, o vacíos) → `department_id=null`, `position_id=null`, log de aviso.

> El campo `ncreditos` (varchar que guarda **nombres**, no créditos —
> [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)) **no** se usa para org; ignorarlo aquí.

### C.3 Seeders/factories de datos de prueba nuevos
Repoblar con factories ([RECIPE 1.A](RECIPE.md)) emails con sufijo **`.comf`** (neutralizados,
nunca envían — [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)). Verificar que
`php artisan migrate:fresh --seed` levanta roles + permisos + producción demo + crew + pivotes.

---

# PARTE D — CÓMO CIERRA LOS HALLAZGOS DE SEGURIDAD

## D.1 C2 — Auto-promoción por mass-assignment (`admin`/`daytest`)
**Hoy:** `User.php:32,43` tienen `admin` y `daytest` en `$fillable`; `PerfilController@update`
(`web.php:208`, sin auth) y `@updatepassword` (`web.php:201`, sin auth) hacen
`request()->except(...)->update()` → cualquiera envía `admin=1` y se auto-promueve
([SECURITY.md C2](SECURITY.md)).

**Cómo lo cierra el RBAC real (de raíz, no parche):**
1. **`admin`/`daytest` dejan de existir** como columnas de acceso → no hay flag que mass-assignar.
   El rol vive en tablas spatie (`model_has_roles`), **no en `users`**, y solo se cambia con el
   permiso `users.assign-role` vía un endpoint dedicado + Policy. **✅ El endpoint dedicado ya existe
   (2026-06-24):** `POST /rolescrud/{id}` (`roles.update`, `RoleAssignmentController@update`) gated por
   `permission:users.assign-role`, que hace `syncRoles` en transacción (ver ▶️ ESTADO arriba).
2. **Form Requests** ([ROADMAP §4.6](ROADMAP.md), [RECIPE 1.6](RECIPE.md)) reemplazan
   `request()->all()/except()`: el flujo de perfil valida una **lista blanca** que físicamente
   no incluye rol/admin. Aunque el atacante envíe `admin=1`, no hay dónde escribirlo.
3. Asignar rol pasa por `$user->assignRole()` (spatie), gated por `users.assign-role` → un crew
   **no puede** llamarlo. La promoción deja de ser un campo de formulario y pasa a ser una
   **acción autorizada**.

## D.2 C3 — Endpoints sin auth
**Hoy:** ~17 rutas fuera de `auth`/`admin` ([SECURITY.md C3](SECURITY.md)), varias mutan estado
global (resets) o permiten cambiar password/perfil de cualquier id.

**Cómo lo cierra:**
1. Tras la fundación, **toda** ruta de mutación entra en grupos con `auth` + middleware de
   **permiso** (`can:users.update`, etc.). El default deja de ser "abierto".
2. Los resets COVID (`/newdayRep`,`/newTD`,`/newWR`,`/Nresult`,`/PhotoReminder`,`/nophoto`) se
   **eliminan** en [RECIPE 0.C / COVID-DECOMMISSION](RECIPE.md) (no se "protegen": desaparecen).
3. Endpoints self-service (`/update`, `/updatepassword/{id}`) + Policy de **pertenencia**
   (`$id === auth()->id()`) → cierra también H2/IDOR. El permiso `profile.update-own` los cubre.
4. Resultado: la autorización deja de depender de "recordar" añadir middleware ruta por ruta;
   el patrón por defecto (Form Request + permiso + Policy) la hace estructural.

---

# PARTE E — ORDEN DE OPERACIONES SEGURO

> Principio ([ROADMAP §3.5, §4](ROADMAP.md)): base **offline con datos de prueba** → libertad
> para rediseñar; pero **orden estricto** para no olvidar detalles. Cada paso deja la app
> levantable. **Oráculo de verificación:** la **instancia de PRODUCCIÓN** (cliente vivo) y el
> dump real `crewcare2406.sql` — antes de retirar cualquier mecanismo viejo, confirmar que el
> nuevo reproduce el comportamiento contra ese oráculo.

| # | Paso | Verificación / oráculo |
|---|---|---|
| **(a)** | **Migraciones del esquema base + spatie.** Crear migraciones de `departments`, `positions`, `position_user`, `productions`, `production_user` + `php artisan vendor:publish` de spatie (sus 5 tablas). Tipos `bigint`/`utf8mb4`, FKs reales. **No** tocar `users.admin/daytest` todavía (la app vieja sigue leyéndolos). | `migrate:fresh` corre limpio en local; esquema coincide con [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md). |
| **(b)** | **Seeders de roles/permisos.** Sembrar los 7 roles y la matriz de permisos de B.1.3. Gate `before` para `super-admin`. | `php artisan tinker`: `Role::count()==7`; permisos esperados existen. |
| **(c)** | **Seeders/factories de datos de prueba** con emails sufijo **`.comf`**. Producción demo + crew + deptos/puestos. | `migrate:fresh --seed` levanta base completa; ningún correo real disparable. |
| **(d)** | **Mapear usuarios actuales a roles** (C.1) y a `production_user` (C.2). Ejecutar como seeder de migración idempotente. | Spot-check: un `admin=1` ↔ `super-admin`; un `daytest=2` ↔ `medic`; un `daytest=3` ↔ `crew` (no médico). Comparar conteos contra la instancia de PRODUCCIÓN. |
| **(e)** | **Reescribir `AdminMiddleware` y el menú para usar permisos.** `AdminMiddleware` → usa `auth + activo + permiso/rol` (o reemplazar por `can:`/`role:` de spatie). Sidebar/header: cambiar `@if(auth()->user()->admin)` (4 sitios) y los `daytest` (20 sitios) por `@can(...)`/`@role(...)`. **Correr en paralelo** con los flags viejos un breve periodo (doble check) si se quiere red de seguridad. | Login con cada rol y verificar que el menú y las rutas coinciden con lo que mostraba el sistema viejo, **comparado contra la instancia de PRODUCCIÓN** como oráculo de "qué veía cada rol". |
| **(f)** | **Retirar `daytest`/`admin` como mecanismo de acceso.** Solo cuando (e) esté verificado contra producción: quitar `admin`/`daytest` de `$fillable`, eliminar `putga/putgb/putgg`, `activaradmin/desactivaradmin`, y finalmente dropear las columnas. Cerrar C2/C3 (Form Requests + grupos `auth`+`can`). | `grep` confirma 0 referencias a `->admin`/`->daytest` como control de acceso; suite de smoke por rol pasa; C2/C3 ya no reproducibles. |

> **Regla de retiro:** ningún mecanismo viejo (`admin`, `daytest`, string `puestodepartamento`)
> se borra hasta que su reemplazo esté **verificado contra la instancia de producción**
> (patrón strangler de [ROADMAP §3.5](ROADMAP.md): lo viejo se va a medida que el vertical se verifica).

---

# PARTE F — RIESGOS Y TRAMPAS

| # | Riesgo / trampa | Mitigación |
|---|---|---|
| **R1** | **`daytest` NO es solo rol — también es "día de prueba" (valor 3, residuo COVID).** Mapear ciegamente `daytest` a rol convertiría a un usuario "en día de prueba" en un rol equivocado. | En el mapeo C.1, tratar **solo** 0/1/2 como rol; 3 y null → `crew`. Documentado en C.1. ([DATABASE-SCHEMA.md](DATABASE-SCHEMA.md), [ROADMAP Fase 0](ROADMAP.md): "`daytest` NO se toca hasta entrar roles"). |
| **R2** | **`age` = "tiene foto de gafete", NO edad.** Si se renombra/usa como edad se corrompe la lógica de credenciales. | No tocar `age` en este bloque; su rename a `has_badge_photo` es de [RECIPE 0.C/1.A](RECIPE.md), no de RBAC. Solo avisar para que el seeder no lo confunda. |
| **R3** | **Clases de modelo en minúscula** (`user`, `departamento`, `puesto`, `userpuesto`, `cmedic`…) — rompen autoload en **Linux/AWS** (case-sensitive). Las nuevas (`Production`, `Department`) deben ser PascalCase desde el día 1. | Crear los modelos nuevos en PascalCase; el rename de los viejos es [RECIPE 1.6](RECIPE.md). Verificar en CI/Linux antes de desplegar a AWS ([ROADMAP §4.5](ROADMAP.md)). |
| R4 | **Junctions vacías + bug de PK con espacio** (`departamentousuario.$primaryKey = 'id_..._usuario '`). Confiar en ellas para migrar org daría 0 filas. | La fuente real es el string `puestodepartamento` (C.2). No leer las junctions; rehacerlas como `production_user`/`position_user` limpias. |
| R5 | **Charset mixto (latin1 vs utf8mb4)** en tablas org/H&S → mojibake al consolidar acentos españoles. | Migraciones nuevas fijan `utf8mb4`; convertir con cuidado ([DATABASE-SCHEMA.md](DATABASE-SCHEMA.md), [RECIPE 1.A](RECIPE.md)). |
| R6 | **Doble fuente de verdad** (string `puestodepartamento` + junctions). Si solo se migra una, la org queda inconsistente. | Tras C.2, el string se **retira**; `production_user` es la única fuente. Verificar contra producción antes de dropear la columna (Paso f). |
| R7 | **`AdminMiddleware` no verifica `activo`** (H5): al reescribirlo, no replicar el bug. | El nuevo control exige `auth + activo + permiso`. Verificar que un usuario `activo=0` queda fuera. |
| R8 | **Romper menús por rol** al cambiar 24 checks (`admin`×4 + `daytest`×20) en 9 vistas. Un `@can` mal mapeado oculta/expone UI a quien no debe. | Migrar vista por vista comparando el resultado contra lo que mostraba cada rol en **producción** (oráculo). Mantener flags viejos en paralelo durante el corte (Paso e). |
| R9 | **spatie + scoping por producción**: spatie es global; el "solo ve lo de su producción" no lo da spatie solo. | Combinar permiso spatie **+** pertenencia a `production_user` en Policies/scope (B.2.3). No asumir que `@can` basta para aislar producciones. |
| R10 | **Sin migraciones del esquema de negocio hoy** → si se introduce spatie sin migrar lo demás, el esquema sigue irreproducible. | [RECIPE 1.A](RECIPE.md): toda tabla que se conserva lleva migración en este mismo bloque; meta = `migrate:fresh --seed` levanta todo. |

---

## Apéndice — Conteo de evidencia
- Puntos de **autorización real**: **2** (`web.php:44` alias `admin` + `AdminMiddleware.php:17-23`).
- Rutas bajo `admin`: **~85** (`web.php:44-197`); bajo `auth`: **1** (`:206`); **sin** middleware: **~17**.
- Checks de **presentación**: `auth()->user()->admin` en vistas = **4** (4 archivos);
  `daytest` en vistas = **20** (8 archivos). Checks de autorización **dentro de controllers** = **0**.
- spatie/laravel-permission: **NO instalado** (`composer.json` solo tiene `laravel/sanctum ^2.11`).
- Junctions org (`userpuesto`, `departamentousuario`): **0 filas** cada una.
</content>
</invoke>
