# RECIPE.md — Receta de ajustes (checklist de ejecución)

**Qué es esto:** la lista ordenada y detallada de cambios para llevar esta base de su
estado actual al destino del [ROADMAP.md](ROADMAP.md), **sin olvidar ningún detalle**.
Es la "receta" repetible. Cada paso tiene casillas, dependencias y avisos de "no olvidar".

**Cómo se usa:**
- Esto es el *plan de acción ordenado*; [PROGRESS.md](PROGRESS.md) es la *bitácora de lo hecho*.
- **No se ejecuta nada todavía** — primero queremos la receta completa y revisada.
- Marcar `[x]` solo cuando algo esté hecho Y verificado. Registrar el resultado en PROGRESS.md.
- **Cada ítem de reconstrucción de un artefacto** (view/model/controller) se ejecuta con el
  [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) (descifrar→objetivo→reconstruir→verificar→retirar;
  reconstruir no parchar; `@if(viejo || @can)` es transición, no final).

**Contexto que manda esta receta** (de [ROADMAP §0](ROADMAP.md)):
- Base **offline con datos de prueba** → libertad para rediseñar tablas/campos sin migrar datos vivos.
- Cada cliente = **copia de esta base en un VPS, personalizada** → mover personalización a config.
- **AWS = requisito** (Amazon) → la base debe ser AWS-desplegable cambiando config, no código.

---

## Reglas de oro (aplican a cada paso)
1. **Toda tabla nueva o rehecha lleva migración.** Nada de cambios directos en la BD sin migración.
2. **`$fillable` siempre como lista blanca.** Nunca volcar `request()->all()/except()` a `update()/fill()`.
3. **Validar toda entrada** con `$request->validate()` / Form Requests.
4. **Personalización por cliente = configuración**, no edición de código por copia.
5. **Abstraer disco/correo/BD** vía `.env`/config (filesystems, mail, database) → VPS↔AWS sin tocar código.
6. **Trabajar en lotes de 5–20 archivos** relacionados. Verificar antes de seguir.
7. **Nada de lógica que muta estado en rutas GET.** POST/PUT/DELETE + `@csrf`.

---

## BLOQUE 0 — Higiene crítica y limpieza  *(empezar por aquí)*
Objetivo: base más pequeña y sin los agujeros que se explotan hoy. Bajo riesgo, alto despeje.

### 0.0 — Quick-wins críticos (horas, bajo riesgo) — de [CODE-AUDIT.md](CODE-AUDIT.md)
- [x] **Resolver el 404 de `app.js` SIN webpack.** *(2026-06-24)* `npm install` está **bloqueado por red/SSL** en este entorno
      (mismo MITM que git), así que NO se corrió el build. Diagnóstico (ver [ASSETS-JS-AUDIT.md](ASSETS-JS-AUDIT.md)): nada del front
      depende del bundle (no hay Vue/axios en uso; `resources/js/app.js` es scaffold). **Solución:** se escribió a mano un
      `public/js/app.js` mínimo. Verificado: `GET /js/app.js` → **200** (antes 404). *(Reactivar Mix/Vite queda para Bloque 4.)*
- [x] **🔴 Bug jQuery roto app-wide:** `asset('https://code.jquery.com/...')` en `layouts/app.blade.php:35`, `auth/login.blade.php`,
      `auth/passwords/email.blade.php` — `asset()` deformaba la URL absoluta → jQuery 404 → el JS inline no corría. Quitado el wrapper
      `asset()` en los 3. Verificado en el HTML servido. *(También cubre el ítem de jQuery roto de [VIEWS-INVENTORY.md](VIEWS-INVENTORY.md) 1.5.B.)*
- [x] **Arreglar parse error** `app/Jobs/ReminderEmail.php:45` (comilla sin cerrar → fatal). *(2026-06-24 — quitado el `from()` roto, hereda config; verificado con `php -l`)*
- [x] **Scheduler:** `Console/Kernel.php:27` `everyMinute()` → **`dailyAt('04:00')`** (antes reseteaba y maileaba a todos 1440×/día). *(2026-06-24)*
- [~] **Centralizar `from`** de correo en `config/mail.php` (hoy 5 direcciones hardcoded en 8 archivos, una es Gmail personal). *(2026-06-24 — default neutro `noreply@crewcare.mx`; quitados el `from()` roto de ReminderEmail y el Gmail personal de encuestasTask. Resto de `from()` de AdminController/Formularios → diferido al refactor a Mailables, Bloque 1.6; los de archivos COVID se borran en 0.C)*
- [x] `find()` → `findOrFail()` en los toggles de `AdminController`. *(2026-06-24 — replace_all, 17 ocurrencias `User::find($id)`; verificado con `php -l`)*
- [x] **✅ Fix de las 413 advertencias de Font Awesome (warning cosmético "Glyph bbox was incorrect; adjusting" del woff2 de
      FA 6.0.0 por CDN).** *(2026-06-24)* FA se usa muchísimo (**194 iconos en 28 vistas**) → se **subió la versión, no se
      quitó**: **6.0.0 → 6.7.2** en los 3 puntos de carga (`layouts/app.blade.php`, `auth/login.blade.php`,
      `auth/passwords/email.blade.php`). Se **removió el atributo `integrity` (SRI)** de los 3 (el hash no se podía verificar
      offline — la red corporativa bloquea el TLS saliente de las tools; un SRI equivocado borraría TODOS los iconos);
      conservados `crossorigin`/`referrerpolicy`/`rel`. Tradeoff menor de seguridad, divulgado. Bootstrap Icons 1.8.1 y
      Google Material Icons coexisten sin tocar. **Pendiente:** `Ctrl+F5` del owner para confirmar. *(Mejora futura:
      self-host de FA — ver Bloque 4.)*

### 0.A — Cerrar críticos de app en vivo (de [SECURITY.md](SECURITY.md))
- [ ] **C2 — Auto-promoción a admin.** Quitar `admin`, `activo`, `daytest` de `$fillable` en
      `User`. En `PerfilController@update/@updatepassword` y `AdminController@acountupdate`,
      reemplazar `request()->except(...)` por `$request->validate([...])` con lista blanca
      (sin `admin/activo/daytest/password` en el flujo de perfil).
- [ ] **C3 — Endpoints sin auth.** Mover a grupos `auth`/`admin`: `/update`, `/updatepassword/{id}`,
      `/changepassword`, los resets (`/newdayRep`,`/newTD`,`/newWR`,`/Nresult`,`/PhotoReminder`,`/nophoto`),
      `/negative-mail`, `/reminder-mail`. (Varios de estos se eliminan en 0.C de todos modos.)
- [ ] **M1 — Quitar `dd()`** ruteados de `MailController`, `ReminderMailController`,
      `FormulariosController:112`, `locationController:205`.
- [ ] **H3 — CORS** `config/cors.php`: cambiar `allowed_origins ['*']` por la lista real necesaria.

### 0.A.bis — Higiene del repo (de [CODE-AUDIT.md](CODE-AUDIT.md) §5)
- [ ] **Poblar `.gitignore`** (`/vendor`, `/node_modules`, `/.env`, `/public/storage`, uploads de usuario);
      `git rm --cached` de `vendor/`, `node_modules/`, `.env`, y las fotos de `public/imagesprf/**`.
- [ ] Borrar basura: `*.rar`, `*.bladeOLD/*BKP`, `injury_report_preview`, archivos 0 bytes, dir vacío
      `crewcarerr/`, `public/img/*old*`/`*copia*`/`*.ai`, `web.config`/`.htaccess` raíz vacíos.
