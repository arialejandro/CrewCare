# SECURITY.md — CrewCare (Auditoría de Seguridad)

Auditoría de seguridad **read-only** sobre el código de la app Laravel 8 en `C:\laragon\www\crewcarerr`.
No se modificó ningún archivo de la aplicación; el único archivo escrito es este documento.
Cada hallazgo fue **verificado contra el código fuente** (no se confió ciegamente en `ARCHITECTURE.md`).
Las referencias son `archivo:línea`.

> Nota: Donde `ARCHITECTURE.md` ya mencionaba un problema, aquí se re-verifica y se amplía con
> evidencia y se sube la severidad cuando el código lo justifica (p. ej. mutaciones sin auth + mass-assignment de `admin`).

> ### ⚠️ Contexto de exposición (recalibración 2026-06-23)
> El repositorio es **git LOCAL, sin remoto** — nunca se ha hecho push a GitHub ni a
> ningún servidor compartido. Esto **recalibra**, no elimina, los hallazgos:
> - **C1 (secretos en `.env`) y C4 (`APP_DEBUG`)**: el *vector de fuga por repositorio*
>   está **contenido mientras el git siga local**. Pasan de "brecha activa" a
>   **prerequisito obligatorio antes del primer `git push`**.
>   **Decisión del owner (2026-06-23):** se descartará el git actual y se hará un `git init`
>   limpio cuando la app esté más completa → el problema de "secretos en el historial"
>   **desaparece** (historial nuevo). Solo queda como checklist en ese momento futuro:
>   `.gitignore` con `/.env`, `.env` fuera del tracking, `.env.example` con placeholders
>   vacíos, y **rotar secretos** (DB, Mailgun, APP_KEY) antes del primer push a un remoto.
>   Los secretos sí siguen en el `.env` real del servidor, así que el riesgo no es cero,
>   pero ya no es un tema de GitHub ni urgente hoy.
> - **C2 (auto-promoción a admin) y C3 (endpoints sin auth)**: **siguen siendo críticos
>   tal cual**. Son vulnerabilidades de la app en ejecución y no dependen de git — un
>   usuario en el navegador las explota hoy. No se recalibran.
>
> En resumen: lo "git" se posterga al pre-push; lo "app en vivo" sigue urgente.

> ### ✅ Estado del lane de seguridad (2026-06-27) — CRÍTICOS y ALTOS de explotación CERRADOS
> Tras la pasada **"2c / menores"**, **todos los CRÍTICOS y ALTOS de explotación quedan cerrados o recalibrados**:
> - **Críticos:** C2 ✅ resuelto (mass-assignment de `admin`); C3 ✅ todos los sub-ítems "sin auth" cerrados (password/perfil,
>   `/dailyreport`, `/nophoto`, resets "cron-vía-URL" eliminados, `/PhotoReminder` eliminado, **`/reminder-mail` gateado a `admin`**);
>   C1/C4 son **notas de pre-push/deploy** (git local, sin exposición hoy).
> - **Altos:** H5 ✅ `AdminMiddleware` ahora exige `activo`; H3 ⚖️ aceptado/bajo riesgo (CORS inocuo con `supports_credentials=false`);
>   H4 ⚖️ bajo + diferido al refactor de validación. **H2 (IDOR de scope) ✅ CERRADO en TODAS las acciones admin sobre `{id}` de
>   crew** (perfil + toggles + roles + puesto) vía `User::canManageCrewMember` (2026-06-28). H1 (GET→POST) **parcial** — convertidos ya
>   `activarusuario`/`activarencuesta` (2026-06-28); restan otros GET mutantes ya autorizados. Lo abierto es **defensa en profundidad**
>   (acciones ya autenticadas/autorizadas), **sin vector de explotación anónima**.
> - **Lo que resta es BAJO/diferido:** el **refactor de validación** (H4 + `$guarded=[]` de DSR), las **notas de deploy** (C1/C4/PRE-PUSH,
>   `MAIL_FROM_ADRESS`, `APP_URL` local), `niveldos`/Policies (H5), y la **reimplementación de `badge:reminder`**. Ninguno es bloqueante
>   ni explotable por un anónimo. Ver `PROGRESS.md` (entrada **🔒 Seguridad (2c) — menores**).
>
> ### 🧹⚡ Actualización del lane (2026-06-28) — optimización de las 7 verticales operativas
> Pasada transversal sobre las 7 verticales (lista+creación) con varios **hardenings err-restrictivos** registrados aquí:
> - **`DailyReport`/`DailyLog`: `$guarded=[]` → `$fillable` explícito** (avanza el sub-ítem DSR de H4; verificado que cubre lo que el
>   controller escribe → sin pérdida silenciosa; **no era vuln activa** porque `store`/`update` ya validan). Resta `SafetyStandard`.
> - **H2 (IDOR de scope) — cerrado el ÚLTIMO path de crew:** `CrewListController@idcard` (detalle de gafete) ahora hace
>   `findOrFail`+`abort_unless(canManageCrewMember, 403)` — cierra el gap latente que el corte #1 del strangler dejó en `idcard($id)`.
> - **Ruta huérfana `daily_reports.pdf` ELIMINADA** (apuntaba a un `downloadPdf()` inexistente = 500 latente; superficie de error retirada).
> - **Hazard: validación de imagen que NUNCA se aplicaba, ahora SÍ** (validaba `main_image_path` —columna— en vez de `main_image`
>   —input real—; refuerza M2 para hazard).
> - **Autorización: `historialWR` migrado de gate legacy `admin` → `permission:medical.view`** (enlaces gateados con `@can`).
> - **Nuevos pendientes (BAJO, registrados):** doble `json_encode` de `additional_images_paths` (hazard/unsafecond); `crew:welcome-resend`
>   sigue enviando el **hash bcrypt** en lugar de un enlace de reset (L2). Ver `PROGRESS.md` (entrada **🧹⚡ Optimización de las 7
>   verticales operativas**).
>
> ### 🛡️ Actualización del lane (2026-06-28) — endurecimiento de los métodos de creación (`store()`/alta)
> Pasada enfocada en los **métodos de CREACIÓN** con varios hardenings err-restrictivos registrados aquí:
> - **H4 (mass-assignment) — cerrado en 4 `store()` más:** `HazardNotificationController`, `unsafecondNotificationController`,
>   `locationController` (V1) y `locationController2` (V2) usaban `$request->all()` tras validar → ahora usan el **array validado** como
>   base (`$data = $request->validate([...])`) con `unset` de los inputs de archivo antes de `create()`. **Verificado que no se pierde
>   ninguna columna** (reconciliación reglas↔`$fillable`↔form; V1 confirmó 118/118). **Resta** solo el `$request->all()` de catálogos en
>   `AdminController` (LOW, diferido al rediseño de catálogos).
> - **`CrewController@newuser` ahora VALIDA (antes NO validaba NADA):** whitelist alineada al form `admin.newuser`; `email`
>   **required|email|unique:users** y `password` **min:8|confirmed** → cierra el alta con email duplicado/inválido o password débil.
> - **M2 (subida de archivos) reforzado:** `unsafecondNotificationController` y `locationController2` no tenían `mimes`/`max:` en la
>   imagen → ahora `image|mimes:jpeg,png,jpg,gif|max:2048`. Además, en `Hazard` la regla de imágenes adicionales (`additional_images_paths.*`)
>   **nunca aplicaba** (el input real es `additional_images[]`) → corregida a `additional_images`+`additional_images.*`. Nombres de archivo
>   anti-colisión: `time().'_main.'` → `time().'_'.uniqid().'_main.'` en Injury/Hazard/Unsafe/Location.
> - **🐞 BUG real corregido (no-seguridad, registrado):** doble `json_encode` de `additional_images_paths` en `Injury`/`Hazard`/`unsafecond@store`
>   (el modelo ya castea a `array`) **rompía la lectura** de las imágenes adicionales → resuelto (array directo).
> - **Nuevos pendientes (BAJO, registrados):** **(a) atomicidad imagen+registro** — los archivos se guardan antes del `create()`; si el
>   `create()` falla queda una imagen **huérfana** → pendiente un job `DeleteOrphanedImages` o transacción con cleanup; **(b) trait
>   `HandlesImageUploads` (DRY)** — el patrón de subida está duplicado en ~5 controladores. Ver `PROGRESS.md` (entrada **🛡️ Endurecimiento
>   de métodos de creación**).

---

## Tabla resumen de hallazgos