- [x] **Restaurar iconos PWA** (`public/images/icons/` ausente). *(2026-06-24 — restaurados con `git checkout` desde HEAD; estaban borrados del disco pero rastreados. `icon-512x512.png` → 200.)*
- [ ] Arreglar `config/filesystems.php` (discos duplicados + ruta rota) → disco `s3` configurable.

### 0.B.bis — Bugs de routing (de [ROUTES.md](ROUTES.md))
- [ ] **`locationController@store`** redirige a name inexistente `locationreport.index` → **500 al guardar**. Fix → `location.crud`.
- [ ] `unsafecondNotificationController@store` → redirigir a `.index` (no a `.store` POST).
- [ ] Eliminar `Route::get('/admin/departamentocrud')` sin acción; resolver duplicados `/profile` y name `encuesta`.

### 0.B — Borrar código muerto / stale (de [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md))
- [ ] `encuestasController@checkfroms` (con bug), `FormulariosController@newformulario1/2`, `checkform`.
- [~] Archivos backup: `*.bladeOLD.php`, `*BKP.php` (idcard, searchusers, etc.). *(2026-06-24 — HECHO: `admin/idcard.bladeOLD.php` y `componentes/searchusers.bladeBKP.php` ELIMINADOS, respaldados en `_legacy_backup/`.)*
- [ ] Bug de sintaxis reportado en `ReminderEmail.php:45` (revisar antes de decidir si se conserva).
- [ ] Archivos `.rar` en la raíz (`app.rar`, `resources.rar`, `routes.rar`) — backups, fuera del repo.

### 0.C — Desmantelar COVID/PCR (seguir orden de [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md))
Orden: **rutas → vistas → jobs → controllers → modelos → columnas (al final)**.
- [ ] **Rutas:** quitar PCR, `virtualqueue/*`, `antigentest`, encuesta diaria, `checkpoint`, `temperature`, resets COVID.
- [ ] **Vistas:** `virtualqueue/*`, `pcrtest/*`, `admin/pcrcrud·pcredit·checkpoint*`, correos COVID.
- [ ] **Jobs/commands:** `SendEmail`, `AntigenTestExport`; revisar `encuestas:task` (cada minuto — del formulario, no PCR; decidir).
- [ ] **Controllers:** `queueController` completo, métodos PCR de `AdminController`, `MailController`.
- [ ] **Modelos:** `pcr`, `prueba`, `usuariopcr`, `queuevirtual` (testqueue), `userform`.
- [ ] **⚠️ NO TOCAR (trampas):** `daytest` (=rol), `age` (=tiene foto). `formulario` y `cmedic` se **conservan** (→ Bloque 2 médico).
- [ ] **Desacoplar primero:** `usuarioscrud.blade` usa `enfermo/notsick/labn`; limpiar esas referencias antes de dropear columnas.
- [ ] **Columnas `users` a eliminar (al final):** `enfermo`, `ultimatemperatura`, `lastpcr`, `resultpcr`, `tested`, `inline`, `labn`, `inlined`.

### 0.D — Limpieza funcional menor
- [ ] Quitar fechas de rodaje **hardcodeadas** en `HomeController` (2025-04) → configurables.
- [ ] Implementar o eliminar `DailyReportController@downloadPdf` + import `Browsershot` sin usar.
- [ ] Consolidar `locationController` vs `locationController2` (misma tabla, dos formularios).

---

## BLOQUE 1 — Fundación: esquema formal + roles + producciones  *(la inversión clave)*
Objetivo: cimientos sobre los que se construye todo. Aprovecha la libertad de datos-de-prueba.

### 1.A — Esquema en migraciones (rediseño limpio) — base: [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)
- [ ] Crear **migraciones** para TODAS las tablas de dominio que se conservan (hoy no hay ninguna).
      Rediseñar con nombres correctos, **FKs reales**, tipos adecuados.
- [ ] **🔴 PK `tinyint(3)` → `bigint unsigned`** en `hazardnotifications` y `unsafeconds` (hoy techo de 255 filas).
- [ ] **🔴 Normalizar charset a `utf8mb4`** en todas las tablas (hoy ~mitad son `latin1` con acentos → mojibake; convertir con cuidado).
- [ ] Arreglar nombres: PK con espacio en el **modelo** `departamentousuario` (la columna real está limpia); FK `id_usrio`; `ncreditos` (varchar que guarda nombres).
- [ ] Crear **seeders + factories** (hoy vacíos) para repoblar datos de prueba (recordar: emails de prueba van con sufijo que no envíe, p. ej. `.comf`).
- [ ] Verificar que `php artisan migrate:fresh --seed` levanta la base completa desde cero.

### 1.B — Simplificar el reporte médico (lo que pediste)
- [ ] Rediseñar `formulario` (~50 columnas planas) → tabla **`medical_records`** esbelta:
      campos núcleo + secciones opcionales **normalizadas** (antecedentes, alergias, contactos
      de emergencia como filas relacionadas, no 50 columnas). Definir el modelo de datos antes de tocar vistas.
- [ ] Retirar campos COVID residuales del formulario (`vacci1` COVID, `crt19`).

### 1.C — Roles reales + multi-producción
- [x] **Instalar `spatie/laravel-permission` + definir roles/permisos.** *(2026-06-24 — CONSTRUIDO Y VERIFICADO contra la BD)*
      Sembrados **8 roles** (`super-admin`, `line-producer`, `coordinator`, `hod`, `medic`, `safety-officer`, `crew` +
      `auditor` solo-lectura) y **39 permisos** granulares (incl. `crew.register`). User tiene trait `HasRoles`;
      `AuthServiceProvider` tiene `Gate::before` para que `super-admin` pase todo. Spatie teams mode = false.
- [x] **Modelos `Production`/`Department` + pivote `production_user` con rol por producción.** *(2026-06-24 — verificado)*
      Creados `Production`, `Department`, `Position` + migraciones (corridas por `--path`). **Rol por producción = columna
      `production_user.role`** (varchar(40), no spatie teams) → un usuario puede ser HOD en una producción y crew en otra.
      Pivote con `department_id`/`position_id` nullable, `is_lead`, `unique(production_id, user_id)`. **`positions.production_id`
      nullable** = catálogo plantilla reutilizable (NULL) + puestos propios por producción (no lista cerrada). Verificado:
      35 deptos / 192 puestos / 1 "Producción Demo" / 88 usuarios atados (2 super-admin + 86 crew).
- [~] Migrar datos actuales de `puestodepartamento`/`userpuesto` al nuevo esquema; **retirar `daytest`**.
      *(2026-06-24 — PARCIAL: rol asignado y pivote atado para los 88, pero `department_id`/`position_id` quedaron **NULL**.
      Causa: `puestodepartamento` son títulos planos **sin separador `-`** ("Line Producer", "APOC"), no "Depto-Puesto".
      **BACKFILL HECHO (2026-06-24):** `database/seeders/BackfillUserPositionsSeeder.php` (idempotente, encadenado tras
      `MapExistingUsersSeeder`; mapa de alias + normalización: acentos/espacios/sufijos numéricos; solo catálogo global,
      nunca crea puestos). **Resultado actual: 75/88 mapeados, 13 NULL** (avanzado 70→75 el 2026-06-24): se agregaron
      **4 puestos nuevos** al catálogo global (Scouter→Locaciones, Bodeguero→Decoración, Coordinador de Utilería→Utilería,
      Sastre→Vestuario; positions 192→196) y se corrigió el bug de **colisión "Bodeguero"** (forma `"Puesto @ Departamento"`
      + índice `name|department`). DB íntegra, 0 discrepancias dept↔puesto; estable en 196 puestos / 75 matched. **Los 13
      títulos NULL restantes quedan DIFERIDOS a decisión de campo del owner** (Cast ×2, COVID TEST MANAGER, Arte ×2,
      Sonido, Supervisor, Segundo Asistente, Ambientador Vestuario, Secretario Produccion, Oficina Transpo, Peon, Limpieza);
      recomendaciones por título en [ORG-TAXONOMY.md](ORG-TAXONOMY.md) §6.B. `daytest` NO se retira aún — strangler.)*