| # | Severidad | Título | Ubicación principal |
|---|---|---|---|
| C1 | **Crítico** | Secretos reales committeados en `.env` y `.env.example` (password BD, SMTP, APP_KEY) | `.env`, `.env.example` |
| C2 | ~~**Crítico**~~ ✅ **RESUELTO (2026-06-25)** | Escalada de privilegios: mass-assignment de `admin` vía endpoints sin auth | `PerfilController.php:22,61`; `routes/web.php:201,208` |
| C3 | **Crítico** (parcial) — ✅ password/perfil/formularios + `/dailyreport` + `/nophoto` cerrados (2026-06-25/26); resets "cron-vía-URL" `/newdayRep`/`/newWR`/`/pruebachedule` **eliminados** (duplicados del comando agendado `encuestas:task`, 2026-06-26); `/PhotoReminder` **también eliminado** (último GET de reset/reminder sin gate; reimplementación diferida como comando `badge:reminder`, 2026-06-26) → sub-ítem "resets de sistema sin auth" **cerrado** | Resets de sistema y endpoints de cambio de password/perfil sin autenticación | `routes/web.php:29-33,198,200-202,208-210` |
| C4 | **Crítico** | `APP_DEBUG=true` en `.env` committeado (fuga de stack traces / config) | `.env` (`APP_DEBUG=true`) |
| H1 | **Alto** (parcial) — ✅ `activarusuario`/`activarencuesta` convertidos a POST+CSRF (2026-06-28); restan otros GET mutantes ya autorizados | Mutación de estado por GET (CSRF) en decenas de rutas admin | `routes/web.php:53-61,74,76,83,…` |
| H2 | **Alto** — ✅ **CERRADO** en TODAS las acciones admin sobre `{id}` de crew (perfil + toggles + roles + puesto) vía `User::canManageCrewMember` (2026-06-28) | IDOR: acciones admin operan sobre `{id}` arbitrario sin verificación de pertenencia | `CrewController.php`, `CrewStatusController.php`, `cmedicController.php` (ya gateados), `PerfilController.php` |
| H3 | ~~**Alto**~~ ⚖️ **ACEPTADO / BAJO RIESGO (2026-06-27)** | CORS permisivo (`allowed_origins=['*']`) — inocuo con `supports_credentials=false` + API mínima (`GET /user` tras `auth:sanctum`) | `config/cors.php:20-22` |
| H4 | ~~**Alto**~~ ⚖️ **BAJO + DIFERIDO (2026-06-27)** — ✅ CERRADO en `acountupdate` vía whitelist validada (2026-06-28); resta el resto (LOW) | Mass-assignment con `$request->all()` / `except()` — ninguno toca `User`/privilegios → al refactor de validación | `AdminController.php:146,185,259,278`; hazard/unsafe/location |
| H5 | **Alto** (parcial) — ✅ `AdminMiddleware` ahora exige `activo` (2026-06-27); resta `niveldos`/Policies | `AdminMiddleware` no verificaba `activo`; `niveldos` es stub no-op; sin Policies/Gates | `AdminMiddleware.php:19`; `niveldos.php:19`; `AuthServiceProvider.php:15` |
| M1 | ~~**Medio**~~ ✅ **RESUELTO (2026-06-27)** | `dd()` en endpoints ruteados (fuga de debug / DoS funcional) | `ReminderMailController.php:22`; `locationController.php:205`; (`MailController`/`newformulario1` ya removidos) |
| M2 | **Medio** | Subida de archivos sin sanitizar extensión (se confía en extensión del cliente) | `InjuryReportController.php:82,91`; hazard/unsafe/location; `cropimageController.php:40` |
| M3 | **Medio** | Operaciones multi-paso sin transacción DB (estados parciales) | `AdminController.php:580-628,650-687,692-743` |
| M4 | **Medio** | `LocaleMiddleware` usa `$request->locale` sin validar | `LocaleMiddleware.php:14` |
| M5 | **Medio** | Tokens Sanctum sin expiración; `AuthenticateSession` deshabilitado | `config/sanctum.php:33`; `Kernel.php:36` |
| M6 | **Medio** | Exposición de PII vía endpoints de búsqueda/CSV (90 días de historial, CSV sin filtro) | `AdminController.php:367-518,856`; `resetController.php:130` |
| L1 | **Bajo** | Direcciones "from" de correo hardcodeadas e inconsistentes (riesgo SPF/DKIM) | `resetController.php:38`; `AdminController.php:595,…`; `FormulariosController.php:377` |
| L2 | **Bajo** | Código muerto con bugs y password sin re-hash en reenvío de bienvenida | `encuestasController.php:27`; `AdminController.php:632-647` |

**Conteo (original):** Crítico = 4 · Alto = 5 · Medio = 6 · Bajo = 2 · **Total = 17**

**Conteo activo (recalibrado 2026-06-27):** desglose de estado actual:
- **Críticos:** C1 (pre-push diferido), C4 (deploy) **activos**; C2 ✅ resuelto; C3 **parcial** (todos los sub-ítems de "sin auth" cerrados; resta solo el GET→POST de mutaciones ya autorizadas, que es H1). → **CRÍTICOS y ALTOS reales: ninguno abierto como crítico de explotación anónima.**
- **Altos:** H1 (GET→POST) **parcial** (✅ `activarusuario`/`activarencuesta` convertidos a POST+CSRF, 2026-06-28; restan otros GET mutantes ya autorizados); H2 (IDOR) **✅ CERRADO en TODAS las acciones admin sobre `{id}` de crew** (perfil `useredit`/`acountupdate` + toggles + roles + puesto) vía `User::canManageCrewMember` (2026-06-28); H5 **parcial** (AdminMiddleware-`activo` ✅ hecho 2026-06-27; resta `niveldos`/Policies); H3 ⚖️ **aceptado/bajo riesgo**; H4 ⚖️ **bajo + diferido a validación** (✅ CERRADO en `acountupdate` vía whitelist validada, 2026-06-28; resta el resto de `$request->all()` de catálogos, LOW); H6 retirado (nota PRE-PUSH). → **Altos activos: 2 (H1-parcial, H5-parcial)**, defensa-en-profundidad / sin explotación anónima.
- **Medios:** M2, M3, M4, M5, M6 **activos**; M1 ✅ **resuelto (2026-06-27)**. → **Medios activos: 5**.
- **Bajos:** L1, L2 **activos**. → **Bajos activos: 2**.
- **Resumen del lane:** los **CRÍTICOS y ALTOS de explotación quedan cerrados o recalibrados**; lo que resta es **BAJO/diferido** — el refactor de validación (H4 + `$guarded=[]` de DSR), notas de deploy (C1/C4/PRE-PUSH), `niveldos`/Policies (H5), y la reimplementación de `badge:reminder`.

---

## CRÍTICOS

### C1 — Secretos reales committeados en `.env` y `.env.example`
- **Ubicación:** `.env` (committeado), `.env.example:3,15,34`
- **Evidencia:**
  - `git ls-files` lista **tanto `.env` como `.env.example`** como archivos versionados (el `.env` con secretos vivos está en el repo).
  - `.env.example:15` → `DB_PASSWORD=AlmostParadise+2022!`
  - `.env.example:34` → `MAIL_PASSWORD="9d6679af29f9a25232c35e04d2bc4164-..."` (credencial Mailgun)
  - `.env.example:3` → `APP_KEY=base64:8IopZRaxAxCq4UnRCb26BKckZgsfLg48U149YkZ9Bgs=` (clave de cifrado fija)
  - El `.env` activo contiene además `MAIL_PASSWORD` con valor no vacío (no se imprime aquí).
- **Impacto:** Cualquiera con acceso al repositorio obtiene credenciales de base de datos y de SMTP de producción, y la `APP_KEY` (que permite forjar/descifrar cookies de sesión, tokens firmados y payloads cifrados). Compromiso total de confidencialidad e integridad.
- **Recomendación:** Rotar de inmediato **todas** las credenciales expuestas (BD, Mailgun, APP_KEY). Eliminar `.env` del control de versiones (`git rm --cached .env`) y purgar el historial. Dejar en `.env.example` solo placeholders vacíos (`DB_PASSWORD=`). Confirmar que `.gitignore` ignora `.env`.