- [~] Reescribir `AdminMiddleware` y el menú (`sidebar`/`header`) para usar **permisos**, no flags
      (en paralelo a `admin`/`daytest`, sin retirarlos aún — strangler).
      *(2026-06-24 — MENÚ AHORA PERMISSION-AWARE (aditivo): cada gate viejo del menú envuelto `viejo || @can` en
      `layouts/app.blade.php`, `header.blade.php`, `sidebar.blade.php` (admin→`users.view`, daytest ramas→`dsr.view`/`medical.view`).
      Hallazgo: el menú lo controla `admin`, NO `daytest`; salida byte-idéntica para todo usuario actual, sin regresión.
      **Aliases spatie registrados** en `app/Http/Kernel.php` `$routeMiddleware` (`role`/`permission`/`role_or_permission`,
      namespace `Middlewares` PLURAL en v5.11) — aún sin adjuntar a rutas. `AdminMiddleware` intacto. **Pendiente:** los
      content-switches `home.blade.php:8` e `inicio.blade.php:67` (cambian contenido, no menú) se migran en su vertical.)*
- [~] **▶️ ROLES visibles/probables — INCREMENTO 1 ✅ HECHO Y VERIFICADO (menú+rutas RBAC fino + 4 cuentas de prueba); pendiente
      la migración del grupo `admin` restante (catálogos/COVID) + la UI de asignación.**
      *(2026-06-24 — verificado `php -l` / `route:list --json` / `view:cache`; seeders OK; **PENDIENTE de verificación en vivo
      incógnito por el owner** de las 4 cuentas).* **HECHO:** **(1) menú+rutas a RBAC FINO** — sidebar
      (`layouts/sidebar.blade.php`, desktop + espejo móvil) reconstruido a **`@can('<permiso>')` ítem por ítem** (mismo permiso
      que protege la ruta → sin visible-pero-403; `daytest` retirado del menú); los verticales del menú **migrados del grupo
      binario `admin` a grupos `permission:` (spatie, cada uno con `auth`)** en `routes/web.php` — users.{view,create,update,
      deactivate,assign-role,assign-department}, crew.view, medical.{view,create}, locations.{view,create}, hazards.{view,create},
      dsr.{view,create,update}, injury.{view,create}; flag `admin` **retirado para esos verticales**; **🛠️ retirado el lock global
      `$this->middleware('admin')` del constructor de `AdminController`** (forzaba `admin==1` en todos sus métodos → anulaba la
      migración per-ruta); aliases spatie ya en `Kernel.php`. **(2) 4 cuentas de prueba** —
      `database/seeders/TestAccountsSeeder.php` (registrado en `DatabaseSeeder`, idempotente, `admin=0` a propósito, password
      `Test1234!`): `test.coordinador@crewcare.test` (`coordinator`), `test.hod@crewcare.test` (`hod`), `test.medico@crewcare.test`
      (`medic`), `test.crew@crewcare.test` (`crew`) → probar en incógnito.
      **(3) ✅ MENÚ AGRUPADO POR SECCIONES (#1)** *(2026-06-24)* — el menú plano por permiso se reorganizó en **secciones con
      encabezado** (CREW/LOCACIONES/SEGURIDAD/MÉDICO/ADMIN) en `layouts/sidebar.blade.php` (desktop + espejo móvil): cada sección
      en **`@canany([...])`** (el encabezado solo aparece si hay ≥1 ítem visible), cada ítem **sigue** con su `@can`.
      **(4) ✅ PANTALLA DE ASIGNACIÓN DE ROL + DEPARTAMENTO (#3) — HECHA Y VERIFICADA** *(2026-06-24 — `php -l` / `route:list
      --json` / `view:cache`; smoke test 35 deptos / 92 usuarios activos)*: controller NUEVO `RoleAssignmentController` (single-
      purpose, NO en `AdminController`) — `index()` lista usuarios activos (eager-load roles, sin N+1); `update($id)` valida +
      `syncRoles` + upsert del pivote `production_user` (`department_id`/`position_id`/`role`/`is_lead`) en transacción, una sola
      "Producción Demo". Vista NUEVA `admin/roles-assign.blade.php` (tabla BS5, `<select>` Rol+Depto por fila, patrón `form=`,
      Laravel-8-safe, paginada 50). Rutas `GET /rolescrud` (`roles.index`) + `POST /rolescrud/{id}` (`roles.update`) gated
      `permission:users.assign-role`; link ADMIN→"Asignar Roles" en el sidebar. **Decisiones:** super-admin EXCLUIDO de asignables
      (anti-escalada); sin selector de producción (una instancia = una producción); v1 = rol+departamento (sin puesto aún); flujo
      coordinator solo-departamento = futuro.
      **(5) ✅ #2 — HOD DEPARTMENT SCOPING — COMPLETO (a "ver acotado" + b "agregar acotado")** *(2026-06-25 — tinker / `php -l` /
      `view:cache`)*:
      • **(a) "VER ACOTADO"** — lógica de alcance por departamento extraída a una **única fuente de verdad** en
      `app/Models/User.php` (`ownDepartmentIds()` + helper estático `applyDepartmentScope($query,$viewer)`, err-restrictivo: sin
      depto determinable no devuelve nada, nunca degrada a "ver todo"; acepta Eloquent\Builder y Query\Builder). **Aplicado** a
      `AdminController` `usuarioscrud` (Crew List) + `comcrud` + `idcardscrud` + `medicocrud`; **`SearchController@runSearch`
      REFACTORIZADO** al mismo helper (DRY). Verificado: super-admin 92, depto 19 → 3, `test.hod` sin pivote → 0.
      • **(b) "AGREGAR ACOTADO"** — `AdminController@newuser` **reescrito**: para un HOD el departamento del nuevo usuario se
      **fuerza** al del propio HOD vía `$viewer->ownDepartmentIds()->first()` (ignora el valor del form; HOD sin depto → alta
      rechazada con flash de error); roles all-departments toman el depto del form (`Department::firstOrCreate`). **Cerró dos huecos
      del alta:** ahora asigna rol base `$user->syncRoles(['crew'])` y **escribe el pivote `production_user`** (`department_id`,
      `role='crew'`, `is_lead=false`) — antes `newuser` solo escribía el string legacy `puestodepartamento` y **nunca poblaba el
      pivote**, dejando al crew nuevo invisible para las listas acotadas de (a). Mail de bienvenida en try/catch (ya no 500ea tras
      crear); PRG (`redirect('/adduser')` + flash). `adduser` pasa `$lockedDept` (helper `lockedDeptName($viewer)`); la vista
      `admin/newuser.blade.php` muestra flashes y un campo de depto **condicional** (input readonly fijo para HOD, sin `name`;
      `<select name="zone">` para all-departments). Verificado: `php -l`, render en ambos estados, transacción rollback. *(Nota
      legacy: `zone`=Departamento, `puestodepartamento`=solo el puesto, `labn`=Jerarquía — deuda de columnas reusadas, NO bloquea
      #2; la verdad del scoping es el pivote.)*
      • **(b.2) ✅ ALTA DE CREW CONECTADA AL CATÁLOGO `departments`/`positions` — HECHO Y VERIFICADO** *(2026-06-25 — `php -l` /
      render en ambos estados / conteos del catálogo en tinker / rechazo cross-departamento / `view:cache`)*: el form de alta dejó
      de usar la **lista hardcodeada de 22 deptos** + texto libre y se **cableó a las tablas normalizadas ya existentes**
      (35 deptos / 196 puestos de catálogo). `AdminController@adduser` carga el catálogo real (`$departments` +
      `$positions` global = `production_id IS NULL`, filtrado al depto del HOD si aplica; helper `lockedDeptName()` inlinado y
      eliminado); la vista `admin/newuser.blade.php` usa un **`<select name="department_id">`** (o hidden fijo para HOD) + un
      **`<select name="position_id">` dependiente** llenado por JS desde `@json($positions)` (puesto OPCIONAL); `newuser` resuelve
      `department_id`/`position_id` **contra el catálogo, sin confiar en el form** (un puesto de otro departamento se **rechaza**;
      el depto del HOD se fuerza) y escribe **FK REALES** al pivote `production_user` (`department_id` + `position_id` — antes
      `position_id` era **siempre null**), conservando `zone` (nombre depto) y `puestodepartamento` (`"Depto-Puesto"`) por compat
      legacy. **Primer paso concreto hacia la formalización de esquema de §3.5** (FK reales en el pivote desde el catálogo); la
      formalización completa (migraciones de todas las tablas, renombres `zone`/`age`/`daytest`, `medical_records`, FKs duras)
      sigue siendo el trabajo mayor de [ROADMAP.md](ROADMAP.md) §3.5 a hacer en incrementos dedicados.
      **(6) ✅ CATÁLOGOS → `catalogs.view` / `catalogs.manage` — HECHO Y VERIFICADO** *(2026-06-25 — `php -l` / `route:list --json` /
      `view:cache` / acceso por rol en `tinker`)*: las rutas de catálogos (departamentos/puestos/notificaciones) salieron del grupo
      legacy `admin` a **dos grupos `permission:` (cada uno con `auth`)** en `routes/web.php` — **`catalogs.view`** = los tres index
      (`departamentocrud`/`positionscrud`/`notificacioncrud`); **`catalogs.manage`** = todas las mutaciones + GET de edición
      (`creardepartamento`/`activardepartamento`/`desactivardepartamento`/`editardepartamento`/`savedepartamento`/`crearpositions`/
      `editpositions`/`saveposition`/`crearnotificacion`/`activarnotificacion`/`desactivarnotificacion`/`editarnotificacion`/
      `savenotificacion`). **Sección "Catálogos" en el sidebar** (`layouts/sidebar.blade.php`, desktop + espejo móvil) gateada
      `@can('catalogs.view')` (Departamentos/Puestos/Notificaciones). Acceso por rol: `catalogs.view` = super-admin/line-producer/
      coordinator/hod/safety-officer/auditor; `catalogs.manage` = super-admin/line-producer. Las rutas migradas muestran
      `permission:catalogs.*` y **ya NO** `AdminMiddleware`; sin dups de nombre por la migración. **Con esto el flag `admin` queda
      casi totalmente retirado** (lo que sobra: COVID + import/export + mail).
      **(7) ✅ POLISH DE BOTONES DE CATÁLOGOS → `@can('catalogs.manage')` — HECHO Y VERIFICADO** *(2026-06-25 — `Blade::compileString`
      + `php -l` sin desbalance `@if/@can` / render de `departamentocrud` en ambos roles / `view:cache`)*: los controles de gestión de
      las tres vistas legacy de CRUD de catálogos (`admin/departamentocrud`/`positionscrud`/`notificacioncrud`) ya van envueltos en
      **`@can('catalogs.manage')`** — se gatearon tres bloques por vista: la card de crear (POST a `/creardepartamento`/
      `/crearpositions`/`/crearnotificacion`), el `<th>Acciones</th>` (en positions: "actions") y el `<td>` de acciones por fila
      (editar + activar/desactivar). Las columnas de listado (id/name/correo) + paginación **siguen visibles** para un rol
      `catalogs.view`-only. Resultado: coordinator/hod/safety-officer/auditor ven el LISTADO pero no los controles de gestión (cierra
      el "visible-pero-403"). Verificado que super-admin (`catalogs.manage`) ve crear + editar + columna Acciones, y `test.hod`
      (`catalogs.view`-only) no ve ninguno pero conserva el listado. **Con esto los catálogos quedan TOTALMENTE pulidos.**
      **(8) ✅ EDITOR EN VIVO DE LA MATRIZ ROL→PERMISO ("Permisos por rol") — HECHO Y VERIFICADO** *(2026-06-25 — el permiso existe y
      SOLO `super-admin` lo tiene; el view renderiza ~278KB; round-trip de `update()` persiste sin borrar otros roles; re-sembrar
      restaura la fábrica)*. Siguiendo **la receta** (RBAC fino + endpoint dedicado, igual que la pantalla de asignación de roles):
      nuevo permiso **`roles.manage-permissions`** en `RolesAndPermissionsSeeder.php` (por **DEFAULT solo `super-admin`** vía
      `Permission::all()`; conteo **42 → 43**); nuevo controller `app/Http/Controllers/RolePermissionController.php` (`edit()` =
      matriz agrupada por módulo; `update()` = `syncPermissions` por rol editable + `forgetCachedPermissions()`); nueva vista
      `resources/views/admin/role-permissions.blade.php` (checkboxes agrupados, aviso "cambios en vivo", columna `super-admin`
      bloqueada). Rutas `GET /permisoscrud` (`roles.permissions.edit`) + `POST /permisoscrud` (`roles.permissions.update`) gated
      `permission:roles.manage-permissions`; ítem **"Permisos por rol"** en el sidebar (sección Admin, desktop + móvil) gateado
      `@can('roles.manage-permissions')`. **🛡️** Columna `super-admin` de **solo lectura** (god-mode) + **guard anti-auto-bloqueo**
      (un no-super-admin no puede quitarse a sí mismo `roles.manage-permissions`). **⚠️ "valor de fábrica" vs "estado vivo":** el
      seeder es el **valor de fábrica** y la BD el **estado vivo**. **✅ RESUELTO (2026-06-25):** el seeder es **NO-DESTRUCTIVO** —
      una guarda (`Role::has('permissions')->exists()`) detecta una instancia viva y **preserva** los grants editados (registra
      permisos nuevos pero no re-sincroniza), con override `RBAC_FORCE_RESEED=true` para un reset intencional. **NO** se hizo botón
      "restaurar a fábrica" (descartado como footgun de auto-destruir). Detalle en [PROGRESS.md](PROGRESS.md) (entrada
      🛡️ SEEDER RBAC NO-DESTRUCTIVO / 🎛️ EDITOR EN VIVO DE LA MATRIZ).
      **PENDIENTE (siguiente):** ▶️ (a) **COVID-DECOMMISSION** — la mayor parte de lo que queda en el grupo `admin` es COVID/PCR/lab/
      queue (se elimina, no se "protege"; import/export + mail/reminders podrían recibir sus propios permisos después), y/o continuar
      **partiendo `AdminController`** (God Object) por recurso; y/o (b) **FORMALIZACIÓN DE ESQUEMA (ROADMAP §3.5)** — el vertical
      mayor (migraciones de todas las tablas de dominio, renombres `zone`/`age`/`daytest`/`labn`, `medical_records`, FKs duras).
      Detalle en [AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) (B.1, Parte E) y [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md).
- [ ] Añadir **scoping por producción/departamento** (un usuario solo ve lo de su producción).

### 1.D — Personalización por cliente vía config (modelo de negocio)
- [ ] Extraer a config/BD: branding (logo, colores, nombre app), módulos activos, textos, idioma por defecto.
- [ ] Abstraer **disco/correo/BD** por `.env`/config (filesystems `public`↔`s3`, mail, database) → base AWS-desplegable.
- [ ] Documentar el procedimiento de "nueva instancia para cliente X" como checklist repetible (← la receta de despliegue).

---

## BLOQUE 1.5 — Optimización transversal de vistas/código  *(continuo)*
Objetivo: "optimizar el código completo de las views". **Inventario hecho** en
[VIEWS-INVENTORY.md](VIEWS-INVENTORY.md) (95 vistas auditadas). Se ejecuta por barridos.

### 1.5.A — Borrado seguro inmediato (vistas muertas, sin dependencias)
- [x] **PASADA DE LIMPIEZA DE CÓDIGO MUERTO — ✅ CERRADA (2026-06-24): 16 ítems eliminados; 2 grupos flagged que SE QUEDAN.**
      **CIERRE (2026-06-24):** +2 ELIMINADAS — `componentes/searchusers.bladeBKP.php` (backup, no renderizable) y
      `componentes/searchcom.blade.php` (HUÉRFANA: `AdminController@searchcom` ruta `/searchcom` devuelve `view("componentes.searchusers")`,
      AdminController.php:403, **no** `searchcom`); respaldadas en `_legacy_backup/`, `view:cache` compila → **14→16 ítems**. **CONSERVADAS
      (override del REMOVE de VIEWS-INVENTORY):** `auth/verify` y `auth/passwords/confirm` — referenciadas por traits Laravel UI
      (`VerifiesEmails`→`view('auth.verify')`, `ConfirmsPasswords`→`view('auth.passwords.confirm')`); aunque sus rutas estén deshabilitadas,
      borrarlas arriesga un 500 en auth → se reevaluarán en el vertical de `auth`. **🚩 BUG MARCADO para el owner:** `AdminController@searchcom`
      devuelve la vista `searchusers` en vez de una propia (copy-paste: o `/searchcom` es redundante con `/searchusers`, o debería hacer algo
      distinto). **La pasada de limpieza de código muerto queda CERRADA.**
      ---
      *(Resumen original — 14 ítems iniciales):*
      Todo respaldado en `_legacy_backup/` ANTES de borrar, verificado (`php -l` / `route:list` / `view:cache`), nada borrado
      del backup (regla del owner). **Eliminado:** métodos/rutas — `cropimageController@uploadCropImages` (con "s", sin ruta;
      el `uploadCropImage` real intacto) y `PerfilController@update` + ruta `POST /update` (name `perfil.update`, sin emisor);
      archivos leftover — `admin/idcard.bladeOLD.php`, `public/img/logo-cc-usrs - old.svg`, `public/img/nlogo25_old.png`;
      vistas huérfanas — `welcome`, `admin/checkpoint-mobile`, `admin/injury_report_preview`,
      `componentes/departamentousuarios`, `componentes/historiawr` (sus hermanas `historia`/`historiamr` SÍ se usan),
      `imports/uploadbulk` (`imports.import` SÍ se usa), `modal/modelformulario`, `selectdepartamentos`.
      **Flagged — NO removido (se quedan):** vistas `correos/*` (sin ref. estática pero `queueController@enviarCorreoTest` hace
      `Mail::send` con nombre de vista DINÁMICO → uso en runtime; además son del módulo de correos masivos a futuro);
      `admin/consultas` (ruta COMENTADA a propósito en `web.php`). Ver entrada 🧹 en [PROGRESS.md](PROGRESS.md).
      **📧 CORREOS — decisión del owner CONFIRMADA (2026-06-24): SE QUEDAN TODAS.** Las `correos/*` se envían al registrarse y para
      eventualidades específicas; el owner cree que con certeza solo `nuevoingreso` funciona, pero **reutilizaban archivos y no
      está seguro** → **NO se borran** (confirma el flag). Se migran a layouts/Mailables en 1.5.D/1.6, no se eliminan.
      Eliminar: ~~`home`~~ *(ya en PASO 5 del Home)*, ~~`welcome`~~, ~~`selectdepartamentos`~~, ~~`modal/modelformulario`~~,
      `auth/verify` *(CONSERVADA: referenciada por trait Laravel UI `VerifiesEmails` → no se borra)*,
      `auth/passwords/confirm` *(CONSERVADA: referenciada por trait Laravel UI `ConfirmsPasswords` → no se borra)*, ~~`injury_report_preview`~~, ~~`imports/uploadbulk`~~,
      ~~`componentes/departamentousuarios`~~, ~~`componentes/historiawr`~~, ~~`componentes/searchcom`~~ *(HECHO 2026-06-24)*,
      ~~`admin/consultas`~~ *(flagged: ruta comentada → se deja)*, ~~`admin/checkpoint-mobile`~~, ~~`admin/idcard.bladeOLD.php`~~, ~~`componentes/searchusers.bladeBKP.php`~~ *(HECHO 2026-06-24)*.

### 1.5.B — Bugs reales a corregir (ver lista en VIEWS-INVENTORY)
- [ ] **`location` V1 `store()` redirige a ruta inexistente → 500 al guardar.** Corregir a `location.crud`.
- [x] Quitar todos los `<script src="{{ asset('https://...') }}">` (jQuery roto app-wide). *(2026-06-24 — 3 sitios; ver Bloque 0.0)*
- [ ] `cmedica`: typo `ovservations`→`observations`, blindar IMC div/0, restaurar textareas.
- [ ] `<tr>` faltante en `medicocrud`/`usuarioscrud`/`searchusers`; `<select>` duplicado en `editarposition`.
- [ ] `formulario`: unificar a un solo POST (hoy POST + fetch GET duplican guardado).
- [ ] `useredit`: `password` a `type="password"`; arreglar `from` roto en `ReminderEmail`/`encuestasTask`.

### 1.5.C — Unificar a UN stack visual (Bootstrap 5)
- [ ] Retirar **Materialize** (CRUD catálogos, pcr*, consultas) y el **Tailwind por CDN** de `dailyreports/show`.
      **▶️ `dailyreports/show` = candidato al PRÓXIMO VERTICAL** (Home y Profile ya CERRADOS; misma patología de stack CSS
      contaminado → reconstruir con [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md)).
- [ ] Un solo jQuery cargado una vez en el layout; quitar BS4/jQuery3.4.1/jQuery1.11.2 recargados en perfil/print.
- [~] **`inicio`/`perfil`/`profile`: migrar BS4→BS5 (tarea cuidadosa, NO solo reordenar assets).** Estas vistas cargan su
      propio Bootstrap 4 + jQuery ANTES del `@extends` (por eso caen en Quirks mode). Mover ese bloque sin migrar rompe el
      Home (invierte qué Bootstrap gana — ver bitácora 2026-06-24). El fix correcto: quitar BS4/jQuery duplicados, migrar
      clases/utilidades y **modales `data-toggle`→`data-bs-toggle`** a BS5, conservar solo cropper.js, y dejar el doctype
      primero (standards mode). Requiere verificación visual con login. Esto también elimina la advertencia de Quirks mode.
      - **`inicio` (el Home): ✅ RECONSTRUIDO, VERIFICADO EN VIVO y LIMPIADO (PASO 5 HECHO)** *(2026-06-24 — PRIMER
        artefacto bajo [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md); compila, `view:cache` OK; **el owner verificó el cropper
        crew en vivo, OK**).* Hecho: `@extends` como primera línea (mata Quirks); estilos→`public/css/inicio.css` y
        cropper→`public/js/inicio.js` cargados vía `@push('styles')`/`@push('scripts')` (mecanismo `@stack` agregado al
        layout `app.blade.php`); **modal del cropper portado BS4→BS5** (`bootstrap.Modal.getOrCreateInstance(...).show()`,
        `btn-close`/`data-bs-dismiss`) → retirado de la vista el stack BS4 + Popper 1.x + jQuery duplicado.
        **PASO 5 (limpieza/retiro) — ✅ HECHO:** retirado el `admin ||` → switch `@if(auth()->user()->can('users.view'))`
        **puro** (RBAC); eliminadas **6 vars muertas** de `HomeController@index` + el `use ...DB;` huérfano (conservadas las
        6 cards + `$weeklyUnsafeReports` de la gráfica diferida); borrado el `home.blade.php` huérfano (respaldado en
        `_legacy_backup/`). Verificado `php -l` / `view:cache` / `route:list`. **🔒 El borrado de `_legacy_backup/` NO es
        parte del paso 5: por regla del owner (2026-06-24) los backups se conservan hasta el "final final" del proyecto y se
        borran AL CIERRE** (ver [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) paso 5). Con eso el vertical Home queda **CERRADO**,
        sin pendientes abiertos. BS4: no se borró nada — el único BS4 vivo está en `profile`/`perfil` (siguiente vertical),
        intacto.
      - **`profile`: ✅ RECONSTRUIDO, VERIFICADO EN VIVO y LIMPIADO (PASO 5 HECHO) — CERRADO** *(2026-06-24 — SEGUNDO
        vertical cerrado bajo [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md); compila, `view:cache` OK; el owner **verificó
        `/profile` en vivo**: cropper OK y el bloque del cuestionario **SE VE**; dossier en [PERFIL-DOSSIER.md](PERFIL-DOSSIER.md)).*
        **`profile.blade.php` = vista VIVA** (`/profile` → `PerfilController@indexb`). Hecho: `@extends('layouts.app')` como
        primera línea (mata Quirks); estilos→`public/css/profile.css` y cropper→`public/js/profile.js` vía `@push`; **modal
        del cropper portado BS4→BS5** (`bootstrap.Modal.getOrCreateInstance(...).show()/.hide()`, `btn-close`/`data-bs-dismiss`)
        → retirado de la vista el stack BS4 + Popper 1.x + jQuery duplicado; CSRF conservador (meta `_token` +
        `window.uploadCropImageUrl` = `route('uploadCropImage')`). **🛠️ El bug de anidamiento (orig. 96-105) fue CORREGIDO**
        (el bloque del cuestionario se reubicó FUERA del modal `display:none`, visible y balanceado, copy verbatim).
        **PASO 5 (limpieza/retiro) — ✅ HECHO:** borrado el huérfano `perfil.blade.php` + `PerfilController@index`
        (respaldados); **ruta `/profile` DEDUPLICADA conservando la de `auth`, NO la de `admin`** (cualquier autenticado ve su
        propio perfil = comportamiento vivo; quitar la de `auth` habría bloqueado el perfil a los crew = regresión),
        `->name('perfil')` intacto; **quitado el `method_field('post')` redundante**. Verificado `php -l` / `route:list` /
        `view:cache`. **🪦 Candidatos de código muerto MARCADOS (no removidos, para la pasada futura de limpieza de
        rutas/controladores):** `PerfilController@update` (ruta `POST /update`, name `perfil.update`, sin emisor vivo, probable
        muerto) y `cropimageController@uploadCropImages` (con "s", **SIN ruta, comprobadamente muerto**). **🧩 Ruido de consola
        de la vista crew = privacidad de Firefox CONFIRMADO** (persiste en **incógnito** → es el font-visibility /
        anti-fingerprinting de Firefox bloqueando el font-stack por defecto de Bootstrap, Helvetica/Noto, fuentes que la app no
        usa; NO código nuestro). Decisión del owner: se deja; se limpiará en el **pulido tipográfico del rediseño final**. Sin
        gating `admin`/`daytest` (solo `encuestadiaria`, que es estado). **El borrado de `_legacy_backup/` va al cierre del
        proyecto** (regla del owner — no es parte del paso 5). **▶️ PRÓXIMO VERTICAL:** elegir el siguiente con la misma
        patología — p. ej. **`dailyreports/show` (Tailwind por CDN)**, ver el ítem de Tailwind/Materialize arriba en 1.5.C.
      - **🔧 ✅ HECHO (2026-06-24) — fix de iconos de las cards:** los iconos SVG del dashboard
        **desaparecieron** tras la reconstrucción por un **bug de CSS sin unidades** (`top: 12` → `top: 12px`) en
        `public/css/inicio.css`, expuesto al migrar BS4→BS5. **Corregidos los 4 `.fondo-svg*`** (valores sin unidad
        → `px`); fix fiel-a-producción. **Requiere `Ctrl+F5`** (caché / service worker) para verse.
      - **🅿️ DIFERIDO AL CIERRE — rediseño visual del dashboard (NO es el fix de iconos):** el **rediseño gráfico** de
        las cards/iconografía, **replantear qué métricas muestra** (coordinación de producción + rodaje: días/hojas
        filmadas, call sheets, calendario + H&S clave) y **eliminar la gráfica "Tendencia Semanal de Reportes Inseguros"**
        se hacen **al final del proyecto** (ver Bloque de pulido/UI final, abajo). Decisión del owner 2026-06-24.
- [ ] Pasar a **Laravel Mix + `mix()`** (bundle + cache-busting); sacar `<style>` inline a `resources/sass`.
- [ ] **Carga condicional** de TinyMCE/Chart.js (vía `@push/@stack`), no en el shell de cada página.

### 1.5.D — Componentizar (matar duplicación)
- [~] **🔍 Search Global — ✅ CONSOLIDADO (motor por preset: `users` + `idcard`); muertos borrados; COVID a decommission;
      pendiente SOLO la verificación en vivo del idcard + borrado de los métodos viejos.**
      *(2026-06-24 — LIMPIEZA HECHA)* Cerrada la limpieza de restos del search: **ELIMINADOS** los endpoints COVID `searchlab`
      y `searcheckpoint` (método + ruta + parciales `componentes/searchlab`/`searcheckpoint`) y los 3 parciales huérfanos
      `componentes/searchusers`/`searchidcard`/`searchdoctor` (sin caller; reemplazados por `search-results`/`search-results-idcard`).
      **Vivo restante:** solo `componentes/searchqueue` (`queueController@searchqueue`, COVID, se va con la cola). Páginas host
      `pcrtest/listcrew` y `admin/checkpoint` siguen pendientes de decommission COVID. Respaldado en `_legacy_backup/`; `php -l` /
      `route:list` / `view:cache` OK.
      *(2026-06-24 — CONSOLIDACIÓN)* `SearchController` es ahora un **motor parametrizado por preset** con un helper protegido
      `runSearch(array $preset, Request, $valor)` que centraliza la lógica segura (`abort_unless(can('crew.view'))`, Eloquent +
      `select()` explícito sin `SELECT *`, `OR` agrupado en closure, alcance por depto restrictivo vía
      `production_user.department_id`, sentinela `"vacio"`, paginación 50, flags de visibilidad). **`users(...)`** → parcial
      `componentes/search-results.blade.php` (salida idéntica a la versión ya verificada en vivo). **`idcards(...)`** → **NUEVO
      parcial `componentes/search-results-idcard.blade.php`** (Zone badge, Foto, F.Name, L.Name, L.Name2, link de gafete,
      check/uncheck Printed, tinte verde si `age==1`; **columnas COVID `labn` ELIMINADAS** — solo estaban en el filtro).
      Ruta `/searchidcard` re-apuntada (alias strangler) a `SearchController@idcards`. **🗑️ MUERTOS BORRADOS:**
      `AdminController@searchcom`/`@searchdoctor` + rutas `/searchcom`,`/searchdoctor` (cero callers). **DEJADOS** (se borran tras
      verificación en vivo del idcard): los viejos `AdminController@searchusers`/`@searchidcard`. **COVID** (`searchlab`/
      `searchqueue`/`searcheckpoint`) **a decommission** (fuera del motor). Verificado `php -l` / `route:list` / `view:cache`.
      **PENDIENTE:** verificación en vivo del idcard/gafetes por el owner (idéntico + Network sin hash) → **borrar los métodos
      viejos `searchusers`/`searchidcard`**. (Opcional futuro: helper JS `searchTable()` con debounce + paginación AJAX real;
      presets de `reportes`/`locaciones` cuando esos módulos se pulan.) *(Descifrado/diseño en [SEARCH-DOSSIER.md](SEARCH-DOSSIER.md).)*
      ---
      *(2026-06-24 — INCREMENTO 1)* Construido `SearchController@users` (`app/Http/Controllers/SearchController.php`): **Eloquent +
      `select()` explícito** (sin `SELECT *` → `$hidden` aplica, **el hash de password ya NO viaja en el payload**), **`OR` agrupado
      en closure** (arregla el bug), autoriza `can('crew.view')`, **alcance por depto** vía `production_user.department_id` si el rol
      NO tiene `crew.view.all-departments` (default RESTRICTIVO: sin depto determinado, no devuelve nada), visibilidad de campos por
      flags (`$canContact`/`$canPersonal`/`$canMedical`), paginación + sentinela `"vacio"` preservados, sin columnas COVID. Parcial
      reutilizable `componentes/search-results.blade.php` (**arregla el `<tr>` faltante**, dedupe badges/paginación; columnas
      idénticas a hoy para super-admin/admin → sin regresión). **+3 permisos** en `RolesAndPermissionsSeeder.php` (idempotente, 39→42):
      `crew.view.contact`, `crew.view.personal`, `crew.view.all-departments`; **`hod` queda restringido a su depto** (contact sí,
      all-departments no) y **`medic` ahora puede REPORTAR ACCIDENTES y buscar** (`crew.view`+contact+personal+all-departments +
      `injury.view`+`injury.create`). **Ruta `/searchusers` re-apuntada (alias STRANGLER)** a `SearchController@users` — mismo nombre
      `searchusers`, mismo grupo `admin`; `AdminController@searchusers` y los demás search **se dejan intactos**. Verificado `php -l` /
      `route:list` / `view:cache`. **PENDIENTE:** verificación en vivo del owner → migrar `comcrud`/`medicocrud` (ya pegan a
      `/searchusers`) + los demás endpoints search CRUD por CRUD → **borrar los métodos search muertos de `AdminController`**
      (`searchcom`/`searchdoctor`). Helper JS `searchTable()` (debounce + paginación AJAX) sigue pendiente. *(Diseño/descifrado en
      [SEARCH-DOSSIER.md](SEARCH-DOSSIER.md).)*
      ---
      *(Contexto original — descifrado/diseño)*. Hay **8 endpoints de búsqueda**: 7 devuelven HTML para
      inyección AJAX (6 en `AdminController` — `searchusers`/`searchcom`/`searchdoctor`/`searcheckpoint`/`searchidcard`/`searchlab` —
      + `queueController@searchqueue`) y 1 moderno JSON aislado (`InjuryReportController@searchUsers`); todos bajo middleware `admin`.
      **Diseño PROPUESTO:** un `SearchController` parametrizado por entidad/preset con `select()` explícito de columnas seguras
      (sin hash en el payload), **Eloquent + `$hidden`** (los 7 HTML usan `DB::table('users')` + `SELECT *` → hoy filtran el hash
      `password` y campos clínicos en cada fila AJAX, cross-ref [SECURITY.md](SECURITY.md) M6), **`OR` agrupado en closure** (arregla
      el bug de filtro de `searchlab`/`searchqueue`), autorización **por permiso** (`can:users.search` o equivalente), parcial
      reutilizable `search-results.blade.php` (arregla el `<tr>` faltante en `searchusers`/`searcheckpoint` + el HTML duplicado) y un
      helper JS `searchTable()` con debounce + paginación AJAX (hoy `->links()` no está cableado). **Migración STRANGLER:** construir
      lo nuevo sin tocar rutas → aliasar rutas legacy devolviendo el mismo HTML (preserva el contrato `keyup #search → fetch →
      $('#usertable').html()`, cero regresión) → migrar CRUD por CRUD → borrar muertos + lo viejo al final. **Faithful:** se
      mantienen las columnas visibles hoy; **reducir la PII mostrada = decisión aparte marcada para el owner.** Reemplaza el
      `_crew-table` de abajo.
- [ ] `_crew-table` parametrizado (columnas + slot de acción) ← colapsa los **7 `search*`** + cuerpo de `usuarioscrud`.
      *(Lo cubre el `SearchController` unificado de arriba.)*
- [ ] **🗑️ Borrar en la migración del search: `searchcom` y `searchdoctor` = CÓDIGO MUERTO confirmado** *(2026-06-24)* — ningún
      Blade los llama (`comcrud`/`medicocrud` pegan a `/searchusers/`); `searchcom` ≡ `searchusers`. Se eliminan al final de la
      migración del SearchController (no antes, para no romper rutas legacy aliasadas durante el strangler).
- [ ] Parciales `_print-script`, `_image-fields`, `_zone-badge`, `_group-badge` (hoy repetidos a mano).
- [ ] CRUD genérico de catálogos ← departamento/posición/notificación + sus `editar*`.
- [ ] Layout único de menú reutilizado por desktop y offcanvas (hoy duplicado ×3 roles); arreglar `href="#"` rotos.
- [ ] **Correos:** `correos/layouts/base` (de CrewCareTemplate) + secciones de contenido; migrar a Mailables tipados.

### 1.5.E — Datos sanos en controllers (perf)
- [ ] Nunca `SELECT *` de `users` a la vista (expone hash) → `select()` de columnas usadas.
- [ ] `with()/withCount()` (eager load) — empezar por N+1 de `dailyreports/index` (`withCount('logs')`).
- [ ] `paginate()` en `hazards`/`unsafeconds`; `select`+`paginate` en `locationcrud` (161 cols).
- [ ] Quitar queries desperdiciadas (`idcard()`, colecciones no usadas en `HomeController`).
- [ ] Correos masivos a `queue()` en vez de `send()` en loop (ver [PERFORMANCE.md](PERFORMANCE.md)).

### 1.5.F — Branding/textos por cliente → config (modelo per-cliente)
- [ ] Sacar logo/nombre/colores/firmas/`from` quemados (header, login, correos: hoy 4 dominios "from" y 3 hosts de logo) a **config**.

### 1.5.G — i18n real (de [CODE-AUDIT.md](CODE-AUDIT.md) §5)
- [ ] Reescribir `en/messages.php` en inglés real (hoy contaminado con español), quitar clave duplicada `answersecond`, sincronizar claves `en`↔`es`, unificar convención (descartar `es.json`).
- [ ] Borrar `LocaleMiddleware.php` (muerto); `config/app.php` locale → `env()`. Extraer strings hardcodeados de las ~82 vistas a `messages.php`.

---

## BLOQUE 1.6 — Backend: estructura y robustez  *(de [CODE-AUDIT.md](CODE-AUDIT.md))*
Objetivo: que el backend deje de ser frágil. Se hace junto con Bloque 1 (mismo esquema nuevo).
- [ ] **Partir `AdminController`** (892 ln / God Object) en controllers por recurso; partir `FormulariosController`.
- [ ] Introducir **Form Requests** (hoy 0) para toda mutación; persistir `$validated`, nunca `$request->all()`.
- [ ] Capa **Services/Actions**: `SurveyService`, `BulkMailService`, `ImageUploadService`, `UserSearchService`, `CsvExportService` (matan la duplicación copy-paste).
- [ ] **Eloquent en todo** (retirar `DB::table`); declarar relaciones en `User` y junctions; `$casts` en vez de `$dates`.
- [ ] **`DB::transaction()`** en flujos multi-paso (selectpuesto, encuesta, y lo que sobreviva de PCR).
- [ ] **Mailables + colas** (`Mail::to()->queue()`); retirar `Mail::send` en loop.
- [ ] Renombrar clases de modelo a **PascalCase** (rompen en Linux/AWS hoy por minúsculas).

## BLOQUE 2 — Documental + firma electrónica
*(detalle en [ROADMAP §Fase 2](ROADMAP.md). Resumen accionable; se desglosa al llegar.)*
- [ ] Motor de plantillas: `DocumentTemplate`, `Document`, variables `{{...}}`, render DomPDF.
- [ ] **Paquetes por producción:** `DocumentPackage` (qué documentos exige cada producción),
      asignación a crew, estado por documento (pendiente/enviado/firmado/vencido), dashboard "quién falta".
- [ ] Firma interna: `SignatureRequest` + `Signature` (trazo, timestamp, IP, **hash del PDF**, bitácora).
      Campo `provider` + tabla de evidencia desde el día 1 (extensible a NOM-151/externo).
- [ ] Módulo **Reportes Médicos** sobre `medical_records` + `cmedic`, permiso `medic`.
- [ ] Documentos de arranque: Hiring Form → Deal Memo → NDA → Contrato → Safety Guidelines.

---

## BLOQUE 3 — Operación de set (territorio Scenechronize)
*(detalle en [ROADMAP §Fase 3](ROADMAP.md). Se desglosa al llegar.)*
- [ ] Cast & Crew sobre la fundación de roles/producciones.
- [ ] Breakdown de guion: `Script` → `Scene` → `BreakdownElement`.
- [ ] Sides personalizados (PDF por persona/depto) usando el motor de plantillas.
- [ ] Call sheets / scheduling conectados con el DSR de Health & Safety.

---

## BLOQUE 3.5 — PWA real (instalable + offline)  *(de [CODE-AUDIT.md](CODE-AUDIT.md) §4)*
La PWA nunca funcionó porque el service worker cachea archivos inexistentes → falla la instalación.
- [ ] Publicar iconos `php artisan laravelpwa:publish` + correr el build (ya en Bloque 0.0).
- [ ] `offline.blade.php` **standalone** (sin `@extends('layouts.app')`, sin auth, sin CDNs).
- [ ] Reemplazar el service worker por uno con **Workbox** (precache automático + runtime caching + network-first navegación). Mantener el paquete solo para el manifest.
- [ ] `APP_URL` con `https://`, `scope:'/'`, servir por HTTPS; `a2hs.js` real o quitarlo.

## BLOQUE 4 — Upgrade de framework + AWS + puente BlackHouse
*(detalle en [ROADMAP §Fase 4 y §4.5](ROADMAP.md); ruta de upgrade en [CODE-AUDIT.md](CODE-AUDIT.md) §3.)*
- [ ] **🔮 FUTURO/diferido — Actualizar Laravel (8.x → LTS/actual soportada) por seguridad y compatibilidad de librerías.**
      Objetivo: mantener vigente el soporte de seguridad y la compatibilidad de dependencias (incl. `spatie/laravel-permission`
      y futuras). **Hacer en una COPIA aislada, NO en esta carpeta, y solo DESPUÉS de la reconstrucción por verticales**
      (cada parte verificada contra producción). Razón: bugs "load-bearing" + drift de migraciones → un salto mayor sobre la
      base actual es riesgoso. **Aislamiento:** `composer update` solo reescribe `crewcarerr/vendor/`; NO afecta a las demás
      apps de `C:\laragon\www\` (lo único compartido es el binario de PHP global, que solo cambia si se toca a mano). Procedimiento:
      revisar el upgrade guide de Laravel por cada salto mayor y verificar la versión de PHP requerida antes de tocar el PHP global.
- [ ] **Upgrade por etapas Laravel 8→9→10→11, PHP 7.3→8.2+** (~4-6 sem). Encadenar el refactor de uploads→S3
      (Flysystem 3) en la etapa L8→9. Quitar fruitcake-cors, facade/ignition; subir sanctum/ui/phpunit.
- [ ] Config-driven multi-servidor: `SESSION`/`CACHE`→redis, `QUEUE`→async, uploads→disco `s3` configurable.
- [ ] Despliegue AWS (S3 → RDS → SES → cómputo) para clientes tier-Amazon; doble pista VPS/AWS.
- [ ] API real (Sanctum) para el puente con BlackHouse (locaciones ↔ H&S de locación).
- [ ] Saneamiento pre-GitHub (cuando se haga `git init` limpio): `.gitignore`, `.env` fuera, rotar secretos.
- [x] **TinyMCE Cloud REMOVIDO** *(2026-06-24 — pedido por el owner)*. La API key estaba atada al dominio real → en otros
      orígenes el editor quedaba **read-only** y lanzaba errores en consola. Se quitó el `<script>` de Tiny Cloud y el
      `tinymce.init` de `layouts/app.blade.php`; el `<textarea>#MyEmail` quedó como textarea plano. `view:cache` compila OK.
- [ ] **Re-introducir editor enriquecido (self-hosted) al construir correos masivos** *(futuro, junto al Bloque 2 documental
      / módulo de campañas)*. Self-hostear TinyMCE (sin Cloud/API key) o usar un editor alterno, **preservando los layouts de
      correo tipo Mailchimp** en `resources/views/correos/*`. Decidir junto con la limpieza general de dependencias del front.
- [ ] **🔮 Self-hostear Font Awesome (backlog de pulido/seguridad).** El fix de las 413 advertencias *(2026-06-24, ver
      Bloque 0.0)* subió FA a **6.7.2 por CDN y removió el `integrity` (SRI)** porque el hash no se podía verificar offline.
      **Mejora recomendada:** descargar el release de FA a `public/` (self-host) → elimina la dependencia del CDN (ideal para
      la red corporativa / offline) y permite **restaurar `integrity` con un hash propio verificable**. Hacer junto con la
      limpieza general de dependencias del front.

---

## BLOQUE 5 — Pulido / UI final  *(al cierre del proyecto)*
Objetivo: el barniz visual final, una vez que los verticales y sus datos ya existen.
- [ ] **🅿️ Rediseño visual del dashboard del Home (DIFERIDO "al final final final de todo").** Decisión del owner
      (2026-06-24): rediseño gráfico de las **cards/iconografía**, **replantear qué métricas muestra** (coordinación de
      producción + rodaje: días/hojas filmadas, call sheets, calendario + H&S clave) y **eliminar la gráfica
      "Tendencia Semanal de Reportes Inseguros"**. **Depende de tablas que aún no existen:** la mitad de
      producción/rodaje (días de rodaje, hojas filmadas, call sheets, calendario) **no tiene respaldo** → requiere
      **crear primero sus tablas** (incl. `productions`); hoy el "Días de rodaje" es un literal hardcodeado y "Días de
      producción" un proxy. La mitad **H&S** sí es cableable con datos reales hoy (`location_report`, `cmedic`,
      `injury_reports`, `hazardnotifications`/`unsafeconds`). **NO confundir con** el fix de iconos de las cards
      (`top: 12`→`top: 12px` en `inicio.css`), que es pendiente cercano del cierre del Home, no diferido (ver 1.5.C).

---

## Pendiente de decidir / preguntas abiertas
- ¿Un solo framework CSS objetivo (Bootstrap 5) y se retira Materialize? (recomendado)
- ¿`encuestas:task` (scheduler cada minuto) se conserva, reescala o se elimina?
- Orden de despliegue AWS: ¿qué cliente tier-Amazon es el primero que lo exige y para cuándo?