### C2 — Escalada de privilegios por mass-assignment del flag `admin` — ✅ RESUELTO (2026-06-25)
- **Ubicación:** `PerfilController.php:22-45` (`update`), `PerfilController.php:61-73` (`updatepassword`), `AdminController.php:232-245` (`acountupdate`); modelo `User.php:32` (`'admin'` en `$fillable`).
- **Evidencia:**
  ```php
  // PerfilController@update  (ruta /update, SIN auth — web.php:208)
  $datosproducto = request()->except(['_token','_method']);
  User::where('id','=',$users->id)->update($datosproducto);

  // PerfilController@updatepassword  (ruta /updatepassword/{id}, SIN auth — web.php:201)
  $input = $request->except('password');
  $user->fill($input)->save();
  ```
  En `User.php:20-49`, `$fillable` incluye `'admin'`, `'activo'`, `'daytest'`. No hay lista blanca de campos.
- **Impacto:** Un usuario puede enviar `admin=1` (o `activo`, `daytest`) en el cuerpo de la petición y **auto-promoverse a administrador**. Combinado con C3 (estas rutas no tienen `auth`), el ataque es trivial y no requiere ni siquiera estar logueado para `/updatepassword/{id}`.
- **Recomendación:** Nunca pasar `request()->all()/except()` a `update()/fill()`. Usar `$request->validate()` con lista blanca explícita de campos editables por el usuario; excluir `admin`, `activo`, `daytest`, `password` del flujo de perfil. Quitar `admin`/`activo`/`daytest` de `$fillable` y setearlos solo por código en flujos admin dedicados.
- **✅ Resolución (2026-06-25):** Se quitó **`admin` de `$fillable`** en `app/Models/User.php` → el flag ya **no es asignable en masa**: un `fill()/update()` con `admin=1` ya no escribe el campo (verificado en `tinker`: `admin` ausente de `getFillable()`; `fill(['admin'=>1])` no lo setea; los campos normales sí se llenan). El otorgamiento legítimo de admin **no se ve afectado** porque usa **asignación explícita** (`$producto->admin=1` en `AdminController@activaradmin`/`desactivaradmin`, gateado por `permission:users.assign-role`), que ignora `$fillable`. `daytest` se **dejó intencionalmente** en `$fillable`: **no es un vector de autorización** (el acceso a rutas lo imponen los permisos spatie; `daytest` solo afecta el display del sidebar). El segundo half de este hallazgo (las rutas `update`/`updatepassword` sin auth + IDOR) se cerró junto con C3 (ver abajo). **Pendiente menor:** seguir migrando los `request()->except()/all()` de perfil a lista blanca explícita (defensa en profundidad; ya sin vector de privilegio tras quitar `admin`).

### C3 — Endpoints de mutación de estado sin autenticación — ✅ PARCIAL (2026-06-25/26/27): password/perfil/formularios + `/dailyreport` + `/nophoto` cerrados; resets "cron-vía-URL" `/newdayRep`/`/newWR`/`/pruebachedule` **eliminados** (duplicados del comando agendado `encuestas:task`); `/PhotoReminder` **también eliminado** (reimplementación diferida como comando `badge:reminder`); `/reminder-mail` (envío masivo) **gateado a `admin`** (2026-06-27) → sub-ítem "resets/reminders/envío-masivo sin auth" **cerrado**; solo resta el GET→POST de mutaciones ya autorizadas (H1)
- **Ubicación:** `routes/web.php`:
  - Resets de sistema: `:29` `/newdayRep`, `:30` `/PhotoReminder`, `:31` `/newTD`, `:32` `/newWR`, `:33` `/Nresult`, `:198` `/nophoto` → `resetController`.
  - Perfil/password: `:200` `/changepassword`, `:201` `/updatepassword/{id}`, `:208` `/update`, `:209` `/pruebachedule`, `:210-211` `/crop-image*`.
  - Formularios: `:202` `/formularios/registro`, `:203` `/formularios/medicos`.
  - Correos: `:40` `/negative-mail`, `:41` `/reminder-mail`.
- **Evidencia:** Estas rutas están **fuera** del grupo `['middleware' => 'admin']` (web.php:44-197) y del grupo `['auth']` (web.php:204-207). `resetController` no tiene `$this->middleware()` en su constructor (no existe constructor). Ej. `Nresult()` (`resetController.php:85-106`) resetea flags de todos los usuarios activos y **crea registros `prueba`/`usuariopcr`** (resultados clínicos falsos).
- **Impacto:** Cualquier anónimo con la URL puede: resetear el estado de salud/encuesta de toda la plantilla, generar registros médicos falsos, exportar PII por CSV (`/nophoto`), disparar envíos masivos de correo (abuso/spam), y cambiar contraseñas/perfiles arbitrarios (ver C2).
- **Recomendación:** Envolver todas estas rutas en `auth` (y `admin` para los resets/exports/correos). Para `/updatepassword/{id}` y `/update`, además verificar pertenencia (`$id === auth()->id()`). Mover los resets a comandos Artisan + scheduler en lugar de rutas HTTP públicas.
- **✅ Resolución parcial (2026-06-25) — cluster password/perfil/formularios CERRADO:**
  - **Account-takeover (lo más crítico) ELIMINADO.** `/changepassword`, `/updatepassword`, `/formularios/registro`, `/formularios/medicos` y `/profile` estaban **fuera** de cualquier grupo `auth` (el grupo `auth` solo envolvía `/profile`). Ahora **todos** viven dentro de un `Route::middleware(['auth'])->group(...)` en `routes/web.php`.
  - **IDOR eliminado en cambio de password.** La ruta perdió el parámetro `{id}`: ahora es **`POST /updatepassword`** (sin id). `PerfilController@updatepassword` se **reescribió** para operar **solo sobre `auth()->user()`** (sin `$id`), validar `password` (`required|string|min:8`) y setear **únicamente** el password (`Hash::make`) — se **quitó** el `fill($request->except('password'))` (la mass-assignment cruft que metía C2). La vista `resources/views/changepassword.blade.php` apunta su `action` a `route('updatepassword')` (sin id).
  - **Verificado:** `php -l` OK; `route:list` muestra las URIs limpias; `gatherMiddleware` confirma `web,auth` en `updatepassword`/`changepassword`/`perfil`/`formularios-registro`/`formularios-medicos`; la vista `changepassword` renderiza y su form resuelve a `/updatepassword` sin id.
- **✅ Resolución adicional (2026-06-26) — `/dailyreport` y `/nophoto` CERRADOS** (ver `PROGRESS.md`, entrada **🔒 Seguridad (2/n)**):
  - **`/dailyreport` (+ `/dailyreport/{id}`, nombre `encuesta`, `encuestasController@viewencuesta`) GATEADO.** Estaba **fuera** de todo grupo `auth` → se **movió** dentro de un nuevo `Route::middleware(['auth'])->group(...)` en `routes/web.php`. Es el **cuestionario clínico diario del crew** (expediente). Además, `viewencuesta()` usa `auth()->user()->encuestadiaria`/`->id`, así que **sin sesión era también un null-deref (HTTP 500)**. `auth` es el gate correcto.
  - **`/nophoto` (nombre `expCsv`, `resetController@expCsv`, export CSV de PII) GATEADO + PERMISO.** Estaba **fuera** de todo grupo `auth` → se **envolvió** en `Route::middleware(['auth','permission:reports.export'])->group(...)`. Streamea un CSV con la PII de **TODOS** los usuarios activos (`name`/`lname`/`lname2`/`borndate`/`sex`/`phone`/`email`/`puestodepartamento`). `reports.export` es un permiso **ya existente** (lo tienen `super-admin`/`coordinator`/`safety-officer`/`auditor`).
  - **Verificado (tinker):** router arranca (110 rutas); `gatherMiddleware` → `encuesta` = `web,auth`; `expCsv` = `web,auth,permission:reports.export`. `php -l` OK en `resetController.php`.
  - **Refinamiento pendiente (BAJO):** `expCsv` exporta **todos** los activos ignorando el alcance por departamento (`User::applyDepartmentScope`, ver M6) — un titular de `reports.export` sin `crew.view.all-departments` igual obtiene a todos. Aplicar el scope por departamento al export. Prioridad baja.
- **✅ Resolución adicional (2026-06-26) — `/newdayRep`, `/newWR`, `/pruebachedule` ELIMINADOS por ser DUPLICADOS del comando agendado** (ver `PROGRESS.md`, entrada **🧹🔒 Seguridad (2b)**):
  - **Descubrimiento clave:** el reset clínico diario **ya es un comando Artisan agendado** — `app/Console/Kernel.php:27` corre **`encuestas:task` a diario a las 04:00**, y `app/Console/Commands/encuestasTask.php@handle` **resetea `encuestadiaria=0` para todos los usuarios activos y manda el correo recordatorio "DAILY REPORT"** (`correos.recordatorio`). Por tanto **no hacía falta "convertir la familia a Artisan" (como decía la recomendación previa): el scheduler real ya existía.** Estos tres endpoints GET sin gate eran **duplicados redundantes** de ese job — un footgun de **reset/envío masivo accesible por cualquier anónimo con la URL**.
  - **Eliminados (route + método, reemplazado por comentario breadcrumb):** **`GET /newdayRep`** (`resetController@newdayRep`, duplicado exacto: mismo reset + mismo correo), **`GET /newWR`** (`resetController@newWR`, mismo reset sin correo, redirigía `/home`) y **`GET /pruebachedule`** (`PerfilController@pruebachedule`, mismo reset, de nombre "test", ni retornaba). También se limpiaron **imports muertos** (`departamento`/`puesto`/`userpuesto`/`usuariosnotificacione`/`Hash` en `resetController.php`; `DB` en `PerfilController.php`).
  - **Verificado:** `php -l` OK en ambos controllers; el router arranca (**110 → 107 rutas**); `newdayRep`/`newWR`/`pruebachedule` ya no existen; **`encuestas:task` sigue registrado** en Artisan (mecanismo diario real intacto).
  - **⚠️ Deploy (verificar por instancia):** la remoción **asume** que el VPS corre el cron estándar `php artisan schedule:run` (que dispara `encuestas:task`). **Si alguna instancia de cliente disparaba el reset diario curleando `/newdayRep` o `/pruebachedule`** por cron, ese cron **debe cambiarse a `schedule:run`**.
- **✅ Resolución adicional (2026-06-26) — `/PhotoReminder` ELIMINADO; reimplementación diferida como comando agendado** (ver `PROGRESS.md`, entrada **🧹🔒 Seguridad (2b)**):
  - **Decisión del owner:** la implementación actual es **"muy rústica"** (un GET sin gate, sin disparador de UI, con su primer loop no-op) → se **elimina ahora** y se **difiere** una reimplementación **"más práctica"** como **comando Artisan agendado `badge:reminder`** (mismo patrón que `encuestas:task` en `app/Console/Kernel.php`), no como URL pública. Era el **último GET de reset/reminder sin gate** de este cluster.
  - **Eliminados (route + método, reemplazado por comentario breadcrumb):** **`GET /PhotoReminder`** (nombre `PhotoReminder`) + `resetController@PhotoReminder`. **NO es un reset clínico ni es COVID:** mandaba un recordatorio de **foto de gafete** (`correos.photo`) a los usuarios con `age=0` (`age` = flag de foto-de-gafete subida); su primer loop era un no-op. **La plantilla `resources/views/correos/photo.blade.php` SE CONSERVA** (lista para reusar en la reimplementación).
  - **`resetController` queda adelgazado:** tras quitar `PhotoReminder`, su único método superviviente es `expCsv` (usa solo el modelo `User`) → se removieron además los imports ya muertos `Request`/`DB`/`Mail`. El controller queda con **solo** `use App\Http\Controllers\Controller; use App\Models\User;`.
  - **Verificado:** `php -l` OK en `resetController.php`; el router arranca (**107 → 106 rutas**); `PhotoReminder` resuelve a **ELIMINADA**; `expCsv` sigue `web,auth,permission:reports.export`.
  - **⏳ Reimplementación pendiente (NO bloqueante):** el comando agendado **`badge:reminder`** que reemplace al GET rústico (la plantilla `correos/photo.blade.php` ya está lista). Registrado en el backlog de `PROGRESS.md`.
- **✅ Resolución adicional (2026-06-27) — `/reminder-mail` GATEADO** (ver `PROGRESS.md`, entrada **🔒 Seguridad (2c) — menores**):
  - **`GET /reminder-mail` (nombre `ReminderMail`, `ReminderMailController@ReminderMail`) estaba `[web]` SIN auth** → cualquier
    anónimo con la URL podía **encolar un envío masivo** del correo "REMINDER DAILY REPORT" a **TODOS** los usuarios activos (el job
    `App\Jobs\ReminderEmail` correa a cada `activo=1`). Era el envío-masivo del cluster "Correos" (`:41`). Ahora va con
    **`->middleware('admin')`** → `[web,admin]`, **consistente con su hermano `/sendreminder`** (mismo envío recordatorio, ya gateado).
    En la misma edición se quitó el `dd("Job dispatched.")` del método (ver M1).
  - **Verificado:** la ruta es ahora `[web,admin]`; `php -l` limpio; el router arranca (**106 rutas**). Con esto **ya no queda
    ningún endpoint de reset/reminder/envío-masivo sin gate** en este hallazgo.
- **⏳ Aún PENDIENTE en este hallazgo (próxima pasada):** las **mutaciones de estado por GET ya autorizadas** (`activaradmin`/`desactivaradmin`/`checkgft`/`uncheckgft` vía GET → pasar a POST+CSRF, ver H1).
- **Nota (2026-06-26) — RESET SELF-SERVICE HABILITADO (vía segura; reduce el anti-patrón admin-fija-passwords):**
  se habilitó el flujo de **restablecimiento de contraseña self-service** ("olvidé mi contraseña") que históricamente nunca
  funcionó (el enlace en `auth/login.blade.php` estaba comentado; el resto era el flujo estándar de Laravel — broker en
  `config/auth.php`, controllers stock, tabla `password_resets`, SMTP Mailgun). El reset basado en **token** (token + match de
  email + throttle 60s + regla `min:8`) es **la vía segura** y **elimina la necesidad de que un admin conozca o fije la
  contraseña de otros usuarios** (un anti-patrón). Complementa los dos fixes ya cerrados de este cluster — `admin` fuera de
  `$fillable` (C2) y `/updatepassword` gateado a `auth()->user()` (C3). Ver `PROGRESS.md` (entrada **🔑 Password reset
  self-service habilitado**).

### C4 — `APP_DEBUG=true` committeado
- **Ubicación:** `.env` (`APP_DEBUG=true`).
- **Evidencia:** El `.env` versionado tiene `APP_DEBUG=true` mientras `APP_URL=https://ap2.crewcare.app` y `.env.example` apunta a `APP_ENV=local`. Con debug activo, cualquier excepción muestra la página de error de Laravel con stack trace, fragmentos de código, variables de entorno y conexión BD.
- **Impacto:** Fuga de rutas internas, fragmentos de queries, credenciales y configuración ante cualquier error en producción. Facilita el reconocimiento para otros ataques.
- **Recomendación:** Forzar `APP_DEBUG=false` en producción; nunca versionar el `.env`. Validar `APP_ENV=production` en despliegue.

---

## ALTOS

### H1 — Mutación de estado por método GET (CSRF) — ⚖️ PARCIAL: ✅ `activarusuario`/`activarencuesta` convertidos a POST+CSRF (2026-06-28)
- **Ubicación:** `routes/web.php` — numerosas rutas `Route::get` que cambian datos, por ejemplo:
  `:53` `/checkgft/{id}`, `:54` `/uncheckgft/{id}`, `:58` `/activarusuario/{id}`, `:59` `/activarencuesta/{id}`, `:60` `/activaradmin/{id}`, `:61` `/desactivaradmin/{id}`, `:74` `/pcrcrud/{id}` (lectura) pero `:83` `/activardepartamento/{id}`, `:96` `/activarnotificacion/{id}`, `:120` `/positivepcr` (POST ok) — y especialmente `:60` `/activaradmin/{id}` que setea `admin=1`.
- **Evidencia:** `AdminController@activaradmin` (`:87-93`): `$producto->admin=1; $producto->update();`, expuesto vía `Route::get('/activaradmin/{id}',...)`. Laravel **no** aplica protección CSRF a peticiones GET.
- **Impacto:** Un atacante puede inducir a un admin autenticado a visitar (o precargar vía `<img>`/prefetch) una URL como `/activaradmin/{id}` y promover una cuenta, activar/desactivar usuarios, marcar resultados PCR, etc., sin token CSRF.
- **Recomendación:** Convertir todas las acciones que mutan estado a `POST`/`PUT`/`DELETE` con `@csrf` en los formularios. Reservar `GET` solo para lectura.
- **✅ Resolución parcial (2026-06-28) — `activarusuario`/`activarencuesta` GET→POST+CSRF:** al extraerlos a `CrewStatusController`
  (paso #5 del strangler, ver `PROGRESS.md` entrada **🏗️ Estructura (4/n)**) ambas rutas se **convirtieron de GET a POST+CSRF**
  (antes mutaban estado por GET sin token). Se actualizaron **4 enlaces en 2 vistas** (`resources/views/admin/usuarioscrud.blade.php`
  y `resources/views/componentes/search-results.blade.php`): de `<a href>` a `<form method="post">@csrf<button>` (verificado: **0
  `<a href>` GET huérfanos** restantes para esas rutas). **Suma a la lista de GET→POST ya cerrados.** Restan otros GET mutantes ya
  autorizados (`checkgft`/`uncheckgft`/`activaradmin`/`desactivaradmin`/…) — defensa en profundidad, sin explotación anónima.
- **✅ Resolución adicional (2026-06-28) — `welcomeresend`/`sendreminder` eliminados como GET (convertidos a comandos Artisan):** los
  dos GET con efecto colateral y sin UI (`/welcomeresend/{id}` y `/sendreminder`, que el paso #7 del strangler había movido a
  `CrewMailController`) se **convirtieron en comandos Artisan** (`crew:welcome-resend {id}` / `crew:daily-reminder`) y sus **rutas web
  GET fueron eliminadas**; `CrewMailController` se retiró a `_legacy_backup/`. Quitan dos GET mutantes más de la superficie. Verificado
  con `route:list` (ya no existen) y `php artisan list` (comandos registrados). Ver `PROGRESS.md` (entrada **⚙️🧹 Correos de crew →
  comandos Artisan**).

### H2 — IDOR: acciones admin sobre `{id}` arbitrario sin verificación de pertenencia — ✅ CERRADO en TODAS las acciones admin sobre `{id}` de crew (perfil + toggles + roles + puesto, 2026-06-28)
- **Ubicación:** `AdminController.php` (`useredit` :216, `acountupdate` :232, `pcrcrud` :748, `crearpcr` :756, `historial` :328, `historialWR` :339); `cmedicController.php` (`create` :31, `store` :50); `PerfilController.php` (`updatepassword` :61). **(NOTA 2026-06-28: `useredit`/`acountupdate` se movieron a `CrewController` y ya NO están en esta lista de IDOR — ver resolución abajo.)**
- **Evidencia:** Todos reciben `$id`/`$id_user` directo de la URL y operan sin comprobar relación con el usuario actual (p. ej. `$user = User::find($id); $user->fill($input)->save();`). Para los métodos dentro del grupo `admin` el control es solo el flag global `admin` (cualquier admin actúa sobre cualquier usuario). Para `updatepassword` (sin auth, C3) es IDOR total para anónimos.
- **Impacto:** Dentro del panel, no hay segregación: cualquier cuenta con `admin=1` puede leer/editar el historial médico (diagnóstico, medicación), PCR y datos de cualquier crew member. Fuera del panel, `updatepassword/{id}` permite resetear el password de cualquier usuario por ID.
- **Recomendación:** Implementar Policies/Gates por modelo y verificar pertenencia (`$id` vs `auth()->id()`) en endpoints de auto-servicio. Para datos clínicos, restringir el acceso por rol (`daytest` médico) y registrar auditoría de accesos.
- **✅ Resolución parcial (2026-06-28) — CERRADO en el path de edición de crew (`useredit`/`acountupdate`):** al extraer el perfil de
  crew a `CrewController` (paso #4 del strangler, ver `PROGRESS.md` entrada **🏗️ Estructura (3/n)**) se **endureció el scope**: se añadió
  a `app/Models/User.php` el helper **`canManageCrewMember(self $target): bool`** — equivalente "de un solo objetivo" de
  `applyDepartmentScope` (true si el viewer tiene `crew.view.all-departments`; si no, true **solo** si comparten depto vía el pivote
  `production_user`). `useredit`/`acountupdate` ahora hacen `findOrFail($id)` + `abort_unless(auth()->user()->canManageCrewMember($user), 403)`.
  **Verificado (tinker):** viewer acotado a depto 19 **SÍ** gestiona target del mismo depto, **NO** uno de otro; super-admin **SÍ** a
  cualquiera. Con esto H2 **deja de ser solo "defensa en profundidad latente"** y queda **CERRADO en ese path**.
- **✅ Resolución (2026-06-28) — CERRADO en TODAS las acciones admin sobre `{id}` de crew:** al extraer los toggles de estatus y el
  rol/grupos/puesto a `CrewStatusController` (pasos #5/#6 del strangler, ver `PROGRESS.md` entrada **🏗️ Estructura (4/n)**) **cada
  acción** (`checkgft`/`uncheckgft`/`activarusuario`/`activarencuesta`/`desactivarusuario`/`activaradmin`/`desactivaradmin`/`putga`/
  `putgb`/`putgg`/`selectpuesto`) se **endureció** con `findOrFail($id)` + `abort_unless(auth()->user()->canManageCrewMember($target), 403)`.
  Junto con el path de perfil ya cerrado (#4), **H2 queda CERRADO en todas las acciones admin sobre `{id}` de crew** (perfil + toggles
  + roles + puesto) — **ya no es solo "defensa en profundidad"**. Para los roles actuales la guarda es no-op (todos tienen
  `crew.view.all-departments`), pero blinda `{id}` manipulados y futuros roles acotados.
- **✅ Resolución adicional (2026-06-28) — ÚLTIMO path de crew cerrado: `CrewListController@idcard` (detalle de gafete):** el corte #1
  del strangler había movido `idcard($id)` **tal cual** (cargaba `User::find($id)` **sin** scope — gap latente conocido). En la pasada
  de las 7 verticales se **endureció** con `findOrFail` + `abort_unless(auth()->user()->canManageCrewMember($users), 403)`. La lista
  `idcardscrud` ya estaba acotada por depto; ahora el **detalle** también → **H2 queda cerrado en TODO el dominio crew** (listas + perfil
  + toggles + roles + puesto + detalle de gafete). Ver `PROGRESS.md` (entrada **🧹⚡ Optimización de las 7 verticales operativas**).

### H3 — CORS totalmente permisivo — ⚖️ RECALIBRADO (2026-06-27): aceptado / bajo riesgo
- **Ubicación:** `config/cors.php:18-22`
- **Evidencia:**
  ```php
  'paths' => ['api/*', 'sanctum/csrf-cookie'],
  'allowed_methods' => ['*'],
  'allowed_origins' => ['*'],
  ```
- **Impacto:** Cualquier origen puede invocar `api/*` y `sanctum/csrf-cookie`. Aunque hoy `supports_credentials=false` y la API es mínima, habilita lectura cross-origin de respuestas y facilita ataques si se amplía la API o se activan credenciales.
- **Recomendación:** Restringir `allowed_origins` a los dominios propios (`https://ap2.crewcare.app`). Limitar `allowed_methods` a los realmente usados. Nunca combinar `'*'` con `supports_credentials=true`.
- **⚖️ Recalibración (2026-06-27) — ACEPTADO / BAJO RIESGO (investigado, dejado como está):** revisado en la pasada "2c / menores".
  La config tiene `paths => ['api/*','sanctum/csrf-cookie']` y **`supports_credentials => false`**, y `routes/api.php` expone
  **solo `GET /user` detrás de `auth:sanctum`**. Con **credentials apagados**, `allowed_origins=['*']` es el **default de Laravel** y
  **no** expone datos autenticados cross-origin (el navegador no adjunta cookies/credenciales sin `supports_credentials=true`). Un
  cliente móvil/API (la app tiene Sanctum + columna `device_token`) **no se ve afectado por CORS** de todos modos — CORS es enforced
  por el navegador, no por clientes nativos. Por tanto **se baja de Alto activo a "aceptado / bajo riesgo"** y **se deja como está**.
  **Re-evaluar SOLO si** en el futuro se añade un SPA de navegador en otro origen **o** se activa `supports_credentials=true`: en ese
  momento restringir `allowed_origins` a los dominios propios. Razonamiento registrado para que **no se vuelva a flaggear como Alto**.

### H4 — Mass-assignment con `$request->all()` / `except()` — ⚖️ RECALIBRADO (2026-06-27): BAJO + diferido al refactor de validación
- **Ubicación:** `AdminController.php:143` (`creardepartamento`), `:182` (`crearnotificacion`), `:256` (`crearpositions`), `:174,211,269` (`save*` con `except('_token')`); `FormulariosController.php:417` (`$datos = request()->all()`) + `:452` (`formulario::create($datos)`); `HazardNotificationController.php:46,67`; `unsafecondNotificationController.php:38,59`; `locationController.php:179,202`.
- **Evidencia:**
  ```php
  // FormulariosController@newformulario (ruta /formularios/registro SIN auth)
  $datos = request()->all();
  ...
  formulario::create($datos);   // formulario.php:25 -> $fillable ~49 campos
  ```
  Los modelos `DailyReport`, `DailyLog`, `SafetyStandard` usan `$guarded = []` (todo asignable) según `ARCHITECTURE.md`; `formulario` tiene `$fillable` de ~49 campos (no `$guarded`, lo que acota algo, pero igual se vuelca `request()->all()`).
- **Impacto:** Inserción/edición de columnas no previstas, datos clínicos manipulables y, en modelos con `$guarded=[]`, escritura de cualquier columna (incl. timestamps, ids de relación). Combinado con rutas sin auth (FormulariosController) agrava la superficie.
- **Recomendación:** Validar con reglas explícitas y construir el array a guardar campo por campo (o `$request->only([...])`). Sustituir `$guarded = []` por `$fillable` acotado en `DailyReport`/`DailyLog`/`SafetyStandard`.
- **⚖️ Recalibración (2026-06-27) — BAJO + DIFERIDO al refactor de validación (Estructura/Limpieza):** investigado en la pasada
  "2c / menores". Quedan **8 usos de `$request->all()`** — `AdminController` (`:146`/`:185`/`:259`/`:278` — CRUD de
  catálogos/notificaciones: `departamento::create`, `usuariosnotificacione::create`, positions), `HazardNotificationController:46`,
  `locationController:179`, `locationController2:30`, `unsafecondNotificationController:38`. Se **confirmó que NINGUNO toca el modelo
  `User` ni una columna de privilegio/auth** (el vector crítico — `admin` en `User` — ya se cerró en C2 quitándolo de `$fillable`).
  En el peor caso se sobre-postean campos no-privilegio de filas de catálogo (p. ej. `activo` en un depto), por lo que **baja a
  severidad BAJA**. El fix correcto es **validación por formulario** (Form Requests / reglas `validate()`), que **pertenece al
  próximo refactor de Estructura/Limpieza (validación)** — se **difiere ahí** para no hacer cambios ciegos de whitelist que rompan
  los formularios de create/update. **Registrado como ítem de backlog bajo ese refactor**, no como crítico abierto. (Los modelos
  `$guarded=[]` de DSR siguen como sub-ítem del mismo refactor.)
- **✅ Resolución parcial (2026-06-28) — CERRADO en `acountupdate` (edición de perfil de crew):** al extraer el perfil a
  `CrewController` (paso #4 del strangler, ver `PROGRESS.md` entrada **🏗️ Estructura (3/n)**) se reemplazó el `$request->except('password')`
  — que dejaba entrar **cualquier** campo a `fill()` (incl. `activo`/`encuestadiaria`, que **sí** son fillable) — por una **whitelist
  validada explícita** = exactamente los **11 campos del form** (`name`, `lname`, `lname2`, `ncreditos`, `phone`, `borndate`,
  `puestodepartamento`, `labn`, `email`, `zone`, `sex`), con reglas permisivas (`nullable`, sin `unique` en `email`); `password` se
  maneja **aparte** y solo cambia si viene con valor. **Resta (LOW):** los demás `$request->all()`/`except()` de catálogos/notificaciones
  (8 usos, ninguno toca `User`/privilegios) siguen diferidos al refactor de validación como se describió arriba.
- **✅ Resolución parcial (2026-06-28) — sub-ítem DSR `$guarded=[]` → `$fillable`:** en la pasada de las 7 verticales,
  **`DailyReport` y `DailyLog`** pasaron de `$guarded=[]` a **`$fillable` explícito** (verificado que el `$fillable` cubre exactamente lo
  que el controller escribe → sin pérdida silenciosa; **no era vuln activa**: `store`/`update` ya usan `$request->validate(...)`).
  **Resta `SafetyStandard`** (aún `$guarded=[]`). Ver `PROGRESS.md` (entrada **🧹⚡ Optimización de las 7 verticales operativas**).
- **✅ Resolución parcial (2026-06-28) — CERRADO en 4 `store()` más (creación de hazard/unsafecond/location V1+V2):** en la pasada de
  endurecimiento de los métodos de creación, `HazardNotificationController@store`, `unsafecondNotificationController@store`,
  `locationController@store` (V1) y `locationController2@store` (V2) dejaron de usar `$request->all()` como base de `create()` y ahora
  parten del **array validado** (`$data = $request->validate([...])`) con `unset` de los inputs de archivo. **Verificado que no se pierde
  ninguna columna** (reconciliación reglas↔`$fillable`↔form; V1 confirmó 118/118). **Resta (LOW):** solo el `$request->all()` de catálogos
  en `AdminController` (diferido al rediseño de catálogos). Ver `PROGRESS.md` (entrada **🛡️ Endurecimiento de métodos de creación**).

### H5 — Autorización débil: `AdminMiddleware` sin `activo` (✅ RESUELTO 2026-06-27), `niveldos` no-op, sin Policies/Gates
- **Ubicación:** `AdminMiddleware.php:17-23`; `niveldos.php:17-21`; `AuthServiceProvider.php:15-29`.
- **Evidencia:**
  ```php
  // AdminMiddleware
  if (auth()->check() && auth()->user()->admin) return $next($request);
  return redirect('/');           // NO comprueba ->activo
  ```
  ```php
  // niveldos: stub que no filtra nada
  public function handle(Request $request, Closure $next){ return $next($request); }
  ```
  `AuthServiceProvider::$policies` está vacío y `boot()` no define gates. Además `niveldos` ni siquiera está registrado como alias en `Kernel.php:59-72`.
- **Impacto:** Un admin **desactivado** (`activo=0`) conserva acceso total. El único control de autorización es un booleano global; no hay granularidad por recurso ni por rol funcional (`daytest`). `niveldos` da falsa sensación de control si alguien lo aplica.
- **Recomendación:** En `AdminMiddleware`, exigir también `auth()->user()->activo`. Eliminar o implementar `niveldos`. Introducir Policies/Gates para los recursos sensibles (médico, PCR, lesiones) y verificar el rol `daytest` donde corresponda.
- **✅ Resolución parcial (2026-06-27, pasada "2c / menores") — `AdminMiddleware` ahora exige `activo`:** el gate binario
  `if (auth()->check() && auth()->user()->admin)` ahora exige **además** `&& auth()->user()->activo` → un admin **desactivado**
  (`activo=0`) ya **no** pasa el gate. **Verificado (tinker):** 2 usuarios con `admin=1`, **0** de ellos con `activo=0` (nadie queda
  bloqueado por error), el super-admin es `admin=1`/`activo=1`. Los otros dos sub-ítems de H5 (`niveldos` no-op + ausencia de
  Policies/Gates) **siguen pendientes**. Ver `PROGRESS.md` (entrada **🔒 Seguridad (2c) — menores**).
- **Nota (2026-06-25) — EDITOR EN VIVO DE LA MATRIZ ROL→PERMISO ("Permisos por rol"), con salvaguardas anti-escalada:**
  se añadió una pantalla para editar EN VIVO la matriz rol→permiso (controller `RolePermissionController`, vista
  `admin/role-permissions.blade.php`, rutas `GET`/`POST /permisoscrud`). Es una **superficie sensible** (permite reasignar permisos),
  por lo que va con **tres controles err-restrictivos**: (1) **gate `permission:roles.manage-permissions`** en ambas rutas, permiso
  que por **DEFAULT solo tiene `super-admin`** (sembrado vía `Permission::all()`); (2) la **columna `super-admin` es de SOLO LECTURA**
  (god-mode, nunca editable por la UI → no se puede degradar al super-admin desde la pantalla); (3) **guard anti-auto-bloqueo:** un
  no-super-admin **no puede** guardar una matriz que le quite `roles.manage-permissions` a su propio rol (evita quedar sin acceso a la
  propia herramienta). Igual que la asignación de roles, `super-admin` queda fuera de lo editable por la UI (anti-escalada). Ver
  `AUTH-RBAC-PLAN.md` (B.1.2) y `PROGRESS.md` (entrada 🎛️ EDITOR EN VIVO DE LA MATRIZ).

### H6 — Valores reales en `.env.example` — **RECALIBRADO (2026-06-26): NO es un Alto activo, es nota PRE-PUSH diferida**
- **⚠️ Recalibración (aclaración del owner, autoritativa):** El framing original de H6 como **Alto/exposición** estaba **mal
  encuadrado**. Los valores reales en `.env` **y** `.env.example` son **intencionales** (acordado al inicio del proyecto):
  existen para poder probar la base offline contra la BD/Mailgun **reales** en local. Como **NO hay repositorio git** para este
  proyecto, **NO hay exposición por repositorio**. Por eso H6 **se retira del conteo de Altos** y se trata como una **nota del
  checklist PRE-PUSH** (misma acción que C1, ver recalibración de arriba), relevante **solo antes de un futuro primer `git push`**.
- **Ubicación:** `.env.example` (y `.env`).
- **Evidencia:** Tanto `.env` como `.env.example` contienen **valores reales, no placeholders**: un valor de `APP_KEY`, un
  password de BD real y un `MAIL_PASSWORD` real de Mailgun. (Los valores **no se transcriben aquí** — se referencian solo por
  nombre.)
- **NO es exposición mientras no haya repo:** sin git/remoto no hay vector de fuga por repositorio. El riesgo aparecería solo si
  en el futuro se inicializa un repo y se hace push **sin** sanear primero — exactamente el momento que cubre el checklist PRE-PUSH.
- **Acción diferida a PRE-PUSH (misma que C1, NO aplicada hoy):** antes del primer `git push`, **sanear** `.env.example` a
  placeholders vacíos (`DB_PASSWORD=`, `MAIL_PASSWORD=`, `APP_KEY=`) **y rotar** las credenciales (key de envío de Mailgun,
  password de BD, regenerar `APP_KEY`). Confirmar que el proceso de deploy **genera** `APP_KEY` por instancia
  (`php artisan key:generate`) en vez de heredar el del template. Ver `PROGRESS.md` (ítem **PRE-PUSH** del backlog de Seguridad y
  entrada **🔑 Password reset self-service habilitado**).

---

## MEDIOS

### M1 — `dd()` en endpoints ruteados — ✅ RESUELTO (2026-06-27)
- **Ubicación:** `MailController.php:20` (`/negative-mail`), `ReminderMailController.php:22` (`/reminder-mail`), `FormulariosController.php:112` (`newformulario1`, `DD($request)`), `locationController.php:205` (en `catch`).
- **Evidencia:** `dd("Job dispatched.")` corta la ejecución y vuelca estado; `DD($request)` al inicio de `newformulario1` imprime toda la petición; el `catch` de `locationController@store` hace `dd('Error...', $e->getMessage())`.
- **Impacto:** Rompe el flujo normal (la respuesta nunca llega al usuario), y `dd($request)` / `dd($e->getMessage())` puede filtrar datos de la petición o detalles internos de errores (incl. fragmentos SQL) al cliente.
- **Recomendación:** Eliminar todos los `dd()/DD()` de rutas accesibles. Manejar errores con logging server-side y respuestas genéricas. Borrar el código muerto (`newformulario1`).
- **✅ Resolución (2026-06-27, pasada de seguridad "2c / menores"):** Los cuatro vectores quedan cerrados:
  - **`ReminderMailController@ReminderMail`** — removido `dd("Job dispatched.")`; ahora retorna `redirect('/home')->with('success', ...)` tras despachar el job (la respuesta vuelve al usuario). Además la ruta `/reminder-mail` quedó **gateada** (ver C3).
  - **`locationController@store`** — removido `dd('Error al guardar el reporte:', $e->getMessage())` del `catch`; ahora registra vía `\Log::error(...)` y retorna `redirect()->back()->withInput()->with('error', ...)` (sin filtrar detalles internos). Se quitó además un `return` de éxito duplicado/muerto que seguía al try/catch.
  - **`FormulariosController.php:112` (`newformulario1`, `DD($request)`)** — ya eliminado: `newformulario1` se removió como código muerto en **🦠 COVID desacople (2/n)** (2026-06-25).
  - **`MailController.php:20`** — ya eliminado: `MailController` completo se movió a `_legacy_backup/` en **🦠 COVID-DECOMMISSION Lote 3** (2026-06-25).
  - **Verificado:** `php -l` limpio en `ReminderMailController.php` y `locationController.php`; el router arranca (106 rutas). Ver `PROGRESS.md` (entrada **🔒 Seguridad (2c) — menores**).

### M2 — Subida de archivos confiando en la extensión del cliente
- **Ubicación:** `InjuryReportController.php:82,91`; `HazardNotificationController.php:51,60`; `unsafecondNotificationController.php:43,52`; `locationController.php:184,193`; `cropimageController.php:36-44`.
- **Evidencia:**
  ```php
  $filename = time().'_main.'.$image->getClientOriginalExtension();   // extensión del cliente
  $path = $image->storeAs('injury_images', $filename, 'public');      // disco público
  ```
  En `cropimageController@uploadCropImage` se decodifica base64 y se escribe con `file_put_contents($folderPath.$imageName, $image_base64)` en `public_path('imagesprf/usrs/')`, sin validar el tipo real de imagen.
- **Mitigación parcial observada:** Inyury/hazard/unsafe/location sí aplican `mimes:jpeg,png,jpg,gif` en `validate()`, lo que reduce el riesgo de subir `.php`. Pero el nombre/extensión final se toma de `getClientOriginalExtension()`, y los archivos quedan en disco **público**; `cropimage` no valida nada.
- **Impacto:** Riesgo de subida de contenido no-imagen vía `cropimage` (sin validación), y en general almacenamiento de ficheros bajo el webroot público. Si el servidor llegara a ejecutar contenido en esos directorios, habría riesgo de RCE; como mínimo, contenido arbitrario servido desde el dominio.
- **Recomendación:** Validar siempre `image|mimes:...|max:...` (incluido `cropimage`); generar el nombre y forzar la extensión a partir del MIME real detectado server-side, no del cliente. Servir las imágenes desde un disco no ejecutable y, de ser posible, fuera del webroot con descarga controlada.
- **✅ Refuerzo (2026-06-28) — hazard: la validación de imagen NO se aplicaba, ahora SÍ:** en `HazardNotificationController` la regla
  `image/mimes/max` apuntaba a `main_image_path` (**nombre de columna**) en vez de `main_image` (**el `name` real del input del form**),
  por lo que la validación de la imagen principal **nunca corría** (Laravel validaba un campo ausente). Corregido a `main_image` → ahora la
  validación `mimes:jpeg,png,jpg,gif` sí se aplica a esa subida. Ver `PROGRESS.md` (entrada **🧹⚡ Optimización de las 7 verticales operativas**).
- **✅ Refuerzo (2026-06-28) — validación de imagen añadida donde faltaba + nombres anti-colisión:** en la pasada de endurecimiento de
  los métodos de creación, `unsafecondNotificationController` y `locationController2` **no tenían** `mimes`/`max:` en la imagen → ahora
  `image|mimes:jpeg,png,jpg,gif|max:2048`. Además, en `Hazard` la regla de imágenes **adicionales** (`additional_images_paths.*`)
  **nunca aplicaba** (el input real del form es `additional_images[]`) → corregida a `additional_images`+`additional_images.*` con
  `image|mimes|max`. Los nombres de archivo pasan de `time().'_main.'` (colisionable en el mismo segundo) a
  `time().'_'.uniqid().'_main.'` en Injury/Hazard/Unsafe/Location. **Sigue pendiente** (no resuelto aquí) lo de fondo de M2: forzar la
  extensión desde el **MIME real** server-side y servir desde un disco no ejecutable. Ver `PROGRESS.md` (entrada **🛡️ Endurecimiento de
  métodos de creación**).

### M3 — Operaciones multi-paso sin transacción
- **Ubicación:** `AdminController.php:580-628` (`positivepcr`), `:650-687` (`negativepcr`), `:692-743` (`negativeantg`).
- **Evidencia:** Cada flujo envía correos, crea `prueba`, crea `usuariopcr` y actualiza varios flags de `User` de forma secuencial sin `DB::transaction()`. Un fallo a mitad deja estados inconsistentes (p. ej. flags cambiados pero sin registro `prueba`, o correos enviados sin persistir).
- **Impacto:** Integridad de datos clínicos comprometida; difícil de auditar. Reintentos pueden duplicar correos/registros.
- **Recomendación:** Envolver cada operación en `DB::transaction()` y separar el envío de correos (idealmente vía cola) del cambio de estado.

### M4 — `LocaleMiddleware` usa entrada sin validar
- **Ubicación:** `LocaleMiddleware.php:14`
- **Evidencia:** `App::setLocale($request->locale);` toma `locale` directamente de la petición sin lista blanca.
- **Impacto:** Bajo, pero permite forzar locales inexistentes (errores) o, según uso, manipular la resolución de archivos de idioma. Mala práctica de validación de entrada.
- **Recomendación:** Validar contra una lista blanca (`['en','es']`) antes de `setLocale`. (Nota: este middleware no está en el grupo `web`; el activo es `localization`.)

### M5 — Tokens Sanctum sin expiración y `AuthenticateSession` deshabilitado
- **Ubicación:** `config/sanctum.php:33` (`'expiration' => null`); `Kernel.php:36` (`AuthenticateSession` comentado).
- **Evidencia:** Tokens de API no caducan; la detección de sesiones concurrentes/invalidación está deshabilitada.
- **Impacto:** Un token filtrado es válido indefinidamente; el cambio de contraseña no invalida otras sesiones activas.
- **Recomendación:** Definir expiración de tokens (p. ej. minutos/horas según uso) y habilitar `AuthenticateSession` para invalidar sesiones al cambiar credenciales.

### M6 — Exposición de PII vía búsquedas y exports CSV
- **Ubicación:** `AdminController.php:367-518` (`searchusers`/`searchcom`/`searchdoctor`/`searchlab`/…), `:328-365` (`historial`/`historialWR` devuelven 90 días + consultas médicas), `:856-891` (`expCsv`); `resetController.php:130-168` (`expCsv` → `/nophoto`).
- **Evidencia:** Las búsquedas devuelven listados completos de usuarios (incl. email) y los exports CSV vuelcan nombre, fecha de nacimiento, sexo, teléfono, email y puesto. `/nophoto` (CSV) está **sin auth** (ver C3). `historialWR` une `formularios` + `cmedic` (datos clínicos) por `id` arbitrario (ver H2).
- **Impacto:** Fuga de PII y datos médicos sensibles, agravada por la falta de auth en `/nophoto` y la ausencia de control de pertenencia.
- **Recomendación:** Proteger todos los exports/búsquedas con `auth`+`admin`, minimizar columnas devueltas, paginar/limitar y aplicar control de acceso por rol a datos clínicos. Registrar auditoría de exportaciones.
- **Nota (2026-06-24) — fix IMPLEMENTADO para `/searchusers` (INCREMENTO 1); el resto de `search*` aún con el patrón viejo:**
  el descifrado del patrón de búsqueda (ver `SEARCH-DOSSIER.md`) confirmó que los 7 endpoints HTML usan `DB::table('users')` + `SELECT *` (ignora el `$hidden` de Eloquent → el hash `password` y campos clínicos viajan en cada fila del payload AJAX). **El `SearchController@users` (nuevo) ya corrige esto para `/searchusers`:** **Eloquent + `select()` explícito** elimina el hash del payload (`$hidden` aplica), **`OR` agrupado en closure** (arregla además el filtro roto de `searchlab`/`searchqueue` cuando se migren) y el **`<tr>` faltante** quedó arreglado en el parcial `componentes/search-results.blade.php`. La ruta `/searchusers` se re-apuntó (alias strangler) a `SearchController@users`. **Los demás endpoints search** (`searchidcard`/`searchlab`/`searcheckpoint`/`searchqueue` + los muertos `searchcom`/`searchdoctor`) **siguen con `DB::table('users')` + `SELECT *` hasta migrarse** CRUD por CRUD; `searchcom`/`searchdoctor` son código muerto (a borrar al final). Pendiente: verificación en vivo del owner. Ver `RECIPE.md` 1.5.D y `PROGRESS.md`.
- **Nota (2026-06-25) — ALCANCE POR DEPARTAMENTO en listados/búsqueda de crew (defensa en profundidad, err-restrictivo):**
  el listado de crew y la búsqueda ahora se **acotan al departamento del usuario** para los roles que **NO** tienen el permiso
  `crew.view.all-departments`. La lógica vive en una única fuente de verdad — `User::applyDepartmentScope($query, $viewer)` (+
  `User::ownDepartmentIds()`) — aplicada a `AdminController@usuarioscrud`/`comcrud`/`idcardscrud`/`medicocrud` y compartida (DRY)
  con `SearchController@runSearch`. Es **err-restrictivo**: un viewer sin `crew.view.all-departments` y sin departamento
  determinable **no ve nada** (nunca degrada a "ver todo"). Reduce la superficie de exposición de PII de este hallazgo para roles
  acotados (p. ej. `hod`), por encima del gate de permiso de cada ruta. Ver `AUTH-RBAC-PLAN.md` y `PROGRESS.md`.

---

## BAJOS

### L1 — Direcciones "from" hardcodeadas e inconsistentes (SPF/DKIM)
- **Ubicación:** `resetController.php:38,123`; `AdminController.php:317,595,607,642,…`; `FormulariosController.php:377,605`.
- **Evidencia:** Se usan dominios "from" distintos y fijos en el código: `notofications@crewcare.app` (nótese el typo), `noreply@crewcare.mx`, `covid@crewcare.tech`.
- **Impacto:** Inconsistencia de remitente → mayor probabilidad de marcado como spam y problemas de alineación SPF/DKIM. El typo `notofications` es además poco profesional.
- **Recomendación:** Centralizar el remitente en `config/mail.php` (`MAIL_FROM_ADDRESS`) y usar `->from(config(...))`. Unificar dominio y corregir el typo.

### L2 — Código muerto con bugs y reenvío de bienvenida sin re-hash — ⏳ welcomeresend ahora es comando Artisan; el leak del hash SIGUE ABIERTO (decisión del owner)
- **Ubicación:** `encuestasController.php:22-32` (`checkfroms`: `User::findOrFail(id)` — `id` sin `$`, error fatal); **(2026-06-28) `welcomeresend` ya NO está en `AdminController`/`CrewMailController` ni en una ruta web: vive ahora en `app/Console/Commands/CrewWelcomeResend.php` (`crew:welcome-resend {id}`)** — envía `'password' => $producto->password`, es decir el **hash**, no la contraseña.
- **Evidencia:** `checkfroms` referencia una constante `id` inexistente; el comando `crew:welcome-resend` incluye el hash bcrypt en el correo de bienvenida (inútil para el usuario y fuga del hash por email).
- **Impacto:** `checkfroms` es código muerto que fallaría si se rutea; el reenvío de bienvenida filtra el hash de contraseña en texto de correo (canal no confiable). **Mitigación parcial (2026-06-28):** al ser ahora un **comando de consola** (no una ruta GET sin UI), su disparo requiere acceso al servidor/CLI — se reduce la superficie, pero el **leak del hash en el cuerpo del correo persiste**.
- **Recomendación:** Eliminar `checkfroms`. En el flujo de bienvenida/reenvío, enviar un **enlace de restablecimiento** (`password.reset`, ya habilitado) en vez del hash. **Estado (2026-06-28):** la limitación quedó **documentada en `CrewWelcomeResend`** y **diferida a decisión del owner** (no se cambió el comportamiento al relocalizar la lógica a comando).

---

## Notas de verificación (qué se descartó)

- **SQL Injection:** Los usos de `DB::raw(...)` en `HomeController.php:46,53,60,67` son cadenas estáticas sin entrada de usuario (`YEAR(created_at)`, etc.). Las búsquedas `LIKE` usan parámetros enlazados (`'%'.$valor.'%'` pasado como binding), no concatenación en SQL crudo. **No se confirmó SQLi.**
- **`niveldos`:** Confirmado como stub no-op y, además, **no registrado** como alias de middleware en `Kernel.php` (no puede aplicarse aunque se intente).
- **`/profile`:** Existe duplicado en `web.php:191` (grupo `admin`) y `:206` (grupo `auth`); ambos protegidos. Sin hallazgo propio.
- **DSR (`DailyReportController`):** `store`/`storeLog`/`update` sí validan con `$request->validate()` y manejan el candado de 24h. El riesgo es el `$guarded=[]` de los modelos (ver H4), no la validación del controller.

> Recordatorio: este documento es solo diagnóstico. **No se aplicó ninguna corrección** en el código.
