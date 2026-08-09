# PROGRESS.md — Bitácora de cambios

Registro cronológico de modificaciones hechas en el repo durante sesiones con Claude.
Objetivo: poder rastrear **qué se cambió, por qué, y qué falta**, sin depender de la
memoria de la sesión. Una entrada por bloque de trabajo. Lo más reciente arriba.

**Convención de entrada:**
- Fecha (YYYY-MM-DD) y título corto del bloque.
- **Estado:** Hecho / En progreso / Pendiente / Revertido.
- **Archivos:** lista de archivos tocados.
- **Qué / Por qué:** descripción y motivo.
- **Riesgo/Notas:** migraciones de BD, contratos de API, pasos de deploy (recordar build
  del front con `npm run prod`), o cualquier cosa que verificar.

---

## Pendientes priorizados (backlog)
Derivados del mapeo en [ARCHITECTURE.md](ARCHITECTURE.md). Ninguno iniciado aún.

### Seguridad (ver [SECURITY.md](SECURITY.md) — 17 hallazgos orig.: 4 críticos, 5 altos, 6 medios, 2 bajos)
> **Estado del lane (2026-06-27): CRÍTICOS y ALTOS de explotación CERRADOS o recalibrados.** C2 ✅, C3 ✅ (todos los sub-ítems
> "sin auth" cerrados; resta solo el GET→POST de H1), M1 ✅, H5-`activo` ✅; H3 ⚖️ aceptado/bajo riesgo; H4 ⚖️ bajo + diferido al
> refactor de validación; H6 retirado (PRE-PUSH). **Resta BAJO/diferido:** validación (H4 + `$guarded=[]` DSR), deploy (C1/C4/PRE-PUSH),
> `niveldos`/Policies (H5), IDOR (H2), GET→POST (H1), `badge:reminder`. Ninguno explotable por anónimo.
- [ ] **PRE-PUSH** (recalibrado — git es local, sin remoto) — `.env` está trackeado y `.gitignore`
      vacío. Mientras el git siga local NO hay fuga por repositorio. **Obligatorio antes del primer
      `git push`:** poblar `.gitignore` (`/.env`), `git rm --cached .env`, dejar `.env.example` con
      placeholders vacíos, **rotar secretos** (DB, Mailgun, APP_KEY) antes de publicar. (Nota: `.env.example`
      también lleva hoy valores reales —`APP_KEY`, password de BD, `MAIL_PASSWORD` de Mailgun—, intencional
      para poder probar la base offline contra la BD/Mailgun reales en local; se sanea a placeholders en este
      mismo paso pre-push.) Ver nota de recalibración en [SECURITY.md](SECURITY.md).
- [x] ~~**CRÍTICO** — Escalada de privilegios: `admin` está en `$fillable` de `User`~~ **✅ DONE (2026-06-25)** —
      se quitó `admin` de `$fillable` en `User.php` → ya no es asignable en masa (verificado en `tinker`); el
      otorgamiento legítimo usa asignación explícita gateada por `permission:users.assign-role`. Ver entrada
      **🔒 Seguridad (1/n)**. (`daytest` se dejó a propósito: no es vector de autorización.)
- [~] **CRÍTICO** — Proteger resets de sistema sin auth — **PARCIAL (2026-06-26):**
      - [x] **`/updatepassword` (+ `/changepassword`/`/profile`/`/formularios/registro`/`/formularios/medicos`) CERRADO** —
            movidos dentro de `Route::middleware(['auth'])`; `/updatepassword` perdió el `{id}` y opera solo sobre
            `auth()->user()` (sin IDOR ni account-takeover). Ver entrada **🔒 Seguridad (1/n)**.
      - [x] **`/dailyreport` (+ `/dailyreport/{id}`) CERRADO (2026-06-26)** — movidos dentro de `Route::middleware(['auth'])`;
            `viewencuesta()` usa `auth()->user()` (sin sesión era además null-deref/HTTP 500). Es el cuestionario clínico
            diario del crew → `auth` correcto. Ver entrada **🔒 Seguridad (2/n)**.
      - [x] **`/nophoto` (export CSV de crew sin auth) CERRADO (2026-06-26)** — envuelto en
            `Route::middleware(['auth','permission:reports.export'])`; `expCsv` exporta PII de TODOS los usuarios activos
            (nombre/apellidos/borndate/sexo/teléfono/email/puesto). Ver entrada **🔒 Seguridad (2/n)**.
      - [x] ~~`/newdayRep`, `/newWR`, `/pruebachedule` (familia "cron-vía-URL" de reset de `encuestadiaria`)~~ **✅ DONE (2026-06-26)** —
            **eliminados como duplicados** del comando agendado **`encuestas:task`** (`Kernel.php:27`, diario 04:00, ya resetea
            `encuestadiaria=0` + correo recordatorio). **No hizo falta convertir a Artisan: el scheduler real ya existía** y estos
            endpoints GET sin gate solo eran un duplicado peligroso (reset/envío masivo accesible por cualquiera). Ver entrada
            **🧹🔒 Seguridad (2b)**. ⚠ Deploy: si una instancia curleaba `/newdayRep`/`/pruebachedule` por cron, cambiar a `schedule:run`.
      - [x] ~~`/PhotoReminder` (`resetController@PhotoReminder`, **GET sin gate**)~~ **✅ DONE (2026-06-26)** —
            **eliminado** (route + método → comentario breadcrumb). Era el **último GET de reset/reminder sin gate**; **NO es reset
            clínico ni COVID:** mandaba recordatorio de **foto de gafete** (`correos.photo`) a `age=0`, su primer loop era no-op.
            El owner lo declaró **"muy rústico"** → **diferido** a reimplementación como comando agendado `badge:reminder` (ver
            backlog de Deuda técnica). La plantilla `correos/photo.blade.php` **se conserva**. Ver entrada **🧹🔒 Seguridad (2b)**.
      - [ ] **Refinamiento (BAJO, 2026-06-26):** `resetController@expCsv` (`/nophoto`) exporta **todos** los usuarios activos
            ignorando el alcance por departamento (`User::applyDepartmentScope`): un titular de `reports.export` sin
            `crew.view.all-departments` igual obtiene a todos. Aplicar el scope por departamento al export. Prioridad baja.
- [ ] **ALTO** — Mutaciones de estado por GET → POST/PUT con `@csrf` (incl. `/activaradmin/{id}`). (H1, sigue abierto.)
- [~] **ALTO** — IDOR en acciones admin sobre `{id}` arbitrario (incl. historial médico) **sigue abierto (H2)**; ~~CORS
      `allowed_origins=['*']`~~ **✅ RESUELTO-COMO-ACEPTADO / BAJO RIESGO (2026-06-27)** — con `supports_credentials=false` +
      API mínima (`GET /user` tras `auth:sanctum`), `['*']` es el default de Laravel y **no** expone datos autenticados
      cross-origin; clientes móvil/API (Sanctum) no se ven afectados por CORS. **Dejado como está**; restringir orígenes solo si
      se añade un SPA de navegador en otro origen o se activa `supports_credentials=true`. Ver entrada **🔒 Seguridad (2c)** y H3 en [SECURITY.md](SECURITY.md).
- [~] **MEDIO** — ~~`AdminMiddleware` debería validar `activo`~~ **✅ DONE (2026-06-27)** — el gate ahora exige
      `&& auth()->user()->activo` (un admin `activo=0` ya no pasa; verificado en tinker: 0 admins quedan bloqueados). Ver entrada
      **🔒 Seguridad (2c)**. Sigue pendiente: transacciones DB en ops multi-paso (`positivepcr`, M3).
- [~] **MEDIO** — ~~Quitar `dd()` de `MailController`/`ReminderMailController`~~ **✅ DONE (2026-06-27)** — `dd()` removido de
      `ReminderMailController@ReminderMail` (+ ruta `/reminder-mail` gateada a `admin`) y de `locationController@store` (ahora
      `\Log::error`); `MailController` y `newformulario1` ya estaban removidos en los lotes COVID (2026-06-25) → **M1 cerrado**.
      Ver entrada **🔒 Seguridad (2c)**. Sigue pendiente: revisar stub `niveldos`; expiración de tokens Sanctum (M5).
- [→] **BAJO → movido al refactor de validación (2026-06-27)** — Mass-assignment con `$request->all()`/`except()` (H4): 8 usos en
      `AdminController` (catálogos/notificaciones), `Hazard`/`unsafecond`NotificationController, `locationController`/`locationController2`.
      **Investigado:** **NINGUNO toca `User` ni columnas de privilegio** (el vector crítico `admin` ya se cerró en C2) → severidad
      **BAJA**. El fix correcto (Form Requests / `validate()` por formulario) **se mueve al refactor de Estructura/Limpieza
      (validación)** — ver Deuda técnica. NO es un crítico abierto. Ver entrada **🔒 Seguridad (2c)** y H4 en [SECURITY.md](SECURITY.md).
- [ ] **DEPLOY (NUEVO 2026-06-26)** — Typo `MAIL_FROM_ADRESS` corregido en `.env.example`, pero el mismo typo existe en el `.env`
      **vivo**. Corregirlo ahí cambia la identidad "from" **activa** (fallback → dirección intencionada) → cambio de entregabilidad:
      aplicar **solo tras confirmar** que la dirección intencionada es un remitente verificado en Mailgun. **Marcar, no auto-aplicar.**
- [ ] **DEPLOY/LOCAL (NUEVO 2026-06-26)** — `APP_URL` local es un host pelado `127.0.0.1` (malformado — sin esquema/puerto); el enlace
      del correo de reset no se prueba E2E localmente hasta ponerlo como URL completa. Producción (`ap2.crewcare.app`) está bien.
      Tema **por-deploy**: cada subdominio VPS de cliente debe fijar su propio `APP_URL`.
- [ ] **i18n (NUEVO 2026-06-26)** — El correo de reset de contraseña sale en **inglés** (notificación `ResetPassword` default). App
      bilingüe es/en → localizar a español vía override de `User::sendPasswordResetNotification` (notificación custom) + strings de
      lang `es`. No bloqueante.

### Performance (ver [PERFORMANCE.md](PERFORMANCE.md) — 14 hallazgos: 5 altos, 7 medios, 2 bajos)
- [ ] **ALTO** — Bulk ops `resetController` + `AdminController@negativepcr/negativeantg`: patrón
      `get()` + `find()`+`update()` por fila (2N+1). Usar update masivo. `negativeantg` además
      renderiza un PDF Dompdf síncrono por usuario.
- [ ] **ALTO** — `Mail::send()` (no `queue()`) dentro de `foreach` en ~10 lugares; envío inline bloqueante (QUEUE=sync).
- [ ] **ALTO** — N+1 en `DailyReportController@index` (sin `with('logs')`; la vista llama `logs->count()` 2x por fila).
- [~] **ALTO** — `locationController@index` hace `locationreport::all()` (~161 columnas) sin paginar ni seleccionar columnas.
      **✅ PARCIAL (2026-06-28):** ya **pagina** (`orderBy('id_loc','desc')->paginate(15)`); resta el **`select` de columnas** sobre la
      tabla ancha. Igual pendiente: N+1 en `DailyReportController@index` (abajo).
- [ ] **MEDIO** — Cargar `select` de columnas en list views sobre tablas anchas; revisar agregados PHP vs DB-side.
- [ ] **BAJO (frontend)** — jQuery/Bootstrap cargados 2x (CDN + bundle); Materialize legacy junto a Bootstrap 5; `asset()` sin `mix()` (sin cache-busting).

### 📌 BACKLOG / FUTURO (diferido — no perder de vista)
- [ ] **🔮 Actualizar Laravel (8.x → versión LTS/actual soportada)** por seguridad y compatibilidad de librerías
      (incl. `spatie/laravel-permission` y futuras dependencias). **NO ahora:** va **después de la reconstrucción por
      verticales**, con cada parte verificada contra producción (hay bugs "load-bearing" + drift de migraciones → un salto
      mayor sobre la base actual es riesgoso). **Hacer en una COPIA aislada** (no en esta carpeta); el update es **por
      proyecto** (`composer update` solo reescribe `crewcarerr/vendor/`, no afecta otras apps de `C:\laragon\www\`; lo único
      compartido es el PHP global de Laragon). Revisar el upgrade guide por cada salto mayor + verificar la versión de PHP
      requerida antes de tocar el PHP global. Detalle en [RECIPE.md](RECIPE.md) Bloque 4 y [ROADMAP.md](ROADMAP.md) §4.6.

### Estructura — partición de AdminController (strangler)
Roadmap ordenado de los cortes del God Object (`AdminController`, ~581 líneas), consolidado a **~6-7 controllers cohesivos**
(NO 12). Riesgo-rateado para que sesiones futuras lo continúen. Detalle arquitectónico en
[ARCHITECTURE.md](ARCHITECTURE.md) §6.
- [x] **1. CrewListController** — listados read-only de crew (`usuarioscrud`/`comcrud`/`medicocrud`/`idcardscrud`) + `idcard`.
      **✅ DONE (2026-06-27)** — ver entrada **🏗️ Estructura (1/n)**. Riesgo: **muy bajo** (movimiento puro). NOTA: `idcard($id)`
      se movió sin scope (H2 defensa-en-profundidad, diferido).
- [x] ~~**2. Catalog CRUD**~~ **✅ DONE (2026-06-28 tarde, fase 3)** — carveado a **`CatalogController`** **junto con su rediseño** (como
      pedía el caveat): el CRUD ahora opera sobre las **tablas NUEVAS** `departments`/`positions` (no las legacy `departamento`/`puesto`
      que nadie leía) + nueva columna **`sort_order`** (reemplaza el hack muerto `users.labn`); Notificaciones siguen en
      `usuariosnotificacione`. Endurecido al moverse: validación (cierra H4 en catálogos), GET→POST en activaciones, `findOrFail`,
      `whereNumber` en rutas, `@can('catalogs.manage')`. Ver entrada **🏗️ Rediseño de catálogos (paso #2) + God Object ELIMINADO**.
- [x] **3. Crew create** (`adduser`/`newuser`) — ya **moderno** (migración 2026-06-25: whitelist explícita de campos, scope por
      depto, escrituras a pivote). **✅ DONE (2026-06-28)** — extraído **verbatim** a **`CrewController`** (escrituras de crew);
      movimiento puro, mismas vistas/rutas/middleware. Ver entrada **🏗️ Estructura (2/n)**. Riesgo: **bajo** (mecánico). NOTA: el
      perfil (paso #4) se **sumará a `CrewController`** y ahí se cierran H2 (scope) + H4 (mass-assignment).
- [x] **4. Crew profile edit** (`useredit`/`acountupdate`) — **✅ DONE (2026-06-28)** — extraído a **`CrewController`** (escrituras
      de crew) **y endurecido al moverse** (no movimiento puro): scope por depto vía el helper `User::canManageCrewMember`
      (`findOrFail`+`abort_unless 403`) → **cierra H2** en este path; y el mass-assign `$request->except('password')` reemplazado por
      **whitelist validada** (los 11 campos del form; `password` aparte) → **cierra H4** en `acountupdate`. Ver entrada **🏗️
      Estructura (3/n)**.
- [x] **5. Crew status toggles** (`checkgft`/`uncheckgft`/`activarusuario`/`activarencuesta`/`desactivarusuario`) — **✅ DONE
      (2026-06-28)** — extraídos a **`CrewStatusController`** y endurecidos: guard de scope por depto (H2) vía
      `findOrFail`+`canManageCrewMember` en la carga del `{id}`; además `activarusuario`/`activarencuesta` **GET→POST+CSRF** (H1).
      Ver entrada **🏗️ Estructura (4/n)**.
- [x] **6. Admin-grant + grupos de prueba** (`activaradmin`/`desactivaradmin`/`putga`/`putgb`/`putgg`/`selectpuesto`) — **✅ DONE
      (2026-06-28)** — extraídos a **`CrewStatusController`** y endurecidos con guard de scope por depto (H2); el grant admin conserva
      el `$producto->admin=1` explícito (no mass-assignable). Ver entrada **🏗️ Estructura (4/n)**.
- [x] **7. Sueltos a su dominio** — **✅ DONE (2026-06-28):** ~~limpiar export CSV muerto en `AdminController`~~ **✅ hecho** —
      `exportCsv()`/`expCsv()` **borrados** de `AdminController` (sin ruta; el export vivo es `resetController@expCsv` → `/nophoto`,
      entrada **🏗️ Estructura (3/n)**); **`historialWR`** movido verbatim a **`cmedicController`** (su dominio médico); **`welcomeresend`/
      `sendreminder`** → ahora **comandos Artisan** (`crew:welcome-resend`/`crew:daily-reminder`); las rutas web GET con efecto colateral
      y sin UI **eliminadas** y `CrewMailController` retirado a `_legacy_backup/` (✅ 2026-06-28, entrada **⚙️🧹 Correos de crew →
      comandos Artisan**). Ver entrada **🏗️ Estructura (4/n)**.
- [x] ~~**2. Catalog CRUD — ÚNICO PASO RESTANTE, DIFERIDO.**~~ **✅ DONE (2026-06-28 tarde, fase 3)** — carveado **junto con su rediseño**
      (como recomendaba el caveat) a **`CatalogController`** sobre las tablas NUEVAS `departments`/`positions` (+ `sort_order`);
      Notificaciones siguen en `usuariosnotificacione` legacy. CRUD endurecido (validación + GET→POST + `findOrFail`). Con esto
      `AdminController` quedó vacío y fue **RETIRADO** a `_legacy_backup/` → **God Object 100% desmantelado** (cero referencias en
      `route:list`). Ver entrada **🏗️ Rediseño de catálogos (paso #2) + God Object `AdminController` ELIMINADO**.

### Deuda técnica / limpieza
- [ ] Crear migraciones para las tablas de dominio (esquema no reproducible desde el repo).
- [ ] Quitar fechas de rodaje hardcodeadas en `HomeController` (2025-04).
- [~] ~~Implementar o quitar `DailyReportController@downloadPdf` + import de `Browsershot` sin usar.~~ **✅ PARCIAL (2026-06-28)** —
      import muerto `Browsershot` **eliminado** y la **ruta huérfana `daily_reports.pdf` ELIMINADA** (apuntaba a un método inexistente;
      ninguna vista la enlazaba). Resta como **FEATURE diferida:** implementar el export PDF del DSR (hoy no ruteado). Ver entrada
      **🧹⚡ Optimización de las 7 verticales operativas**.
- [ ] **Reimplementar el recordatorio de foto de gafete como comando agendado `badge:reminder`** — el owner declaró la versión
      anterior (`GET /PhotoReminder`, ya **eliminada** en **🧹🔒 Seguridad (2b)**) **"muy rústica"** y pidió rehacerla **"más práctica"**:
      un comando Artisan agendado (mismo patrón que `encuestas:task` en `app/Console/Kernel.php` — reset/notify diario) que mande
      `correos.photo` a los usuarios con `age=0` (`age` = flag de foto-de-gafete subida; **NO** es COVID ni el reset clínico diario).
      La plantilla `resources/views/correos/photo.blade.php` **se conservó** lista para reusar. No bloqueante.
- [x] ~~Borrar código muerto: `encuestasController@checkfroms`, `FormulariosController@newformulario1`.~~ **✅ DONE (2026-06-25)** — removidos `encuestasController@checkfroms` y los 3 métodos muertos de `FormulariosController` (`newformulario1`/`newformulario2`/`checkform`); ver entrada **COVID desacople (2/n)**.
- [~] ~~Consolidar `locationController` vs `locationController2`.~~ **✅ RESUELTO POR DECOMISIÓN (2026-06-28 tarde)** — ambos
      controllers (+ las 5 vistas `location*`) se **MOVIERON a `_legacy_backup/decommission-2026-06-28/`** y sus rutas `/location*` se
      eliminaron; el **Scouting H&S es ahora el ÚNICO scouting**. El modelo `locationreport` se **conserva** (lo usa el dashboard).
      Ver entrada **🔁 Ajustes del owner sobre "los 4 items"**.
- [ ] **Migrar la métrica del dashboard `locationreport` → `scouting_reports` (HomeController)** — al decomisionar el scouting viejo
      (2026-06-28 tarde) el modelo `App\Models\locationreport` se **conservó** solo porque `HomeController` aún cuenta
      `locationreport::count()` y usa `locationreport::oldest()` para los "días de producción". Migrar esos dos usos a
      `scouting_reports` y entonces retirar el modelo legacy. No bloqueante.
- [x] ~~**Autofirma transversal (DSR + 3 reportes de incidentes)**~~ **✅ DONE (2026-06-28 tarde, fase 2)** — el patrón de autofirma
      server-side se extendió al **DSR** (`author_name`) y a los **3 reportes de incidentes** (`make_by`/`make_date`), y la columna
      **`created_by_id`** se añadió a las **5 tablas de reportes** (`$fillable` + `auth()->id()` en `store`). La atribución ya no es un
      campo libre falsificable. Ver entrada **✍️ Autofirma TRANSVERSAL · Homologación de las 3 vistas show**.
- [x] ~~**Doble `json_encode` de `additional_images_paths` (hazard/unsafecond)** — el controller hace `json_encode` y el modelo además
      castea a `array` → doble codificación.~~ **✅ DONE (2026-06-28)** — era un **BUG real** (rompía la lectura de imágenes adicionales):
      se quitó el `json_encode` manual en `Injury`/`Hazard`/`unsafecond@store`; ahora se asigna el array directo (deja que el cast del
      modelo codifique). Ver entrada **🛡️ Endurecimiento de métodos de creación**.
- [ ] **Trait `HandlesImageUploads` (DRY) + job `DeleteOrphanedImages` (atomicidad)** — el patrón de subida de imágenes está duplicado
      en ~5 controladores (`Injury`/`Hazard`/`unsafecond`/`location`/`location2`); extraerlo a un trait. Además, hoy los archivos se
      guardan **antes** del `create()` → si el `create()` falla queda una **imagen huérfana**: pendiente un job de limpieza
      `DeleteOrphanedImages` o transacción con cleanup. Detectado en la pasada **🛡️ Endurecimiento de métodos de creación** (2026-06-28).
      No bloqueante.
- [ ] **Export PDF del DSR (`DailyReportController`)** — implementar como feature (la ruta huérfana `daily_reports.pdf` y el import
      `Browsershot` ya se eliminaron el 2026-06-28). No bloqueante.
- [ ] **Refactor de validación (Estructura/Limpieza)** — añadir validación por formulario (Form Requests / reglas `validate()`)
      a los controllers que hoy vuelcan `$request->all()`/`except()` sin lista blanca, y sustituir `$guarded=[]` por `$fillable`
      acotado en los modelos DSR (`DailyReport`/`DailyLog`/`SafetyStandard`). **✅ AVANCE (2026-06-28):** `DailyReport` y `DailyLog` ya
      pasaron a `$fillable` explícito (verificado que cubre lo que el controller escribe); resta `SafetyStandard` y los `$request->all()`
      de catálogos. **Origen:** H4 de [SECURITY.md](SECURITY.md), recalibrado
      a BAJO y diferido aquí en la entrada **🔒 Seguridad (2c)** (ninguno toca `User`/privilegios; el vector crítico ya cerrado en C2).
      Hacerlo CRUD por CRUD para no romper los formularios de create/update con cambios ciegos de whitelist.
- [ ] Centralizar dirección "from" de correos (hoy 3 distintas).
- [x] ~~**Catálogos (departamentos/puestos/notificaciones CRUD)** — *petición del owner (2026-06-25):* HOMOLOGARLOS con `adduser`.~~
      **✅ DONE (2026-06-28 tarde, fase 3)** — el CRUD (ahora en `CatalogController`) opera sobre las **mismas tablas nuevas**
      `departments`/`positions` que usa el alta de crew → catálogo y formulario por fin coinciden. Ver entrada **🏗️ Rediseño de catálogos**.
- [x] ~~**Ordenar departamentos/puestos (call sheet + Crew List)** — columna `sort_order` en `departments`/`positions` + reordenar en el
      CRUD; `users.labn` ("Jerarquía") muerto.~~ **✅ DONE (2026-06-28 tarde, fase 3)** — columna **`sort_order INT NOT NULL DEFAULT 0`**
      añadida a `departments` y `positions` (`$fillable`+`$casts`); `CatalogController` reordena; `CrewController@adduser` ordena los
      dropdowns por `sort_order`. El orden lo dan los catálogos, no la persona. **Pendiente del lote de esquema:** dropear `users.labn`.
      Ver entrada **🏗️ Rediseño de catálogos**.

---

## Contexto clave del repositorio (2026-06-24)
- **Este repo = BASE/plantilla, OFFLINE, datos de PRUEBA.** No es producción. Se puede
  reestructurar a fondo (rehacer tablas, renombrar campos) sin dañar nada vivo.
- **Modelo de negocio:** cada cliente recibe una copia de esta base en su VPS, personalizada.
  ⟹ mover personalización a **configuración**, no a edición de código por copia.
- **AWS = requisito de cliente (Amazon exige su tecnología).** La base debe ser AWS-desplegable
  cambiando `.env`/config, no código (abstraer disco→S3, BD→RDS, correo→SES).
- **Consecuencia en la estrategia de esquema:** ya NO aplica "strangler" a esta base (era para
  datos vivos). Aquí **rediseñamos el esquema limpio en migraciones** directamente. El strangler
  queda solo para actualizar instancias de clientes ya desplegadas.
- Petición del owner: simplificar la BD (p. ej. reporte médico ~50 campos → `medical_records`
  esbelta) y optimizar el código de las views. Plan ordenado en [RECIPE.md](RECIPE.md).

## Decisiones de producto fijadas (2026-06-23)
1. **Firma electrónica: interna** (gratis, control total), modelo extensible a NOM-151/proveedor externo.
2. **Validez legal: firma simple** por ahora; diseñar para **NOM-151** futura sin rehacer.
3. **Documentos: paquetes configurables por producción** (no lista fija). Ej.: Amazon exige
   Safety Guidelines firmado por crew; cada proyecto puede pedir documentos distintos / varios.
   Orden de arranque: Hiring Form → Deal Memo → NDA → Contrato → Safety Guidelines.
4. **GitHub: empezar de cero** cuando la app esté más completa (`git init` limpio → sin
   problema de secretos en historial). Saneamiento de `.env`/secretos = checklist pre-push futuro.
5. **Esquema: formalizar SÍ, por strangler pattern** (no big-bang). Primer paso de mayor palanca:
   convertir el esquema actual en migraciones (reproducible/versionable sin cambiar datos).
   Renombres de campos confusos por módulo. Detalle en [ROADMAP.md](ROADMAP.md) §3.5.

---

## Historial de cambios

> **⚠ RECONSTRUIDO EN BLOQUE (2026-08-06).** La bitácora se había quedado en **2026-06-28**;
> las entradas de **2026-07-06 → 2026-08-06** se repusieron sintetizando la **memoria**
> (`/memory/*.md`) y el **`git log`** (tras el reinit de historial del 2026-07-31, que
> squasheó lo previo). El detalle fino de cada bloque vive en su nota de memoria enlazada.
> De aquí en adelante se registra por bloque en tiempo real. Fechas = de la memoria/commits.

### 2026-08-08 — 📊 Wrap: membrete vivo + borrador usable (ver+omitir+notas) + flujo de bloques + móvil
- **Estado:** Hecho (verificado por harness: render borrador+sellado TODO OK, `editorChoices()` correcto, sello del wrap con elecciones VÁLIDO, WRAP-0002 intacto). SIN SQL. 2 commits (`b0855bf4`, `74c8e8af`).
- **Archivos:** `resources/views/admin/wrap/show.blade.php`, `resources/views/admin/wrap/_editor-note.blade.php` (nuevo), `app/Http/Controllers/WrapReportController.php`.
- **Qué / Por qué (3 arreglos reportados + 1 feature):**
  1. **Membrete VIVO.** El hero y la ficha "Producción" tomaban el nombre CONGELADO del payload (`s1.produccion` = "Producción Demo"). Ahora usan `brand_name` en vivo (HTLR) como TODOS los demás docs. El proyecto se renombra (QMAS→QPCS→QAAS) y se refleja aunque el doc ya esté emitido; el sello protege el CONTENIDO, no el membrete. Sello intacto.
  2. **Borrador VISIBLE.** La tira "Borrador en vivo / Emitir y sellar" vivía en un `.stage` de `min-height:100vh` → llenaba la pantalla y empujaba el documento una hoja abajo (parecía que "no renderizaba nada"). Ahora es una tira compacta (`.wrap-controls`) y el documento completo queda debajo. Mismo arreglo para el mensaje post-emisión.
  3. **Flujo de bloques + móvil.** Cuerpo fuera del `<td>` de `report-wrap` → `<div class="doc-body">` (Chrome respeta `break-inside:avoid` en flujo normal); `@page` oficio con respiro superior + pie fijo. Móvil: tablas con scroll horizontal, hero ajusta el nombre al ancho. Costo: hero sólo en 1ª hoja (pie fijo sí repite).
  4. **FEATURE — panel de borrador (omitir apartados + notas por apartado).** `<details>` con checkbox incluir (s2–s8; s1+sello fijos) + campo de nota por apartado, dentro del form de emisión; JS de preview EN VIVO. **Omitir = SILENCIOSO** (elección del owner): apartados omitidos ocultos por `<style>` generado (`display:none` → fuera del PDF). **Notas = una por apartado** (cinta bajo el encabezado, imprimible). Elecciones CONGELADAS en `payload.editor` al emitir → las cubre el sello.
- **Riesgo/Notas:** SIN SQL, SIN cambio de modelo (el `editor` va dentro del `payload` JSON existente → no hay drift retroactivo; WRAP-0002 sin la clave renderiza igual). **El owner debe confirmar el render** (móvil + borrador + panel), es la plantilla antes del rollout. Ver [[wrap-report-final-built]] y [[doc-hero-band-homologation]].

### 2026-08-08 — 🚑 Ambulancias (Parte D): flag en scouting + badge del recurso del día en MEDEVAC/PAE (delta #54)
- **Estado:** Hecho (verificado por harness: regresión de sellos + salida de builders + render de los elementos nuevos). Delta #54 aplicado local.
- **Archivos:** `database/owner-apply/2026-08-08-scouting-has-ambulance.sql` (nuevo), `app/Models/ScoutingReport.php`, `app/Http/Requests/StoreScoutingReportRequest.php`, `app/Http/Controllers/ScoutingReportController.php`, `resources/views/admin/scoutings/_form.blade.php`, `resources/views/admin/scoutings/show.blade.php`, `resources/lang/{es,en}/reports.php`, `app/Support/MedevacPosterBuilder.php`, `resources/views/admin/medevac/show.blade.php`, `app/Support/AmbulanceResourceBadge.php` (nuevo), `app/Support/EmergencyActionPlanBuilder.php`, `resources/views/admin/pae/show.blade.php`.
- **Qué / Por qué:**
  1. **Scouting `has_ambulance`** (tri-estado Sí/No/sin declarar): decisión de PLANEACIÓN (la unidad se verifica aparte). Delta #54 (`TINYINT(1) NULL`). **Crítico de sello:** scouting está SELLADO y no tenía `$signatureExcludes`; se declaró `$signatureExcludes = ['has_ambulance']` → la bandera NO entra al hash y los scoutings ya sellados siguen VÁLIDOS (probado: fijar la bandera no altera el sello; la clave nunca aparece en el payload canónico).
  2. **MEDEVAC** (builder v1→**v2**): el póster congela `has_ambulance` del scouting y la vista pinta "Ambulancia prevista: Sí/No". Additivo: los pósters v1 no la traen → la línea no se pinta.
  3. **PAE** (builder v1→**v2**): nuevo `App\Support\AmbulanceResourceBadge` (snapshot congelado del recurso del día: ambulancia en sitio con su acta+veredicto / medio declarado / hueco). El PAE lo embebe y lo pinta como tarjeta con tono por veredicto. **No sobreclama:** el cotejo de tripulación se declara "documentos revisados", NUNCA "verificado contra registro" (esa capacidad no existe).
- **Riesgo/Notas:** Regresión de sellos LIMPIA respecto a Parte D — MEDEVAC 16/16 y PAE 1/1 válidos; los 3 scoutings ALTERADOS (id 25/26/27) traen firmas VIEJAS (204-206) y ya estaban rotos por drift local previo (la bandera está excluida del hash, no puede causarlo; prod arranca vacío). Cambios de builder solo afectan emisiones NUEVAS (payload congelado). Aplicar en prod: delta #54. Ver [[ambulance-verification-module]].

### 2026-08-08 — 🖨️ Acta ambulancia: cuerpo en FLUJO DE BLOQUES (fin real del texto cortado)
- **Estado:** Hecho (verificado por harness: 80 puntos como `.amb-chk-row`, render TODO OK, sello intacto). Demo = **AMBU-0017**. SIN SQL.
- **Archivos:** `resources/views/ambulance/acta.blade.php` (CSS + estructura).
- **Qué / Por qué:** el intento de "una `<tr>` por punto" SEGUÍA cortando — Chrome tampoco respeta `break-inside` en FILAS de tabla de forma fiable (área notoriamente floja). Lo que SÍ respeta es `break-inside:avoid` en **bloques en flujo normal**. Fix definitivo: el cuerpo se sacó de la tabla `report-wrap` y va en un `<div class="doc-body">` (flujo de bloques); el checklist volvió a ser lista de divs (`.amb-chk-row`, cada uno `break-inside:avoid`). Los saltos caen ENTRE puntos, nunca dentro. El hero + cintillo van full-bleed arriba (una vez); el pie fijo se repite por hoja vía margen inferior del `@page`; y el `@page` deja **margen superior de respiro** en cada hoja (el buffer que pidió el owner). Papel Oficio.
- **Riesgo/Notas:** SIN SQL, SIN cambio de sello. **Cambio de marco:** el hero ya NO se repite en cada hoja (sale solo en la 1ª; el pie sí se repite con el folio/UUID) — trade necesario para paginar en flujo de bloques. El diseño por lo demás es idéntico. **El owner debe confirmar el PDF** (ahora el mecanismo es el fiable de CSS). Ver [[doc-hero-band-homologation]] (gotcha impresión #2, actualizado) y [[ambulance-verification-module]].

### 2026-08-08 — 🖨️ Acta ambulancia: cuerpo como FILAS de tabla (fin del texto cortado)
- **Estado:** Hecho (verificado por harness: render TODO OK, 80 puntos = 80 `<tr>`, sello intacto, AMBU-0005 válida). Demo = **AMBU-0016**. SIN SQL.
- **Archivos:** `resources/views/ambulance/acta.blade.php` (reestructura del `<tbody>` + CSS).
- **Qué / Por qué:** el texto SEGUÍA cortándose en los saltos pese al `overflow:visible`. Causa definitiva: **todo el cuerpo vivía en UNA sola celda `<td>` del report-wrap**, y Chrome IGNORA `break-inside` dentro de una celda que pagina (parte el contenido a media línea). La ÚNICA cosa que Chrome nunca parte entre hojas es una **fila de tabla de primer nivel**. Fix: el cuerpo se reescribió como **una `<tr>` por sección y una `<tr>` por punto del checklist** (celda `.acell` con el padding del cuerpo; `.chkcell` con el grid de 3 columnas dentro). El hero (thead) y el pie (tfoot) siguen repitiéndose por hoja igual que antes — el marco visual no cambia. Se eliminó el `<div class="body">` envolvente.
- **Riesgo/Notas:** SIN SQL, SIN cambio de sello (solo maquetado). El diseño se ve idéntico; cambia solo cómo se fragmenta al imprimir. **El owner debe confirmar el PDF** — pero las filas de tabla son atómicas por definición, así que el corte de texto queda resuelto. Ver [[doc-hero-band-homologation]] (gotcha de impresión) y [[ambulance-verification-module]].

### 2026-08-08 — 🖨️ Fix de impresión: `.sheet overflow:visible` (texto cortado) + nombre = créditos
- **Estado:** Hecho (verificado por harness: render TODO OK). Demo = **AMBU-0015**. SIN SQL. **Toca el chrome COMPARTIDO** (beneficia a todos los reportes largos).
- **Archivos:** `resources/views/componentes/_report-v2-head.blade.php` (chrome), `app/Http/Controllers/AmbulanceController.php`, `app/Http/Controllers/InspectionController.php`.
- **Qué / Por qué (feedback del owner: "se sigue cortando el texto"):**
  1. **CAUSA REAL del texto cortado:** el chrome ponía `.sheet{overflow:hidden}` (para las esquinas redondeadas en pantalla) y **NUNCA lo reseteaba en impresión**. Un contenedor `overflow:hidden` hace que Chrome trate la hoja como UN fragmento y **recorte** el contenido en los saltos de página en vez de fluirlo — por eso ni los divs ni el papel Oficio lo arreglaban. Fix: `overflow:visible` en `.sheet` en `@media print` y en `:root[data-view="print"]`. Papel ya era **Oficio** (216×340mm). Aplica a TODOS los reportes largos.
  2. **Nombre del firmante = NOMBRE DE CRÉDITOS** (`User::displayName` → `ncreditos`), no `shortName`. Da "Ari Rómulo" CON acento (el crédito real); `shortName` daba "Ari Romulo" sin acento (del `lname`). Footer + Inspector de Datos. Igualado en inspección de herramienta.
- **Riesgo/Notas:** SIN SQL, SIN cambio de sello (nombre solo en actas NUEVAS). El `overflow:visible` en print es inocuo para reportes de 1 hoja y correcto para los largos. **El owner debe re-verificar el PDF** (ahora sí debería fluir sin cortar). Ver [[doc-hero-band-homologation]].

### 2026-08-08 — 🚑 Acta de ambulancia: 3ª ronda de ajustes del owner (nombre, paginación, cintillo)
- **Estado:** Hecho (verificado por harness: render TODO OK; sello intacto; AMBU-0005 sigue válida). Demo = **AMBU-0014**. SIN SQL nuevo.
- **Archivos:** `resources/views/ambulance/acta.blade.php`, `app/Http/Controllers/AmbulanceController.php`, `app/Http/Controllers/InspectionController.php`.
- **Qué / Por qué (feedback del owner):**
  1. **"Reporte elaborado por" salía solo "Ari"** → `inspector_name = User::shortName($author)` (nombre de pila + 1er apellido → "Ari Rómulo"). La ronda 2 usó `->name` que se quedaba en "Ari". Igualado en la inspección de herramienta. Afecta footer **y** el Inspector de "Datos".
  2. **Se perdía información en los saltos de página (incluido el sello):** el checklist era una `<table>` ANIDADA dentro de la celda del `report-wrap`; Chrome NO respeta `break-inside` en filas de tablas anidadas dentro de una celda paginada → recortaba filas. Se reescribió como **lista de DIVS** (`.amb-chk-row`, grid 3 col) que SÍ respeta `break-inside:avoid` → cada fila salta entera de hoja. (El owner separó fecha/hora con `|` en el cintillo; se conserva.)
  3. **Datos duplicaba la fecha** (ya está en el cintillo "Fecha y hora") y se veía amontonada → se **quitó** la fact "Fecha" de Datos.
  4. **Borde izquierdo amarillo del cintillo** marcado como defecto → override `.band .lead{border-left:0}` en el `<style>` del acta (después del chrome → solo afecta al acta).
- **Riesgo/Notas:** SIN SQL, SIN cambio de sello (todo lo del hash intacto; nombre corto solo en actas NUEVAS). La paginación por DIVS es el arreglo robusto conocido para el recorte de tablas anidadas en Chrome; **el owner debe re-verificar el PDF impreso**. Ver [[doc-hero-band-homologation]] y [[ambulance-verification-module]].

### 2026-08-08 — 🚑 Acta de ambulancia: 2ª ronda de ajustes del owner + locación GPS (delta #55)
- **Estado:** Hecho (verificado por harness: render + aserciones TODO OK; sello íntegro; AMBU-0005 sigue válida). Delta #55 aplicado local. Demo = **AMBU-0013**.
- **Archivos:** `resources/views/ambulance/acta.blade.php`, `resources/views/ambulance/execute.blade.php`, `app/Http/Controllers/AmbulanceController.php`, `app/Models/AmbulanceInspection.php`, `database/owner-apply/2026-08-08-ambulance-location.sql` (nuevo).
- **Qué / Por qué (feedback del owner sobre la 1ª hoja):**
  1. **Proyecto y proveedor cambian de lugar:** hero = **proyecto** (`brand_name`); banda lead = **proveedor**. (La ronda anterior los tenía al revés.)
  2. **Cintillo:** se **quita "Veredicto"** (se repetía muy cerca del grande de abajo); "Fecha" → **"Fecha y hora"**; sub del lead = **solo el folio** (se quitó el nombre del tipo, que ya está en Datos → sin duplicar).
  3. **Locación por GPS-back:** nueva captura de dónde se verificó. `_geo-capture` en modo **silent** en el form (capta lat/lng por detrás y sugiere el nombre del scouting cercano; editable), columnas `latitude/longitude/location_label` (delta #55), se muestra en el cintillo. **Hash-excluida** (contexto/provenencia añadida después de que ya había actas → si entrara al sello, AMBU-0005 saldría ALTERADA por drift de esquema; probado que excluirla la mantiene válida).
  4. **Rama/Nivel** ya no inicia en minúscula (`ucfirst`).
- **Riesgo/Notas:** SIN cambio de sello para actas viejas (locación excluida; verificado AMBU-0005 válida). Aplicar en prod: **delta #55**. Ver [[ambulance-verification-module]] y [[scouting-geo-module]] (GPS en el navegador).

### 2026-08-08 — 🚑 Acta de ambulancia: 6 ajustes de diseño del owner (post-homologación)
- **Estado:** Hecho (verificado por harness: crea acta demo sellada, renderiza y afirma cada arreglo; sello íntegro tras render, ALTERADO al manipular). Demo corregida = **AMBU-0010** (clones de prueba borrados).
- **Archivos:** `resources/views/componentes/_doc-hero.blade.php` (+`$heroHideCallbox`), `resources/views/ambulance/acta.blade.php`, `app/Http/Controllers/AmbulanceController.php`, `app/Http/Controllers/InspectionController.php`, `resources/views/ambulance/execute.blade.php`.
- **Qué / Por qué (feedback del owner sobre el PDF):**
  1. **Hero ya no duplica "CrewCare"** — el pie del hero antepone "CrewCare"; el módulo pasó de `'CrewCare · Verificación de ambulancia'` a `'Verificación de ambulancia'`.
  2. **Se quitó el cuadro negro** (locación/fecha): nuevo flag `$heroHideCallbox` en `_doc-hero` (retrocompatible; default lo muestra). La fecha vive en la banda.
  3. **Hero muestra el PROVEEDOR, no el proyecto** — `heroProject = provider_name` (el proyecto sigue en el logo + banda).
  4. **Fotos sin pie de foto** — se quitaron los `.cap` ("Unidad"/"Evidencia N"): no aportaban.
  5. **Observaciones = SOLO el campo libre** — el controlador dejó de auto-volcar las fallas del checklist y la correspondencia (ya viven en su tabla/campo); `observations = note ?: null`.
  6. **Footer con nombre corto** — `inspector_name = $author->name` (como DSR/Injury/Scouting), no `fullName()` → "Ari Rómulo", no "Ari Rómulo Romulo". Igualado en la inspección de herramienta.
  - **Extra:** el checklist (largo) ahora **fluye entre hojas** (`.amb-chk{break-inside:auto}` + filas protegidas + encabezado repetido) → mata el hueco en blanco de la página 1.
- **Riesgo/Notas:** SIN SQL. Cambios de vista aplican a actas ya selladas al abrirlas; los cambios de DATO (observaciones/nombre) solo afectan actas NUEVAS (las selladas conservan su valor congelado, es correcto). Sello intacto (se firma sobre el dato). Ver [[ambulance-verification-module]].

### 2026-08-08 — 🎨 Actas homologadas al chrome v2 + Exportar PDF (ambulancia + herramienta)
- **Estado:** Hecho (verificado: ambas vistas compilan y **renderizan** con acta sellada real → documento standalone `<!DOCTYPE>`+`report-wrap`, botón `#pdfBtn` (Exportar PDF = `window.print()`), **sin** el layout de la app (no `cc-sec` de sidebar), sello CFDI presente, y **sello íntegro tras render** —no toca el dato—). Construido con 2 subagentes en paralelo (archivos disjuntos).
- **Archivos:** `resources/views/ambulance/acta.blade.php`, `resources/views/inspection/acta.blade.php` (ambas reescritas). SIN cambios de controlador/rutas/modelo/SQL.
- **Qué / Por qué (petición del owner):** las dos actas usaban `@extends('layouts.app')` (chrome de app con sidebar) → se veían arcaicas y **no exportaban a PDF**. Se reconstruyeron como **documento standalone** sobre el "chrome v2" compartido que ya usan DSR/PAE/MEDEVAC/Injury (`_report-v2-head/-toolbar/-foot` + `_doc-hero` + banda + `.sec`): cinematográfico en pantalla, blanco imprimible, y **Exportar PDF vía `window.print()`** (mismo mecanismo que todos; sin dompdf/Browsershot). Se preservó TODA la lógica (veredicto derivado, datos congelados, tripulación+CONOCER cotejado, checklist, foto+evidencia, correspondencia, observaciones, RETIRADO, desbloqueo del paro, action item, sello CFDI). Motivo de negocio: si la producción no usa CrewCare de base, **los PDF se envían siempre** — son la bitácora diaria.
- **Riesgo/Notas:** SIN SQL. El sello no cambia (se firma sobre el dato, no el render). Gotcha reencontrado: `@endif@if` inline encadenados rompen Blade (`\B@`) → se aplanó en PHP. Ver [[doc-hero-band-homologation]].

### 2026-08-08 — 🚑 Ambulancias (3c/n): evidencia fotográfica múltiple sellada (delta #53)
- **Estado:** Hecho (verificado: delta #53 aplicado local, controlador `php -l` limpio, `execute`/`acta` compilan, y **la evidencia entra al sello**: acta con 3 fotos íntegra → quitar una = ALTERADO en el verificador). Sube 0 riesgo a sellos viejos (no hay actas en prod).
- **Archivos:** `database/owner-apply/2026-08-08-ambulance-evidence-photos.sql` (NEW, delta **#53**), `app/Models/AmbulanceInspection.php` (+`evidence_photos` fillable/cast/`evidencePhotoUrls()`), `app/Http/Controllers/AmbulanceController.php` (guarda varias `evidence_photos[]`), `resources/views/ambulance/execute.blade.php` (+sección repetible), `resources/views/ambulance/acta.blade.php` (+galería).
- **Qué / Por qué (petición del owner):** poder **adjuntar varias fotos como PRUEBA** de lo verificado — para sostener una **revocación** (paro / no autorizado) o para **argumentar una autorización** (apta) después del hecho. Columna `ambulance_inspections.evidence_photos` JSON (lista de rutas, disco 'public'); es **contenido → entra al hash** (evidencia inmutable; cambiarla en un acta sellada = ALTERADO). Captura repetible ("agregar otra foto"), opcional; se comprime en el navegador (HEIC) como el resto.
- **Riesgo/Notas:** **Aplicar delta #53 en prod** (ADD COLUMN idempotente, requiere el #52). Sin permiso/lang nuevos. Ver [[ambulance-verification-module]].

### 2026-08-08 — 🚑 Ambulancias (3b/n · AJUSTES del owner): un solo checklist + TAMP/CONOCER + términos
- **Estado:** Hecho (verificado: controlador `php -l` limpio, 14 rutas intactas, 5 vistas compilan, y simulación del flujo nuevo: alta empresa + TAMP CONOCER cotejado + **checklist COMPLETO AMB-02=67 puntos** → apta, sello íntegro, `trigger_scope='completa'`, crew cotejado en snapshot).
- **Archivos:** `app/Http/Controllers/AmbulanceController.php` (inspectForm/storeInspection reescritos, helper muerto retirado), `app/Models/AmbulanceInspection.php` (+const `TRIGGER_FULL='completa'`), `resources/views/ambulance/execute.blade.php` (reescrita), `resources/views/ambulance/partials/_crew-new-row.blade.php` (NEW), `resources/views/ambulance/acta.blade.php` (ajustes).
- **Qué / Por qué (feedback del owner):**
  1. **UN SOLO CHECKLIST completo**, como herramienta/maquinaria: cada ambulancia en set abre la inspección y se corre entera. **Fuera los 4 disparadores** (identidad/unidad/consumo/riesgo); el checklist = `applicablePoints()` completo del tipo. `trigger_scope` guarda `'completa'`.
  2. **Términos SIN abreviar**: rol "Técnico en Atención Médica Prehospitalaria (TAMP)"; glosa TAMP/RPBI/DEA junto al checklist y en el acta.
  3. **TAMP cotejado con folio CONOCER** + **sustento fotográfico del certificado Y de la persona** (no solo el papel: el certificado puede ser de otra persona). Se crea `AmbulanceCrew` + `ExternalAuthorization`(CONOCER) validado (manual, atestado) bajo la empresa; `verified=true` solo con folio+2 fotos.
  4. **Todas las fotos del checklist OPCIONALES** (unidad + fotos del TAMP); sin las 2 fotos, el TAMP queda registrado "sin cotejar".
  5. **Alta de proveedor en el mismo apartado**: en el form eliges empresa existente o das una de alta inline; cada ambulancia se enlaza a su empresa; **misma empresa reusa, empresa distinta = alta nueva**.
- **Riesgo/Notas:** SIN SQL nuevo (columnas del #52 ya lo soportan; `trigger_scope` seguía en el esquema). Correspondencia tipo↔riesgo ahora es sección OPCIONAL del mismo checklist (no un disparador). **Sigue pendiente Parte D** (badge en MEDEVAC/PAE + flag scouting, seal-sensitive). Ver [[ambulance-verification-module]].

### 2026-08-08 — 🚑 Ambulancias (3/n · MÓDULO): controlador + 9 vistas + veredicto + rutas + sidebar
- **Estado:** Hecho (verificado: `php -l` controlador+veredicto limpio, **14 rutas registran**, **9 vistas Blade compilan**, `AmbulanceVerdict` fail-safe probado con catálogo real —AMB-02/unidad 63 puntos→apta, AMBV-012 paro_inmediato→PARO, compuerta sin outcome→no_exec nunca apta—, sidebar entrada+guard en ambas copias). Falta prueba HTTP con sesión (owner). **Construido con 3 subagentes en paralelo** (archivos disjuntos) + integración manual de los compartidos.
- **Archivos:** `app/Http/Controllers/AmbulanceController.php` (NEW, 14 métodos), `app/Support/AmbulanceVerdict.php` (NEW), `resources/views/ambulance/{index,day-resource,execute,acta,records,providers,provider-show}.blade.php` + `ambulance/partials/{_doc-row,_doc-form}.blade.php` (9 NEW), `routes/web.php` (+grupo `ambulance.manage`), `resources/views/layouts/sidebar.blade.php` (+entrada `heart-pulse` en 2 copias).
- **Qué / Por qué:** el módulo funcional completo. **Parte A** recurso del día (form 3 estados). **Parte B** proveedor + padrón (alta en el momento con foto) + documentos con **validación MANUAL** (checkbox attestation + quién-validó, calcado de la cédula; CONOCER = folio tecleado + clave/nombre impresos + foto, sin QR). **Parte C** formulario en sitio (tipo + 4 disparadores, cada uno abre solo su parte; checklist derivado de `applicablePoints`∩`triggersOn`; 'riesgo' captura correspondencia sin checklist) → acta sellada con veredicto fail-safe + action item en paro. Encuadre "constancia de verificación de recurso de emergencia en sitio".
- **Riesgo/Notas:** SIN SQL nuevo (usa deltas #51/#52). Reachability via sidebar. **PENDIENTE Parte D** (badge en MEDEVAC/PAE + flag sí/no en scouting) — toca documentos SELLADOS → se hará con cuidado de regresión (scouting sellado: el flag irá en `$signatureExcludes` para no romper sellos viejos; MEDEVAC/PAE congelan payload por emisión → editar el builder no toca sellos existentes). Ver [[ambulance-verification-module]].

### 2026-08-08 — 🚑 Ambulancias (2/n · ESQUEMA): 5 tablas + modelos + verificador 'ambu' + permiso
- **Estado:** Hecho (verificado con arnés: 5 modelos bootean, tipo 'ambu' registrado, **round-trip del sello: crear→firmar→VÁLIDO, manipular placas→ALTERADO, retirar→sigue íntegro (hash-excluido), retired_reason nunca al DTO público**, "en trámite" sin folio/fecha→"no lo tiene", permiso sembrado). Delta **#52** aplicado local + seeder de permiso corrido.
- **Archivos:** `database/owner-apply/2026-08-08-ambulance-verification.sql` (NEW, delta **#52**, 5 tablas), `app/Models/{AmbulanceInspection,AmbulanceProvider,AmbulanceCrew,ExternalAuthorization,AmbulanceDayResource}.php` (NEW), `app/Support/SealVerifier.php` (+1 tipo 'ambu'), `database/seeders/AmbulancePermissionsSeeder.php` (NEW).
- **Qué / Por qué:** el modelo de datos de todo el bloque. **5 tablas:** `ambulance_providers` (empresa, se califica 1 vez), `ambulance_crew` (padrón, alta en el momento), `external_authorizations` (docs empresa+persona, polimórfico, **validación MANUAL** con quién-validó calcada de `medic_credentials`; sin QR/registro por ahora), `ambulance_day_resources` (recurso del día, **3 estados** ambulancia/medio-declarado/nada), `ambulance_inspections` (ACTA sellada, espejo de `tool_inspections`: `HasDigitalSignatures`+`GeneratesUuidKey`+`TracksCorrectiveActions`, verificador público **'ambu'**). Decisiones del owner aplicadas: scouting solo dará sí/no ambulancia (viene en sub-bloque A); validación manual con quién-validó; **sin QR**; badge dirá "DOCUMENTOS REVISADOS" (modelo capaz de "VERIFICADO CONTRA REGISTRO" a futuro vía `validation_method`).
- **Riesgo/Notas:** **Aplicar delta #52 en prod** (5 tablas, `CREATE TABLE IF NOT EXISTS`, requiere el #51) + `php artisan db:seed --class=AmbulancePermissionsSeeder` + `cache:clear`. `$signatureExcludes` del acta = `is_active`+retiro (todo lo demás al hash). "TODO por el safety" → UN permiso `ambulance.manage` (safety-officer + super-admin). Ver [[ambulance-verification-module]].
- **Pendiente del bloque:** A) recurso del día (form + inyectar en scouting el sí/no + tiempo de respuesta); B) controlador/vistas de proveedor+padrón+docs (validación manual); C) formulario en sitio + acta (calculador de veredicto fail-safe `AmbulanceVerdict`) + vista del acta; D) badge en MEDEVAC/PAE; + cableado (rutas/sidebar×2) + regresión.

### 2026-08-08 — 🚑 Ambulancias (1/n · CIMIENTO): catálogo de tipos + puntos (NOM-034)
- **Estado:** Hecho (verificado: 6 tipos/92 puntos importados = `meta.totales`; herencia terrestre monótona L1=48⊆L2=67⊆L3=80⊆L4=83; AMBV-104 en L2 sí/L3-L4 no; 72 compuertas/13 pide-documento; verified_at NULL en todo; `php -l` implícito por bootstrap). Delta **#51** aplicado local + seeder corrido.
- **Archivos:** `database/owner-apply/2026-08-08-ambulance-catalog.sql` (NEW, delta **#51**), `database/seeders/data/crewcare_ambulancias_catalogo.json` (NEW, copia del owner), `database/seeders/AmbulanceCatalogSeeder.php` (NEW), `app/Models/AmbulanceType.php` (NEW), `app/Models/AmbulanceInspectionPoint.php` (NEW).
- **Qué / Por qué:** primer sub-bloque del bloque grande "Verificación de ambulancias". Cimiento = el catálogo "de fondo" gemelo del de herramientas (`tools`): `ambulance_types` (6 tipos NOM-034: terrestre traslado/básica/avanzada/UCI + aérea + marítima) + `ambulance_inspection_points` (92 puntos binarios). La pertenencia punto↔tipo se **DERIVA** por rama+nivel con herencia A⊆B⊆C⊆D (`AmbulanceType::applicablePoints()`), no por pivote. Estado se DECLARA (verified_at NULL; el seeder nunca marca verificado ni pisa is_active).
- **Riesgo/Notas:** **Aplicar delta #51 en prod** + `php artisan db:seed --class=AmbulanceCatalogSeeder` (prod se levanta fresco desde el repo). **Correspondencia tipo↔riesgo: 0 puntos del catálogo la respaldan** (`meta.pendientes` lo dice: es criterio de industria, la fija el owner) → se modelará aparte como propuesta editable. Aérea/marítima: `applicablePoints($capacityLevel)` — sus puntos propios + los 'todas' según capacidad resolutiva declarada (apéndice A por defecto). Ver [[ambulance-verification-module]].
- **Pendiente del bloque (sub-bloques siguientes):** A) recurso de traslado del día (3 estados) + campo tiempo-de-respuesta en scouting; B) tabla NUEVA `external_authorizations` (docs proveedor empresa+persona) + CONOCER (folio+QR-acelerador, snapshot); C) formulario en sitio + acta sellada + verificador; D) badge en MEDEVAC/PAE (registro vs declaración). Falta 1-2 certificados CONOCER de muestra para el QR.

### 2026-08-08 — ⚡ Inspección: selects de crew/depto ahora buscables (typeahead)
- **Estado:** Hecho (verificado: `execute.blade.php` y `_typeahead.blade.php` compilan, `php -l` limpio, ambos selects llevan `js-typeahead` y el componente se incluye). Solo presentación, sin SQL.
- **Archivos:** `resources/views/inspection/execute.blade.php`.
- **Qué / Por qué (petición del owner):** los `<select>` de **Departamento** y **Dueño** listaban cientos de crew en un dropdown crudo → lento de capturar. El owner pidió "campo vacío que llama a la persona desde la BD (autocompletar)". Se les puso `class="… js-typeahead"` y se incluyó `componentes._typeahead` (mejora progresiva ya usada en scouting/event-picker): el input se vuelve un buscador **escribe-y-filtra** (sin acentos) sobre las opciones ya renderizadas; el `<select>` nativo sigue siendo el control real (su `name` se envía) y es el **fallback sin JS**. No necesita AJAX porque las opciones ya vienen de la BD.
- **Riesgo/Notas:** SIN SQL, SIN endpoint nuevo. El `required` del depto lo mueve el typeahead a server-side (el Form Request ya lo exige). **Regla del owner (feedback):** de aquí en adelante, todo `<select>` largo de personas/catálogo debe ser buscable → aplicar el mismo patrón. Ver [[selects-largos-typeahead]].

### 2026-08-08 — 🧹 Inspección: retirada "Pendiente de inspección hoy" (deuda inventada)
- **Estado:** Hecho (verificado: sin referencias a `dayList`, `php -l` limpio, index compila). Commit `de53462b` en `clean-main`.
- **Archivos:** `app/Http/Controllers/InspectionController.php`, `resources/views/inspection/index.blade.php`.
- **Qué / Por qué (owner):** la "lista del día" se derivaba de un atributo ESTÁTICO del catálogo (`inspection_regime = por_jornada`), NO de lo que realmente llega al set → es un *bluff* peligroso (hace creer que eso es todo lo que hay que revisar, o que llegará algo que quizá no llega). Una deuda real de arribos tendría que venir de una fuente que DECLARE qué llega (p. ej. un scouting), no del catálogo. Se retira el bloque de la vista, el `$dayList` del `index()` y el método `dayList()`.
- **Riesgo/Notas:** `inspection_regime` SIGUE gobernando la **vigencia** de un acta (`vigenteFor`: un acta de ayer de un por_jornada no vale hoy) — eso es legítimo y no se toca. Sin SQL. Ver [[tool-physical-unit-and-consult]] / [[inspection-preventive-and-verifier]].

### 2026-08-08 — 🔧 Inspección: unidad física (serie + dueño) + foto real + consulta de actas + imagen de tipo
- **Estado:** Hecho (verificado: 6 blades compilan sin fuga, `php -l` limpio, rutas registradas, **sello incluye la unidad física y detecta el alterado**, delta aplicado local). Commit `92c5e232` en `clean-main`.
- **Archivos:** `database/owner-apply/2026-08-08-tool-inspection-serial-owner-photo.sql` (NEW, delta **#47**), `app/Models/ToolInspection.php`, `app/Models/Tool.php`, `app/Http/Controllers/InspectionController.php`, `routes/web.php`, `resources/views/inspection/{execute,acta,index,show}.blade.php`, `resources/views/inspection/{records,tool-images}.blade.php` (NEW).
- **Qué / Por qué (petición del owner):** HER-001… es un TIPO de referencia, pero hay VARIAS herramientas del mismo tipo; el acta no distinguía la unidad, ni dueño, ni foto, y no había forma de consultar inspecciones.
  1. **Tres niveles de identidad:** TIPO (`tools.image_path`, imagen genérica) · UNIDAD (emerge del **N.º de serie**, sin tabla nueva) · ACTA (congela marca/serie + dueño + foto real).
  2. **Captura en la inspección:** marca, modelo, **serie**, **dueño** (crew o texto libre, nombre congelado con `User::displayName`), **foto real** (`ImageCompressor` + `data-cc-photo`, HEIC en el navegador). Todo entra al **sello** (columnas de contenido, sin `signatureExcludes` nuevo).
  3. **Consulta (lo que faltaba):** `/inspeccion/actas` (`records`) — busca por serie/dueño/tipo/folio y **agrupa por serie**; `?serial=` = línea de tiempo de esa unidad. Enlace desde el índice.
  4. **Imagen del tipo:** `/inspeccion/imagenes` (`tool-images`) — grid para subir/reemplazar con el tiempo; placeholder mientras no haya. Se muestra en form, ficha y grid.
- **Riesgo/Notas:** **Aplicar delta #47 en prod** (SQL owner-apply; prod se levanta fresco desde el repo). Gate reutilizado `tools.inspect` (imagen de tipo = dato de referencia; se puede endurecer). Foto sellada por RUTA (doctrina DSR). SVG permitido en imagen de tipo (subida por usuarios de confianza). Ver [[tool-physical-unit-and-consult]].

### 2026-08-08 — 🐛 Código Blade filtrándose a pantalla (Inspección de herramientas + Permisos)
- **Estado:** Hecho (verificado: 6 blades compilan sin fuga, 28 directivas → 28 `echo`, render real alterna `checked`, `php -l` limpio, `view:clear`). 1 solo archivo tocado.
- **Archivos:** `app/Providers/AppServiceProvider.php`.
- **Qué / Por qué:** el owner vio texto crudo tipo `code) === 'ok'>` bajo los botones **Cumple/Falla**. Causa raíz: las plantillas usan `@checked` / `@selected` / `@disabled` / `@readonly` / `@required`, **directivas de Laravel 9+**, y este proyecto es **Laravel 8.83**. Sin registrar, Blade las escupe como texto; el `->` dentro de `old('answers.'.$p->code)` metía un `>` que cerraba el `<input>` antes de tiempo y volcaba la cola a la vista. En los `<select>` (epi, gafetes, alta de usuario) el daño era **silencioso**: la preselección y el `old()` de rebote no funcionaban.
- **Fix:** se **registran las 5 directivas** en `AppServiceProvider::boot()` con la MISMA semántica del core de Laravel 9 (`<?php if (…): echo 'checked'; endif; ?>`). Arregla de golpe los 6 archivos (inspección, permisos, epi×2, gafete designer, alta de usuario) y cualquier uso futuro.
- **Gotcha aprendido:** `Blade::directive` **quita los paréntesis exteriores** antes de invocar el callback (las directivas del core NO) → hay que reponerlos: `if ({$expression})`, no `if{$expression}`. Detectado al verificar el PHP compilado (daba `ifold(...)`).
- **Riesgo/Notas:** **SIN SQL.** Toma efecto al recompilar las vistas (prod se levanta fresco desde el repo → sin caché vieja). Ver [[laravel8-missing-attribute-directives]].

### 2026-08-07 — 👥 Crew List (seguimientos): quita Sexo + Apellido, nombre a mostrar unificado, buscador móvil
- **Estado:** Hecho (verificado: blades compilan, `php -l` limpio, helpers probados en datos reales, densidad/buscador medidos en navegador). Commits `fc358ec7`, `d04036a2`, `fab32600`, `e6ee91c0` en `clean-main`.
- **Archivos:** `admin/usuarioscrud.blade.php`, `componentes/search-results.blade.php`, `componentes/_crew-list-styles.blade.php`, `app/Models/User.php`, `app/Support/CrewRosterBuilder.php`, `app/Support/PaeOrgChart.php`, `admin/badge/_card.blade.php`, `componentes/_idcard-row.blade.php`.
- **Qué / Por qué:** peticiones del owner sobre el Crew List tras el bloque de usabilidad.
  1. **Sexo fuera** (1 letra, se veía mal apilada en móvil). `colspan` 7→6. `users.sex` intacta.
  2. **Apellido fuera** + **nombre a mostrar**: nuevos helpers `User::shortName()` (1ª palabra de `name` + 1er `lname`) y `User::displayName()` (Nombre en Créditos `ncreditos` si tiene letras → ignora basura "0"/"-"; si no, `shortName`). La celda "Miembro" usa `displayName` (antes solo `->name`, parcial) → el apellido no se pierde de vista. `colspan` 6→5.
  3. **Regla unificada** en las superficies de "cómo se llama": gafete `_card` (displayName, arregla bug de imprimir crédito "0"), lista de gafetes `_idcard-row` (shortName principal, el crédito ya va de subtítulo), organigrama del PAE (`PaeOrgChart::displayName` delega; solo prefill de PAE nuevo, los sellados no cambian), export `CrewRosterBuilder` ("Nombre" = displayName + `ncreditos` al select). **EXCLUIDOS:** clínicos + sellados + correos personales + saludo del sidebar (ahí va nombre completo/legal o trato personal).
  4. **Buscador móvil**: compartía renglón con Exportar+Nuevo y se aplastaba a "Busc". Ahora la barra envuelve y en <576px el buscador va al 100% + botones 50/50 debajo.
- **Riesgo/Notas:** **SIN SQL** (solo lee `ncreditos`/`name`/`lname`). Permisos sin cambio. **Decisión abierta:** el export ahora muestra crédito-o-corto (ya no el 2º apellido); si se prefiere nombre legal completo en ese documento formal, es 1 línea. Ver [[sidebar-topbar-crew-density]].

### 2026-08-07 — 🧭 Usabilidad: sidebar 1-entrada-por-módulo · topbar móvil · densidad Crew List · export documento
- **Estado:** Hecho (verificado: 10 blades compilan, `php -l` limpio, ruta `crew.export` alta y `putga/putgg` fuera, roster 92/92 en orden canónico, densidad móvil 324→160px medida en navegador). Sin commit aún al escribir esto.
- **Archivos:** `layouts/sidebar.blade.php`, `layouts/header.blade.php`, `admin/usuarioscrud.blade.php`, `componentes/search-results.blade.php`, `componentes/_crew-list-styles.blade.php`, `admin/{unsafeconds,hazards,injuryreports}.blade.php`, `admin/dailyreports/index.blade.php`, `routes/web.php`, `app/Http/Controllers/{CrewListController,CrewStatusController}.php`, **NUEVOS** `app/Support/CrewRosterBuilder.php` + `resources/views/admin/crew-export.blade.php`, **BORRADO** `componentes/_group-toggles.blade.php`.
- **Qué / Por qué:**
  1. **Sidebar — UNA entrada por módulo.** Cada módulo salía DOS veces (lista + "nuevo", distinguibles solo por la ese final). Se quitaron las **6 duplicadas** de AMBAS copias (escritorio+móvil): crew_new, loc_new, daily_new, unsafe_cond, unsafe_act, accident. **36 → 30** entradas por copia. El "nuevo" es ahora la **acción primaria** de cada lista (arriba-dcha, gateada por su permiso de creación): crew (btn "Nuevo miembro"), scouting/daily (ya tenían `cc-idx-cta`; el daily se gateó), y se agregó `cc-idx-cta` a condiciones/actos/accidentes. Ninguna ruta de creación se tocó. **Móvil:** las secciones ahora **recuerdan** cuál quedó abierta (localStorage `cc-sb-open-mobile`, solo en el offcanvas; el escritorio conserva su comportamiento).
  2. **Topbar móvil.** Solo la **marca** (`logo-cc-login.svg`, cuadrada) en móvil; el lockup completo (`logo-cc-usrs.svg`) y la marca del cliente vuelven **solo en escritorio** (`d-md-none` / `d-none d-md-inline`). Selector de idioma con objetivo táctil **≥44px** en móvil.
  3. **Crew List móvil — densidad, no recorte.** El `.cc-stack` global apilaba 6 datos + acciones (~324px/tarjeta). Override **acotado a `.crew-page .crew-table.cc-stack`** (no toca otras tablas): cabecera = celda "Miembro" (avatar 34px, sin etiqueta), campos en una línea compacta, kebab **flotante** en la esquina (no gasta renglón). **324px → 160px (2.02×)**, medido en navegador. **Nada se esconde** (teléfono/correo/F.Nac. siguen).
  4. **Opciones muertas del menú de acciones.** "Convertir/Quitar Admin" y "Supervisor" fuera del menú (en las 2 vistas). **Hallazgo:** `admin` **SIGUE VIVA** (gatea `/importcrew` vía AdminMiddleware + `User::canSeePanel()` + rótulo; 2 usuarios activos con admin=1) → **solo se quitó del menú, su backing se conserva**. "Supervisor" (`daytest`) era **muerta** (nadie leía el valor) → borrados el parcial `_group-toggles`, las rutas `putadm/putsup` y los métodos `putga/putgg`. La columna `daytest` queda (pendiente DROP en owner-apply). "Activate WR" (`encuestadiaria`) se **deja** (tiene backing vivo: gatea la entrega del expediente) — enumerada, a la espera del owner.
  5. **Export del Crew List → DOCUMENTO vertical** (reemplaza el CSV genérico `/nophoto`, que queda como endpoint sin enlace). `CrewRosterBuilder` (solo lectura): **FORMATO** en la vista (una tabla a lo ancho Cargo·Nombre·Email·Números de teléfono, **banda oscura** por departamento, `window.print()`); **ORDEN jerárquico** = departamentos por `departments.sort_order` (orden canónico del llamado) y puestos por `positions.sort_order`. Marca de agua **opcional** (propósito elegido al exportar). Lo vacío se queda vacío. Respeta el scope por depto.
- **Riesgo/Notas:** **SIN nuevo SQL** (todo lee catálogos/pivote existentes). Permisos sin cambio (export gateado por `reports.export`; creación por su permiso). **Reportes al owner:** (a) departamentos fuera del orden canónico = `Demo(3)`, `Oficina Producción(1)`, `Transporte(1)` (etiquetas legacy sin equivalente en el catálogo → al final, no se acomodan a mano); (b) **teléfono sin tipo** — el esquema guarda un solo `phone` sin clasificar (no hay Móvil/Casa) → se pinta con "Tel." neutro; (c) **contacto por texto** = SÍ cabe (el `phone` es cadena libre `string|max:50`, hoy 0 filas lo usan) pero no hay campo dedicado; (d) **segunda unidad** = la app no modela unidades → documento de una sola unidad, no se inventa; (e) **"Página X de Y"** = no lo pinta Chromium (window.print sin motor PDF; dompdf/Browsershot descartados) → va por contador `@page` (progressive) + pie corriente "Emisión: fecha". Ver [[sidebar-topbar-crew-density]] y [[crew-list-export-document]].

### 2026-08-07 — 🔐 Auth: layout compartido glass + reset renovado + credenciales en ES
- **Estado:** Hecho (verificado en navegador: login glass, ambas pantallas de reset renderizan 200, token/email prellenados, 2 toggles, credenciales en ES, 320px sin scroll-h). Commit `f988d08b`.
- **Archivos:** `layouts/auth.blade.php` (nuevo), `componentes/_auth-password.blade.php` + `_auth-submit.blade.php` (nuevos), `auth/login.blade.php` + `auth/passwords/{email,reset}.blade.php` (a layout), `public/css/login.css` (glass), `lang/es/auth.php` (nuevo), `lang/{es,en}/auth_ui.php`.
- **Qué / Por qué:** el owner pidió (1) trabajar el password reset, (2) darle al login el **glass** de la app, (3) el aviso de credenciales salía en inglés. Se extrajo un **layout de auth compartido** (login + reset, sin duplicar), tarjeta **glass** (backdrop-filter como `_brand-theme`). **`reset.blade.php` estaba EN BLANCO** (`@section` sin `@extends`) → arreglado. **`lang/es/auth.php` no existía** → `auth.failed` caía al inglés; creado → "Estas credenciales no coinciden…". `confirm.blade.php` NO se tocó (extiende `layouts.app`, contexto con marca).
- **Riesgo/Notas:** presentación pura (rutas/@csrf/campos sin cambio). SIN SQL. Ver [[login-screen]].

### 2026-08-07 — 🔐 Login: renovación UI (dark full-height, foco/carga/error, mostrar contraseña, textos ES)
- **Estado:** Hecho (verificado en navegador: público, sin login → 320px sin scroll-h, error en bloque con mensaje del servidor, toggle, sin errores de consola). Commit `a999df6b`.
- **Archivos:** `resources/views/auth/login.blade.php`, `public/css/login.css`, `resources/lang/{es,en}/auth_ui.php`.
- **Qué / Por qué:** el login (única superficie sin marca de cliente, azul CrewCare fijo) se veía inacabado en escritorio (degradado a gris, tarjeta anclada arriba). Ahora: pantalla completa centrada (`flex`+`margin:auto` → tolera teclado móvil), superficie oscura pareja, **foto de fondo retirada** (glow azul sutil), **autocontenido** (fuera jQuery/Bootstrap/FontAwesome/4 fuentes/`http` inseguro → solo Poppins+login.css), foco visible, estado cargando, errores dentro del bloque, mostrar/ocultar contraseña, táctiles 40–52px, `type=email`/`inputmode`. Textos ES: Correo/Contraseña; copyright año dinámico + "Rights".
- **Riesgo/Notas:** presentación pura (auth/rutas/mensajes del servidor sin cambio). SIN SQL. `auth.failed` sigue en inglés (mensaje del servidor, fuera de alcance). Ver [[login-screen]].

### 2026-08-07 — 📝 Captura fluida: borradores múltiples offline + CSV de medidas + fila condicional + textos
- **Estado:** Hecho (verificado en navegador SIN login: round-trip + IndexedDB PASS; CSV con rollback; regresión de sellos). Commits `4b434d43` (A), `e082e6f4` (B+C+D), `6fc6800a` (CSV) en `clean-main`.
- **Archivos:** `public/js/cc-drafts.js` (nuevo), `resources/views/componentes/_drafts-tray.blade.php` (nuevo), `public/serviceworker.js`, `admin/scoutings/_form.blade.php` + `_hazard-row.blade.php`, `admin/dailyreports/create.blade.php`, `admin/injuryreportcreate.blade.php`, `admin/hazardnotification.blade.php`, `admin/unsafecondnotification.blade.php`, `lang/es|en/reports.php`, `app/Http/Controllers/HazardEventController.php`, `routes/web.php`, `admin/hazard-events/index.blade.php`.
- **Qué / Por qué:** el bloque ya estaba casi todo (slices previos); el owner pidió CERRAR lo faltante. **A** = borradores VARIOS en el dispositivo (IndexedDB, motor `cc-drafts.js` que arregla el colapso de `name[]` del autosave viejo, bandeja compartida, reconstrucción de filas dinámicas; cableado en los 5 forms de captura solo en alta) + SW network-first para abrir la forma sin red. **B** = plegables en lesión/acto/condición (DSR no: usa `<h5>`). **C** = fila de peligro revela Control/Residual/Personal solo al calificar (presentación; sello intacto). **D** = textos que explicaban implementación reescritos. **CSV** = export/import de medidas de control por hoja de cálculo (ordenado por uso, idempotente, no pisa con vacío, no inventa). Limpieza demo: scouting #1 Casa Marte (13 filas en blanco → 0).
- **Riesgo/Notas:** **SIN nuevo SQL** (borradores=cliente; CSV usa `control_measure_es/_en` del delta #49 ya pendiente; SW=estático). Envío offline de reportes COMPLETOS vía `/api/sync/up` = pieza APARTE (no construida, reportada). Las 207 medidas siguen 0/207 = contenido del owner. Falta que el owner camine: offline real y la bandeja autenticada. Ver [[fluid-capture-block]].

### 2026-08-06 — 🚑 PAE · VERSIONADO (editar = nueva revisión sellada que reemplaza)
- **Estado:** Hecho (E2E verificado: v1→editar→v2 supersede, folio estable, ambos sellos válidos, index/prefill/render; delta aplicado local).
- **Archivos:** NUEVO `database/owner-apply/2026-08-06-pae-versioning.sql`. EDITADOS `app/Models/EmergencyActionPlan.php`, `app/Http/Controllers/PaeController.php`, `app/Support/EmergencyActionPlanBuilder.php`, `routes/web.php` (pae.edit), `resources/views/componentes/_report-v2-toolbar.blade.php` (+botón editar opt-in), `resources/views/admin/pae/{show,create,index}.blade.php`.
- **Qué/Por qué:** el owner pidió poder EDITAR para versionar de verdad (antes todos caían en v1 porque la versión era la del *formato*). Como el PAE es sellado/inmutable, **editar emite una REVISIÓN NUEVA** (doc sellado aparte con su hash/QR/uuid) que **supersede** a la anterior. Delta: `revision`+`supersedes_id`+`root_id`. **Folio ESTABLE** anclado a `root_id` (v1/v2/v3 = mismo `PAE-####`); versión visible = `v{revision}.0`. Flujo: botón "Nueva versión" → `pae.edit` prellena el form (contactos+scoutings+opciones del payload) → `store` con `supersedes_uuid` sube la revisión, sella y apaga la anterior (`is_active=0`). Index lista sólo la vigente; una versión vieja muestra aviso "reemplazada".
- **Riesgo/Notas:** **delta SQL nuevo** → [[pendientes-prod]]. El sello no se rompe al superseder (`is_active` fuera del hash). ⚠ `plan_label` SÍ entra al hash: no editarlo tras sellar. Reversa la decisión previa de "emisiones independientes". Ver [[pae-emergency-action-plan]].

### 2026-08-06 — 🚑 PAE · ajustes finos: versión del documento, split SPFX/Stunts, radio, quitar "Elaborado por"
- **Estado:** Hecho (render + checks verificados; PAE-0018 emitido de muestra).
- **Archivos:** `app/Support/PaeOrgChart.php` (split slot), `resources/views/admin/pae/show.blade.php`.
- **Qué/Por qué:** feedback del owner. (1) **Versión = del DOCUMENTO** (`"v".payload.version` = v1.0), no la de la app (`config('crewcare.doc_version')`, que queda sólo en el UUID del pie). (2) **SPFX y Stunts SEPARADOS** (`spfx_stunts` → slots `spfx` + `stunts`; el `show` conserva `spfx_stunts`/`safety` como claves legado). (3) **Radio = canal** que teclea el emisor (el "Radio COO" era dato de demo, no del producto). (4) **Se quitó "Elaborado por"** del encabezado (ya está en el pie); meta a **flex** (Coordinador · Unidad · Versión del documento).
- **Riesgo/Notas:** sin SQL. Los PAE viejos con slot `spfx_stunts` siguen renderizando (clave legado). Render no altera el hash. Ver [[pae-emergency-action-plan]].

### 2026-08-06 — 🚑 PAE · 2º feedback: header con llamado, organigrama, homologación (3ª pasada)
- **Estado:** Hecho (render + no-fugas + sello + regresión DSR/MEDEVAC/scouting verificados; screenshot PAE-0017 OK).
- **Archivos:** `app/Support/{PaeOrgChart,EmergencyActionPlanBuilder}.php`, `app/Http/Controllers/PaeController.php`, `resources/views/componentes/_doc-hero.blade.php` (+`$heroMeta` opcional), `resources/views/admin/pae/{show,create}.blade.php`, `resources/views/admin/scoutings/show.blade.php`. **Sin SQL.**
- **Qué/Por qué:** feedback del owner sobre el screenshot. (1) **Header:** la imagen no salía porque los PAE viejos no traían `main_image` (pre-cambio) → re-emitir. Se agregó **sub-línea de LLAMADO** en el hero (`$heroMeta` NUEVO en `_doc-hero`, retrocompatible): tipo `loc_setting`·`shoot_time` · escenas `scene` · fecha `date_shoot` (congelado en `header.call`); **también al header del scouting**. (2) **Encabezado limpio:** se quitaron Proyecto/Fecha/Día (ya en el hero) y **se retiraron los toggles `header_show`**. (3) **Organigrama** (renombrado de "Cadena de mando"): **coordinador de emergencia = Safety** (rol `safety-officer`; UPM va en slot propio `produccion_upm`), **oculta puestos sin nombre**, **nombre = `users.ncreditos`** con fallback. (4) **Homologación de tamaños** (911/teléfono/ETA ~24px; tarjetas 15px). (5) **Mapa:** fallback al `map_image` del MEDEVAC sellado. (6) **"Qué decir al reportar"** práctico (plantilla de radio con huecos + chips). 
- **Riesgo/Notas:** `_doc-hero` ganó `$heroMeta` opcional (default null) → DSR/MEDEVAC/Injury sin cambios. Badges de riesgo: honestos (Casa Lalo es texto libre → sin badge). Render no altera el hash. Ver [[pae-emergency-action-plan]].

### 2026-08-06 — 🚑 PAE · rediseño ESTRUCTURAL + badges de norma + mapeo del proyecto (2ª pasada)
- **Estado:** Hecho (E2E verificado: 1 loc, company move, sin-event_id, sello ALTERADO, DSR idéntico; screenshots OK).
- **Archivos:** `resources/views/admin/pae/{show,create}.blade.php`, `app/Support/EmergencyActionPlanBuilder.php`, `app/Http/Controllers/PaeController.php`, `resources/views/componentes/_report-v2-head.blade.php` (recibe `.std-*`), `resources/views/admin/dailyreports/show.blade.php` (pierde `.std-*` local). **Sin SQL** (todo el dato nuevo va en el `payload` JSON existente).
- **Qué/Por qué:** feedback del owner sobre el documento. (1) **Estructura** ya no es volcado de tablas: **HOJA DE ACTIVACIÓN que se repite por locación** (franja 911·ambulancia·teléfono display + traslado médico ETA/hospital/mapa) y **una sola vez** cadena de mando en TARJETAS (3 grandes + 4 chicas), guion de radio "Qué decir al reportar" (5 col), Fases 01–06 (3×2, texto del MEDEVAC), Riesgos del día, Procedimientos rápidos (evac. total desde datos reales), sello. **Se eliminó el acuse de recepción** (100+ personas/locación; el sello se conserva). (2) **Riesgos = tarjetas con filo por nivel, ordenadas desc**, con **badges de norma del MISMO `_standards-chips` del DSR**: resueltos y CONGELADOS en el builder desde `event_id` vía `HazardEvent::with('standards')` (CSATF primero, tope 3, `standards_more`); **sin `event_id` → sin badge** (honesto). (3) **Mapeo del proyecto (DETALLE):** `heroProject = brand_name` EN VIVO (antes `production_name` libre → "CrewCare"; ahora muestra "HTLR"), `heroTime = null` (mató "Día 17 HRS"), `heroImage = main_image_path` congelado (antes route_map vacío), + **encabezado seleccionable** (`header_show[...]` → `header.show`). (4) `.std-*` **extraído del DSR al chrome `_report-v2-head`** (2º adoptante del parcial; valores idénticos → DSR igual).
- **Riesgo/Notas:** el render NO altera el hash (sello sobre el DATO — verificado: manipular→ALTERADO, restaurar→VÁLIDO). Reconciliación PASO2 vs PASO4: solo activación+traslado se repiten por locación; cadena/fases/procs una vez (verificado en company move). Gotcha: `usort` no estable en PHP 7.4 → partición manual CSATF. Ver [[pae-emergency-action-plan]].

### 2026-08-06 — 🚑 PAE · rediseño de vistas por feedback del owner
- **Estado:** Hecho (render + sin fugas de código + sello verificados).
- **Archivos:** `resources/views/admin/pae/{show,create,index}.blade.php`, `app/Support/{PaeOrgChart,EmergencyActionPlanBuilder}.php`, `app/Http/Controllers/PaeController.php`.
- **Qué/Por qué:** el owner pidió (1) que el **documento** siga la estructura/campos de su PDF de referencia (PAE de rodaje) pero en estilo CrewCare, con la **versión de documento tipo DSR**; (2) index/create al estilo de la app (sin "logo" gigante ni código en botones). **show REHECHO sobre el chrome report-v2** (`_report-v2-head/-toolbar/-foot` + `_doc-hero` + banda + `.sec`): secciones 1·Mapeo de riesgos (tabla Zona|Riesgo|Nivel|**Medida de control**|**Responsable**, desde `risk_assessment.control`/`.personnel`) · 2·Organigrama (Rol|Nombre|Teléfono|**Radio**, 7 puestos) · 3·Procedimientos rápidos (texto fijo) · 4·Acuse de recepción (firma en papel). Versión (`config('crewcare.doc_version')`) visible en banda/meta/footer. index/create reescritos con clases reales del design system.
- **Riesgo/Notas:** **2 gotchas corregidos:** (a) `@selected/@checked/@disabled` NO existen en Laravel 8 → quedaban literales (`isEmpty())>`, `id)>`) → reemplazados por ternarios; (b) el `_report-v2-head` requiere `$primary` definido por la vista. También el gotcha Blade `@endif@if` (espacio). Sello intacto (solo cambió el render, no el DATO). Ver [[pae-emergency-action-plan]].

### 2026-08-06 — 🚑 PAE · Plan de Atención a Emergencias (Frente A, delta owner-apply 2026-08-06)
- **Estado:** Hecho (E2E + adversarial A4 + render verificados). Cableado en zona reservada YA aplicado (con visto bueno del owner).
- **Archivos:** NUEVOS `app/Models/EmergencyActionPlan.php`, `app/Support/{EmergencyActionPlanBuilder,PaeOrgChart}.php`, `app/Http/Controllers/PaeController.php`, `resources/views/admin/pae/{index,create,show}.blade.php`, `database/owner-apply/2026-08-06-pae-emergency-action-plans.sql`, `database/seeders/PaePermissionsSeeder.php`. EDITADOS `app/Support/SealVerifier.php` (+`'pae'`), `routes/web.php` (grupo `pae.*` gateado), `resources/views/layouts/sidebar.blade.php` (link "PAE · Emergencias" en 2 copias + `pae.issue` en el canany de Seguridad).
- **Qué/Por qué:** 2º documento del motor de salida (hermano del MEDEVAC): UNO por llamado, 1-2 locaciones (company move). Builder solo-lectura → payload congelado → sello (`HasDigitalSignatures`), verificador público `'pae'`, permiso propio `pae.issue` (solo safety). Cuerpo propio: cabecera + **organigrama una vez** (Line Producer=rol `line-producer`, UPM=puesto 14, Safety=rol `safety-officer`, Set Medic=rol `medic`, +911) + **bloque por locación** (hospital+mapa+riesgos del día desde `scouting.risk_assessment`). Riesgos = **híbrido** (resumen textual siempre + folio/QR del mapa sellado si existe) + **opción** de embeber vistas del mapa (data-URIs, downscaler GD aislado). Vacío se omite.
- **Riesgo/Notas:** **delta SQL nuevo** (`emergency_action_plans`) + permiso `pae.issue` → ver [[pendientes-prod]]. Sello sobre el DATO (payload+día+fecha+unidad; `is_active` fuera). Enlace de ruta por `{pae:uuid}`. Ver [[pae-emergency-action-plan]].

### 2026-08-06 — 📷 Aceptar imágenes HEIC en todos los formularios (Frente B)
- **Estado:** Hecho (código + lint + Blade compile). ⚠ Conversión server INERTE en local (ver nota).
- **Archivos:** `app/Support/ImageCompressor.php` (`heicSupport/isHeic/heicToJpegBytes/normalizeForUpload`), `app/Providers/AppServiceProvider.php` (regla `heic_ok`), 10 controllers + 4 FormRequests (validación `+heic,heif`/`heic_ok` + wiring `normalizeForUpload`), ~11 blades (`accept=".heic,.heif"`), NUEVO `public/js/cc-photo-auto.js` (convierte HEIC→JPEG en el navegador para todos los forms de foto).
- **Qué/Por qué:** iPad/iPhone entregan .heic; hoy no entraban. Todos los campos de imagen (scouting, DSR, accidentes, peligros, actos, mapeo, MEDEVAC, materialidad, gafetes, perfil, mitigación) aceptan HEIC y **convierten a JPEG reusando el pipeline** existente. Cliente convierte en Safari (equipos del set); server convierte con Imagick+libheif si está.
- **Riesgo/Notas:** 🔴 **Imagick NO está instalado en local** (`extension_loaded('imagick')=false`) → la conversión SERVER no corre aquí; se apoya en el JS del navegador. Si llega un HEIC crudo sin poder convertir, **rechaza con mensaje accionable, NUNCA guarda un archivo invisible**. Para prod hace falta ImageMagick+libheif+php-imagick → [[pendientes-prod]]. JPEG/PNG sin cambios (passthrough).

### 2026-08-06 — 🎇 Catálogo SPFX: de tabla a rejilla de tarjetas (Frente C)
- **Estado:** Hecho (25 tarjetas verificadas a 1280/768/375px).
- **Archivos:** `resources/views/admin/sfx-effects/index.blade.php` (SOLO ese).
- **Qué/Por qué:** el paso 4e prometió SPFX como rejilla; había quedado como tabla que solo se volvía tarjeta <767px. Ahora es rejilla de tarjetas en TODOS los anchos, reusando el lenguaje de tarjetas de **inspección** (`inspection/_tool-card`, tinte a `--brand-primary`). Conserva toda la info (tipo/familia/riesgo/insumos/estado con sus `title` de sello) + búsqueda/filtro/paginación. SDS (`consumables/index`) sigue tabla (lo acordado).
- **Riesgo/Notas:** solo vista, sin SQL/permisos/controlador. De paso arregla el scroll horizontal que la tabla tenía a 768px.

### 2026-08-06 — 📚 Catch-up de los 5 mapas de referencia (post-compact)
- **Estado:** Hecho.
- **Archivos:** `ARCHITECTURE.md`, `DATABASE-SCHEMA.md`, `ROUTES.md`, `VIEWS-INVENTORY.md`, `ORG-TAXONOMY.md` (+ memoria `documentation-map.md`).
- **Qué/Por qué:** los 5 mapas 🟡 seguían en el snapshot de auditoría de ~junio y no reflejaban los ~12 módulos nuevos (deltas #40-#51). A cada uno se le **prepuso** un bloque fechado "⚠ ACTUALIZACIÓN 2026-08-06" con la verdad de terreno (cuerpo viejo intacto): **53 controllers · 51 modelos · 26 servicios · 226 rutas · 84 tablas · 188 vistas**, RBAC Spatie vivo + catálogo de permisos, rutas/modelos/servicios por módulo nuevo, tablas nuevas por delta.
- **Riesgo/Notas:** Solo docs, sin código ni SQL. **🔴 Hallazgo:** el dump `database/schema/mysql-schema.dump` está en **56 tablas** vs **84** reales (28 de deltas #41-#50 aplicadas por SQL local, dump sin regenerar) → regenerarlo es owner (afecta bootstrap de prod). Tablas residuales: `clinic_attestations`(0, leftover beta), `scouting_canvases`/`canvas_pins` (superseded #48). **Pendiente:** dossier `Estado-Real-CrewCare.md` (pasada dedicada). Ver [[documentation-map]].

### 2026-08-06 — 🗺️ Mapeo de riesgos: documento HOMOLOGADO al chrome del DSR
- **Estado:** Hecho (verificado en render pantalla+papel). Commit `77c4f839`.
- **Archivos:** `resources/views/admin/riskmaps/document.blade.php`.
- **Qué/Por qué:** el documento traía su propio header/hero/chip a mano (genérico, "punto negro" = glifo por defecto de un `view_type` sin icono). Reescrito sobre el MISMO chrome que DSR/scouting: `_report-v2-head`/`-toolbar`/`-foot` + `_doc-hero` (se repite por hoja) + banda de datos + secciones `.sec`. Contenido propio (lienzo/pines/inventario/tabla/sello) conservado y retokenizado. Dos caras vidrio/papel.
- **Riesgo/Notas:** Sin SQL. Sello sobre el DATO → intacto. **Gotcha Blade `@endif@if(...)`** (deja el 2º `@if` literal → `endif` huérfano) corregido con un espacio. Ver [[risk-map-module]].

### 2026-08-05/06 — 🚸 Mapeo: biblioteca de SEÑALES industriales (ISO/hazmat/EPP/clima) + delta #51
- **Estado:** Hecho. Commits `2438f4d0`, `ade00a9c`, `2a999365`, `a4fd6a42`, `a3f00d64`.
- **Archivos:** `resources/svg/senaletica/*` (80 SVG) + `_generate.php`, `resources/rm-signs.generated.php` (NUEVO), `app/Support/RiskSigns.php` (NUEVO), `app/Models/RiskMap.php`, `_rm-icon.blade.php`, `admin/riskmaps/{document,edit}.blade.php`.
- **Qué/Por qué:** el owner subió 80 SVG reales; se sirven como **`<img data:>` AISLADO** (mata la colisión de IDs internos del SVG). Auto-mapeo por evento (`hazardSymbol`/`weatherSign` por palabra clave), coherencia de color + plate blanco. Override `risk_icon` acepta cualquier slug de biblioteca.
- **Riesgo/Notas:** Solo **delta #51** (`hazard_events.risk_icon`, ya en [[pendientes-prod]]). Iconos cosméticos → fuera del sello. Ver [[risk-map-module]].

### 2026-08-04 — 📱 Pasada de usabilidad MÓVIL
- **Estado:** Hecho. Commits `db3fa52e`, `b5b5397b`, `ef78e3f5`, `a4ba20fc`.
- **Qué/Por qué:** casi todo se captura en teléfono. Consulta médica y tablas del scouting apiladas (`cc-stack`), editor de mapeo táctil (d-pad/zoom), detalle de scouting responsive, matriz 5×5 FUERA del scouting (solo Amazon MGM), footer duplicado, picker de peligros encimado (causa real: `flex-shrink`), auto-scroll a SB132.
- **Riesgo/Notas:** Solo vistas, sin SQL. Ver [[mobile-responsive-pass]].

### 2026-08-03 — 🗺️ MÓDULO Mapeo de Riesgos y Recursos (delta #50)
- **Estado:** Hecho (30/30). Commits `309980cf`, `e8bae0d0`, `4f03fce3`, `306b7992`, `8461e83f`.
- **Qué/Por qué:** editor APARTE que produce un **documento SELLADO** (una página por vista); pines por `x_pct/y_pct` sobre la imagen (sin html2canvas). Verificador `'rmap'`, permiso `riskmap.issue`. Reemplaza los pines (#48) y la galería.
- **Riesgo/Notas:** **delta #50** (3 tablas InnoDB) + seeder de permisos — ver [[pendientes-prod]]. Ver [[risk-map-module]].

### 2026-08-01 — 🌊 Captura fluida (8 slices, delta #49) · Mapeo como módulo propio (retiro #48)
- **Estado:** Captura fluida EN PROGRESO por slices. Commits `86ead606`→`adf766de`; `3bb415b5`,`de8ca990`,`8f822bbb`,`610afaa2`,`e352a08a`.
- **Qué/Por qué:** entrar por ACTIVIDAD + control pre-propuesto, secciones plegables, foto única + HEIC, borrador automático offline. El mapeo se movió fuera del scouting; el **delta #48** (pines sobre lienzos `scouting_canvases`/`canvas_pins`) quedó **retirado/obsoleto**.
- **Riesgo/Notas:** **delta #49** (`control_measure_es/_en`). Ver [[fluid-capture-block]], [[location-mapping-module]].

### 2026-07-31 — 🚑 MEDEVAC (delta #46) · 🦠 Vigilancia epidemiológica (delta #45)
- **Estado:** Hecho (35/35, 28/28).
- **Qué/Por qué:** MEDEVAC = 1ª plantilla del **motor de documentos** (render del scouting → congela+sella → verificador `'mdvc'`, permiso `medevac.issue`). Epi = panel **AGREGADO nunca nominal** (permiso `epi.view`) + `indicator_terms`/`outbreak_studies` + estudio de brote sellado `'brote'`. SILENCIOSO (cero correos/umbrales).
- **Riesgo/Notas:** **deltas #45, #46**. Ver [[medevac-poster-module]], [[epi-surveillance-module]].

### 2026-07-30 — 📄 Emisión de permisos de trabajo (delta #44)
- **Estado:** Hecho (50/50).
- **Qué/Por qué:** ciclo emitir→verificar en sitio→cerrar (`issued_permits`, permiso `permits.issue`, doble firma congelada, autorización externa DECLARADA, cierre con fire-watch, verificador `'perm'` 3-estados).
- **Riesgo/Notas:** **delta #44**. Ver [[permit-issuance-module]].

### 2026-07-26 — 🔧 Catálogos herramienta/permisos (#41) · Inspección de herramienta (#42) · Inspección preventiva + verificador 3-estados (#43)
- **Estado:** Hecho (48/48, 44/44, 35/35).
- **Qué/Por qué:** 12 tablas de catálogo (73 tipos/67 puntos/15 permisos), enforcement de inspección (acta **sellada+congelada**, PARO desbloqueable, aviso HOD), régimen preventivo derivado, verificador **3 estados** (VÁLIDO/RETIRADO/ALTERADO).
- **Riesgo/Notas:** **deltas #41, #42, #43**. Ver [[tools-permits-catalog-layer]], [[tool-inspection-vertical]], [[inspection-preventive-and-verifier]].

### 2026-07-24/25 — 🧹 BETA eliminado (→ core del medic) · 📋 Wrap report · ♊ Twins Acto/Condición
- **Estado:** Hecho.
- **Qué/Por qué:** beta borrado (commit `3b52b69a`), sobrevive como **registro LITE core del `medic`** (trigger XOR, sello versionado v2). **Wrap report** = 8º documento (contraste predicho-vs-real SB132, `WrapReportBuilder` solo-lectura, sellable, cero nombres). **Gemelos Acto/Condición Insegura** (FK al scouting, override de hash null-only).
- **Riesgo/Notas:** SQL de Wrap + twins (ver memorias). Ver [[beta-modules-lite-patients]], [[wrap-report-final-built]], [[twins-acto-condicion-state]].

### 2026-07-19/22 — 🩺 Identidad médica + Cédula (Paso A+B) · Injury dos salidas · DSR compliance/peso · Consultas médicas · Baseline BD
- **Estado:** Hecho.
- **Qué/Por qué:** fuente única `User::isMedic()` (rol Spatie `medic`); `medic_credentials` (badge verificado, whitelist de dominio, antisuplantación, robot `sep_auto` **apagado**). **Injury** LITE (`/accident/{id}`) vs COMPLETA (gateada) + addendum con sello propio + CFDI. **DSR**: compresión de imágenes (GD ~1600px), cierre de hallazgos, normas N:M (`standardables`), safety meeting con foto. **Consultas médicas** (medicamentos contables, bitácora, KEY MEDIC). **Baseline** `mysql-schema.dump` (56 tablas), versión 3.5.
- **Riesgo/Notas:** varias tablas (`medic_credentials`, `standardables`, consultas). **🔴 el sello SHA es sin clave → re-keyar a HMAC pendiente.** Ver [[medic-identity-and-cedula-foundation]], [[medic-credential-module]], [[injury-doc-two-outputs]], [[dsr-compliance-and-weight]], [[medical-consults-module]], [[code-health-and-db-baseline-plan]], [[sha-seal-vs-handwritten-signature]].

### 2026-07-12/18 — 🗂️ Catálogo de eventos (207) · SDS/RBAC · SPFX · Pilares 1-5 · Notificaciones+materialidad
- **Estado:** Hecho.
- **Qué/Por qué:** **catálogo único** de 207 eventos + 83 normas (N:M `hazard_event_standard`), selector compartido en los 5 reportes. **SDS** (fichas nacen pendientes hasta `sds.manage`). **SPFX** (`sfx_effect_types` + HDS 16 secciones). **Pilares 1-5** (captura 2 fases, DSR Master Hub event-driven, SDS/consumibles, override 5×5, Magic Links, Feature Flags). **Notificaciones** de riesgo Alto/Extremo + pestaña Materialidad.
- **Riesgo/Notas:** varias tablas de catálogo. Ver [[hazard-events-catalog]], [[sds-verification-and-rbac]], [[sfx-effect-types-layer-a]], [[pillars-1-5-safety-ux]], [[notifications-materiality-discovery]].

### 2026-07-06/07 — 🌎 Scouting geo · 📊 Amazon MGM RA
- **Estado:** Hecho.
- **Qué/Por qué:** **motor geo** (hospitales privados-primero + ETA + sugerencia de nombre de locación; corre en el NAVEGADOR). **Amazon MGM RA** = scouting al formulario oficial (Prob×Cons, matriz 5×5, riesgo residual, `_risk-matrix` reutilizable, export bilingüe).
- **Riesgo/Notas:** Ver [[scouting-geo-module]], [[amazon-mgm-ra-module]].

### 2026-06-28 (tarde, fase 3) — 🏗️ Rediseño de catálogos (paso #2) + God Object `AdminController` ELIMINADO · 📊 Dashboard rediseñado
- **Estado:** Hecho (verificado). Cierra el **ÚNICO paso restante del strangler** (paso #2, catálogos) **y con él RETIRA el God
  Object `AdminController` por completo** (cero referencias vivas en `route:list`); además moderniza el **dashboard** (rama admin de
  `inicio`). **No es from-scratch:** se carvea el último bloque sobre las **tablas NUEVAS** y se reescribe presentación; nada nuevo de
  dominio. **Verificado en bloque:** `php -l` limpio + `route:list` (sin `AdminController`) + render de las 6 vistas de catálogo con
  datos vivos.
- **1) 📚 Catálogos carveados al NUEVO `CatalogController` — sobre las tablas NUEVAS, no las legacy.** Nuevo
  `app/Http/Controllers/CatalogController.php` toma Departamentos/Puestos/Notificaciones. **Decisión del owner:** el CRUD opera sobre
  **`departments`/`positions`** (las que el alta de crew y el call sheet **sí usan**), **NO** las legacy `departamentos`/`puestos` que
  nadie leía → catálogo y formulario de alta por fin coinciden (cierra el follow-up de homologar catálogos↔`adduser`). Notificaciones
  siguen en el modelo legacy **`usuariosnotificacione`** (sin equivalente nuevo).
- **2) 🔢 Orden canónico `sort_order` (reemplaza el hack muerto `users.labn`/"Jerarquía").** Nueva columna
  **`sort_order INT NOT NULL DEFAULT 0`** en `departments` y `positions` (aplicada local vía `DB::statement`; documentada en
  `database/owner-apply/2026-06-28-cuatro-items-safety.sql` "FASE 3"). El orden fluye a **Crew List / call sheet**. `CrewController@adduser`
  ya ordena los dropdowns por `sort_order`; los modelos `Department`/`Position` ganaron `sort_order` en `$fillable`+`$casts`.
- **3) 🛡️ Endurecimiento del CRUD de catálogos:** validación en todo create/update (antes `$request->all()`); activaciones **GET→POST**
  (`activardepartamento`/`activarnotificacion` ya no mutan por GET; + nuevas `activarposition`/`desactivarposition`); `findOrFail`;
  `whereNumber` en las rutas; mutaciones por **POST+CSRF** + `@can('catalogs.manage')` en las vistas.
- **4) 💀 `AdminController` RETIRADO — God Object 100% desmantelado.** Tras carvear los catálogos, `AdminController` quedó **vacío** →
  **movido** a `_legacy_backup/decommission-2026-06-28/app/Http/Controllers/` + `composer dump-autoload`. **Cero referencias vivas** en
  `route:list`. Era el último corte del strangler iniciado con `CrewListController`.
- **5) 🎨 6 vistas de catálogo reescritas** (`departamentocrud`, `editardepartamento`, `positionscrud`, `editarposition`,
  `notificacioncrud`, `editarnotificacion`) a las tablas nuevas + `sort_order`, **Bootstrap 5** responsivo, mutaciones por POST+CSRF,
  `@can('catalogs.manage')`. Rutas en `routes/web.php` repuntadas de `AdminController` → `CatalogController`.
- **6) 📊 Dashboard rediseñado (moderno + responsivo).** `resources/views/inicio.blade.php` (**rama admin**) reescrita: banner de
  bienvenida con la marca CrewCare (`#ff9900`), **6 KPIs** en tarjetas con grid responsivo **2→3→6** columnas (móvil/tablet/desktop),
  gráfica de tendencia en card con alto fluido (`clamp`). Tailwind por CDN homologado al estilo del Daily Safety Report. **Intactos:** la
  **rama crew** (cropper de foto de perfil), los scripts (Chart.js core del layout + `chartjs-plugin-trendline` + Cropper.js + `inicio.js`)
  y el id del canvas `weeklyUnsafeTrendChart`. (El dashboard ya cuenta `scouting_reports`, no el `locationreport` decomisionado —
  documentado en la fase anterior.)
- **✅ Verificación:** `php -l` limpio; `route:list` **sin `AdminController`**; render de las 6 vistas de catálogo con datos vivos.
- **Archivos:** `app/Http/Controllers/CatalogController.php` **[NUEVO]**, `app/Models/Department.php`, `app/Models/Position.php`
  (`sort_order` en `$fillable`+`$casts`), `app/Http/Controllers/CrewController.php` (dropdowns por `sort_order`), `routes/web.php`,
  `resources/views/admin/{departamentocrud,editardepartamento,positionscrud,editarposition,notificacioncrud,editarnotificacion}.blade.php`
  (6 vistas reescritas), `resources/views/inicio.blade.php` (rama admin), `database/owner-apply/2026-06-28-cuatro-items-safety.sql`
  ("FASE 3"); **MOVIDO a `_legacy_backup/decommission-2026-06-28/app/Http/Controllers/`:** `AdminController.php`; `PROGRESS.md`,
  `ARCHITECTURE.md`, `ROADMAP.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (catálogos validados + GET→POST; el God Object queda eliminado; dashboard solo
  presentación). El `sort_order` ya está aplicado en la BD local (vía `DB::statement`) — el owner replica el mismo SQL ("FASE 3"). Con
  esto el strangler de `AdminController` queda **CERRADO** (los 7 pasos #1-#7 hechos).

### 2026-06-28 (tarde, fase 2) — ✍️ Autofirma TRANSVERSAL (DSR + 3 incidentes) · Homologación de las 3 vistas show al estilo DSR
- **Estado:** Hecho (verificado). Cierra dos follow-ups sembrados en la entrada de autofirma del Scouting (abajo): **(1)** extiende la
  **autofirma server-side** a TODOS los reportes (DSR + Hazard + Cond. Insegura + Lesión) y añade la columna **`created_by_id`** a las
  **5 tablas de reportes**; **(2)** **REESCRIBE** las 3 vistas SHOW de incidentes al **estándar de oro del Daily Report** → los 4 reportes
  + Scouting se ven como **un solo producto**. **No es from-scratch:** se endurecen escrituras existentes y se homologa diseño; nada nuevo
  de dominio. **Verificado en bloque:** `php -l` limpio en los **8 archivos** tocados; render vía `tinker` de los **4 create + 4 show**
  con datos reales.
- **1) ✍️ Autofirma (sistema cerrado) en TODOS los reportes — el autor y la fecha de elaboración YA NO vienen del form:** se fijan
  **server-side** con el usuario autenticado para que no se puedan **falsear** (trazabilidad de quién/cuándo). Aplicado a:
  - **DSR** (`DailyReportController@store`): `author_name = auth()->user()->name`; se quitó `author_name` del `required` de la validación;
    el input del create quedó **readonly**.
  - **Hazard / Cond. Insegura / Lesión** (`HazardNotificationController`, `unsafecondNotificationController`, `InjuryReportController`):
    `make_by = auth()->user()->name`, `make_date = now()`; make_by/make_date **fuera** de la validación; inputs del create **readonly**.
  - **Scouting** ya lo tenía (entrada anterior).
  - **NUEVA columna `created_by_id BIGINT UNSIGNED NULL`** aplicada en local (vía `DB::statement`, **sin migrate**) en **`daily_reports`,
    `hazardnotifications`, `unsafeconds`, `injury_reports`, `scouting_reports`**; añadida a los `$fillable` de cada modelo; el `store()` la
    setea con `auth()->id()` (escritura gateada con `Schema::hasColumn`).
- **2) 🎨 Homologación de diseño + badges — las 3 vistas SHOW de incidentes REESCRITAS al estándar del DSR** (`dailyreports/show.blade.php`):
  Tailwind CDN, hero band con marca, meta strip, las **MISMAS** clases de badge (`.badge-CSATF #b91c1c`, `.badge-OSHA #1d4ed8`,
  `.badge-STPS #15803d`), celda de badge normativo con código + **"📄 Ver boletín"** (gateada por `@if(...regulation_code)`), tarjetas de
  contenido, galería de imágenes, print-footer de firma (`make_by` + POWERED BY CrewCare) y `@media print`. Resultado: los **4 reportes
  (DSR + 3 incidentes) + Scouting** se ven como **un solo producto**.
- **3) 🗄️ Esquema YA aplicado en la BD local** (vía `DB::statement`, equivalente a correr el SQL del owner; **sin migrate**): columnas de los
  ítems 1-3 del plan + `created_by_id` en las 5 tablas; `reference_url` poblado en **61 normas**; `scouting_reports` creada.
- **✅ Verificación:** `php -l` limpio (8 archivos); `tinker` render OK de los 4 create + 4 show con datos reales.
- **Archivos:** `app/Http/Controllers/DailyReportController.php`, `app/Http/Controllers/HazardNotificationController.php`,
  `app/Http/Controllers/unsafecondNotificationController.php`, `app/Http/Controllers/InjuryReportController.php` + sus modelos
  (`$fillable += created_by_id`), `resources/views/admin/dailyreports/create.blade.php` (input readonly),
  `resources/views/admin/hazard.blade.php`, `resources/views/admin/unsafecond.blade.php`, `resources/views/admin/injuryreport.blade.php`
  (SHOW reescritas + create inputs readonly), `PROGRESS.md`, `ARCHITECTURE.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (la autofirma cierra falsificación de autor/fecha; la homologación es solo
  presentación). El esquema (created_by_id + ítems 1-3) **ya está aplicado en la BD local** — el owner replica el mismo SQL en sus
  instancias. **Follow-ups:** migrar la métrica del dashboard `locationreport` → `scouting_reports` (`HomeController`); **chips de
  severidad/estado** en Hazard/Cond. Insegura; corregir la **fila #29** del catálogo (Vías Férreas = Bulletin #28, no #29); **deep-links
  STPS** (NOMs apuntan al portal, no al PDF directo de cada norma).

### 2026-06-28 (tarde) — 🔁 Ajustes del owner sobre "los 4 items": Call Sheet EN PAUSA · Scouting viejo DECOMISIONADO · Autofirma
- **Estado:** Hecho (verificado). Enmienda **posterior** a la entrada "los 4 items" (abajo) con **3 decisiones del owner**: se
  **retira** el call sheet inicial (no refleja un llamado real), se **decomisiona** el scouting legacy de 161 campos (el nuevo
  Scouting H&S queda como ÚNICO), y el Scouting H&S gana **autofirma** (sistema cerrado/auditable). **No es from-scratch:** se
  mueve código a `_legacy_backup/`, se limpian rutas/sidebar/permisos y se endurece una sola escritura. **Verificado:** `php -l`
  limpio, `route:list` sano (sin `/call-sheets` ni `/location*`), renders OK.
- **1) 📋 Call Sheet (Item 4) → EN PAUSA / retirado.** El owner decidió que el módulo inicial **NO refleja un callsheet real**: un
  llamado se construye desde **guion(es)/script + cast list + números de personaje + necesidades por departamento**. Se reconstruirá
  **"cuando lleguemos con dirección"**. **ACCIONES:** `app/Models/CallSheet.php`, `app/Http/Controllers/CallSheetController.php` y
  `resources/views/admin/callsheets/*` **MOVIDOS** a `_legacy_backup/decommission-2026-06-28/`; rutas `/call-sheets` **eliminadas**
  de `routes/web.php` (reemplazadas por un comentario "EN PAUSA"); sección **"Llamados"** del sidebar **eliminada** (desktop +
  mobile); permisos `call_sheets.view`/`call_sheets.create` **revertidos** de `RolesAndPermissionsSeeder` + **borrados** de la BD
  local; el `CREATE TABLE call_sheets` del SQL `database/owner-apply/2026-06-28-cuatro-items-safety.sql` **marcado EN PAUSA** (no
  aplicar). **Idea que sobrevive:** auto-adjuntar boletines CSATF (vía `reference_url`) cuando se identifique un riesgo — se
  re-implementa en el rediseño con dirección.
- **2) 🗺️ Scouting viejo (`location_report`, 161 campos) → DECOMISIONADO; el nuevo Scouting H&S es ahora el ÚNICO scouting.**
  **ACCIONES:** `locationController.php`, `locationController2.php` y vistas `location.blade.php`, `location2.blade.php`,
  `locationcrud.blade.php`, `locationreport.blade.php`, `locationreport2.blade.php` **MOVIDOS** a
  `_legacy_backup/decommission-2026-06-28/`; rutas `/location*` **eliminadas**; sección **"Locaciones"** del sidebar ahora apunta
  **SOLO** al módulo nuevo ("Scoutings" → `scoutings.index`, "Nuevo Scouting" → `scoutings.create`). **EXCEPCIÓN IMPORTANTE:** el
  modelo `App\Models\locationreport` se **CONSERVA en su lugar** porque `HomeController` aún cuenta `locationreport::count()` (y usa
  `locationreport::oldest()` para los "días de producción") en el dashboard. **FOLLOW-UP (pendiente):** migrar esa métrica del
  dashboard de `locationreport` → `scouting_reports`.
- **3) ✍️ Scouting H&S ahora con AUTOFIRMA (sistema cerrado).** En `ScoutingReportController@store`, `make_by` y `make_date` **ya no**
  se toman del form: se fijan **server-side** (`make_by = auth()->user()->name`, `make_date = now()`) + **NUEVA columna**
  `created_by_id = auth()->id()` para atribución a prueba de manipulación. Los inputs "Elaborado por"/"Fecha" del create se
  reemplazaron por campos **read-only autollenados**. El `CREATE TABLE scouting_reports` del SQL ganó `created_by_id BIGINT UNSIGNED
  NULL`. **Motivo:** sistema cerrado/auditable — un "Elaborado por" libre lo podía **falsificar** cualquiera; ahora es el usuario
  autenticado. **FOLLOW-UP transversal:** extender este patrón de autofirma al DSR (`author_name`) y a los 3 reportes de incidentes
  (`make_by`).
- **Archivos:** `app/Http/Controllers/ScoutingReportController.php`, `resources/views/admin/scoutings/create.blade.php`,
  `routes/web.php`, `resources/views/layouts/sidebar.blade.php`, `database/seeders/RolesAndPermissionsSeeder.php`,
  `database/owner-apply/2026-06-28-cuatro-items-safety.sql`; **MOVIDOS a `_legacy_backup/decommission-2026-06-28/`:**
  `CallSheet.php`, `CallSheetController.php`, `admin/callsheets/*`, `locationController.php`, `locationController2.php`,
  `location*.blade.php` (5 vistas); `PROGRESS.md`, `ROADMAP.md`, `ARCHITECTURE.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (autofirma cierra falsificación; se decomisiona código duplicado; el call sheet
  se aparca, no se borra). El modelo `locationreport` se **conserva a propósito** por el dashboard. **Pasos del owner:** aplicar
  solo los Items 1/2/3 del SQL (el `call_sheets` queda EN PAUSA); el scouting necesita `created_by_id` en `scouting_reports`.
  **Follow-ups:** dashboard `locationreport`→`scouting_reports`; autofirma transversal (DSR + 3 incidentes).

### 2026-06-28 — ✅ "Los 4 items" del plan de seguridad/compliance — IMPLEMENTADOS
- **Estado:** Hecho (verificado). Implementación completa del **plan aprobado "los 4 de una vez"** (sembrado en la entrada
  pre-compact de abajo). **Todo el código está en el repo**; verificado en bloque: `php -l` limpio, render vía `tinker`,
  `route:list` (**10 rutas nuevas**), compilación de Blade. **Todo DEFENSIVO** (escrituras gateadas con `Schema::hasColumn`,
  módulos nuevos en paralelo al legacy) → **nada se rompe antes de que el owner aplique el esquema**. El **SQL consolidado del
  owner** vive en **`database/owner-apply/2026-06-28-cuatro-items-safety.sql`** (aplicar fuera de Laravel, consistente con el
  estado "sin migraciones" del repo).
- **1) 📎 Catálogo `reference_url` (liga "Ver boletín"):** `database/seeders/SafetyCatalogSeeder.php` ahora **poblado con 45 URLs
  CSATF reales + 6 OSHA + 10 STPS**; la vista `resources/views/admin/dailyreports/show.blade.php` añade la liga **"Ver boletín"**.
  **Δ esquema (en el SQL):** `ALTER TABLE safety_standards ADD COLUMN reference_url VARCHAR(500) NULL` + **re-correr el seeder**.
- **2) 🏷️ Catálogo normativo en los 3 reportes de incidentes (Hazard / Cond. Insegura / Lesión):** select de `category_name`
  **opcional** en sus `create`; `store()` **snapshotea** `regulation_badge`+`regulation_code` (escritura gateada con
  `Schema::hasColumn`); `show()` resuelve `reference_url` y muestra badge + "Ver boletín". Tocados:
  `HazardNotificationController`, `unsafecondNotificationController`, `InjuryReportController` + sus modelos (`fillable`) + vistas.
  **Δ esquema:** `ADD COLUMN regulation_badge VARCHAR(20) NULL, regulation_code VARCHAR(50) NULL` en `hazardnotifications`,
  `unsafeconds`, `injury_reports`.
- **3) 🗺️ Scouting H&S combinado — MÓDULO NUEVO (en paralelo al `location_report` legacy, que se CONSERVA):**
  `app/Models/ScoutingReport.php`, `app/Http/Controllers/ScoutingReportController.php`, vistas
  `resources/views/admin/scoutings/{index,create,show}.blade.php`. Rutas **`/scoutings`** (reutiliza permisos `locations.*`).
  Encabezado de emergencia + **13 categorías de riesgo** (Sí/No/N-A + nivel + catálogo normativo) + **gatillo SB132**
  (`requires_specific_ra`) + capa operativa (viabilidad/acuerdos en JSON). `show()` homologado al estilo "revista" del Daily
  Report. **Δ esquema:** `CREATE TABLE scouting_reports` (ver SQL).
- **4) 📋 Call Sheet "el llamado" + auto-adjunto de boletines CSATF — MÓDULO NUEVO:** `app/Models/CallSheet.php`,
  `app/Http/Controllers/CallSheetController.php` (index/create/store/show/send/pdf), vistas
  `resources/views/admin/callsheets/{index,create,show,pdf}.blade.php`. Rutas **`/call-sheets`**. **Permisos NUEVOS**
  `call_sheets.view`/`call_sheets.create` (añadidos a `RolesAndPermissionsSeeder` + asignados a
  super-admin/line-producer/coordinator/safety-officer). Recoge los riesgos del día (catálogo manual y/o **heredados del Daily
  Report del día** vía `daily_report_id`) y **adjunta/enlaza los boletines CSATF aplicables** (vía `reference_url`); **PDF
  nativo con DomPDF** (`Barryvdh\DomPDF\Facade\Pdf`). Sidebar: nueva sección **"Llamados"**. **Δ esquema:** `CREATE TABLE
  call_sheets` (ver SQL).
- **🛣️ Rutas:** `routes/web.php` con sección **SCOUTING H&S** y sección **CALL SHEETS** — **10 rutas nuevas verificadas con
  `route:list`**. **🧭 Sidebar:** "Scouting H&S" bajo Locaciones + nueva sección "Llamados".
- **✅ Verificación:** `php -l` limpio; `tinker` render OK; `route:list` (10 rutas nuevas); Blade compila.
- **Archivos:** `database/seeders/SafetyCatalogSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`,
  `app/Models/ScoutingReport.php` **[NUEVO]**, `app/Models/CallSheet.php` **[NUEVO]**,
  `app/Http/Controllers/ScoutingReportController.php` **[NUEVO]**, `app/Http/Controllers/CallSheetController.php` **[NUEVO]**,
  `app/Http/Controllers/HazardNotificationController.php`, `app/Http/Controllers/unsafecondNotificationController.php`,
  `app/Http/Controllers/InjuryReportController.php` + sus modelos, `resources/views/admin/dailyreports/show.blade.php`,
  `resources/views/admin/scoutings/{index,create,show}.blade.php` **[NUEVAS]**,
  `resources/views/admin/callsheets/{index,create,show,pdf}.blade.php` **[NUEVAS]**, `routes/web.php`,
  `resources/views/layouts/sidebar.blade.php`, `database/owner-apply/2026-06-28-cuatro-items-safety.sql` **[NUEVO]**,
  `PROGRESS.md`, `ROADMAP.md`, `ARCHITECTURE.md`.
- **Riesgo/Notas:** **Bajo** / **DEFENSIVO** — las escrituras de columnas nuevas van gateadas con `Schema::hasColumn`, y los dos
  módulos nuevos (scouting/call sheet) corren **en paralelo** al legacy (el `location_report` de ~161 campos se conserva) → nada
  se rompe **antes** de que el owner aplique el SQL. **Pasos manuales del owner:** aplicar
  `database/owner-apply/2026-06-28-cuatro-items-safety.sql` (las columnas de los ítems 1/2 + las 2 tablas nuevas
  `scouting_reports`/`call_sheets`) y **re-correr `SafetyCatalogSeeder`** para poblar `reference_url`. Copyright AMPTP/CSATF:
  solo **enlazar** al PDF oficial, nunca copiar contenido.

### 2026-06-28 — 📚 Catálogo normativo 22→67 + diseño call sheet/boletines + plan de 4 (pre-compact)
- **Estado:** Parcial (A hecho; B/C diseño; plan de 4 aprobado, **arranca tras el compact**). Entrada de **preservación de estado**
  antes de un compact: deja sembrado el catálogo normativo expandido, dos ideas de producto del owner y el **plan aprobado "los 4 de
  una vez"** con sus **deltas de esquema** (varios necesitan COLUMNAS nuevas que el owner aplica fuera de Laravel → se dejará SQL +
  código). **No se tocó código en esta entrada** salvo el seeder de (A).

- **A) ✅ Catálogo normativo expandido — HECHO.** Se creó **`database/seeders/SafetyCatalogSeeder.php`** (idempotente, `firstOrCreate`
  por `regulation_code`) y se corrió en local: `safety_standards` pasó de **22 → 67 filas**:
  - **45 AMPTP/CSATF Safety Bulletins** (#1–#45, lista oficial verificada en csatf.org) — badge **CSATF**.
  - **6 OSHA/Cal-OSHA:** 29 CFR 1904 (recordkeeping), IIPP T8 §3203, caídas 1926 Subpart M, LOTO 1910.147, espacios confinados
    1910.146, HazCom 1910.1200 — badge **OSHA**.
  - **10 NOMs STPS:** 019/030/009/002/029/017/036/018/033/031 — badge **STPS**.
  - Las **22 filas originales se conservan**. **🐞 Bug de datos a corregir a mano:** la fila existente "Vías Férreas" está como
    **Bulletin #29** pero el oficial es **#28** (Railroad); **#29 = Globos aerostáticos** → corregir el `category_name` de esa fila.

- **B) 💡 Idea del owner — boletines CSATF en el call sheet:** cuando se identifique un riesgo de locación/actividad, el call sheet
  (al enviarse) **enlaza/adjunta automáticamente el Boletín CSATF aplicable** (prevención específica del día para el crew).
  - **PDF de servidor disponible:** `barryvdh/laravel-dompdf` + `dompdf/dompdf` **YA están en `composer.json`**.
  - El **call sheet NO existe aún** (sin modelo/controlador) → es **Fase 3** del ROADMAP.
  - **⚠️ Derechos de autor:** los bulletins son copyright AMPTP/CSATF → **NO** copiar contenido a la app; **enlazar** al PDF oficial
    de csatf.org (solo número/título).

- **C) 📄 Análisis del PDF de scouting del owner ("CARNADA XV años"):** es un reporte **OPERATIVO de producción** (no de seguridad).
  Secciones: 01 Resumen ejecutivo (dirección, días montaje/rodaje/desmontaje, complejidad, ambulancia, techbase, alimentación,
  basecamp); 02 Checklist de viabilidad (áreas críticas → OK/Por Asignar/Pendiente + responsable + obs); 03 Espacios por departamento
  (fotos + requerimientos/restricciones/pendientes); 04 Equipo adicional; 06 Acuerdos (responsable/fecha/estatus); 07 Horarios de
  montaje; 08 Itinerario 24 h; 09 Notas operativas por área; firma. **Visión del owner:** app "para producción", H&S = módulo de
  valor → **combinar lo operativo del Carnada con la capa H&S**.

- **🎯 PLAN APROBADO "los 4 de una vez" (arranca tras el compact; usar subagentes; el owner aplica el SQL de columnas fuera de
  Laravel — se deja SQL + código):**
  1. **Catálogo `reference_url`** *(bajo riesgo, base de B)* — **Δ esquema:** `ALTER TABLE safety_standards ADD COLUMN reference_url
     VARCHAR(500) NULL` (+ opc. `ADD COLUMN summary TEXT NULL`). Poblar URLs oficiales por boletín en el seeder; botón **"Ver Boletín
     CSATF"** en los reportes.
  2. **Conectar el dropdown del catálogo** en Hazard / Cond. Insegura / Lesión (hoy **solo el DSR** lo usa): persistir la selección.
     **Δ esquema:** `ADD COLUMN safety_standard_id BIGINT NULL` (o snapshot badge/code) en `hazardnotifications` / `unsafeconds` /
     `injury_reports`; resolver badge/code en `store` (como `DailyReportController@storeLog`); mostrar badge en show + print. *(bajo riesgo)*
  3. **Scouting combinado** *(el GRANDE)* — operativo Carnada + capa H&S: encabezado de emergencia + ~13 categorías (Sí/No/N-A +
     nivel de riesgo + dropdown catálogo + **gatillo de actividad especial → fuerza Specific Risk Assessment de SB132**) + secciones
     operativas; **reemplaza el form de ~161 campos**; homologar al estilo visual del DSR. **Δ esquema:** rediseño — tabla nueva o
     reestructura de `location_report`.
  4. **Call sheet (Fase 3) con auto-adjunto de boletines** *(desde cero, cierra B)* — **Δ esquema:** tabla/modelo **`call_sheets`** +
     controlador + vistas + render **DomPDF**; recorre los riesgos del día (scouting/DSR vía catálogo) y **adjunta/enlaza** los
     boletines aplicables (vía `reference_url`) al enviar.
  - **Transversal:** homologar **print CSS** de todos los reportes al del DSR; chips de severidad/estado (necesitan columnas);
    **matriz de riesgo 5×5** (🟢🟡🟠🔴).
- **Archivos:** `database/seeders/SafetyCatalogSeeder.php` (nuevo), `PROGRESS.md`, `ROADMAP.md`, `ARCHITECTURE.md`.
- **Riesgo/Notas:** el seeder es **idempotente** (`firstOrCreate` por `regulation_code`) → re-correrlo no duplica. Pendiente manual: el
  **fix del Bulletin #28/#29** (Vías Férreas) en la fila legacy. Las **Δ de esquema** (ítems 1/2/3/4) las aplica el owner **fuera de
  Laravel**; no hay migración versionada todavía (consistente con el estado "sin migraciones" del repo). Copyright AMPTP/CSATF: **solo
  enlazar**, nunca copiar contenido de los boletines.

### 2026-06-28 — 🛡️ Endurecimiento de métodos de creación (`store()`/alta)
- **Estado:** Hecho (verificado). Pasada **enfocada en los métodos de CREACIÓN** (`store()`/alta) de las verticales con subida de
  imágenes + el alta de crew. Cierra **mass-assignment** en 4 `store()`, **valida por fin** `CrewController@newuser`, **refuerza la
  validación de imágenes**, y **corrige un BUG real de doble `json_encode`** que rompía la lectura de imágenes adicionales. **No es
  from-scratch:** cambios acotados, err-restrictivos. **Verificado en bloque:** `php -l` limpio en los controllers tocados; `route:list`
  **sano (103 rutas)**; renders OK.
- **1) 🐞 Doble `json_encode` corregido (BUG real — rompía la lectura de imágenes adicionales):** en `InjuryReportController@store`,
  `HazardNotificationController@store` y `unsafecondNotificationController@store` se hacía `json_encode($additionalImagePaths)` **a
  mano** antes de guardar, pero el modelo **ya castea** `additional_images_paths => 'array'` → **doble codificación** que **ROMPÍA** la
  lectura posterior de las imágenes adicionales. Ahora se asigna el **array directo** (igual que ya hacía `locationController`).
- **2) 🔐 Mass-assignment cerrado en 4 `store()`:** `HazardNotificationController`, `unsafecondNotificationController`,
  `locationController` (V1) y `locationController2` (V2) usaban `$request->all()` **tras** validar → cualquier campo posteado entraba a
  `create()`. Ahora usan el **ARRAY VALIDADO** como base (`$data = $request->validate([...])`), con `unset` de los inputs de archivo
  antes de `create()`. **Verificado que NO se pierde ninguna columna** (reconciliación reglas↔`$fillable`↔form; V1 confirmó **118/118
  columnas**).
- **3) 🛟 `try/catch` añadido** en `HazardNotificationController`, `unsafecondNotificationController` y `locationController2` (antes **sin
  manejo de error** → exception cruda + imágenes huérfanas). Replican el patrón de `locationController` (`Log::error` +
  `back()->withInput()->with('error',...)`).
- **4) 🖼️ Validación de imagen reforzada:** `unsafecondNotificationController` y `locationController2` **no tenían** `max:`/`mimes` en la
  imagen → ahora `image|mimes:jpeg,png,jpg,gif|max:2048`.
- **5) 🐞 Bug latente extra (Hazard):** la regla `additional_images_paths.*` **nunca aplicaba** (el input real del form es
  `additional_images[]`); corregida a `additional_images` + `additional_images.*` con `image|mimes|max`.
- **6) 🧷 Nombres de archivo anti-colisión:** `time().'_main.'` (colisionable en el mismo segundo) → `time().'_'.uniqid().'_main.'` en
  Injury/Hazard/Unsafe/Location.
- **7) 🔐 `CrewController@newuser` ahora VALIDA (antes NO validaba NADA):** whitelist alineada al form `admin.newuser` —
  `name`/`lname`/`ncreditos`/`borndate`/`labn`/`phone` **required**; `email` **required|email|unique:users**; `password`
  **min:8|confirmed** (el form ya trae `password_confirmation`). **Cierra el alta con email duplicado/inválido o password débil.**
- **⏳ PENDIENTES / DIFERIDOS detectados (NO corregidos):**
  - **(a) Atomicidad imagen+registro:** los archivos se guardan **antes** del `create()`; si el `create()` falla, el archivo queda
    **huérfano**. Pendiente un job de limpieza **`DeleteOrphanedImages`** o una transacción con cleanup.
  - **(b) Trait `HandlesImageUploads` (DRY):** el patrón de subida está **duplicado en ~5 controladores** — extraer a un trait.
  - **(c) `cmedicController@store` setea `created_at` a mano** — **NO tocado** por riesgo: la tabla legacy puede **no** tener
    `updated_at`.
  - **(d) `CrewController@newuser`:** el pivote `production_user` se inserta con `DB::table()->updateOrInsert` + timestamps manuales —
    eso es **CORRECTO** (`DB::table` no auto-timestampa), **NO es bug**.
- **✅ Verificación:** `php -l` limpio en todos los controllers tocados; `route:list` confirma **103 rutas**; renders OK.
- **Archivos:** `app/Http/Controllers/InjuryReportController.php`, `app/Http/Controllers/HazardNotificationController.php`,
  `app/Http/Controllers/unsafecondNotificationController.php`, `app/Http/Controllers/locationController.php`,
  `app/Http/Controllers/locationController2.php`, `app/Http/Controllers/CrewController.php`, `PROGRESS.md`, `SECURITY.md`,
  `ARCHITECTURE.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (se cierra mass-assignment, se valida el alta, se refuerza validación de imagen y se
  corrige un bug de doble codificación; **no se relaja nada**). Cambio de comportamiento a vigilar: `newuser` ahora **rechaza** altas
  con email duplicado/inválido o password < 8 (antes pasaban sin validar). Diferidos: atomicidad/huérfanos (job `DeleteOrphanedImages`)
  y trait `HandlesImageUploads`.

### 2026-06-28 — 🧹⚡ Optimización de las 7 verticales operativas
- **Estado:** Hecho (verificado). Pasada **transversal** sobre las **7 verticales operativas** de la app (cada una con su patrón
  **lista + creación**, su propio controller/modelo/vistas, rutas en `routes/web.php`). Se **endurece, se limpia código muerto, se
  corrigen redirects rotos y validaciones que no se aplicaban, y se pagina** donde faltaba. **No es from-scratch:** cambios acotados,
  err-restrictivos, **sin tocar el límite de cumplimiento de 24 h del DSR** ni el alcance HSE cross-department (decisión de producto
  diferida, ver al final). **Verificado en bloque:** `php -l` limpio en todos los controllers tocados; `route:list` **sano (103 rutas
  de la app)**; renders OK vía `tinker`.
- **1) Daily Reports (`DailyReportController` + modelos `DailyReport`/`DailyLog`):**
  - **🔐 `DailyReport` y `DailyLog`: `$guarded=[]` → `$fillable` explícito** (endurecimiento; **NO era vuln activa** porque `store`/
    `update` ya usan `$request->validate(...)` — se **verificó que el `$fillable` cubre exactamente** lo que el controller escribe →
    **sin pérdida silenciosa**). Avanza el sub-ítem DSR del refactor de validación (H4).
  - **🧹 Eliminado import muerto `Spatie\Browsershot\Browsershot`** + **ELIMINADA la ruta huérfana `daily_reports.pdf`** (apuntaba a
    `downloadPdf()` **inexistente** = 500 latente; **ninguna vista la enlazaba**). El **export PDF queda como FEATURE PENDIENTE** (no
    ruteada) — ya no es bug de routing.
  - **⏱️ `storeLog()`: candado de 24 h REORDENADO ANTES de validar/procesar** (fail-fast, igual que `update()`). El **límite de 24 h
    de cumplimiento se PRESERVA intacto**.
- **2) Accidentes (`InjuryReportController`):** eliminado método privado muerto `storeImage()`; `searchUsers()` ahora valida `term`
  (`nullable|string|max:255`). La lista ya paginaba (10). *(Ya tenía la mejor validación del grupo.)*
- **3) Condiciones Inseguras (`unsafecondNotificationController` + vista `unsafeconds`):** corregido **redirect roto post-store**
  (`unsafenotifications.store` POST → `unsafenotifications.index`); `index()` ahora **pagina** (`paginate(15)`, más reciente primero)
  + `links()` en la vista.
- **4) Acciones Inseguras / Hazards (`HazardNotificationController` + vistas):** corregido **MISMATCH de campo de imagen** — la
  validación usaba `main_image_path` (**nombre de columna**) en vez de `main_image` (**el `name` real del input**), así que la
  validación `image/mimes/max` **NUNCA se aplicaba**; ahora valida `main_image`. Corregido **redirect roto**
  (`hazard_notifications.create` → `.index`); `index()` **pagina(15)+links**. **PENDIENTE detectado (NO corregido):** doble
  codificación de `additional_images_paths` (el controller hace `json_encode` y el modelo además castea a `array`).
- **5) Scouting/Locaciones (`locationController`/`locationController2` + vista `locationcrud`):** `index()` ahora **pagina**
  (`orderBy('id_loc','desc')->paginate(15)`) + `links()`. `locationController2` solo crea (sin listado). **PENDIENTE:** consolidar
  V1/V2 (comparten tabla `location_report`, formularios distintos) — decisión de producto.
- **6) Consultas Médicas (`cmedicController`):** eliminado `index()` **MUERTO** (sin ruta, duplicaba `historialWR`); `store()` ahora
  valida el objetivo con `User::findOrFail($id_user)`; **🔐 ruta `historialWR` MIGRADA del grupo legacy `admin` → `permission:medical.view`**
  (**CAMBIO DE AUTORIZACIÓN:** antes flag binario `admin=1`, ahora permiso `medical.view`; verificado middleware
  `web,auth,permission:medical.view`). Los enlaces a "Historial Médico" en `usuarioscrud` y `search-results` se envolvieron en
  `@can('medical.view')` para no mostrar un link que daría 403.
- **7) Lista de gafetes (`CrewListController@idcard` — detalle):** añadido `findOrFail` + `abort_unless(auth()->user()->canManageCrewMember($users), 403)`
  — **cierra el ÚLTIMO pendiente H2 del dominio crew** (la lista `idcardscrud` ya estaba acotada; ahora el **detalle** también). Cierra
  el gap latente que el corte #1 del strangler había dejado en `idcard($id)`.
- **📌 DECISIONES DE PRODUCTO DIFERIDAS (NO asumidas):**
  - **Scope por departamento** en las verticales de **Seguridad/Médico/Locaciones** — hoy **cross-department por diseño de cumplimiento
    HSE**; solo se acotó el **dominio CREW**.
  - **Consolidación Scouting V1/V2** (`locationController`/`locationController2`).
  - **Implementar el export PDF del DSR** (hoy no ruteado tras quitar la ruta huérfana).
  - **`crew:welcome-resend`** manda **hash bcrypt** → debería enviar **enlace de reset** (L2).
  - **Doble `json_encode`** en hazard/unsafecond (`additional_images_paths`).
- **✅ Verificación:** `php -l` limpio en todos los controllers/modelos tocados; `route:list` confirma **103 rutas**, `daily_reports.pdf`
  **ausente**, `historialWR` con middleware `web,auth,permission:medical.view`; renders OK de las listas vía `tinker`.
- **Archivos:** `app/Http/Controllers/DailyReportController.php`, `app/Models/DailyReport.php`, `app/Models/DailyLog.php`,
  `app/Http/Controllers/InjuryReportController.php`, `app/Http/Controllers/unsafecondNotificationController.php`,
  `resources/views/unsafeconds/*`, `app/Http/Controllers/HazardNotificationController.php`, `resources/views/hazard*`,
  `app/Http/Controllers/locationController.php`, `resources/views/locationcrud*`, `app/Http/Controllers/cmedicController.php`,
  `resources/views/admin/usuarioscrud.blade.php`, `resources/views/componentes/search-results.blade.php`,
  `app/Http/Controllers/CrewListController.php`, `routes/web.php`, `PROGRESS.md`, `ARCHITECTURE.md`, `SECURITY.md`, `ROUTES.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (se endurecen modelos y autorización, se corrigen redirects/validaciones rotas, se
  agrega paginación; **no se relaja nada**). Cambio de comportamiento a vigilar: `historialWR` pasó de gate `admin` a `permission:medical.view`
  (los titulares actuales de `medical.view` ya lo tienen; los enlaces se gatearon con `@can`). El candado de 24 h del DSR **intacto**.

### 2026-06-28 — ⚙️🧹 Correos de crew → comandos Artisan + limpieza de residuos de doc
- **Estado:** Hecho (verificado). Dos bloques: **(A)** se convierten `welcomeresend`/`sendreminder` (los GET con efecto colateral y
  sin UI que el paso #7 del strangler había movido a `CrewMailController`) en **comandos Artisan**, eliminando las rutas web y
  retirando `CrewMailController`; **(B)** limpieza de **residuos obsoletos** de documentación en `ARCHITECTURE.md` (rutas COVID ya
  eliminadas en sesiones previas que el doc seguía presentando como vivas). **No introduce hallazgos nuevos** — cierra deuda.
- **A) `welcomeresend`/`sendreminder` → COMANDOS ARTISAN (eliminadas las rutas web GET):**
  - **➕ NUEVO `app/Console/Commands/CrewWelcomeResend.php`** (signature **`crew:welcome-resend {id}`**) — reenvía el correo de
    bienvenida a un crew member por ID.
  - **➕ NUEVO `app/Console/Commands/CrewDailyReminder.php`** (signature **`crew:daily-reminder`**) — variante **manual** de
    `encuestas:task`: notifica a los activos con `encuestadiaria=0` el mismo recordatorio, **sin resetear** (a diferencia de
    `encuestas:task`, que resetea `encuestadiaria=0` + manda el recordatorio a diario a las 04:00).
  - **🔧 `app/Console/Kernel.php`** — ambos comandos **registrados** en `$commands` (junto a `encuestasTask`).
  - **✂️ RUTAS WEB ELIMINADAS:** `GET /sendreminder` y `GET /welcomeresend/{id}` (eran **GET con efecto colateral y sin UI**) →
    quitadas de `routes/web.php`. **Verificado con `route:list`: ya no aparecen.**
  - **🗄️ `CrewMailController` RETIRADO** — el controller (creado horas antes hoy como destino del corte #7) se movió a
    **`_legacy_backup/CrewMailController.php.20260628.bak`**; ya **NO** existe en `app/Http/Controllers/`. Su lógica vive ahora en
    los dos comandos.
  - **⚠️ LIMITACIÓN HEREDADA (documentada en `CrewWelcomeResend`, NO corregida — no se cambió comportamiento):** el correo de
    bienvenida incluye `users.password`, que es el **HASH bcrypt** (inservible para el usuario, leak menor del hash). Lo correcto
    sería enviar un **enlace de `password.reset`** (ya habilitado). Queda **pendiente de decisión del owner** (ver L2 de
    [SECURITY.md](SECURITY.md)).
- **B) Limpieza de residuos de doc en `ARCHITECTURE.md`** (rutas ya eliminadas hace tiempo que el doc seguía mostrando como vivas;
  **verificado con `route:list`: ninguna existe**):
  - **§1 "Protección de rutas / Sin middleware"** — `/newdayRep, /newTD, /newWR, /Nresult, /PhotoReminder` ya **no** son rutas vivas;
    marcadas como **ELIMINADAS**.
  - **§3 "Flujos de usuario"** — la nota "Reset diario: `GET /newdayRep` (resetController, sin auth)" corregida (ruta eliminada;
    el reset real es el comando agendado `encuestas:task`).
  - **§5 mapeo de seguridad** (snapshot histórico de auditoría) — **no se borró el hallazgo** (no reescribir la historia); se anotó
    junto a esos ítems que **YA fueron resueltos/eliminados** en sesiones previas.
  - **§3 tabla de Controllers** — la nota de `MailController`/`ReminderMailController` ("Terminan en `dd()`; ruteados como GET")
    ajustada: el `dd()` de `ReminderMailController` ya se removió (ahora redirect con flash); `MailController` ya no existe.
- **✅ Verificación:** `route:list` confirma que `/sendreminder` y `/welcomeresend/{id}` (y las COVID `newdayRep`/`newTD`/`newWR`/
  `Nresult`/`PhotoReminder`/`pruebachedule`) **no existen**; `php artisan list` muestra **`crew:welcome-resend`** y
  **`crew:daily-reminder`** registrados; `_legacy_backup/CrewMailController.php.20260628.bak` presente y `CrewMailController.php`
  ausente de `app/Http/Controllers/`.
- **Archivos:** `app/Console/Commands/CrewWelcomeResend.php` **[NUEVO]**, `app/Console/Commands/CrewDailyReminder.php` **[NUEVO]**,
  `app/Console/Kernel.php`, `routes/web.php`, `_legacy_backup/CrewMailController.php.20260628.bak` **[MOVIDO]**, `ARCHITECTURE.md`,
  `ROUTES.md`, `SECURITY.md`, `PROGRESS.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (se elimina superficie GET con efecto colateral; el reenvío y el recordatorio
  manual pasan a comando administrable por consola). Pendiente-menor: el hash en el correo de bienvenida (L2) → decisión del owner.

### 2026-06-28 — 🏗️ Estructura (4/n) — toggles, roles y correos extraídos (pasos #5/#6/#7); AdminController 581→191, solo catálogos
- **Estado:** Hecho (verificado). Quinta pasada del strangler de `AdminController` — completa en **UNA pasada los pasos #5, #6 y #7**
  del roadmap §6. Con esto el **God Object queda esencialmente desmantelado**: `AdminController` pasa de **581 → 191 líneas** y ya
  **solo contiene los CATÁLOGOS** (departamentos/notificaciones/positions) = el paso #2 DIFERIDO a su rediseño. Los toggles y roles se
  **endurecen al moverse** (cierran **H2** en TODAS las acciones admin sobre `{id}`); dos GET mutantes pasan a **POST+CSRF**.
- **A) PASO #5 (toggles de estatus) + PASO #6 (rol/grupos/puesto) → NUEVO `app/Http/Controllers/CrewStatusController.php`:**
  - **➕ Métodos movidos desde `AdminController`:** `checkgft`, `uncheckgft`, `activarusuario`, `activarencuesta`, `desactivarusuario`
    (#5) + `activaradmin`, `desactivaradmin`, `putga`, `putgb`, `putgg`, `selectpuesto` (#6).
  - **🔐 H2 (IDOR de scope) CERRADO en TODAS las acciones admin sobre `{id}`.** Cada acción ahora hace `findOrFail($id)` +
    `abort_unless(auth()->user()->canManageCrewMember($target), 403)`. Para los roles actuales (titulares de `users.assign-role`/
    `users.assign-department`) la guarda es **no-op** (todos tienen `crew.view.all-departments`), pero **blinda `{id}` manipulados y
    futuros roles acotados**.
  - **🔐 GET→POST (H1, parcial).** `activarusuario` y `activarencuesta` **CONVERTIDOS GET→POST+CSRF** (antes mutaban por GET sin token).
    Se actualizaron **4 enlaces en 2 vistas** (`resources/views/admin/usuarioscrud.blade.php` y
    `resources/views/componentes/search-results.blade.php`): de `<a href>` a `<form method="post">@csrf<button>`. Verificado: **0
    `<a href>` GET huérfanos** restantes para esas rutas.
  - **🛣️ `routes/web.php`** — las rutas se **repuntan** a `CrewStatusController`, conservando sus grupos `permission`
    (`users.update` / `users.deactivate` / `users.assign-role` / `users.assign-department`).
- **B) PASO #7 — sueltos extraídos a su dominio:**
  - **`historialWR($id)`** movido **VERBATIM** a `app/Http/Controllers/cmedicController.php` (su dominio médico; `cmedicController` ya
    servía `componentes.historiamr`). Ruta repuntada; conserva gate `admin`.
  - **`welcomeresend($id)`** y **`sendreminder()`** movidos **VERBATIM** a NUEVO `app/Http/Controllers/CrewMailController.php`
    (correos de crew disparados por admin). Rutas repuntadas; conservan gate `admin`. **NOTA pendiente-menor:** ambas son GET con
    efecto colateral y sin UI hoy; convertir a POST/Artisan queda **diferido** (no hay form que migrar, acceso ya restringido a admin).
  - *(El dead-code `exportCsv`/`expCsv` ya se había borrado en la entrada previa de hoy.)*
- **🧹 LIMPIEZA — `AdminController` 581 → 191 líneas.** Tras vaciar tantos métodos, se retiraron los **imports ya muertos** (`User`,
  `Carbon`, `Hash`, `Mail`, `userpuesto`, `cmedic`); quedan solo los usados por los catálogos (`Controller`, `Request`, `DB`,
  `departamento`, `puesto`, `usuariosnotificacione`). `AdminController` ya **solo contiene los CATÁLOGOS** (departamentos/
  notificaciones/positions) = el paso #2 DIFERIDO (espera el rediseño de normalización). **16 rutas** siguen apuntando a
  `AdminController` (todas catálogos).
- **🧭 Helper usado:** `User::canManageCrewMember(self $target): bool` en `app/Models/User.php` (ya existía desde el paso #4).
- **✅ Verificación (todas OK):** `php -l` limpio en `CrewStatusController`, `CrewMailController`, `cmedicController`,
  `AdminController`; `route:list` confirma las **14 rutas repuntadas** y que `activarusuario`/`activarencuesta` ahora son **POST**;
  render OK de `usuarioscrud` (**231513 bytes**), `historialWR` (**26256**), `positionscrud` (**34708**), `departamentocrud`
  (**37105**); `search-results.blade` compila OK; `tinker` confirma `canManageCrewMember` en ambos sentidos.
- **Archivos:** `app/Http/Controllers/CrewStatusController.php` **[NUEVO]**, `app/Http/Controllers/CrewMailController.php`
  **[NUEVO]**, `app/Http/Controllers/cmedicController.php`, `app/Http/Controllers/AdminController.php`,
  `resources/views/admin/usuarioscrud.blade.php`, `resources/views/componentes/search-results.blade.php`, `routes/web.php`,
  `ARCHITECTURE.md`, `PROGRESS.md`, `SECURITY.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (toggles/roles endurecidos con scope + 2 GET→POST; correos/historial movidos
  verbatim). Con esto **solo queda el paso #2 (catálogos), DIFERIDO** a su rediseño de normalización; el God Object está
  esencialmente desmantelado salvo ese bloque.

### 2026-06-28 — 🏗️ Estructura (3/n) — perfil de crew extraído y endurecido + limpieza CSV muerto
- **Estado:** Hecho (verificado). Cuarto corte del God Object `AdminController` (paso #4 del roadmap §6) — extrae el **PERFIL de
  crew** a `CrewController` **y, a diferencia de los cortes previos, lo ENDURECE al moverse** (no es movimiento puro): cierra **H2**
  (IDOR de scope) y **H4** (mass-assignment) en este path. Incluye además la **parte de limpieza de CSV muerto del paso #7**.
- **A) PASO #4 — perfil de crew extraído y endurecido (`useredit`/`acountupdate` → `CrewController`):**
  - **➕ `app/Http/Controllers/CrewController.php`** — `useredit($id)` (form de edición, **GET**) y `acountupdate(Request,$id)`
    (persistencia, **POST**) se **movieron** desde `AdminController` a `CrewController` (que ya tenía `adduser`/`newuser` del paso
    #3). Las **escrituras de crew** quedan así consolidadas en un solo controller.
  - **🛣️ `routes/web.php`** — las 2 rutas se **repuntan** a `CrewController` dentro del grupo `['auth','permission:users.update']`,
    **sin cambio de verbo/nombre/URI**: `useredit` (GET), `acountupdate` (POST, nombre `account.update`).
  - **🔐 H2 (IDOR de scope) CERRADO en este path.** Se añadió a `app/Models/User.php` el helper
    **`canManageCrewMember(self $target): bool`** — equivalente "de un solo objetivo" de `applyDepartmentScope`: devuelve `true` si
    el viewer tiene `crew.view.all-departments`; si no, `true` **solo** si comparten departamento vía el pivote `production_user`.
    `useredit` y `acountupdate` ahora hacen `findOrFail($id)` + `abort_unless(auth()->user()->canManageCrewMember($user), 403)`.
  - **🔐 H4 (mass-assignment) CERRADO en `acountupdate`.** El original hacía `$request->except('password')` → **cualquier** campo
    posteado entraba a `fill()` (aunque `admin` ya no es fillable, `activo`/`encuestadiaria` **sí** lo son → un POST manipulado podía
    tocarlos). Ahora valida una **WHITELIST explícita** = exactamente los **11 campos del form** (`name`, `lname`, `lname2`,
    `ncreditos`, `phone`, `borndate`, `puestodepartamento`, `labn`, `email`, `zone`, `sex`) con reglas permisivas (`nullable`, sin
    `unique` en `email` para no romper editar el propio registro); `password` se maneja **aparte** y solo cambia si viene con valor.
  - **✂️ `AdminController`** — ambos métodos removidos, reemplazados por **comentarios breadcrumb** que apuntan a `CrewController`.
- **B) PASO #7 (parcial) — código muerto CSV eliminado de `AdminController`:**
  - Se **borraron `exportCsv()` y `expCsv()` de `AdminController`** — confirmado **SIN ruta** (`route:list` + grep). El export CSV
    **vivo** es `resetController@expCsv` → `/nophoto` (nombre `expCsv`, `permission:reports.export`), usado por
    `usuarioscrud`/`medicocrud`/`comcrud`. Quedó comentario breadcrumb. **Respaldo del `AdminController` previo en `_legacy_backup/`.**
- **✅ Verificación (todas OK):** `php -l` limpio en `User.php`, `CrewController.php`, `AdminController.php`; `route:list` confirma
  `useredit`/`acountupdate` → `CrewController`; render de `useredit` OK (**28353 bytes**); `tinker` confirma `canManageCrewMember` en
  ambos sentidos (viewer acotado a depto 19: **SÍ** gestiona target del mismo depto, **NO** gestiona target de otro depto; super-admin
  **SÍ** a cualquiera).
- **Archivos:** `app/Http/Controllers/CrewController.php`, `app/Http/Controllers/AdminController.php`, `app/Models/User.php`,
  `routes/web.php`, `ARCHITECTURE.md`, `PROGRESS.md`, `SECURITY.md`.
- **Riesgo/Notas:** **Bajo** / **err-restrictivo** (se añade scope + whitelist validada; no se relaja nada). **A diferencia de los
  cortes #1/#3, NO es movimiento puro**: hay endurecimiento explícito. **Siguiente corte planeado:** paso #5 (toggles de estado de
  crew) — aplicar la misma guarda `canManageCrewMember` en la carga del `{id}`.

### 2026-06-28 — 🏗️ Estructura (2/n) — alta de crew extraída: CrewController (escrituras de crew)
- **Estado:** Hecho (verificado). Tercer corte del God Object `AdminController` (paso #3 del roadmap §6) — extrae el **ALTA de
  crew** a un controller propio. Es un **movimiento puro** — misma lógica verbatim, mismos nombres de método, mismas vistas,
  mismos nombres/URIs/middleware de ruta → **cero cambio de comportamiento**. No introduce hallazgos nuevos ni toca seguridad.
- **➕ NUEVO `app/Http/Controllers/CrewController.php`** — contiene los **2 métodos de alta de crew**, extraídos **verbatim** de
  `AdminController`: `adduser()` (form de alta, **GET**) y `newuser(Request)` (persistencia, **POST**). Lógica idéntica, **misma
  vista** (`admin.newuser`) y mismos nombres de método.
- **🛣️ `routes/web.php`** — las **2 rutas** (grupo `['auth','permission:users.create']`) se **repuntan** de `AdminController` a
  `CrewController`. **URIs, nombres y middleware SIN CAMBIO** → no hizo falta tocar ninguna vista.
- **✂️ `app/Http/Controllers/AdminController.php`** — removidos ambos métodos, reemplazados por **comentario breadcrumb** que
  apunta a `CrewController`.
- **🧭 NOTA de diseño — partición de crew en dos controllers.** `CrewListController` (paso #1) = **lecturas** de crew;
  `CrewController` (este paso) = **escrituras** de crew. El **perfil** (`useredit`/`acountupdate`, paso #4) se **sumará a
  `CrewController`** y cerrará ahí **H2** (scope por depto) + **H4** (mass-assignment) en la fase de endurecimiento.
- **✅ Verificación:** `php -l` limpio en `CrewController.php` y `AdminController.php`; `route:list` confirma `adduser`/`newuser`
  → `CrewController@...` (URIs/nombres/middleware sin cambio); render de `adduser()` vía tinker OK (**46971 bytes**).
- **Archivos:** `app/Http/Controllers/CrewController.php` **[NUEVO]**, `app/Http/Controllers/AdminController.php`,
  `routes/web.php`, `ARCHITECTURE.md`, `PROGRESS.md`.
- **Riesgo/Notas:** **Bajo** / **movimiento puro** (err-neutral: misma lógica, mismos contratos de ruta/vista/middleware).
  **Siguiente corte planeado:** paso #4 — perfil de crew (`useredit`/`acountupdate`) **sobre `CrewController`**, ya con
  endurecimiento (scope por depto + whitelist validada) → cierra H2 + H4.

### 2026-06-27 — 🏗️ Estructura (1/n) — primer corte del God Object: CrewListController extraído
- **Estado:** Hecho (verificado). Arranca el lane de **Estructura** (partición de `AdminController`, el God Object de ~581
  líneas) por **strangler**: este primer corte extrae las **5 lecturas de listados de crew** a un controller propio. Es un
  **movimiento puro** — misma lógica verbatim, mismos nombres de método, mismas vistas, mismos nombres/URIs de ruta →
  **cero cambio de comportamiento**. No introduce hallazgos nuevos ni toca seguridad.
- **➕ NUEVO `app/Http/Controllers/CrewListController.php`** — contiene los **5 métodos READ-ONLY** de listado de crew,
  extraídos **verbatim** de `AdminController`: `usuarioscrud`, `comcrud`, `medicocrud`, `idcardscrud` (listas paginadas de
  usuarios, **cada una ya acotada por departamento** vía `User::applyDepartmentScope`) y `idcard($id)` (vista de la
  credencial/gafete). Lógica idéntica, **mismos nombres de vista** (`admin/usuarioscrud`, `admin/comcrud`, `admin/medicocrud`,
  `admin/idcardscrud`, `admin/idcard`) y mismos nombres de método.
- **🛣️ `routes/web.php`** — las **5 rutas** se **repuntan** de `AdminController` a `CrewListController`. **URIs, nombres y
  grupos de middleware SIN CAMBIO:** `/usuarioscrud`, `/comcrud`, `/idcardscrud`, `/idcard/{id}` siguen en el grupo
  `permission:users.view`; `/medicocrud` sigue en `permission:medical.view`. Como las vistas blade referencian estas rutas por
  **nombre de ruta / URI hardcodeada**, **NO hizo falta tocar ninguna vista**.
- **✂️ `app/Http/Controllers/AdminController.php`** — removidos los 5 métodos, reemplazados por **comentarios breadcrumb** que
  apuntan a `CrewListController`. `AdminController` baja de **581 → ~536 líneas**.
- **✅ Verificación:** `php -l` limpio en ambos controllers; el router arranca (**106 rutas**, sin cambio); las 5 rutas ahora
  resuelven a `CrewListController@...`; renderizando cada lista como super-admin logueado devuelve HTML sano
  (`usuarioscrud` 231087 bytes, `comcrud` 111986, `medicocrud` 104729, `idcardscrud` 108221).
- **🧭 NOTA sobre `idcard($id)` (H2, defensa en profundidad).** `idcard($id)` se movió **tal cual**: carga `User::find($id)`
  **sin** scope por departamento. El endurecimiento de scope se **difiere** a la fase de seguridad dedicada (los carves de
  mutación), **NO** se hace en este corte estructural.
- **🧭 CORRECCIÓN A REGISTRAR sobre H2 (IDOR / scope por depto en acciones admin sobre `{id}`): NO es explotable hoy.** Un
  subagente de mapeo lo **sobre-estimó** (asumió p. ej. que un HOD tiene `users.assign-role`). **Verificado contra
  `database/seeders/RolesAndPermissionsSeeder.php`:** los roles que tienen `users.update`/`users.assign-role`/`users.deactivate`
  **también tienen `crew.view.all-departments`**, así que **nadie** que pueda invocar esas acciones admin está restringido por
  departamento. H2 es por tanto un **gap de defensa-en-profundidad (latente)** — importaría solo si un rol futuro recibiera esas
  perms **sin** `all-departments` — a cerrar cuando se carven/endurezcan los métodos de mutación de usuario. **No listar como
  hallazgo activo explotable.**
- **Archivos:** `app/Http/Controllers/CrewListController.php` **[NUEVO]**, `app/Http/Controllers/AdminController.php`,
  `routes/web.php`, `ARCHITECTURE.md`, `PROGRESS.md`.
- **Riesgo/Notas:** **Bajo** / **movimiento puro** (err-neutral: misma lógica, mismos contratos de ruta/vista). **Siguiente
  corte planeado:** ver el backlog **"Estructura — partición de AdminController (strangler)"** abajo (paso 2: catálogos CRUD,
  ⚠ posiblemente diferido al rediseño de catálogos).

### 2026-06-27 — 🔒 Seguridad (2c) — menores: AdminMiddleware activo, dd() fuera, /reminder-mail gateado
- **Estado:** Hecho (verificado). Pasada de **menores** que **cierra el lane de seguridad**: tres fixes pequeños err-restrictivos
  + dos decisiones registradas (no omisiones). Con esto **los CRÍTICOS y ALTOS de explotación quedan todos cerrados o
  recalibrados**; lo que resta es BAJO/diferido. **No introduce hallazgos nuevos.**
- **🔐 FIX 1 — `AdminMiddleware` ahora exige `activo` (H5, sub-ítem).** `app/Http/Middleware/AdminMiddleware.php`: el gate binario
  `if (auth()->check() && auth()->user()->admin)` ahora exige **además** `&& auth()->user()->activo`. Antes, un admin
  **desactivado** (`activo=0`) **seguía pasando** el gate. **Verificado (tinker):** 2 usuarios con `admin=1`, **0** de ellos
  `activo=0` (nadie queda bloqueado por error); el super-admin es `admin=1`/`activo=1`.
- **🔐 FIX 2 — `dd()` fuera de `ReminderMailController` + `/reminder-mail` gateado (M1 + C3).**
  `app/Http/Controllers/ReminderMailController@ReminderMail`: removido el `dd("Job dispatched.")` (abortaba la respuesta con un
  volcado **tras** despachar el job); ahora retorna `redirect('/home')->with('success', ...)`. **Y la ruta** `/reminder-mail`
  (nombre `ReminderMail`) era **GET sin auth** (`[web]`) → **cualquier anónimo** con la URL podía **encolar un envío masivo** del
  correo "REMINDER DAILY REPORT" a **TODOS** los usuarios activos (el job `App\Jobs\ReminderEmail` correa a cada `activo=1`).
  Ahora va con `->middleware('admin')` → `[web,admin]`, **consistente con su hermano `/sendreminder`**. **Verificado:** la ruta es
  ahora `[web,admin]`, lint limpio, el router arranca (**106 rutas**).
- **🔐 FIX 3 — `dd()` fuera de `locationController@store` (M1).** `app/Http/Controllers/locationController@store` (~líneas 201-208):
  removido el `dd('Error al guardar el reporte:', $e->getMessage())` del `catch` (volcaba detalles internos de la excepción al
  usuario y abortaba); ahora **loggea** vía `\Log::error(...)` y retorna `redirect()->back()->withInput()->with('error', ...)`. Se
  quitó además un `return` de éxito **duplicado/muerto** que seguía al try/catch (el éxito ya se retorna dentro del `try`).
  **Verificado:** lint limpio. (Los otros dos vectores de M1 — `MailController:20` y `FormulariosController@newformulario1` — ya
  estaban removidos en los lotes COVID de 2026-06-25 → M1 queda **cerrado**.)
- **🧭 DECISIÓN 1 (registrada, NO omisión) — mass-assignment `$request->all()` → BAJO + diferido al refactor de validación (H4).**
  Investigados los **8 usos**: `AdminController` (`:146`/`:185`/`:259`/`:278` — CRUD de catálogos/notificaciones),
  `HazardNotificationController:46`, `locationController:179`, `locationController2:30`, `unsafecondNotificationController:38`.
  **NINGUNO toca el modelo `User` ni una columna de privilegio/auth** (el vector crítico — `admin` — ya se cerró en C2 quitándolo
  de `$fillable`) → en el peor caso se sobre-postean campos no-privilegio de filas de catálogo → **severidad BAJA**. El fix
  correcto es **validación por formulario** (Form Requests / reglas `validate()`), que **pertenece al refactor de
  Estructura/Limpieza (validación)** — diferido ahí para no hacer cambios ciegos de whitelist que rompan los formularios de
  create/update. **Registrado como backlog bajo ese refactor**, NO como crítico abierto.
- **🧭 DECISIÓN 2 (registrada) — CORS dejado como está: aceptado / bajo riesgo (H3).** `config/cors.php` tiene
  `allowed_origins=['*']` **pero** `paths` solo `['api/*','sanctum/csrf-cookie']` y **`supports_credentials=false`**, y
  `routes/api.php` expone **solo `GET /user` tras `auth:sanctum`**. Con **credentials apagados**, `['*']` es el **default de
  Laravel** y **NO** expone datos autenticados cross-origin; un cliente móvil/API (Sanctum + `device_token`) **no se ve afectado
  por CORS** de todos modos (CORS es enforced por el navegador). **Dejado como está** (bajo riesgo); restringir orígenes solo si en
  el futuro se añade un SPA de navegador en otro origen **o** se activa `supports_credentials=true`. Razonamiento registrado para
  que **no se re-flaggee como Alto**.
- **🏁 CIERRE DEL LANE:** con esta pasada, **los CRÍTICOS y ALTOS de explotación quedan cerrados o recalibrados**. Lo que resta es
  **BAJO/diferido:** el refactor de validación (H4 + `$guarded=[]` de DSR), notas de deploy (C1/C4/PRE-PUSH, `MAIL_FROM_ADRESS`,
  `APP_URL` local), `niveldos`/Policies (H5), y la reimplementación de **`badge:reminder`**. Ninguno es bloqueante ni explotable
  por un anónimo.
- **Archivos:** `app/Http/Middleware/AdminMiddleware.php`, `app/Http/Controllers/ReminderMailController.php`,
  `app/Http/Controllers/locationController.php`, `routes/web.php`, `SECURITY.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo / **err-restrictivo** (se endurece el gate admin con `activo`, se quita superficie de envío masivo sin
  auth, se dejan de filtrar detalles de error). **Siguiente (ya BAJO/diferido):** refactor de validación (H4), GET→POST de
  mutaciones ya autorizadas (H1), `badge:reminder`.

### 2026-06-26 — 🧹🔒 Seguridad (2b) — endpoints de reset/reminder sin auth eliminados (el cron real ya existe; `/PhotoReminder` diferido a comando)
- **Estado:** Hecho (verificado). Continúa el lane de **seguridad**: elimina **cuatro endpoints GET sin auth** — los **tres** de
  reset de `encuestadiaria` de TODA la plantilla activa (uno además mandaba correo masivo) **y** `/PhotoReminder` (recordatorio de
  foto de gafete) — un footgun de **reset/envío masivo accesible por cualquiera con la URL**. Con esto el sub-ítem "resets/reminders
  de sistema sin auth" de **C3 queda CERRADO** (último GET de este tipo eliminado). **No introduce hallazgos nuevos** — avanza C3.
- **🔑 DESCUBRIMIENTO CLAVE — el reset clínico diario YA es un comando Artisan agendado.** `app/Console/Kernel.php:27`
  corre **`encuestas:task` a diario a las 04:00**, y `app/Console/Commands/encuestasTask.php@handle` **resetea
  `encuestadiaria=0` para todos los usuarios activos y manda el correo recordatorio "DAILY REPORT"** (`correos.recordatorio`).
  Por tanto los endpoints web de abajo eran **duplicados redundantes** de ese job agendado → no hacía falta "convertirlos a
  Artisan" (como decía el plan previo): el mecanismo correcto ya existía, los endpoints solo eran un duplicado peligroso.
- **✂️ ENDPOINTS + MÉTODOS ELIMINADOS (3, reemplazados por comentarios breadcrumb — consistente con los lotes COVID; nada se
  movió a `_legacy_backup/` esta vez porque no se removieron archivos completos):**
  - **`GET /newdayRep`** (nombre `newdayRep`) + `resetController@newdayRep` — **duplicado exacto** de `encuestas:task` (mismo
    reset `encuestadiaria=0` + mismo correo recordatorio).
  - **`GET /newWR`** (nombre `newWR`) + `resetController@newWR` — mismo reset `encuestadiaria=0`, sin correo, redirigía `/home`.
  - **`GET /pruebachedule`** (nombre `pruebachedule`) + `PerfilController@pruebachedule` — mismo reset, de nombre "test",
    ni siquiera retornaba.
- **🧹 IMPORTS MUERTOS LIMPIADOS (ya sin uso tras quitar los métodos):**
  - `resetController.php`: removidos `use App\Models\departamento;`, `use App\Models\puesto;`, `use App\Models\userpuesto;`,
    `use App\Models\usuariosnotificacione;`, `use Illuminate\Support\Facades\Hash;` (ningún método superviviente los usaba — tras
    esta primera limpieza solo quedaban `PhotoReminder`/`expCsv`; `PhotoReminder` también se eliminó en el seguimiento de abajo).
  - `PerfilController.php`: removido `use Illuminate\Support\Facades\DB;` (solo `pruebachedule` lo usaba).
- **➕ SEGUIMIENTO (misma pasada) — `/PhotoReminder` ELIMINADO + `resetController` adelgazado al mínimo:**
  - **`GET /PhotoReminder`** (nombre `PhotoReminder`) + `resetController@PhotoReminder` **eliminados** (route + método → comentario
    breadcrumb). Era el **último GET de reset/reminder sin gate**, sin disparador de UI. **NO es reset clínico ni COVID:** mandaba
    un recordatorio de **foto de gafete** (`correos.photo`) a `age=0` (`age` = flag de foto-de-gafete subida); su primer loop era un
    no-op. **Decisión del owner:** la implementación actual es **"muy rústica"** → se **difiere** una reimplementación **"más práctica"**
    como comando agendado `badge:reminder` (ver backlog de Deuda técnica). **La plantilla `resources/views/correos/photo.blade.php`
    SE CONSERVA** para reusar.
  - **`resetController` queda mínimo:** tras quitar `PhotoReminder`, su único método superviviente es `expCsv` (usa solo el modelo
    `User`) → se removieron además los imports ya muertos `Request`/`DB`/`Mail`. El controller queda con **solo**
    `use App\Http\Controllers\Controller; use App\Models\User;`.
- **✅ Verificación:** `php -l` limpio en `resetController.php` y `PerfilController.php`; el router arranca — **conteo de rutas
  110 → 107 → 106** (las 3 de reset + `/PhotoReminder`); `newdayRep`/`newWR`/`pruebachedule`/`PhotoReminder` ahora resuelven a
  **ELIMINADA** (ya no existen); **`encuestas:task` sigue registrado** en Artisan (el mecanismo diario real intacto); `expCsv`
  sigue `web,auth,permission:reports.export`; `encuesta` sigue `web,auth`.
- **⚠️ DEPLOY (verificar por instancia):** quitar estos endpoints URL **asume que el VPS corre el cron estándar de Laravel**
  `php artisan schedule:run` (que dispara `encuestas:task`). **Si alguna instancia de cliente tenía en su lugar un cron que
  curleaba `/newdayRep` (o `/pruebachedule`)** para disparar el reset diario, ese cron **debe cambiarse a `schedule:run`**.
  Marcar como ítem de verificación de deploy.
- **⏳ Aún PENDIENTE (NO resuelto aquí):**
  - **Reimplementar `badge:reminder`** — el recordatorio de foto de gafete que reemplace al `/PhotoReminder` eliminado, como
    comando Artisan agendado (mismo patrón que `encuestas:task`); la plantilla `correos/photo.blade.php` ya está lista. **No
    bloqueante** (registrado en el backlog de Deuda técnica).
  - **GET→POST de mutaciones de estado ya autorizadas (H1):** `/activaradmin`, `/desactivaradmin`, `/checkgft`, `/uncheckgft`
    — viven dentro de grupos `permission:` (autorizadas) pero mutan por **GET**; convertirlas a POST+CSRF toca 8 `<a href>`
    pelados en `admin/usuarioscrud`, `admin/idcardscrud`, `componentes/search-results`, `componentes/search-results-idcard`.
    **Siguiente paso planeado.**
  - **Cosmético COVID:** `resources/views/correos/recordatorio.blade.php` aún trae un bloque de contacto "departamento COVID"
    + nombres de manager/coordinador COVID obsoletos — limpieza de contenido, separada de esta pasada.
- **Archivos:** `routes/web.php`, `app/Http/Controllers/resetController.php`, `app/Http/Controllers/PerfilController.php`,
  `SECURITY.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo / **err-restrictivo** (se elimina superficie de reset/envío masivo sin auth; el reset diario legítimo
  sigue por el comando agendado `encuestas:task`). **Caveat de deploy:** ver el ítem ⚠️ DEPLOY de arriba (si una instancia
  curleaba `/newdayRep`/`/pruebachedule` por cron, cambiar a `schedule:run`). **Siguiente:** reimplementar el recordatorio de
  gafete como comando agendado `badge:reminder` (no bloqueante; plantilla `correos/photo.blade.php` lista) y el GET→POST de
  mutaciones de estado (H1).

### 2026-06-26 — 🔒 Seguridad (2/n) — rutas sin auth gateadas (`/dailyreport`, `/nophoto`)
- **Estado:** Hecho (verificado). Segunda pasada del lane de **seguridad**: cierra **dos rutas genuinamente sin
  autenticación** del cluster C3 de [SECURITY.md](SECURITY.md) — el cuestionario clínico diario `/dailyreport` y el export
  CSV de PII `/nophoto`. Ambas estaban **fuera** de cualquier grupo `auth`. **No introduce hallazgos nuevos** — avanza C3.
- **🔐 FIX 1 — `/dailyreport` (+ `/dailyreport/{id}`) GATEADO.** `routes/web.php`: `/dailyreport` y `/dailyreport/{id}`
  (nombre `encuesta`, `encuestasController@viewencuesta`) estaban **fuera** de todo grupo `auth` → se **movieron** dentro de
  un nuevo `Route::middleware(['auth'])->group(...)`. Nota: `viewencuesta()` llama `auth()->user()->encuestadiaria` y
  `auth()->user()->id`, así que **sin sesión** era un null-deref (HTTP 500) **además** de estar sin auth. Es el
  **cuestionario clínico diario del crew** (expediente) → `auth` es el gate correcto.
- **🔐 FIX 2 — `/nophoto` (export CSV de PII) GATEADO + PERMISO.** `routes/web.php`: `/nophoto` (nombre `expCsv`,
  `resetController@expCsv`) estaba **fuera** de todo grupo `auth` → se **envolvió** en
  `Route::middleware(['auth','permission:reports.export'])->group(...)`. Este endpoint **streamea un CSV con la PII de TODOS
  los usuarios activos** (`name`, `lname`, `lname2`, `borndate`, `sex`, `phone`, `email`, `puestodepartamento`).
  `reports.export` es un permiso **ya existente** (lo tienen `super-admin`/`coordinator`/`safety-officer`/`auditor` según
  `RolesAndPermissionsSeeder`).
- **✅ Verificación (tinker):** el router arranca (**110 rutas**); `gatherMiddleware` → `encuesta` = `web,auth`;
  `expCsv` = `web,auth,permission:reports.export`. `php -l` limpio en `resetController.php`.
- **⏳ PENDIENTE (sigue en el lane de seguridad, NO hecho aquí):**
  - **`/pruebachedule`** (`PerfilController@pruebachedule`, resetea `encuestadiaria` de TODOS los activos, **GET sin gate**) —
    pertenece a la familia **"cron-vía-URL"** junto con `/newdayRep`/`/newWR`/`/PhotoReminder` (`resetController`, también GET
    sin gate, disparados por un **cron del servidor**). Gatear con `auth` **rompería el cron** → el fix correcto es convertir
    TODA la familia a **comandos Artisan + scheduler (Kernel)**. Cambia el wiring de deploy/cron → **necesita visto bueno del
    owner**. Próximo refactor enfocado.
  - **GET→POST de mutaciones de estado ya autorizadas (H1):** `/activaradmin/{id}`, `/desactivaradmin/{id}`, `/checkgft/{id}`,
    `/uncheckgft/{id}` — viven dentro de grupos `permission:` (autorizadas) pero mutan estado por **GET**; convertirlas a
    POST+CSRF toca los `<a href>` de las vistas blade. Pendiente.
  - **Refinamiento (BAJO):** `expCsv` (`/nophoto`) exporta **todos** los activos ignorando el alcance por departamento
    (`User::applyDepartmentScope`): un titular de `reports.export` sin `crew.view.all-departments` igual obtiene a todos.
    Prioridad baja.
- **Archivos:** `routes/web.php`, `SECURITY.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo / **err-restrictivo** (se exige `auth` donde no había ninguno; `/nophoto` además exige
  `permission:reports.export`). **Siguiente:** convertir la familia "cron-vía-URL" (`/pruebachedule`/`/newdayRep`/`/newWR`/
  `/PhotoReminder`) a comandos Artisan + scheduler (con visto bueno del owner) y el GET→POST de mutaciones de estado (H1).

### 2026-06-26 — 🔑 Password reset self-service habilitado (cierra el flujo "olvidé mi contraseña")
- **Estado:** Hecho (verificado). Habilita el flujo **self-service de restablecimiento de contraseña** ("olvidé mi
  contraseña") que **históricamente nunca funcionó**. **Causa raíz:** el enlace en la pantalla de login estaba
  **comentado**, así que el usuario nunca podía llegar al flujo estándar de reset de Laravel. Todo lo demás **ya estaba
  presente y era estándar**: `Auth::routes()`, los controllers stock `Auth\ForgotPasswordController` +
  `ResetPasswordController` (traits `SendsPasswordResetEmails` / `ResetsPasswords`), las vistas `auth/passwords/*`, la
  migración de la tabla `password_resets`, el broker en `config/auth.php` y el SMTP de Mailgun.
- **🔧 CAMBIO 1 (código/template).** `resources/views/auth/login.blade.php:59` — se **descomentó** el
  `<div class="foot"> <a href="{{ route('password.request') }}">¿Olvidaste tu password?</a> </div>`. Con esto el usuario
  por fin llega al flujo de reset estándar.
- **🔧 CAMBIO 2 (config/template).** `.env.example` — se corrigió el typo `MAIL_FROM_ADRESS` → `MAIL_FROM_ADDRESS`. Con el
  typo, `config/mail.php` (que lee `MAIL_FROM_ADDRESS`) **nunca** usaba el remitente intencionado y caía al default de la
  config → la dirección "from" configurada se ignoraba en silencio.
- **✅ Verificación (tinker):** la ruta `password.request` → `password/reset` resuelve a
  `App\Http\Controllers\Auth\ForgotPasswordController@showLinkRequestForm`; la vista de login renderiza (4127 bytes) con el
  texto visible "¿Olvidaste tu password?" y `href` a `/password/reset`; las tres vistas `auth.passwords.email` / `reset` /
  `confirm` compilan vía `Blade::compileString`.
- **🔒 Encuadre de seguridad — complementa la pasada de seguridad previa.** El reset self-service basado en token (token +
  match de email + throttle 60s + regla `min:8`) es **la vía segura**, y **elimina la necesidad de que un admin conozca o
  fije la contraseña de otros usuarios** (un anti-patrón). Complementa los dos fixes críticos ya registrados (ver entrada
  **🔒 Seguridad (1/n)**): `admin` fuera de `$fillable` (C2) y `/updatepassword` gateado al usuario autenticado (C3). Nota
  añadida también en [SECURITY.md](SECURITY.md).
- **⏳ PENDIENTE (registrado en el backlog de Seguridad/Deploy; NO hecho aquí):**
  - **(deploy/local)** El `.env` local tiene `APP_URL` como host pelado `127.0.0.1` (malformado — sin esquema/puerto). El
    env de producción (`ap2.crewcare.app`) está bien. Para probar el enlace del correo de reset **localmente** de extremo a
    extremo, `APP_URL` debe ser una URL completa (esquema + host[:puerto]). Es un tema **por-deploy**: cada subdominio VPS de
    cliente debe fijar su propio `APP_URL` correcto.
  - **(deploy)** El mismo typo `MAIL_FROM_ADRESS` existe también en el `.env` **vivo**. Corregirlo ahí cambia la identidad
    "from" **activa** del correo (del fallback a la dirección intencionada) — un cambio outward-facing/de entregabilidad —
    así que aplicarlo **solo tras confirmar** que la dirección intencionada es un remitente verificado en Mailgun. **Marcar,
    no auto-aplicar.**
  - **(feature/i18n)** El **correo** de reset sale en **inglés** (notificación `ResetPassword` default de Laravel). La app es
    bilingüe es/en. Pendiente: localizarlo a español vía override de `User::sendPasswordResetNotification` (notificación
    custom) + strings de lang `es`. No bloqueante.
  - **(SEGURIDAD — diferido a PRE-PUSH, NO es exposición)** Tanto el `.env` como el `.env.example` llevan **valores reales**
    (un `APP_KEY`, un password de BD y un `MAIL_PASSWORD` de Mailgun). **Esto es intencional** (acordado al inicio del
    proyecto): permiten probar la base offline contra la BD/Mailgun reales en local. Como **NO hay repositorio git** para
    este proyecto, **NO hay exposición** por repositorio. Sanear `.env.example` a placeholders + **rotar** los secretos (key
    de Mailgun, password de BD, regenerar `APP_KEY`) queda **diferido al checklist PRE-PUSH** existente — solo relevante antes
    de un futuro primer `git push`. Registrado por nombre en [SECURITY.md](SECURITY.md) — **sin** copiar los valores reales.
- **Archivos:** `resources/views/auth/login.blade.php`, `.env.example`, `SECURITY.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo / **err-restrictivo** (se habilita la vía segura de reset por token; se reduce el anti-patrón de
  admin-fija-passwords). Los 4 pendientes de arriba son **por-deploy / no bloqueantes**; el de los secretos reales en
  `.env`/`.env.example` es **intencional para pruebas locales y NO es exposición** (no hay repo) → sanear+rotar queda
  **diferido al checklist PRE-PUSH** (ver [SECURITY.md](SECURITY.md)). **Siguiente:** localizar el correo de reset y corregir
  `APP_URL` local para pruebas E2E.

### 2026-06-25 — 🔒 Seguridad (1/n) — escalada de privilegios + account-takeover cerrados (2 CRÍTICOS)
- **Estado:** Hecho (verificado). Primera pasada del lane de **seguridad**: cierra **dos hallazgos CRÍTICOS** de
  [SECURITY.md](SECURITY.md) — la **escalada de privilegios por mass-assignment de `admin`** (C2) y el **account-takeover +
  IDOR en cambio de password** junto con el cluster de rutas password/perfil/formularios **sin auth** (C3, parcial).
- **🔐 FIX 1 — Escalada de privilegios (C2) CERRADO.** `app/Models/User.php`: se **quitó `admin` de `$fillable`**. Antes,
  cualquier update de perfil/cuenta que volcara `request()` (`fill()`/`update()`) permitía mandar `admin=1` y auto-promoverse
  (el `AdminMiddleware` chequea `users.admin==1`) → bypass de todo el RBAC. Ahora `admin` **no es asignable en masa**. El
  otorgamiento legítimo **no se afecta**: usa **asignación explícita** (`$producto->admin=1` en
  `AdminController@activaradmin`/`desactivaradmin`, gateado por `permission:users.assign-role`), que ignora `$fillable`.
  **`daytest` se dejó intencionalmente** en `$fillable` (no es vector de autorización: el acceso a rutas lo imponen los
  permisos spatie; `daytest` solo afecta el display del sidebar).
- **🔐 FIX 2 — Account-takeover + IDOR en password (C3, cluster password/perfil) CERRADO.** `routes/web.php`: `/changepassword`,
  `/updatepassword`, `/formularios/registro` (clínico), `/formularios/medicos` y `/profile` estaban **fuera** de cualquier grupo
  `auth` (el grupo `auth` solo envolvía `/profile`). Ahora **todos** viven dentro de un `Route::middleware(['auth'])->group(...)`.
  **Lo más grave:** `/updatepassword/{id}` era **POST sin auth** → cualquiera podía POSTear un password nuevo para **cualquier
  user id** (account-takeover) y mass-assignar campos. La ruta **perdió el `{id}`**: ahora es `POST /updatepassword` (sin id).
  `PerfilController@updatepassword` se **reescribió** para operar **solo sobre `auth()->user()`** (sin IDOR), validar `password`
  (`required|string|min:8`) y setear **únicamente** el password (`Hash::make`) — se quitó la cruft de
  `fill($request->except('password'))`. `resources/views/changepassword.blade.php`: el `action` del form apunta ahora a
  `route('updatepassword')` (sin id).
- **✅ Verificación:** **FIX 1** — `tinker`: `admin` ausente de `getFillable()`; `fill(['admin'=>1])` **no** lo setea; los campos
  normales sí se llenan; `$u->admin=1` explícito **sí** funciona. **FIX 2** — `php -l` OK; `route:list` muestra URIs limpias;
  `gatherMiddleware` confirma `web,auth` en `updatepassword`/`changepassword`/`perfil`/`formularios-registro`/`formularios-medicos`;
  la vista `changepassword` renderiza y su form resuelve a `/updatepassword` (sin id).
- **⏳ PENDIENTE (próximas pasadas del lane de seguridad) — rutas sin gate / sensibles detectadas cerca:**
  - `/pruebachedule` (`PerfilController@pruebachedule`, resetea `encuestadiaria` de TODOS los usuarios activos, **GET sin gate**
    → gatear o convertir en comando Artisan).
  - `/nophoto` (`resetController@expCsv`, **export CSV de crew sin auth**).
  - `/dailyreport` (`encuestasController@viewencuesta`, sin gate; usa `auth()->user()`).
  - **Mutaciones de estado por GET** (`activaradmin`/`checkgft`/`uncheckgft` vía GET → POST+CSRF, ver H1).
  - Mass-assignment con `$request->all()` en catalog/notification/location controllers (severidad menor — esos modelos no tienen
    campos de privilegio), `dd()` en `ReminderMailController`, CORS `allowed_origins=['*']`.
- **Archivos:** `app/Models/User.php`, `routes/web.php`, `app/Http/Controllers/PerfilController.php`,
  `resources/views/changepassword.blade.php`, `SECURITY.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo / **err-restrictivo** (se quita una capacidad de mass-assignment y se exige `auth`; el otorgamiento de
  admin sigue por la vía explícita gateada). **Siguiente:** segunda pasada de seguridad (resets sin gate `/pruebachedule`/`/nophoto`/`/dailyreport`,
  GET→POST de mutaciones de estado, CORS, `dd()`).

### 2026-06-25 — 🦠 COVID desacople (2/n) + limpieza de código muerto — métodos de SÍNTOMAS eliminados
- **Estado:** Hecho (verificado). Segundo lote del **desacople**: elimina el cuestionario de **síntomas** COVID
  (`sintoma1-8`/`convivencia` → `users.enfermo`) que vivía en **métodos muertos (no ruteados)**, cerrando el
  acoplamiento síntomas→`enfermo`. Lo borrado de las vistas se **MUEVE a `_legacy_backup/`** (regla del owner).
- **✂️ MÉTODOS MUERTOS REMOVIDOS de `FormulariosController` (3, no ruteados):** `newformulario1` (empezaba con `DD()`),
  `newformulario2`, `checkform` — los tres sostenían el cuestionario de síntomas COVID (`sintoma1-8`/`convivencia`) que
  escribía `users.enfermo`. El archivo pasó de **668→194 líneas**. **SE QUEDAN los métodos clínicos VIVOS:**
  `registrarformulario` (`/formularios/medicos`) y `newformulario` (`/formularios/registro`) — `newformulario` guarda el
  registro de historial médico y **NO** toca `enfermo`.
- **✂️ MÉTODO MUERTO REMOVIDO de `encuestasController`:** `checkfroms` (tenía un bug: `findOrFail(id)` con la constante
  `id` indefinida). **SE QUEDA `viewencuesta`** (`/dailyreport`).
- **🗑️ VISTA MOVIDA A `_legacy_backup/`:** `resources/views/correos/avisos.blade.php` (correo COVID huérfano; su único
  consumidor vivo era el ya removido `newformulario2`; `queueController` ya estaba en `_legacy_backup/`).
- **✅ Verificación:** `php -l` OK en ambos controladores; `composer dump-autoload`; `route:list` arranca (**110 rutas**);
  todas las rutas clínicas vivas (`dailyreport`, `formularios/registro`, `formularios/medicos`, todas las de DSR).
- **🧱 NOTAS:** La lógica síntomas→`enfermo` queda **totalmente eliminada**; `enfermo` es ahora una columna **dormida**,
  escrita solo por data-setters (`User::$fillable`, `TestAccountsSeeder`, `AdminController@newuser` default) hasta que el
  lote de esquema la dropee. `accesos/noautorizado.blade.php` **se queda** (aún lo usa `registrarformulario`);
  `accesos/autorizado.blade.php` queda **huérfano** (solo lo usaba el removido `checkform`) pero se deja en su sitio por
  ahora (no es claramente COVID — pantalla de resultado de cuestionario).
- **⏳ PENDIENTE (repurpose del formulario, gateado por diseño):** el flujo VIVO de `formulario` (`newformulario` +
  `formulario.blade.php`) aún procesa los campos COVID `vacci1` (vacuna COVID) y `crt19` (certificado COVID) — salen como
  parte del repurpose de "Medical Reports", que necesita primero la decisión de rediseño.
- **Archivos:** `app/Http/Controllers/FormulariosController.php`, `app/Http/Controllers/encuestasController.php`,
  `_legacy_backup/` (solo creció — `correos/avisos.blade.php` movido), `PROGRESS.md`, `COVID-DECOMMISSION.md`.
- **Riesgo/Notas:** Bajo. Los métodos removidos no estaban ruteados (código muerto); `correos/avisos` respaldado en
  `_legacy_backup/`. **Siguiente:** lote de esquema (DROP de columnas/tablas) y el repurpose del formulario (`vacci1`/`crt19`).

### 2026-06-25 — 🦠 COVID desacople (1/n) — flag `enfermo` / NotSick removido de la Crew List
- **Estado:** Hecho (verificado). Primer lote del **desacople** (editar vistas/escritores antes de tocar columnas):
  saca el flag `enfermo` y el botón **NotSick** de la Crew List, de extremo a extremo (vista + búsqueda AJAX + tint de filas +
  controlador + ruta + proyección de búsqueda + mapeo del importador).
- **Archivos tocados (7):**
  - `resources/views/admin/usuarioscrud.blade.php` — quitado el `<li>` de **NotSick** (gateado por `@if($user->enfermo===1)`, con
    un `@if/@endif` mal formado). El dropdown de Crew List queda limpio (Credencial / Editar / Historial Médico se quedan).
  - `resources/views/componentes/search-results.blade.php` — quitado el mismo bloque NotSick (el parcial de búsqueda AJAX
    reflejaba `usuarioscrud`).
  - `resources/views/admin/comcrud.blade.php` — quitado el tint de fila por `enfermo` (`@if enfermo==0 <tr> @else <tr class="table-warning">`);
    ahora un `<tr>` plano.
  - `app/Http/Controllers/AdminController.php` — removido el método `notsick()` (ponía `users.enfermo=0`).
  - `routes/web.php` — removida la ruta `/notsick`.
  - `app/Http/Controllers/SearchController.php` — quitado `'enfermo'` de la proyección de columnas de `PRESET_USERS` (+ doc comment
    actualizado); `labn` ya se había removido antes (nota en su comentario).
  - `app/Imports/QueueImport.php` — quitado el mapeo de columnas COVID del import de crew (`enfermo`,`temperatura`,`inline`,`tested`,
    `resultpcr`,`labn`); **se queda** el import de crew, `age` (gafete) y `daytest` (rol legacy, hasta el lote de esquema).
- **Verificación:** `php -l` OK en todos los PHP tocados; `route:list` arranca (**110 rutas**); `/notsick` ya no aparece;
  `usuarioscrud` **renderiza** (229 KB, sin "Not Sick", "Editar" presente); `search-results` compila (directivas balanceadas);
  `comcrud` **renderiza** (112 KB, sin `table-warning`); `view:cache` compiló **todas** las vistas sin error.
- **⏳ Pendiente (sigue acoplado a `enfermo`):**
  - `FormulariosController` lógica síntomas→`enfermo` (líneas ~331-619) — parte del lote de **REPURPOSE** del formulario/cuestionario
    clínico, no tocado aquí.
  - Campos COVID mostrados en `useredit`/`idcardscrud` (`temperatura`/`resultpcr`/`labn`) — aún por revisar/quitar.
  - Escritores a nivel de datos que **se quedan hasta dropear la columna**: `User::$fillable`, `TestAccountsSeeder`,
    `AdminController@newuser` (default).

### 2026-06-25 — 🦠 COVID-DECOMMISSION Lote 3b + 4 — resets PCR, checkpoint/temperatura y barrido de correos COVID
- **Estado:** Hecho (verificado: `php -l` limpio en `AdminController.php` y `resetController.php`; `composer dump-autoload`; `route:clear`/`view:clear` OK;
  `route:list` **arranca con 111 rutas**; las rutas COVID `checkpoint`/`temperature`/`newTD`/`Nresult` ya **NO aparecen**). Cierra los dos lotes COVID que
  quedaban diferidos. Lo borrado **NO se borra duro** — se **MUEVE a `_legacy_backup/`** (regla del owner: nada del backup se elimina hasta el cierre).
- **✂️ MÉTODOS REMOVIDOS de `resetController` (Lote 3b):** `newTD` (reseteaba `resultpcr/inline/tested` de cola PCR), `CleanR` (reseteaba `resultpcr`, no
  ruteado), `Nresult` (creaba registros `prueba`+`usuariopcr`, marcaba PCR negativo — **era el último consumidor vivo de esos 2 modelos**). **SE QUEDAN
  (clínicos / gafete):** `newdayRep`, `newWR` (resetean `encuestadiaria`), `PhotoReminder`, `expCsv`. Archivo 169→119 líneas.
- **✂️ MÉTODOS REMOVIDOS de `AdminController` (Lote 4):** `checkpoint` (devolvía `admin/checkpoint`, control de temperatura COVID) y `temperature`
  (escribía `users.ultimatemperatura`). **SE QUEDA `notsick`** (escribe `enfermo`, acoplado al botón NotSick de `usuarioscrud`) → diferido al lote de desacople.
- **🧹 IMPORTS REMOVIDOS de `resetController` (ya muertos tras quitar los métodos):** `use App\Models\usuariopcr;`, `use App\Models\prueba;` y `use Carbon\Carbon;`
  (Carbon solo lo usaba `Nresult`). De `AdminController` no quedaron imports muertos por estos cambios.
- **🛣️ RUTAS RETIRADAS de `routes/web.php` (4):** `/newTD`, `/Nresult` (Lote 3b), `/checkpoint`, `/temperature` (Lote 4). **SE QUEDAN (NO se tocaron):**
  `/newWR`, `/newdayRep`, `/PhotoReminder`, `/notsick`, `/historialWR`.
- **🗑️ ARCHIVOS MOVIDOS A `_legacy_backup/` (10):**
  - **Modelos (2):** `app/Models/prueba.php`, `app/Models/usuariopcr.php` (sin referencias vivas tras quitar `Nresult` — verificado cero `prueba::`/`usuariopcr::`).
  - **Vista (1):** `resources/views/admin/checkpoint.blade.php` (no existía `checkpoint-mobile`).
  - **Correos COVID huérfanos (7):** `resources/views/correos/{avisosg,certificados,nuevoingreso,personalizado,pruebas,pruebasentrada,testnotification}.blade.php`
    (su único consumidor era `queueController@scheduletest`, ya en backup desde Lote 1).
- **⚠ `correos/avisos.blade.php` SE QUEDA** — aún lo referencia `FormulariosController:605` (método muerto); se irá en el lote de desacople con ese método.
- **⏳ NO en estos lotes (COVID restante):** (1) **lote de desacople** — columna `enfermo`/botón NotSick en `usuarioscrud` + `labn` en búsquedas + campos COVID en
  `useredit`/`idcardscrud` + mapeo COVID de `QueueImport`; métodos muertos `FormulariosController@newformulario1/2/checkform` + `encuestasController@checkfroms`
  (libera `correos/avisos`). (2) **lote de esquema (ÚLTIMO)** — DROP de tablas (`pcr`/`prueba`/`usuariopcr`/`testqueue`) y columnas `users`
  (`tested`/`resultpcr`/`enfermo`/`inline`/`labn`/`ultimatemperatura`/`inlined`/`lastpcr`) — ⚠ **NUNCA** dropear `daytest` (=rol) ni `age` (=foto de gafete).
- **Archivos:** `app/Http/Controllers/resetController.php`, `app/Http/Controllers/AdminController.php`, `routes/web.php`, `_legacy_backup/` (solo creció — 10
  artefactos movidos con timestamp), `PROGRESS.md`, `COVID-DECOMMISSION.md`.
- **Riesgo/Notas:** Bajo. Todo lo retirado va respaldado en `_legacy_backup/`. Verificado: `route:list` **111 rutas**, lint OK, rutas COVID
  (`checkpoint`/`temperature`/`newTD`/`Nresult`) ausentes. **Siguiente:** lote de desacople (vistas/búsquedas/importador) y, al final, el drop de columnas/tablas.

### 2026-06-25 — 🦠 COVID-DECOMMISSION Lote 3 — vertical lab/resultados PCR eliminado
- **Estado:** Hecho (verificado: `php -l` limpio en `AdminController.php` y `web.php`; `composer dump-autoload`; `route:clear`/`view:clear` OK;
  `route:list` **arranca con 115 rutas**; grep residual limpio — solo falsos positivos por substring: la vista `historiamr` que se queda,
  `ReminderMailController`, y el `SendEmailVerificationNotification` del framework). **Tercer lote** del decommission COVID/PCR
  (ver [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md)). Esta pasada retira **el vertical de lab/resultados PCR** (lab, historial PCR, marcado
  positivo/negativo, certificado PDF). Lo borrado **NO se borra duro** — se **MUEVE a `_legacy_backup/`** (regla del owner: nada del backup
  se elimina hasta el cierre).
- **✂️ MÉTODOS REMOVIDOS de `AdminController` (reemplazados por comentarios breadcrumb):** `historial` (historial PCR, devolvía
  `componentes.historia`, join `usuariopcr`+`prueba`), `testpcrcrud` (`/listcrew`), `mainlab` (`/lab`), `positivepcrO` (no ruteado),
  `notoday`, `notodays`, `positivepcr`, `negativepcr`, `negativeantg` (PDF Dompdf de certificado).
- **🧹 IMPORTS REMOVIDOS de `AdminController` (ya muertos tras quitar los métodos):** `use App\Models\usuariopcr;`, `use App\Models\prueba;`,
  `use Dompdf\Dompdf;`, y `use ZipArchive;` (este último **ya era un import muerto previo** → limpieza de calidad de código, sin cambio
  de comportamiento).
- **✅ SE QUEDAN en `AdminController` (verificado, siguen presentes):** `historialWR` (clínico), `temperature` (diferido al **Lote 4**),
  `welcomeresend`, `sendreminder`.
- **🛣️ RUTAS RETIRADAS de `routes/web.php` (9):** `/negative-mail` (`MailController@sendMail`), `/historial/{id}/`, `/notoday`, `/notodays`,
  `/lab`, `/listcrew`, `/positivepcr`, `/negativepcr`, `/negativeantg`. **SE QUEDAN (NO se tocaron):** `/welcomeresend`, `/reminder-mail`,
  `/newTD`, `/Nresult`, `/checkpoint`, `/temperature`, `/notsick`, `/historialWR`.
- **🗑️ ARCHIVOS MOVIDOS A `_legacy_backup/`:**
  - `resources/views/pcrtest/` (carpeta completa: `mainlab`, `listcrew`).
  - `resources/views/componentes/historia.blade.php` (historial PCR).
  - `resources/views/correos/{positive,negative,negativean,notifypositive}.blade.php`.
  - `app/Jobs/SendEmail.php` (mandaba `correos.negative` a resultado PCR; from `covid@crewcare.tech`).
  - `app/Http/Controllers/MailController.php` (disparaba `SendEmail`; terminaba en `dd()`).
- **⏳ NO en este lote (diferido a Lote 3b):** `resetController` métodos `newTD`/`CleanR`/`Nresult` + sus rutas (`/newTD`, `/Nresult`),
  modelos `prueba` + `usuariopcr` (**siguen vivos porque `resetController@Nresult` los usa**), y el **barrido de los correos COVID huérfanos
  restantes** (`pruebas`/`pruebasentrada`/`certificados`/`testnotification`/`avisosg`).
- **⏳ NO en este lote (diferido a Lote 4):** página host `checkpoint` + `temperature` (`/checkpoint`, `/temperature`, `ultimatemperatura`).
- **Archivos:** `app/Http/Controllers/AdminController.php`, `routes/web.php`, `_legacy_backup/` (solo creció — 7 artefactos movidos con
  timestamp), `PROGRESS.md`, `COVID-DECOMMISSION.md`.
- **Riesgo/Notas:** Bajo. Todo lo retirado va respaldado en `_legacy_backup/`. Verificado: `route:list` **115 rutas**, lint OK, grep residual
  limpio (solo falsos positivos por substring). **Siguiente:** Lote 3b (`resetController` PCR + modelos `prueba`/`usuariopcr` + barrido de
  correos huérfanos) y Lote 4 (checkpoint/temperatura), y por último el drop de columnas/tablas.

### 2026-06-25 — 🦠 COVID-DECOMMISSION Lote 2 — vertical PCR CRUD eliminado
- **Estado:** Hecho (verificado: `php -l` limpio en `AdminController.php`, `resetController.php` y `web.php`; `route:clear`/`view:clear` OK;
  `route:list` **arranca con 297 rutas** y **CERO rutas de PCR CRUD** — ⚠ nota: `testpcrcrud`/`/listcrew` siguen existiendo, son un método de lab
  del **Lote 3**, solo coinciden por substring). **Segundo lote** del decommission COVID/PCR (ver [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md)).
  Esta pasada retira **el vertical PCR CRUD** (el CRUD de resultados PCR). Lo borrado **NO se borra duro** — se **MUEVE a `_legacy_backup/`**
  (regla del owner: nada del backup se elimina hasta el cierre).
- **🗑️ ARCHIVOS MOVIDOS A `_legacy_backup/`:**
  - `app/Models/pcr.php` (modelo `pcr` — lo usaban **solo** los 5 métodos PCR CRUD, ya removidos → queda sin código).
  - `resources/views/admin/pcrcrud.blade.php`.
  - `resources/views/admin/pcredit.blade.php`.
- **✂️ EDITS DE CÓDIGO:**
  - `app/Http/Controllers/AdminController.php`: removidos los **5 métodos** `pcrcrud`/`crearpcr`/`editarpcr`/`savepcr`/`eliminarpcr`
    (eran ~líneas 711–761), reemplazados por un comentario breadcrumb; también se quitó el `use App\Models\pcr;` que ya no se usaba.
  - `app/Http/Controllers/resetController.php`: removido un `use App\Models\pcr;` **MUERTO** (nunca lo usaba — el archivo trabaja con
    `usuariopcr`/`prueba`, no con `pcr`). Limpieza de calidad de código, sin cambio de comportamiento.
- **🛣️ RUTAS RETIRADAS de `routes/web.php` (5):** `pcrcrud`, `crearpcr`, `editarpcr`, `savepcr`, `eliminarpcr`.
- **⏳ NO en este lote (lotes posteriores):** lab/resultados (`mainlab`/`testpcrcrud`/`positivepcr`/`negativepcr`/`negativeantg`/`notoday`/`notodays`),
  checkpoint/temperatura, correos COVID + job `SendEmail` + `MailController`, remoción de los demás modelos (`prueba`/`usuariopcr`/`queuevirtual`/`userform`)
  y el drop de columnas/tablas (incl. la tabla `pcr`, AL FINAL).
- **ℹ️ Aclaración del dueño registrada (2026-06-25):** `DailyReport` (el cuestionario diario) = **"expediente clínico" (cuestionario de expediente
  clínico), NO es COVID** → se **CONSERVA**. `AdminController@sendreminder` (asunto "DAILY REPORT", filtra `encuestadiaria=0`) pertenece a este flujo
  clínico y **se quedó**. No confundir con `formulario`/`formularios` (historial médico, REPURPOSE).
- **Archivos:** `app/Http/Controllers/AdminController.php`, `app/Http/Controllers/resetController.php`, `routes/web.php`,
  `_legacy_backup/` (solo creció — 3 artefactos movidos con timestamp), `PROGRESS.md`, `COVID-DECOMMISSION.md`.
- **Riesgo/Notas:** Bajo. Todo lo retirado va respaldado en `_legacy_backup/`. El modelo `pcr` lo usaban **solo** esos 5 métodos (verificado) → ahora
  totalmente removido. Verificado: `route:list` **297 rutas**, lint OK, sin rutas de PCR CRUD. **Siguiente:** los lotes COVID restantes (lab/resultados,
  checkpoint, correos/jobs, modelos y por último columnas/tablas).

### 2026-06-25 — 🦠 COVID-DECOMMISSION Lote 1 — vertical de la cola virtual eliminado
- **Estado:** Hecho (verificado: `php -l` limpio en `web.php`; `route:clear`/`view:clear` OK; `route:list` **arranca limpio con 307 rutas** y **CERO rutas de cola**;
  ningún código superviviente referencia `queueController`/`exportController`/`AntigenTestExport`). **Primer lote** de varios del decommission COVID/PCR
  (ver [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md)). Esta pasada retira **completo el vertical de la cola virtual** (cola PCR/antígeno): controladores,
  vistas, rutas y el export de antígeno. Lo borrado **NO se borra duro** — se **MUEVE a `_legacy_backup/`** (regla del owner: nada del backup se elimina hasta el cierre).
- **🗑️ ARCHIVOS MOVIDOS A `_legacy_backup/`:**
  - `app/Http/Controllers/queueController.php` (REMOVE COMPLETO — todos sus métodos eran de la cola COVID).
  - `app/Http/Controllers/exportController.php`.
  - `app/exports/AntigenTestExport.php`.
  - `resources/views/virtualqueue/` (carpeta completa: `scheduletest`, `antigentest`, `crudtest`).
  - `resources/views/componentes/searchqueue.blade.php`.
- **🛣️ RUTAS RETIRADAS de `routes/web.php` (13):** `searchqueue`, `mymail`, `enviarcorreos` (`enviarCorreoTest`), `testexport` (`export`), `virtualqueue` (`scheduletest`),
  `antigentest`, `crudtest`, `selecta`, `selectb`, `selectglobal`, `userqueue`, `usertested`, `remindertest`.
- **✅ SE QUEDAN en su sitio (NO se tocaron):** `sendreminder` (`AdminController`, recordatorio del formulario) e `importcrew`/`crewstore` (`importController` — KEEP/REPURPOSE,
  limpiar el mapeo COVID después).
- **⚠️ Notas / huérfanos detectados:**
  - El modelo **`message`** (plantilla TinyMCE) queda **HUÉRFANO** — solo lo usaba `queueController`. **Decisión pendiente** (REMOVE o reusar para correos del formulario);
    NO se removió en este lote.
  - Los dos enlaces a `/userqueue` en `layouts/header.blade.php` están **dentro de comentarios HTML** (inertes) → no rompen nada al irse la ruta.
- **⏳ NO en este lote (lotes posteriores, mayormente en `AdminController.php` que no se puede paralelizar):** PCR CRUD (`pcrcrud`/`crearpcr`/`editarpcr`/`savepcr`/`eliminarpcr`),
  lab/resultados (`mainlab`/`testpcrcrud`/`positivepcr`/`negativepcr`/`negativeantg`/`notoday`/`notodays`), checkpoint/temperatura, correos COVID + job `SendEmail` + `MailController`,
  remoción de modelos (`pcr`/`prueba`/`usuariopcr`/`queuevirtual`/`userform`) y el drop de columnas/tablas (AL FINAL).
- **Archivos:** `routes/web.php`, `_legacy_backup/` (solo creció — 5 artefactos movidos con timestamp), `PROGRESS.md`, `COVID-DECOMMISSION.md`.
- **Riesgo/Notas:** Bajo. Todo lo retirado va respaldado en `_legacy_backup/`. Verificado: `route:list` arranca con **307 rutas** y **cero rutas de cola**; nada vivo referencia
  los controladores/export removidos. **Siguiente:** los lotes COVID restantes (PCR CRUD, lab/resultados, checkpoint, correos/jobs, modelos y por último columnas/tablas).

### 2026-06-25 — 🛡️ SEEDER RBAC NO-DESTRUCTIVO (guarda anti-reset de cambios en vivo)
- **Estado:** Hecho (verificado: re-correr el seeder sobre una BD poblada **preservó** una edición viva simulada — `hod` conservó un
  permiso extra; `RBAC_FORCE_RESEED=true` forzó el reset de fábrica — `hod` volvió a fábrica; conteo de permisos sigue en **43**).
- **A. 🧱 MODELO DE DESPLIEGUE (aclaración).** El repo offline es la **plantilla BASE**. Cada proyecto de cliente se despliega
  **FRESCO** en su propio subdominio VPS (p. ej. `pelinorte.crewcare.mx`) corriendo migraciones + seeders **UNA sola vez** sobre una
  BD vacía. **El seeder NUNCA está pensado para re-correr en una instancia viva.**
- **B. 🛡️ GUARDA NO-DESTRUCTIVA en `RolesAndPermissionsSeeder.php`.** Justo después de `firstOrCreate` de los roles y **ANTES** de
  aplicar la matriz, un guard chequea `Role::has('permissions')->exists()`. Si la matriz ya existe en la BD (instancia viva donde un
  `super-admin` pudo editar permisos vía "Permisos por rol"), el seeder **registra los permisos nuevos del catálogo** (vía el loop de
  `firstOrCreate` previo) pero **NO re-sincroniza/pisa** los grants existentes de los roles — avisa y retorna temprano,
  **preservando las ediciones vivas**.
- **C. 🔑 OVERRIDE `RBAC_FORCE_RESEED=true`** (env var) salta la guarda para un **reset de fábrica intencional**.
- **D. ❌ "Botón restaurar a fábrica" RECHAZADO.** La idea previa de exponer un botón "restaurar valores de fábrica" en la UI se
  **descartó explícitamente** como un footgun de **auto-destruir**: un clic equivocado borraría la config viva. La protección real
  que se quería es la guarda no-destructiva del seeder (arriba), no un botón.
- **Archivos:** `database/seeders/RolesAndPermissionsSeeder.php`, `PROGRESS.md`, `AUTH-RBAC-PLAN.md`, `RECIPE.md`.
- **Riesgo/Notas:** Aditivo / **err-seguro** (por default preserva el estado vivo; el reset destructivo solo bajo flag explícito).
  Resuelve el trade-off del punto F de la entrada 🎛️ EDITOR EN VIVO (ver abajo).

### 2026-06-25 — 🎛️ EDITOR EN VIVO DE LA MATRIZ ROL→PERMISO ("Permisos por rol")
- **Estado:** Hecho (verificado: el permiso existe y **SOLO `super-admin`** lo tiene; el view renderiza ~278KB; el round-trip de
  `update()` persiste y **NO** borra los permisos de los demás roles; re-sembrar restaura la fábrica). Se construyó una **pantalla
  para editar EN VIVO la matriz rol→permiso** desde la UI (antes la única forma de cambiar grants era editar el seeder y re-sembrar).
  Cierra el último hueco de "todo se toca por seeder": ahora un `super-admin` ajusta permisos por rol sin tocar código.
- **A. 🔑 NUEVO PERMISO `roles.manage-permissions`** en el catálogo de `RolesAndPermissionsSeeder.php`. Por **DEFAULT solo
  `super-admin` lo tiene** (vía `Permission::all()`). Conteo de permisos **42 → 43**.
- **B. 🧩 NUEVO controller `app/Http/Controllers/RolePermissionController.php`** — `edit()` renderiza la matriz agrupada por módulo;
  `update()` hace `syncPermissions` por cada rol editable + `forgetCachedPermissions()`. **La columna `super-admin` es de SOLO
  LECTURA** (god-mode, nunca editable por la UI). **Guard anti-auto-bloqueo:** un no-super-admin **no puede** guardar una matriz que
  le quite `roles.manage-permissions` a su propio rol (evita quedarse sin acceso a esta misma pantalla).
- **C. 🖼️ NUEVA vista `resources/views/admin/role-permissions.blade.php`** — matriz de checkboxes agrupada por módulo, aviso de
  **"cambios en vivo"** y columna `super-admin` bloqueada.
- **D. 🛣️ RUTAS (`routes/web.php`)** bajo grupo `permission:roles.manage-permissions`: **`GET /permisoscrud`**
  (`roles.permissions.edit`) y **`POST /permisoscrud`** (`roles.permissions.update`).
- **E. 🧭 SIDEBAR** (`resources/views/layouts/sidebar.blade.php`, sección Admin, desktop + espejo móvil): nuevo ítem **"Permisos por
  rol"** gateado `@can('roles.manage-permissions')`.
- **F. ⚠️ CONCEPTO CLAVE — "valor de fábrica" vs "estado vivo".** El seeder es ahora el **valor de fábrica** (factory default) y la
  BD es el **estado vivo**. **✅ RESUELTO (2026-06-25):** el trade-off de "re-sembrar pisa la matriz viva" se cerró haciendo el
  **seeder NO-DESTRUCTIVO** — una guarda (`Role::has('permissions')->exists()`) detecta una instancia viva y **preserva** los grants
  editados (registra permisos nuevos del catálogo pero no re-sincroniza), con override `RBAC_FORCE_RESEED=true` para un reset
  intencional. **NO se hizo un botón "restaurar a fábrica"** (se descartó como footgun de auto-destruir). Ver entrada
  **🛡️ SEEDER RBAC NO-DESTRUCTIVO** arriba.
- **Archivos:** `database/seeders/RolesAndPermissionsSeeder.php`, `app/Http/Controllers/RolePermissionController.php` (NUEVO),
  `resources/views/admin/role-permissions.blade.php` (NUEVO), `routes/web.php`, `resources/views/layouts/sidebar.blade.php`,
  `PROGRESS.md`, `AUTH-RBAC-PLAN.md`, `RECIPE.md`, `SECURITY.md`.
- **Riesgo/Notas:** Aditivo / **err-restrictivo** (gate `super-admin`-only por default; columna `super-admin` de solo lectura; guard
  anti-auto-bloqueo). Trade-off documentado: re-sembrar pisa la matriz viva (ver punto F). **Siguiente:** COVID-DECOMMISSION,
  continuar partiendo `AdminController`, y la formalización mayor de esquema de ROADMAP §3.5.

### 2026-06-25 — 🔘 Botones de catálogos gateados con @can('catalogs.manage') (cierra el 403 visible)
- **Estado:** Hecho (verificado por `Blade::compileString` + `php -l` limpio en las tres vistas (sin desbalance `@if/@can`) + render de
  `departamentocrud` en ambos roles + `view:cache`). **Cierra el polish que quedó pendiente tras la migración RBAC de las rutas de
  catálogos** (entrada 🗂️ CATÁLOGOS): los controles de gestión de las tres vistas legacy de CRUD de catálogos ahora se envuelven en
  **`@can('catalogs.manage')`**, así un rol con SOLO `catalogs.view` ya **NO** ve botones que 403ean al enviar (se cierra el
  "visible-pero-403").
- En cada vista se gatearon tres bloques: **(A)** la card superior de "crear" (el form que POSTea a `/creardepartamento` /
  `/crearpositions` / `/crearnotificacion`), **(B)** el encabezado de columna de acciones `<th>Acciones</th>` (en positions:
  "actions"), y **(C)** el `<td>` de acciones por fila (link de editar + controles de activar/desactivar). Las columnas de listado
  (id / name / correo) y la paginación **permanecen visibles** para un rol `catalogs.view`-only.
- **Resultado de UX:** un rol `catalogs.view`-only (coordinator/hod/safety-officer/auditor) ahora ve el **LISTADO** de catálogos pero
  no el form de crear / editar / activar / desactivar. Verificado: super-admin (`catalogs.manage`) ve crear + editar + la columna
  Acciones; `test.hod` (solo `catalogs.view`) **no ve NINGUNO** de ellos pero conserva el listado de departamentos.
- **Archivos:** `resources/views/admin/departamentocrud.blade.php`, `resources/views/admin/positionscrud.blade.php`,
  `resources/views/admin/notificacioncrud.blade.php`, `PROGRESS.md`, `AUTH-RBAC-PLAN.md`, `RECIPE.md`.
- **Riesgo/Notas:** Bajo / solo presentación (la autorización real ya la dan las rutas `permission:catalogs.manage`; esto solo oculta
  los controles a quien no puede usarlos). **Con esto los catálogos quedan totalmente pulidos.** **Siguiente:** COVID-DECOMMISSION,
  continuar partiendo `AdminController`, y la formalización mayor de esquema de ROADMAP §3.5.

### 2026-06-25 — 🔌 ALTA DE CREW CONECTADA AL CATÁLOGO (departments/positions → FK reales en el pivote)
- **Estado:** Hecho (verificado por `php -l` en `AdminController.php` + render del view en ambos estados + conteos del catálogo
  en `tinker` + resolución/rechazo de puesto cross-departamento + `view:cache`). El formulario de **alta de crew**
  (`admin/newuser.blade.php`) **se cableó por fin a las tablas normalizadas `departments` / `positions`** (que ya existían:
  35 departamentos / 196 puestos de catálogo). Antes el form usaba una **lista HARDCODEADA de 22 nombres** de departamento en un
  `<select name="zone">` + un input de texto libre `puestodepartamento`, y `AdminController@newuser` escribía `position_id=null`
  al pivote `production_user` → estaba **desconectado del catálogo nuevo**. Este cambio **conecta el flujo de alta** a esas
  tablas (sin migración nueva — las tablas ya existían).

- **A. 📥 `AdminController@adduser` — CARGA EL CATÁLOGO REAL.** Ahora pasa a la vista **`$departments`**
  (`Department::where activo`, ordenado) y **`$positions`** (`Position::whereNull('production_id')->where activo`, con
  `id`/`name`/`department_id` — el catálogo GLOBAL = `production_id IS NULL`), más **`$lockedDept`** (nombre) y **`$lockedDeptId`**.
  Para un **HOD** (sin `crew.view.all-departments`) el catálogo se **filtra a SOLO su propio departamento** + sus puestos. Se
  **eliminó** el helper privado `lockedDeptName()` (inlinado).

- **B. 🧩 Vista `resources/views/admin/newuser.blade.php` — SELECT de departamento + SELECT dependiente de puesto.**
  - El campo de departamento es ahora un **`<select name="department_id">`** poblado desde `$departments` (value = id del
    departamento); o, para un HOD, un **display readonly** + **`<input type="hidden" name="department_id" value="{lockedDeptId}">`**.
  - El campo de puesto es ahora un **`<select name="position_id" id="positionSelect">` dependiente**, llenado por un pequeño
    snippet **vanilla-JS** (`@push('scripts')`) desde un array **`@json($positions)`**, filtrado al departamento elegido (al
    `change` del select; o directamente al departamento fijo para un HOD). **El puesto es OPCIONAL.**

- **C. ✅ `AdminController@newuser` — RESUELVE CONTRA EL CATÁLOGO (no confía en el form) + ESCRIBE FK REALES.** Ahora lee
  **`department_id`** + **`position_id`** (ya **no** los strings `zone` / `puestodepartamento`). Los resuelve contra el CATÁLOGO y
  **NO confía en el form:** el departamento debe existir (`Department::find`); el puesto (opcional) debe pertenecer a ESE
  departamento (`Position::whereNull('production_id')->where activo->where department_id->find`) — un `position_id` de **otro
  departamento se RECHAZA** (resuelve a null). Para un **HOD** el departamento se sigue **forzando** al propio. Escribe **FK REALES**
  al pivote `production_user`: **`department_id` + `position_id`** (antes `position_id` era **siempre null**). Por compatibilidad
  con vistas legacy que aún leen estas columnas, mantiene **`zone`** = nombre del departamento (el perfil muestra `zone`) y fija
  **`puestodepartamento`** = `"Depto-Puesto"` (canónico) o solo el nombre del departamento si no se eligió puesto. Los nuevos
  usuarios siguen recibiendo el rol spatie `crew`; el mail sigue envuelto en try/catch; se conserva el PRG (redirect).

- **D. ✅ Verificación.** `php -l` limpio en `AdminController.php`; el view **RENDERIZA en ambos estados** (select libre de
  departamento para roles all-departments → **35 opciones**; HOD = campo fijo readonly, depto 19 "Locaciones" con **13 puestos**);
  conteos del catálogo **35 departamentos / 196 puestos**; un puesto válido resuelve OK y un puesto de un departamento DISTINTO es
  correctamente **RECHAZADO** (server-side); `view:cache` compila.

- **E. 🛡️ SEGUIMIENTO (2026-06-25) — UX/seguridad del campo de departamento: TRES estados según `$restricted`.** `adduser()`
  ahora pasa una bandera nueva **`$restricted`** (rol sin `crew.view.all-departments`, ej. HOD) y el campo de departamento en
  `newuser.blade.php` tiene **tres estados** en vez de caer al picker completo: **(1) restringido SIN departamento asignado** →
  alerta de aviso ("No tienes un departamento asignado; pide a un coordinador que te asigne uno…"), **NO** se muestra el picker y el
  botón de **alta queda DESHABILITADO** (alta bloqueada); **(2) restringido CON departamento** (HOD con depto) → campo readonly fijo
  a su depto + `department_id` oculto (submit habilitado); **(3) rol all-departments** (super-admin/coordinador/line-producer) →
  `<select>` del catálogo completo (submit habilitado). Antes, un HOD **sin** departamento caía al picker completo (confuso, podía
  capturar al departamento equivocado). Verificado renderizando los tres estados (bloqueado = aviso + botón deshabilitado + sin
  select; fijo y completo conservan el botón habilitado); `php -l` limpio; `view:cache` compila. Nota: `test.hod` no tiene
  departamento asignado, así que muestra el estado (seguro) bloqueado hasta que se le asigne uno vía `/rolescrud`.

- **Archivos:** `app/Http/Controllers/AdminController.php`, `resources/views/admin/newuser.blade.php`, `PROGRESS.md`, `RECIPE.md`,
  `DATABASE-SCHEMA.md`.
- **Riesgo/Notas:** Aditivo / **err-restrictivo** (un puesto cross-departamento se rechaza del lado del servidor; el departamento
  del HOD se fuerza). Este cambio **CONECTA el flujo de alta** a las tablas normalizadas que YA existían; **NO** es la formalización
  completa del esquema (migraciones para todas las tablas de dominio, renombrar `zone`/`age`/`daytest`, adelgazar el reporte médico
  a `medical_records`, FKs duras), que sigue siendo el trabajo mayor de **ROADMAP §3.5** a hacer en incrementos dedicados.

### 2026-06-25 — 🗂️ CATÁLOGOS → permission:catalogs.* (retira más del flag `admin`) + menú Catálogos
- **Estado:** Hecho (verificado por `php -l` en `web.php` + `route:list --json` + `view:cache` + acceso por rol en `tinker`).
  Continúa el retiro del flag legacy `admin`: las rutas de **catálogos** (departamentos / puestos / notificaciones) salieron del
  grupo `Route::group(['middleware'=>'admin'])` y se migraron a **dos grupos de permiso fino** (cada uno también con `auth`), bajo
  el mismo patrón strangler. Se añadió además una sección "Catálogos" al menú gateada por permiso. Con esto el flag `admin` queda
  **casi totalmente retirado** — lo que sobrevive en su grupo es esencialmente COVID (a borrar) + import/export + mail.

- **1. 🔐 RUTAS MIGRADAS (`routes/web.php`) — `admin` (binario) → dos grupos `permission:` (spatie, cada uno con `auth`):**
  - **`permission:catalogs.view`** → las tres rutas de listado/index: **`departamentocrud`**, **`positionscrud`**, **`notificacioncrud`**.
  - **`permission:catalogs.manage`** → todas las mutaciones + los GET de formulario de edición: **`creardepartamento`**,
    **`activardepartamento`**, **`desactivardepartamento`**, **`editardepartamento`**, **`savedepartamento`**, **`crearpositions`**,
    **`editpositions`**, **`saveposition`**, **`crearnotificacion`**, **`activarnotificacion`**, **`desactivarnotificacion`**,
    **`editarnotificacion`**, **`savenotificacion`**.
  - Las copias viejas se **removieron** del grupo `admin` (reemplazadas por una nota de una línea "migrados a catalogs.*"), y el
    comentario de cabecera del grupo `admin` se actualizó (catálogos ya no aparece listado ahí).

- **2. 🧭 SIDEBAR — nueva sección "Catálogos" (`resources/views/layouts/sidebar.blade.php`, desktop + espejo móvil).** Sección
  gateada por **`@can('catalogs.view')`** con tres links: **Departamentos** (ruta `departamentocrud`), **Puestos** (`positionscrud`)
  y **Notificaciones** (`notificacioncrud`).

- **3. ✅ Verificación.** `php -l` limpio en `web.php`; `route:list --json` muestra que las rutas de catálogos ahora llevan
  `permission:catalogs.view` / `permission:catalogs.manage` y **ya NO** `AdminMiddleware`; **sin nombres de ruta duplicados** por esta
  migración (el único dup preexistente es `encuesta` ×2 de `/dailyreport`, no relacionado); `view:cache` compila. Acceso por rol
  confirmado vía `tinker`: **`catalogs.view`** = super-admin, line-producer, coordinator, hod, safety-officer, auditor;
  **`catalogs.manage`** = super-admin, line-producer únicamente (medic y crew no tienen ninguno).

- **4. ⚠️ POLISH CONOCIDO (NO hecho — follow-up).** Las vistas legacy de los CRUD de catálogos (`admin/departamentocrud`,
  `positionscrud`, `notificacioncrud`) probablemente siguen renderizando los controles de crear/editar/activar **sin condicional**,
  así que un rol con SOLO `catalogs.view` (coordinator/hod/safety-officer/auditor) vería botones de gestión que **403ean al
  enviar** (RBAC correcto, pero los botones deberían envolverse en `@can('catalogs.manage')`). Refinamiento pequeño pendiente.

- **5. 🏛️ ESTADO DEL GRUPO `admin` TRAS ESTA MIGRACIÓN.** Ya casi vacío: contiene esencialmente las rutas de COVID/PCR/lab/queue
  (a eliminar con COVID-DECOMMISSION), import/export y mail/reminders — es decir, el flag `admin` está **casi totalmente retirado**;
  lo que queda es mayormente COVID (a borrar) + import/export + mail (estos dos podrían recibir sus propios permisos más adelante).

- **Archivos:** `routes/web.php`, `resources/views/layouts/sidebar.blade.php`, `PROGRESS.md`, `AUTH-RBAC-PLAN.md`, `RECIPE.md`.
- **Riesgo/Notas:** Aditivo/reversible (strangler). El acceso es **err-restrictivo** (las rutas exigen el permiso fino; sin él, 403).
  **Con esto los catálogos quedan migrados.** **Siguiente:** (a) envolver los botones de gestión de las vistas de catálogos en
  `@can('catalogs.manage')` (polish pequeño), y (b) la pasada mayor de **COVID-DECOMMISSION** (la mayor parte de lo que sobra en el
  grupo `admin` es COVID), y/o continuar partiendo `AdminController`.

### 2026-06-25 — 🔒 #2(b) HOD ACOTADO AL AGREGAR + pivote en alta + rol base
- **Estado:** Hecho (verificado por `php -l` + render del view en ambos estados + transacción de prueba en `tinker` (rollback) +
  `view:cache`). **#2(b) "AGREGAR ACOTADO":** un HOD ahora solo puede **agregar crew a su PROPIO departamento**, y el alta de crew
  por fin **escribe el pivote RBAC `production_user`** (cerrando el hueco que dejaba a los nuevos invisibles para las listas
  acotadas por depto). Con esto **#2 (scoping de departamento del HOD) queda COMPLETO** — (a) "ver acotado" (hecho antes esta
  sesión) **+** (b) "agregar acotado".

- **A. 🔒 #2(b) — FORZAR EL DEPTO DEL HOD AL ALTA + ESCRIBIR EL PIVOTE.**
  - **`AdminController@newuser` REESCRITO.** Determina el departamento del nuevo miembro: para un **HOD** (rol **SIN**
    `crew.view.all-departments`) el departamento se **FUERZA al del propio HOD** vía `$viewer->ownDepartmentIds()->first()` — el
    valor enviado en el formulario se **ignora** (enforcement del lado del servidor). Si el HOD **no** tiene departamento asignado,
    el alta se **rechaza** con un flash de error ("pide a un coordinador que te asigne uno"). Para roles **CON** all-departments
    (super-admin/coordinator/line-producer) el departamento viene del formulario y se resuelve/crea con `Department::firstOrCreate`
    (mismo patrón que `MapExistingUsersSeeder`).
  - **Cierra DOS huecos antiguos del alta:** (1) los nuevos usuarios ahora reciben un **rol spatie base** vía
    `$user->syncRoles(['crew'])` (antes `newuser` no asignaba ningún rol), y (2) **se ESCRIBE la fila del pivote `production_user`**
    (`department_id`, `role='crew'`, `is_lead=false`) — antes `newuser` solo escribía el string desnormalizado legacy
    `puestodepartamento` y nunca tocaba el pivote, así que el crew recién agregado quedaba **invisible** para las listas acotadas
    por departamento.
  - **Robustez del correo:** el correo de bienvenida (`Mail::send`) ahora va envuelto en **try/catch** → una falla de
    correo/SMTP ya **no** dispara un 500 después de que el usuario ya fue creado.
  - **PRG (POST-redirect-GET):** ahora retorna `redirect('/adduser')` con un flash de `status` de éxito (antes devolvía la vista
    directamente).
  - **`AdminController@adduser`** ahora pasa **`$lockedDept`** a la vista (el nombre del departamento del HOD, o `null` para roles
    all-departments); nuevo helper privado **`AdminController::lockedDeptName($viewer)`**.
  - **Vista `resources/views/admin/newuser.blade.php`:** se agregaron mensajes flash de `status`/`error` al inicio; el campo de
    departamento es ahora **condicional** — para un HOD renderiza un input **READONLY** fijado a su departamento (sin `name`, así no
    se envía; el servidor lo fuerza igual), en otro caso muestra el `<select name="zone">` completo de departamentos.

- **B. 🏷️ NOTA DE MODELO DE DATOS LEGACY (descubierta, NO arreglada ahora — para el cleanup futuro de esquema).** En este
  formulario de alta el campo `name="zone"` en realidad guarda el **DEPARTAMENTO** (no un zone/código de gafete),
  `puestodepartamento` guarda **solo el texto del puesto** (no "Depto-Puesto"), y `labn` (una columna COVID) se reusa como
  **"Jerarquía"**. Es más de la deuda conocida de "columnas reusadas con nombre equivocado" (como `age`=tiene-foto,
  `daytest`=rol). **La corrección de #2(b) NO depende de estas columnas legacy** — la fuente de verdad del scoping es el pivote
  `production_user.department_id`, que ahora se escribe correctamente.

- **C. ✅ Verificación.** `php -l` limpio en `AdminController.php`; el view de `newuser` **renderiza limpio en ambos estados** (select
  de departamento para roles all-departments, campo readonly fijo para un HOD) una vez compartido el `$errors` bag (las directivas
  `@error` son preexistentes); una **transacción de prueba con rollback** confirmó que tras asignar a `test.hod` el departamento 19,
  `$hod->ownDepartmentIds()` devuelve `[19]` y el HOD se identifica correctamente como dept-forzado; `view:cache` compila.

- **Archivos:** `app/Http/Controllers/AdminController.php`, `resources/views/admin/newuser.blade.php`, `PROGRESS.md`,
  `AUTH-RBAC-PLAN.md`, `RECIPE.md`, `DATABASE-SCHEMA.md`.
- **Riesgo/Notas:** Aditivo / **err-restrictivo** (un HOD sin departamento NO puede agregar — falla cerrado, nunca degrada a
  "agregar a cualquier depto"). Con #2(b) hecho, **#2 (scoping de departamento del HOD) queda COMPLETO** (ver + agregar). El
  pivote `production_user` ya se puebla en el alta → los nuevos aparecen en las listas acotadas. **Siguiente:** migrar las rutas
  `admin` restantes por vertical (catálogos → `catalogs.view`/`catalogs.manage`) + opcionalmente un selector de puesto / flujo
  coordinator de solo-departamento. Ver el "▶️ SIGUIENTE PASO INMEDIATO" actualizado.

### 2026-06-25 — 🔒 #2(a) HOD ACOTADO POR DEPARTAMENTO (ver) + helper único + .env fix
- **Estado:** Hecho (verificado por `tinker` + `php -l` + `view:cache`). **#2(a) "VER ACOTADO":** un HOD (que NO tiene
  `crew.view.all-departments`) ahora ve **solo el crew de su propio departamento** en la Crew List y pantallas hermanas, con la
  lógica de alcance extraída a una **única fuente de verdad** en el modelo `User`. Incluye un fix incidental de `.env` que
  rompía el bootstrap de `tinker`. **#2(b) "AGREGAR ACOTADO" queda PENDIENTE** (ver SIGUIENTE PASO).

- **A. 🔒 #2(a) — HOD DEPARTMENT SCOPING ("VER ACOTADO").**
  - **Fuente única de verdad en `app/Models/User.php`:** método de instancia **`ownDepartmentIds()`** (los `department_id` del
    usuario desde el pivote `production_user`) + helper estático **`applyDepartmentScope($query, User $viewer)`**. Comportamiento:
    viewer **CON** `crew.view.all-departments` → sin restricción; viewer **SIN** él pero con depto(s) conocido(s) → solo usuarios
    que comparten uno de sus departamentos (`whereExists` sobre `production_user`); viewer **SIN** él y **sin** depto determinable
    → **no devuelve NADA** (err-restrictivo, nunca degrada a "ver todo"). El helper acepta **tanto** `Eloquent\Builder`
    (`User::query()`) **como** `Query\Builder` (`DB::table('users')`) — `whereExists`/`whereRaw`/`whereColumn` existen en ambos y
    la tabla base es `users`.
  - **Aplicado en `AdminController`** a los cuatro métodos de listado de crew: **`usuarioscrud`** (Crew List), **`comcrud`**,
    **`idcardscrud`** (gafetes) y **`medicocrud`** → un HOD ve SOLO su departamento en todas esas pantallas.
  - **`SearchController@runSearch` REFACTORIZADO** para usar el MISMO `User::applyDepartmentScope()`, eliminando su bloque
    de alcance por depto previamente duplicado inline (DRY — una sola fuente compartida por la búsqueda en vivo y la Crew List).
  - **✅ Verificación (tinker):** (1) `test.hod` (aún sin depto en el pivote) → conteo acotado **0** (err-restrictivo); (2)
    super-admin → **92** (todos los activos); (3) un viewer restringido con departamento 19 → acotado **3** = exactamente los 3
    activos de ese departamento. `php -l` limpio en `User.php` / `AdminController.php` / `SearchController.php`; `view:cache` compila.

- **B. 🛠️ FIX `.env` (incidental, hallado al verificar).** El `.env` tenía pegado un fragmento de array PHP
  (`'gemini' => [ 'key' => env('GEMINI_API_KEY'), ],`) — sintaxis dotenv inválida que rompía el bootstrap de `php artisan tinker`
  ("Failed to parse dotenv file. Encountered unexpected whitespace"). Se reemplazó ese bloque de 3 líneas por una línea
  `GEMINI_API_KEY=` válida. **Nota para el registro:** si se va a cablear Gemini, el array `'gemini' => [...]` va en
  `config/services.php`, **no** en `.env`. (No se expuso ningún secreto en los docs.)

- **Archivos:** `app/Models/User.php`, `app/Http/Controllers/AdminController.php`, `app/Http/Controllers/SearchController.php`,
  `.env`, `PROGRESS.md`, `AUTH-RBAC-PLAN.md`, `RECIPE.md`, `SECURITY.md`.
- **Riesgo/Notas:** Aditivo / defensa en profundidad y **err-restrictivo** por diseño (nunca degrada a "ver todo"). El owner puede
  **probar (a) ya**: asignar a `test.hod` un departamento vía `/rolescrud` y luego ver la Crew List en incógnito → debería ver solo
  ese departamento. **PENDIENTE #2(b) "agregar acotado"** (forzar el depto del HOD en el alta de crew + escribir el pivote
  `production_user`): ver el SIGUIENTE PASO actualizado abajo.

### 2026-06-24 — 🧭 MENÚ AGRUPADO (#1) + 🗂️ PANTALLA ASIGNAR ROLES/DEPTO (#3)
- **Estado:** Hecho (verificado por `php -l` + `route:list --json` + `view:cache`; smoke test en BD OK).
  **PENDIENTE de verificación en vivo por el owner** (entrar a `/rolescrud` como super-admin/line-producer y
  asignar a `test.hod` un departamento). Dos verticales en una sesión: el menú plano por permiso se **agrupó en
  secciones** (#1) y se construyó la **pantalla de asignación de rol + departamento** (#3) que faltaba.

- **1. 🧭 MENÚ AGRUPADO (#1) — `resources/views/layouts/sidebar.blade.php` (desktop + espejo móvil).** El menú
  plano gateado ítem-por-ítem (de la entrada 🧑‍🤝‍🧑 ROLES VISIBLES) se **reorganizó en SECCIONES con encabezado**.
  Cada sección va envuelta en **`@canany([...])`** → el encabezado solo aparece si el usuario puede ver al menos un
  ítem debajo; cada ítem **sigue** con su `@can('<permiso>')` individual (mismo permiso que protege su ruta → sin
  visible-pero-403). Secciones y sus permisos:
  - **CREW:** Nuevo Miembro (`users.create`), Ver Crew List (`users.view`), Lista gafetes (`users.view`).
  - **LOCACIONES:** Scoutings (`locations.view`), Scouting (`locations.create`).
  - **SEGURIDAD:** Daily Reports (`dsr.view`), Nuevo Daily (`dsr.create`), Cond. Inseguras (`hazards.view`),
    Cond. Insegura (`hazards.create`), Acc. Inseguras (`hazards.view`), Acc. Insegura (`hazards.create`),
    Accidentes (`injury.view`), Accidente (`injury.create`).
  - **MÉDICO:** Consultas Médicas (`medical.view`).
  - **ADMIN:** Asignar Roles (`users.assign-role`).

- **2. 🗂️ PANTALLA DE ASIGNACIÓN DE ROL + DEPARTAMENTO (#3) — el vertical que faltaba (era el sub-paso c2).**
  - **Controller NUEVO `app/Http/Controllers/RoleAssignmentController.php`** (single-purpose, **NO** dentro de
    `AdminController`). `index()` lista los usuarios activos (eager-load de roles spatie para evitar N+1) con su rol
    y departamento actuales; `update($id)` valida y, dentro de una **transacción DB**, hace `syncRoles([$role])` +
    upsert del pivote `production_user` (`department_id`, `position_id`, `role`, `is_lead = role==='hod'`) para la
    única **"Producción Demo"**.
  - **Vista NUEVA `resources/views/admin/roles-assign.blade.php`:** tabla Bootstrap-5 con un `<select>` de **Rol** y
    uno de **Departamento** por fila (+ botón Guardar), usando el patrón **`form=`** (atributo HTML) para tener forms
    válidos dentro de la tabla. **Laravel-8-safe** (ternario `selected`, **no** `@selected`); paginada a 50 con
    `pagination::bootstrap-5`.
  - **Rutas (`routes/web.php`)** bajo `Route::middleware(['auth','permission:users.assign-role'])`:
    `GET /rolescrud` (`roles.index`), `POST /rolescrud/{id}` (`roles.update`). Verificado vía `route:list --json`:
    ambas llevan `permission:users.assign-role`.
  - **Sidebar:** sección ADMIN con el link "Asignar Roles" gateado `@can('users.assign-role')` → visible solo para
    **super-admin** + **line-producer**.
  - **🧱 DECISIONES DE DISEÑO (registradas):**
    1. **`super-admin` EXCLUIDO a propósito** de la lista de roles asignables (god-mode solo por seeder/manual, nunca
       por la UI → evita escalada). Asignables = line-producer, coordinator, hod, medic, safety-officer, crew, auditor.
    2. **SIN selector de producción** — el modelo de despliegue es **una copia independiente de la app por VPS de
       cliente**, así que una instancia = una producción ("Producción Demo").
    3. **v1 asigna ROL + DEPARTAMENTO únicamente** (sin selector de puesto todavía; al cambiar de departamento se
       limpia el `position_id` viejo porque pertenecía a otro departamento).
    4. `coordinator` tiene `users.assign-department` pero **NO** `users.assign-role`, así que esta v1 (gateada por
       `assign-role`) es para super-admin/line-producer; un **flujo coordinator de solo-departamento queda como
       refinamiento futuro**.

- **3. ✅ Verificación:** `php -l` limpio (`RoleAssignmentController`, `web.php`); `view:cache` compila; smoke test
  (Producción Demo id 1, **35 departamentos activos**, **92 usuarios activos**; `test.hod` hoy tiene rol `hod` pero
  **aún sin fila de pivote** → el owner le asignará un departamento por la pantalla para habilitar el scoping HOD #2).

- **Archivos:** `resources/views/layouts/sidebar.blade.php`, `app/Http/Controllers/RoleAssignmentController.php`
  (NUEVO), `resources/views/admin/roles-assign.blade.php` (NUEVO), `routes/web.php`, `PROGRESS.md`,
  `AUTH-RBAC-PLAN.md`, `RECIPE.md`, `ROADMAP.md`.
- **Riesgo/Notas:** Aditivo. Con la pantalla de asignación existiendo, **#2 (acotar la Crew List del HOD a su
  departamento) queda DESBLOQUEADO** — pero el owner debe **primero asignar a `test.hod` un departamento vía
  `/rolescrud`** para que haya fila en `production_user` que filtrar. `super-admin` queda fuera de los roles
  asignables por diseño (anti-escalada). El pivote se upserta en transacción.

### 2026-06-24 — 🎚️ MATRIZ DE PERMISOS DEL MENÚ — alineada a la imagen del owner
- **Estado:** Hecho (re-sembrado idempotente + verificado vía `tinker`). El owner aportó una **matriz
  permiso×rol del menú** (imagen) y se alineó la matriz rol→permiso del seeder a ella. **4 cambios de
  grant concretos** (aditivos, ningún permiso retirado):
  - `hod` += `users.create` — el HOD ahora puede **registrar crew de su área**. Conteo de permisos de
    `hod`: **14 → 15**.
  - `coordinator` += `locations.view` + `locations.create` — el Coordinador ahora **ve y crea Scoutings**.
    Conteo `coordinator`: **20 → 22**.
  - `line-producer` += `medical.view` — ahora puede **ver Consultas Médicas**. Conteo `line-producer`: **38 → 39**.
  - `safety-officer` += `medical.view` — ahora puede **ver Consultas Médicas**. Conteo `safety-officer`: **23 → 24**.
- **🔍 `auditor` SIN CAMBIOS** — el owner confirmó explícitamente **"solo-lectura completo"**: `auditor`
  conserva **todos** los permisos `.view` (incl. `medical.view`) → ve cada ítem de lectura del menú, sin
  crear nada. Conteo `auditor`: **12** (sin cambio).
- **Conteos por rol tras la alineación:** super-admin **42** · line-producer **39** · coordinator **22** ·
  hod **15** · medic **13** · safety-officer **24** · crew **5** · auditor **12** (los demás sin cambio).
- **Matriz final permiso×rol del menú** (✅=concedido · —=no), columnas
  super-admin/line-producer/coordinator/hod/medic/safety-officer/crew/auditor:
  - `users.create`:     ✅ ✅ ✅ ✅ — — — —
  - `users.view`:       ✅ ✅ ✅ ✅ — — — ✅
  - `medical.view`:     ✅ ✅ — — ✅ ✅ — ✅
  - `locations.view`:   ✅ ✅ ✅ — — ✅ — ✅
  - `locations.create`: ✅ ✅ ✅ — — ✅ — —
  - `dsr.view`:         ✅ ✅ ✅ ✅ — ✅ — ✅
  - `hazards.view`:     ✅ ✅ ✅ ✅ — ✅ — ✅
  - `hazards.create`:   ✅ ✅ — — — ✅ ✅ —
  - `injury.view`:      ✅ ✅ ✅ ✅ ✅ ✅ — ✅
  - `injury.create`:    ✅ ✅ — — ✅ ✅ ✅ —
- **Archivos:** `database/seeders/RolesAndPermissionsSeeder.php`, `PROGRESS.md`, `AUTH-RBAC-PLAN.md`,
  `ORG-TAXONOMY.md`.
- **Riesgo/Notas — 🔒 PRIVACIDAD:** `medical.view` **expone datos clínicos** (consultas / historial médico).
  Tras esta alineación queda concedido a `line-producer` y `safety-officer` (y `auditor` ya lo tenía) —
  **decisión explícita del owner**. El seeder es idempotente; re-correr es seguro. Verificado vía `tinker`
  que los conteos por rol coinciden con los de arriba.

### 2026-06-24 — 🧹 SEARCH — limpieza COVID + huérfanos (searchlab/searcheckpoint + 3 parciales)
- **Estado:** Hecho (verificado por `php -l` + `route:list` + `view:cache`). Limpieza de los restos del search:
  se retiran los 2 endpoints de búsqueda COVID y 3 parciales Blade huérfanos que quedaron tras la consolidación del
  `SearchController` por preset (ver entradas 🔍 SEARCH previas).
- **🗑️ ELIMINADO — 2 endpoints de búsqueda COVID (método + ruta + parcial):**
  - `AdminController@searchlab` (+ ruta `/searchlab`) y su vista `componentes/searchlab.blade.php`.
  - `AdminController@searcheckpoint` (+ ruta `/searcheckpoint`) y su vista `componentes/searcheckpoint.blade.php`.
- **🗑️ ELIMINADO — 3 parciales Blade huérfanos** (sin caller vivo; reemplazados por
  `componentes/search-results.blade.php` y `componentes/search-results-idcard.blade.php` en la consolidación previa):
  `componentes/searchusers.blade.php`, `componentes/searchidcard.blade.php`, `componentes/searchdoctor.blade.php`.
- **✅ SE QUEDA (vivo):** `componentes/searchqueue.blade.php` (`queueController@searchqueue`, COVID) — permanece hasta su
  propio decommission.
- **⏳ Páginas host COVID que llamaban a estos endpoints — siguen PENDIENTES de decommission COVID:**
  `resources/views/pcrtest/listcrew.blade.php` (llamaba a `/searchlab`) y `resources/views/admin/checkpoint.blade.php`
  (llamaba a `/searcheckpoint`). Sus páginas anfitrionas siguen existiendo y se retiran en el decommission COVID.
- **Archivos:** `app/Http/Controllers/AdminController.php` (2 métodos search borrados), `routes/web.php` (2 rutas borradas),
  `resources/views/componentes/` (5 parciales borrados: searchlab, searcheckpoint, searchusers, searchidcard, searchdoctor),
  `_legacy_backup/` (solo creció — todos los borrados respaldados con timestamp), `PROGRESS.md`, `COVID-DECOMMISSION.md`,
  `SEARCH-DOSSIER.md`, `VIEWS-INVENTORY.md`, `RECIPE.md`.
- **Riesgo/Notas:** Bajo. Todo lo borrado va respaldado en `_legacy_backup/` (regla del owner: nada del backup se borra hasta
  el cierre). Verificado: `php -l` limpio (AdminController, web.php); `route:list` muestra `searchlab`/`searcheckpoint` en 0;
  `view:cache` compila.

### 2026-06-24 — 🧑‍🤝‍🧑 ROLES VISIBLES — RBAC FINO (menú + rutas) + CUENTAS DE PRUEBA
- **Estado:** Hecho (verificado por `php -l` + `route:list --json` + `view:cache`/`view:clear`; seeders corridos OK);
  **PENDIENTE de verificación en vivo (incógnito) por el owner** de las 4 cuentas de prueba. **INCREMENTO 1 del vertical
  "Roles visibles":** se migran los verticales del menú del flag binario `admin` a **permisos finos `permission:` (spatie)**, se
  reconstruye el sidebar a `@can` ítem-por-ítem, y se crean 4 cuentas de prueba para probar RBAC puro en incógnito. Aditivo/
  reversible, con [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md). Cierra el search del lado idcard (ver punto 1).

- **1. 🗑️ MUERTOS BORRADOS — `AdminController@searchusers` y `@searchidcard`.** El motor `SearchController` ya los servía y el
  owner **verificó el idcard/gafetes en vivo esta sesión** → se borran los dos métodos viejos (cada uno reemplazado por un
  comentario `NOTA` de una línea). Con esto el search queda **totalmente cerrado**.

- **2. 🔐 RBAC FINO — rutas del menú migradas de `admin` (binario) a `permission:` (spatie).** `routes/web.php` reestructurado:
  los verticales **del menú** salieron del grupo `admin` a **grupos `permission:` por permiso** (cada uno con `auth`):
  - `users.view`: usuarioscrud, comcrud, idcardscrud, idcard/{id} — `users.create`: adduser, newuser —
    `users.update`: useredit, acountupdate, checkgft, uncheckgft, activarusuario, activarencuesta —
    `users.deactivate`: desactivarusuario — `users.assign-role`: activaradmin, desactivaradmin, putga, putgb, putgg —
    `users.assign-department`: selectpuesto.
  - `crew.view`: searchusers, searchidcard (SearchController). `medical.view`: medicocrud, historial —
    `medical.create`: consulta (cmedica.create), cmedica.store.
  - `locations.create`: location (form), locationreport.store, location2/create, location2/store —
    `locations.view`: locationcrud, location2/{id}, location/{id}.
  - `hazards.create`: hazardnotification (create), hazard-notifications (store), unsafenotifications/create + /store —
    `hazards.view`: unsafeacts (index), unsafeact/{id}, unsafeconds (index), unsafecond/{id}.
  - `dsr.create`: dsr-reports/create + (store) + /{id}/log — `dsr.update`: dsr-reports/{id}/update —
    `dsr.view`: dsr-reports (index), /{id}/pdf, /{id} (show).
  - `injury.create`: accident (create), accidentCreate (store) — `injury.view`: accidents (index), accident/{id}, users/search.
  - **Ordering preservado en código:** las rutas de segmento fijo (`/dsr-reports/create`, `/location2/create`) se declaran
    ANTES de sus `/{id}` para que el router no enlace `{id}="create"`.

- **3. 🏛️ STAYS en el grupo legacy `admin` (sigue exigiendo `admin=1`) — para el SIGUIENTE incremento / decommission COVID:**
  catálogos (departamentos/puestos/notificaciones → futuro `catalogs.*`), todo COVID/PCR/lab/queue (`searchlab`,
  `searcheckpoint`, `searchqueue`, `mainlab`, `pcr*`, `positivepcr`, `negativepcr`, `negativeantg`, `virtualqueue`…),
  import/export y mail/reminders.

- **4. 🛠️ FIX CRÍTICO hallado en verificación — `AdminController::__construct()` tenía un `$this->middleware('admin')` global**
  que forzaba `admin==1` en TODOS sus métodos → anulaba la migración per-ruta (un coordinador con `users.view` seguía
  bloqueado por `AdminMiddleware`). **Se RETIRÓ el lock del constructor** (comentario explicativo + constructor vacío). Seguro:
  cada método de `AdminController` queda cubierto por su nuevo grupo `permission:` (verticales migrados) o por el grupo `admin`
  restante (catálogos/COVID). Verificado con `route:list --json`: las rutas migradas muestran **solo** `PermissionMiddleware`
  (sin `AdminMiddleware`); las de catálogos/COVID siguen con `AdminMiddleware`.

- **5. 🧭 SIDEBAR reconstruido** (`resources/views/layouts/sidebar.blade.php`, desktop + espejo móvil): las 3 ramas
  mutuamente-excluyentes `@if/@elseif` keyeadas en el flag viejo `daytest` se reemplazan por una **lista PLANA** donde cada ítem
  va gateado por su propio `@can('<permiso>')`, usando el **mismo permiso que protege su ruta** → sin ítems visibles-pero-403.
  El flag `daytest` queda **retirado de las decisiones de menú**.

- **6. 🧪 CUENTAS DE PRUEBA — nuevo seeder `database/seeders/TestAccountsSeeder.php`** (registrado en `DatabaseSeeder.php`, corrido
  OK). Crea 4 usuarios **`admin=0` a propósito** (prueban RBAC puro, no el bypass de admin), idempotente (`updateOrCreate` por
  email + `syncRoles`), contraseña `Test1234!`:
  - `test.coordinador@crewcare.test` → `coordinator` · `test.hod@crewcare.test` → `hod` ·
    `test.medico@crewcare.test` → `medic` · `test.crew@crewcare.test` → `crew`.
  - Permisos resueltos verificados (tinker): coordinator = users.view/create, dsr.view, hazards.view, injury.view;
    hod = users.view, dsr.view, hazards.view, injury.view; medic = medical.view, injury.view/create;
    crew = hazards.create, injury.create.

- **7. ✅ Verificación:** `php -l` limpio en `web.php` / `AdminController` / `TestAccountsSeeder`; `route:list --json` confirma el
  split de middleware (migradas = solo Permission; catálogos/COVID = Admin); `view:cache` compila; `route:clear` corrido.
  Backups (`web.php`, `sidebar.blade.php`, `AdminController.php`) con timestamp en `_legacy_backup/` (regla: nada de
  `_legacy_backup/` se borra hasta el cierre del proyecto).

- **8. ⚠️ CAVEAT a tener presente:** los ~86 usuarios sembrados como `crew` verán un **sidebar casi vacío** (crew solo tiene
  `hazards.create`/`injury.create` + perfil) porque el fallback de menú por `daytest` ya no existe. Es lo **esperado/correcto** en
  esta base de prueba offline; el staff real necesita asignación de rol adecuada — que es el SIGUIENTE sub-paso (la UI de
  asignación, aún pendiente). La cuenta super-admin del owner no se ve afectada (42 permisos → ve todo; sigue `admin=1` → las
  rutas de catálogos/COVID también funcionan).

- **Archivos:** `routes/web.php` (reestructurado a grupos `permission:`), `app/Http/Controllers/AdminController.php`
  (constructor sin lock + 2 métodos search borrados), `resources/views/layouts/sidebar.blade.php` (reconstruido a `@can`
  ítem-por-ítem), `database/seeders/TestAccountsSeeder.php` (NUEVO), `database/seeders/DatabaseSeeder.php` (registro),
  `_legacy_backup/` (solo creció), `PROGRESS.md`, `ROADMAP.md`, `AUTH-RBAC-PLAN.md`, `RECIPE.md`.
- **Riesgo/Notas:** Aditivo/reversible. Lo viejo (`admin`/`daytest`) permanece para catálogos/COVID hasta el siguiente
  incremento (strangler). **PENDIENTE owner (en vivo, incógnito):** entrar como cada una de las 4 cuentas y confirmar que cada
  una ve su menú distinto y que sus links funcionan (sin 403); confirmar que el super-admin del owner sigue viendo el menú
  completo y todos los CRUD.

### 2026-06-24 — 🔍 SEARCH GLOBAL CERRADO + 🧑‍🤝‍🧑 ROLES (estado/dirección)
- **Estado:** Hecho (consolidación del search, compila/verificado por `php -l` + `route:list` + `view:cache`);
  **PENDIENTE de verificación en vivo del idcard/gafetes por el owner** + diagnóstico/dirección de ROLES registrado. Cierra la
  consolidación del SearchController (motor por preset) y fija el SIGUIENTE VERTICAL (hacer los roles visibles/probables).

- **1. 🔍 SEARCH GLOBAL — CONSOLIDACIÓN CERRADA.** `SearchController` es ahora un **motor parametrizado por preset** con un helper
  protegido `runSearch(array $preset, Request, $valor)` que centraliza TODA la lógica segura: `abort_unless(can('crew.view'))`,
  Eloquent + `select()` explícito (sin `SELECT *`; `$hidden` quita el hash), filtro `OR` agrupado en closure (arregla el bug de
  precedencia), alcance por departamento (propio si NO tiene `crew.view.all-departments`; **default RESTRICTIVO** vía pivote
  `production_user.department_id`), sentinela `"vacio"`, paginación 50, flags de visibilidad (`crew.view.contact`/
  `crew.view.personal`/`medical.view`).
  - **`users(Request,$valor)`** → preset users → parcial `componentes/search-results.blade.php`. **Salida idéntica a la versión ya
    verificada en vivo.**
  - **`idcards(Request,$valor)`** → preset idcard → **NUEVO parcial `componentes/search-results-idcard.blade.php`** (columnas:
    Zone badge, Foto, F.Name, L.Name, L.Name2, link de gafete, check/uncheck Printed, tinte verde si `age==1`). **Columnas COVID
    (`labn`) ELIMINADAS del idcard** (solo estaban en el filtro, no se renderizaban).
  - **Ruta `/searchidcard/{valor}` re-apuntada** a `SearchController@idcards` (mismo nombre/grupo `admin`). Backups con timestamp
    en `_legacy_backup/`.
  - **🗑️ MUERTOS BORRADOS:** `AdminController@searchcom` + `@searchdoctor` (métodos) **y** sus rutas `/searchcom`, `/searchdoctor`
    (grep confirmó cero callers).
  - **DEJADOS (strangler, se borran tras verificación en vivo del idcard):** `AdminController@searchusers` y `@searchidcard`
    (los viejos métodos, ya sin uso porque las rutas apuntan al `SearchController`).
  - **COVID intactos para decommission:** `searchlab`, `searchqueue`, `searcheckpoint` (NO entran al motor; se decomisionan con
    COVID-DECOMMISSION).
  - **✅ Verificado:** `php -l` (3 archivos), `route:list` (`/searchusers`→`SearchController@users`, `/searchidcard`→
    `SearchController@idcards`; `/searchcom` y `/searchdoctor` ya **NO** existen; lab/queue/checkpoint presentes), `view:cache`
    compila. **Sin regresión para admins.**
  - **PENDIENTE owner:** verificación en vivo del idcard/gafetes (buscar como admin = idéntico; Network sin hash). El
    `/searchusers` ya lo verificó OK. **Tras OK del idcard → borrar los viejos `AdminController@searchusers`/`@searchidcard`.**
    (Opcional futuro: helper JS con debounce + paginación AJAX real; presets de `reportes`/`locaciones` cuando esos módulos se pulan.)

- **2. 🧑‍🤝‍🧑 ROLES — estado y dirección (insight de esta sesión).** El owner intentó probar distintos roles en incógnito y NO
  encontró cómo, y su menú no cambió. Aclaraciones registradas:
  - **NO existe UI para asignar roles todavía** — el backend RBAC está, pero la pantalla para designar roles se construye en el
    vertical de usuarios/RBAC. Hoy los roles solo se **sembraron** (2 admin = `super-admin`, 86 = `crew`).
  - El menú del owner **no cambia** porque (a) es `super-admin` (ve todo por diseño) y (b) el menú está solo **PARCIALMENTE**
    cableado a permisos (a nivel de bloque `users.view`/`dsr.view`/`medical.view`, con el flag viejo `admin` como respaldo `OR`),
    **NO ítem por ítem**. Por eso las diferencias por rol hoy son toscas ("ve el sidebar o no").
  - **Lo que el owner QUIERE (y se recomendó como SIGUIENTE VERTICAL):** migrar **menú + rutas a RBAC fino** (cada rol ve su
    propio menú/pantallas, ítem por ítem, retirando el flag `admin` por vertical) + crear **cuentas de prueba**
    (`test.coordinador@…`, `test.hod@…`, `test.medico@…`, `test.crew@…`) con contraseña conocida y su rol, para probar en
    incógnito; y (después) la **pantalla de asignación de roles**. El owner eligió cerrar el search PRIMERO; tras eso, ESTE es el
    siguiente foco.

- **Archivos:** `app/Http/Controllers/SearchController.php` (motor por preset + `idcards()`),
  `resources/views/componentes/search-results-idcard.blade.php` (NUEVO), `app/Http/Controllers/AdminController.php`
  (borrados `searchcom`/`searchdoctor`), `routes/web.php` (alias `/searchidcard` + rutas muertas removidas; backup),
  `_legacy_backup/` (solo creció), `PROGRESS.md`, `RECIPE.md`, `SEARCH-DOSSIER.md`, `AUTH-RBAC-PLAN.md`.
- **Riesgo/Notas:** Aditivo/reversible. Lo que se borró (`searchcom`/`searchdoctor`) estaba respaldado y sin callers. Lo viejo
  (`searchusers`/`searchidcard` métodos) se conserva hasta el OK del idcard. Va con [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md).

### 2026-06-24 — 🔍 SEARCH UNIFICADO — INCREMENTO 1 (controller + permisos + alias)
- **Estado:** Hecho (compila/verificado por `php -l` + `route:list` + `view:cache`); **PENDIENTE de verificación en vivo por el
  owner**. PRIMER incremento real del SearchController unificado bajo [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) (strangler,
  aditivo/reversible). Construye lo nuevo, re-apunta SOLO `/searchusers`, y **deja todo lo viejo en su lugar**.

- **1. 🔐 Permisos (seeder `RolesAndPermissionsSeeder.php`, re-ejecutado, idempotente): +3 → 42 totales.** Nuevos:
  `crew.view.contact` (teléfono/email), `crew.view.personal` (fecha-nac/sexo), `crew.view.all-departments` (alcance; **su
  ausencia = solo el propio departamento**). Matriz: line-producer/coordinator/safety-officer **+= contact + all-departments**;
  **hod += contact PERO sin all-departments (queda restringido a su depto)**; **medic += `crew.view`, `crew.view.contact`,
  `crew.view.personal`, `crew.view.all-departments`, `injury.view`, `injury.create` → ahora puede REPORTAR ACCIDENTES y buscar
  (requisito del owner)**; auditor += all-departments (sin PII). Conteos por rol: super-admin 42, line-producer 38,
  safety-officer 23, coordinator 20, hod 14, medic 13, auditor 12, crew 5.

- **2. 🧩 `SearchController@users` (NUEVO — `app/Http/Controllers/SearchController.php`).** Autoriza con `can('crew.view')`;
  consulta con **Eloquent User + `select()` explícito** (sin `SELECT *` → `$hidden` aplica, **el hash de password ya NO viaja en
  el payload**); filtro `OR` **agrupado en closure** (arregla el bug de precedencia); **alcance:** si el rol NO tiene
  `crew.view.all-departments` se restringe a su propio departamento por el pivote `production_user.department_id` (**default
  RESTRICTIVO:** si no se determina el depto, no devuelve nada — no filtra otros deptos). Visibilidad de campos por permiso
  (`$canContact`/`$canPersonal`/`$canMedical`). Paginación y sentinela `"vacio"` preservados. Sin columnas COVID.
  **Flag:** la precisión del alcance depende de completar el backfill (75/88 hoy).

- **3. 🧱 Parcial reutilizable `resources/views/componentes/search-results.blade.php`.** Replica las columnas del `searchusers`
  actual, **arregla el `<tr>` faltante**, dedupe badges/paginación, y renderiza contacto/personal/clínico **solo según los flags
  de permiso**. Para un buscador con todos los permisos (super-admin/admin) las columnas son **idénticas a hoy → sin regresión**.

- **4. 🔀 Ruta `/searchusers/{valor}` re-apuntada (STRANGLER)** de `AdminController@searchusers` a `SearchController@users` —
  **mismo nombre (`searchusers`), mismo grupo `admin`** (la protección de ruta NO se cambió aún). `AdminController@searchusers`
  **se DEJA en su lugar** (se borra cuando todos los CRUD migren). Las demás rutas search (`searchidcard`/`searchlab`/
  `searcheckpoint`/`searchqueue`, los muertos `searchcom`/`searchdoctor`) **sin tocar**. Backup de `routes/web.php`.

- **5. ✅ Verificado:** `php -l` (controller + web.php), `route:list` (`searchusers`→`SearchController@users`, nombre+grupo
  intactos), `view:cache` compila. **Sin regresión para admins.**

- **PENDIENTE owner:** verificación en vivo (buscar en `/usuarioscrud` como admin = idéntico; pestaña Network del `/searchusers`
  = sin hash de password). **Tras OK:** migrar `comcrud`/`medicocrud` (ya pegan a `/searchusers`, deberían heredar) + los demás
  endpoints search CRUD por CRUD, y al final **borrar los métodos search muertos de `AdminController`**.
- **Archivos:** `database/seeders/RolesAndPermissionsSeeder.php`, `app/Http/Controllers/SearchController.php` (NUEVO),
  `resources/views/componentes/search-results.blade.php` (NUEVO), `routes/web.php` (+backup), `PROGRESS.md`, `RECIPE.md`,
  `SECURITY.md`, `SEARCH-DOSSIER.md`.
- **Riesgo/Notas:** Aditivo/reversible. Lo viejo intacto (alias strangler). El alcance por depto es restrictivo por diseño;
  su precisión sube al cerrar el backfill (13 títulos NULL diferidos).

### 2026-06-24 — 🔍 SEARCH DESCIFRADO + 📧 CORREOS CONFIRMADOS (se quedan)
- **Estado:** Hecho (descifrado/diseño = documentación; **el build del SearchController unificado queda PENDIENTE del go-ahead
  del owner**). Dos ítems: el patrón de búsqueda quedó descifrado en un dossier, y la decisión de correos quedó confirmada.

- **1. 📧 CORREOS — decisión del owner CONFIRMADA: se quedan TODAS.** Las plantillas `correos/*` se envían al registrarse y
  para eventualidades específicas. El owner cree que con certeza solo `nuevoingreso` funciona, pero **reutilizaban archivos y
  no está seguro** → **NO se borran**, se conservan todas. (Confirma el flag previo de "flagged — se quedan".)

- **2. 🔍 SEARCH — DESCIFRADO** (dossier creado: [SEARCH-DOSSIER.md](SEARCH-DOSSIER.md)). El owner pidió evaluar unificar/optimizar
  el patrón de búsqueda. Hallazgos:
  - **8 endpoints de búsqueda; 7 devuelven HTML para inyección AJAX** (6 en `AdminController`: `searchusers`, `searchcom`,
    `searchdoctor`, `searcheckpoint`, `searchidcard`, `searchlab`; 1 en `queueController`: `searchqueue`) + **1 moderno JSON
    aislado** en `InjuryReportController@searchUsers`. Todos bajo middleware `admin`.
  - **`searchcom` y `searchdoctor` son CÓDIGO MUERTO confirmado:** ningún Blade los llama; `comcrud`/`medicocrud` pegan a
    `/searchusers/`. (`searchcom` ≡ `searchusers`.) → **a borrar en la migración.**
  - **Seguridad:** los 7 endpoints HTML usan `DB::table('users')` + `SELECT *`, que **ignora el `$hidden` de Eloquent** → el
    hash `password` y campos clínicos viajan en cada fila del payload AJAX (hoy no se imprimen). Las vistas exponen
    email/fecha-nac/sexo/teléfono de toda la plantilla (cross-ref [SECURITY.md](SECURITY.md) M6).
  - **Bugs:** `OR` sin agrupar en `searchlab`/`searchqueue` rompe filtros; falta `<tr>` en `searchusers.blade.php:19` y
    `searcheckpoint.blade.php:18`; paginación `->links()` no cableada al AJAX. Restos COVID (`labn`/`resultpcr`/`daytest`/`tested`).
  - **Contrato AJAX:** `keyup` en `#search` → `fetch('/{endpoint}/'+valor+'/?page=1')` → `response.text()` (HTML parcial) →
    `$('#usertable').html(...)`; input vacío manda sentinela `"vacio"`.

- **3. 🧩 Diseño unificado PROPUESTO (pendiente del "arranca" del owner para construir):** un `SearchController` parametrizado
  por entidad/preset con `select()` explícito de columnas seguras (sin hash en payload), Eloquent + `$hidden`, **`OR` agrupado
  en closure** (arregla el bug), autorización por permiso (`can:users.search` o equivalente), un parcial reutilizable
  `search-results.blade.php` (arregla el HTML roto + el duplicado) y un helper JS `searchTable()` con debounce + paginación AJAX.
  **Migración STRANGLER:** construir lo nuevo sin tocar rutas → aliasar rutas legacy devolviendo el mismo HTML (preserva el
  contrato, cero regresión) → migrar CRUD por CRUD → borrar muertos (`searchcom`/`searchdoctor`) y lo viejo al final. **Faithful:**
  se mantienen las columnas visibles hoy (solo se deja de sobre-traer); **reducir la PII mostrada queda como decisión aparte
  marcada para el owner.**

- **Riesgo/Notas:** Cero cambios de código (descifrado + diseño = documentación). El build del SearchController NO se ha empezado
  — espera el go-ahead del owner. Cuando arranque, va con [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) (strangler, aditivo/reversible).

### 2026-06-24 — 🧹 LIMPIEZA DE CÓDIGO MUERTO — ✅ CERRADA (cierre de residuos)
- **Estado:** Hecho. **Cierre de la pasada de limpieza de código muerto:** se resolvieron los 4 residuos que RECIPE/VIEWS-INVENTORY
  tenían "abiertos". **+2 ELIMINADAS** (respaldadas en `_legacy_backup/resources/views/componentes/`, `view:cache` compila):
  - `resources/views/componentes/searchusers.bladeBKP.php` — archivo backup (`.bladeBKP.php`, no renderizable por Laravel).
  - `resources/views/componentes/searchcom.blade.php` — vista **HUÉRFANA**: su método `AdminController@searchcom` (ruta `/searchcom`)
    devuelve `view("componentes.searchusers")` (AdminController.php:403), **no** `searchcom` → nada la renderiza.
- **✅ CONSERVADAS (override del "REMOVE" de VIEWS-INVENTORY):** `auth/verify.blade.php` y `auth/passwords/confirm.blade.php` — están
  referenciadas por los traits de Laravel UI (`VerifiesEmails`→`view('auth.verify')`, `ConfirmsPasswords`→`view('auth.passwords.confirm')`).
  Aunque sus rutas estén deshabilitadas, borrarlas arriesga un 500 en flujos de auth por una ganancia mínima → se DEJAN; se reevaluarán
  en el vertical de `auth`.
- **🚩 BUG MARCADO para el owner:** `AdminController@searchcom` (ruta `/searchcom`) devuelve la vista de `searchusers` en vez de una
  propia. Funciona (no rompe), pero parece copy-paste: o `/searchcom` es redundante con `/searchusers`, o el método debería hacer algo
  distinto. Decisión del owner: eliminar la ruta/método redundante o corregirlo.
- **Total acumulado de la pasada de limpieza de código muerto: 16 ítems eliminados** (14 previos + estos 2). **La pasada queda CERRADA.**

### 2026-06-24 — 🧹 LIMPIEZA DE CÓDIGO MUERTO
- **Estado:** Hecho. Pasada de limpieza de código muerto a lo ancho del repo. **Todo respaldado en `_legacy_backup/` ANTES
  de borrar**, verificado, y **nada se borró de `_legacy_backup/`** (regla del owner: backups hasta el cierre del proyecto).

- **🗑️ ELIMINADO (comprobadamente muerto, cero referencias) — 14 ítems:**
  - **Métodos/rutas (2):**
    - `cropimageController@uploadCropImages` (con "s", **sin ruta**; el `uploadCropImage` real — singular — quedó **intacto**).
    - `PerfilController@update` + su **ruta `POST /update`** (name `perfil.update`) — **nada le hacía POST** (form 100% AJAX).
  - **Archivos leftover (3):** `resources/views/admin/idcard.bladeOLD.php`, `public/img/logo-cc-usrs - old.svg`,
    `public/img/nlogo25_old.png`.
  - **Vistas huérfanas (9):** `welcome.blade.php` (default de Laravel), `admin/checkpoint-mobile.blade.php`,
    `admin/injury_report_preview.blade.php`, `componentes/departamentousuarios.blade.php`,
    `componentes/historiawr.blade.php` (sus hermanas `historia`/`historiamr` **SÍ se usan**),
    `imports/uploadbulk.blade.php` (`imports.import` **SÍ se usa**), `modal/modelformulario.blade.php`,
    `selectdepartamentos.blade.php`.

- **🚩 FLAGGED — NO removido (revisión del owner) — 2 grupos que SE QUEDAN:**
  - **Vistas `correos/*`** (`CrewCareTemplate`, `avisosg`, `bienvenida`, `certificados`, `nuevoingreso`, `personalizado`,
    `pruebasentrada`, `testnotification`): **sin referencia estática**, PERO `queueController@enviarCorreoTest` hace
    `Mail::send($correo)` con **nombre de vista DINÁMICO** desde input del usuario → podrían usarse en runtime. Además son del
    **módulo de correos masivos a futuro** (editor tipo Mailchimp que el owner difirió). **Decisión: se quedan.**
  - **`admin/consultas.blade.php`:** su ruta está **COMENTADA** (deshabilitada a propósito) en `web.php` → **se deja** por si
    se reactiva.

- **✅ Verificación:** `php -l` OK en los controladores tocados; `route:list` limpio (`perfil`→GET `/profile`→`indexb` y
  `uploadCropImage`→POST `/crop-image-upload` **intactos**; `perfil.update` / POST `/update` **ya no existen**); `view:cache`
  **compila** (prueba que ninguna vista viva incluía las borradas). Estado final con `view:clear`.
- **Archivos:** `routes/web.php`, `app/Http/Controllers/PerfilController.php`, `app/Http/Controllers/cropimageController.php`,
  + las 12 vistas/archivos borrados, `_legacy_backup/` (solo creció), `RECIPE.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo riesgo. Con esto quedan **HECHOS** los 2 candidatos que el cierre de Profile había MARCADO
  (`uploadCropImages` y `PerfilController@update`). Los backups en `_legacy_backup/` se conservan hasta el final del proyecto.

### 2026-06-24 — ✅ VERTICAL PROFILE CERRADO (paso 5)
- **Estado:** Hecho. **SEGUNDO vertical CERRADO** (tras el Home) bajo [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md). El owner
  **verificó `/profile` en vivo**: cropper OK, y el bloque del cuestionario **ahora SE VE** (el bug de anidamiento corregido
  el 2026-06-24 quedó confirmado en navegador). Ejecutado el **PASO 5** (limpieza/retiro) del vertical.
- **Cambios del PASO 5:**
  - **Borrado el huérfano `resources/views/perfil.blade.php` + el método `PerfilController@index`** (ambos respaldados antes
    en `_legacy_backup/`).
  - **🔴 Ruta `/profile` DEDUPLICADA (lección):** las dos declaraciones **diferían en middleware** — una en grupo `admin`,
    otra en grupo `auth`. **Se conservó la de `auth`** (cualquier usuario autenticado ve su propio perfil; era el
    comportamiento vivo — en Laravel gana la última declarada) con su `->name('perfil')` **intacto** (lo usa el header). Se
    quitó la de `admin`. **De haberse quitado la otra se habría BLOQUEADO el perfil a los crew = regresión.**
  - **Quitado el `{{ method_field('post') }}` redundante** de `profile.blade.php` (form AJAX, POST plano; sin method spoofing).
- **🪦 Candidatos de código muerto MARCADOS (no removidos — para una pasada futura de limpieza de rutas/controladores):**
  (a) **`PerfilController@update`** (ruta `POST /update`, name `perfil.update`) — sin emisor vivo, **probable muerto**;
  (b) **`cropimageController@uploadCropImages`** (con "s") — **SIN ruta, comprobadamente muerto**.
- **🧩 Ruido de consola de la vista crew CONFIRMADO = privacidad de Firefox** (NO código nuestro). Persiste en **incógnito**
  (donde no hay extensiones) → es el **font-visibility / anti-fingerprinting de Firefox** bloqueando el font-stack por defecto
  de Bootstrap (Helvetica Neue / Noto Sans — fuentes que la app **no usa**). **Decisión del owner:** se deja como está;
  "silenciarlo" implicaría forzar la tipografía global (cambio visual) → se limpiará de forma natural en el **pulido tipográfico
  del rediseño final**.
- **Archivos:** `routes/web.php` (dedup de ruta), `resources/views/profile.blade.php` (quitado `method_field`),
  `app/Http/Controllers/PerfilController.php` (removido `@index`), `_legacy_backup/` (solo creció: +`perfil.blade.php`,
  +`PerfilController.php`, +`web.php`, +`profile.blade.php`; **nada borrado de ahí**), `RECIPE.md`, `PROGRESS.md`.
- **Verificado:** `php -l PerfilController` OK; `route:list` (`/profile`→`indexb`, nombre `perfil` presente); `view:cache`
  compila. `_legacy_backup/` **solo creció**, nada borrado (regla del owner: backups hasta el cierre del proyecto).
- **Riesgo/Notas:** Bajo riesgo. La dedup de ruta es el punto delicado (conservar `auth`, no `admin`). Con esto el vertical
  Profile queda **CERRADO** (su backup se conserva hasta el final del proyecto, como el del Home).

### 2026-06-24 — 🛠️ PROFILE: BUG DE ANIDAMIENTO CORREGIDO + ruido de consola = extensión
- **Estado:** Hecho (el fix de anidamiento) + diagnóstico del ruido de consola. Ajuste de método registrado
  (ver [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) paso 1). **Cambia el estado anterior:** el bug de anidamiento
  del `profile.blade.php` ya **NO** se "preserva" — el owner pidió explícitamente que estos bugs se **arreglen**.

- **1. 🛠️ BUG DE ANIDAMIENTO DEL `profile.blade.php` — CORREGIDO (ya NO preservado).** El bloque del cuestionario
  de salud (el "Hola… responda su cuestionario" + botón a `/dailyreport`) estaba **ATRAPADO dentro de
  `<div class="modal" id="modal">`** (`display:none`) por un `</div>` mal colocado → **nunca se mostraba** y el HTML
  quedaba **desbalanceado**. **Fix aplicado:** se cierra el modal correctamente (un `</div>` para `#modal`) y el
  bloque se **reubicó FUERA del modal**, en un `container > row > col-8`, **visible y balanceado**. **Copy original
  conservada verbatim** (incl. el "Gracias responder su cuestionario de salud" — typo de redacción NO tocado; queda
  como decisión menor del owner). **Verificado:** `php artisan view:cache` compila sin errores. Backup intacto en
  `_legacy_backup/`.
  - **NOTA / decisión pendiente del owner:** ese prompt de cuestionario **duplica** el que ya aparece en el Home
    (`inicio`). El owner debe decidir si lo deja visible en `/profile` o lo **elimina por redundante**.

- **2. 🧩 Mensajes de consola en la vista crew = navegador/extensión, NO código nuestro.** Los avisos "Request for
  font 'Helvetica Neue'/'Noto Sans' blocked at visibility level 2 (requires 3)" (source `utils.js:247`) y "El diseño
  fue forzado antes de que la página se cargara…" (source `node.js:416`) provienen de una **EXTENSIÓN del navegador**
  (anti-fingerprinting) que bloquea el font-stack por defecto de Bootstrap (Helvetica Neue/Noto Sans — fuentes que la
  app **ni siquiera referencia**; usamos Poppins/Roboto/Nunito/FA/Material). **No son archivos de la app** (la app usa
  `app.js`/`profile.js`/`inicio.js`). "Laravel PWA: ServiceWorker registration successful" es normal. **Verificación
  pendiente del owner:** abrir `/profile` en **ventana privada (sin extensiones)** → esos mensajes desaparecen.
  **Mejora futura ya anotada:** self-host de Font Awesome.

- **3. 🔧 AJUSTE AL MÉTODO (REBUILD-PROTOCOL paso 1 "Descifrar"):** cuando la reconstrucción detecte un **BUG claro**,
  se **PROPONE y se ARREGLA** (explicándolo), **no** se "preserva" en silencio. Preservar-tal-cual se reserva para
  comportamiento **genuinamente load-bearing**, y aun así se **marca + se propone el fix**. (El owner quiere corregir
  estos problemas, no arrastrarlos.) Registrado en [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) paso 1.

- **Archivos:** `resources/views/profile.blade.php` (fix de anidamiento), `REBUILD-PROTOCOL.md`, `PROGRESS.md`,
  `RECIPE.md`.
- **Riesgo/Notas:** Bajo riesgo. El fix es estructural (HTML balanceado) y aditivo respecto a la copy (verbatim).
  Sigue PENDIENTE la verificación en vivo del `profile` por el owner — **ahora el bloque del cuestionario debe VERSE**.

### 2026-06-24 — 🔧 PROFILE RECONSTRUIDO — PENDIENTE VERIFICACIÓN EN VIVO
- **Estado:** En progreso. **SEGUNDO artefacto reconstruido** bajo [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) (tras el Home):
  `profile.blade.php` **RECONSTRUIDO, compila OK** (`php artisan view:cache` exitoso), **PENDIENTE de verificación en vivo
  por el owner** (no confirmado en navegador todavía; **NADA viejo borrado** — todo aditivo/reversible, el retiro va en el
  paso 5).
- **Cambios:**
  - **`@extends('layouts.app')` como primera línea** (mata Quirks); estilos inline → `public/css/profile.css`; JS del cropper
    → `public/js/profile.js`.
  - **Modal del cropper PORTADO BS4→BS5:** `$modal.modal('show'/'hide')` → `bootstrap.Modal.getOrCreateInstance(el).show()/.hide()`;
    markup `class="close" data-dismiss` → `class="btn-close" data-bs-dismiss`. Se **quitó de la vista el stack BS4** (CSS/JS +
    Popper 1.x) y el **jQuery duplicado**. Cropper.js/css conservados vía `@push`.
  - **CSRF conservador:** se mantuvo el meta `_token`; la URL de subida se expone como `window.uploadCropImageUrl`
    (= `route('uploadCropImage')`).
  - **⚠️ Bug de anidamiento (orig. líneas 96-105) PRESERVADO tal cual** y marcado con comentario Blade
    (`⚠️ Posible bug de anidamiento... verificar contra producción antes de corregir`). **NO se "arregló"** (fiel-a-producción
    hasta confirmar contra el oráculo).
  - **`method_field('post')` redundante CONSERVADO** (flag para limpieza posterior; el form es AJAX pero `PerfilController@update`
    sigue ruteado).
  - **Backup:** `_legacy_backup/resources/views/profile.blade.php`. **NADA borrado de `_legacy_backup/`** (regla: backups hasta el cierre).
  - **Verificado:** `php artisan view:cache` compila sin errores.
- **Checklist de verificación en vivo (owner, navegador, usuario crew en `/profile`):**
  - (1) la card de perfil renderiza foto/logos/nombre/zona/puesto/edad;
  - (2) el cropper abre el modal BS5, recorta 1:1 y "Actualizar" sube vía AJAX, hace `alert` y recarga `/profile` con la foto nueva;
  - (3) "Cancelar" y la `btn-close` cierran el modal;
  - (4) el bloque del cuestionario se ve **IGUAL que en producción** (especialmente la zona del anidamiento marcado);
  - (5) `document.compatMode === 'CSS1Compat'` (sin Quirks);
  - (6) **cero errores de consola**.
- **PASO 5 del profile (DIFERIDO — tras verificación en vivo OK):** borrar el huérfano `resources/views/perfil.blade.php` +
  `PerfilController@index`; **deduplicar la ruta `/profile`** (`web.php:191` vs `:206`); **limpiar el `method_field('post')`
  redundante**. (El borrado de `_legacy_backup/` NO es parte del paso 5 — va al cierre del proyecto, regla del owner.)
- **Riesgo/Notas:** Todo aditivo y reversible. Nada viejo retirado todavía. El backup vive fuera del web root.

### 2026-06-24 — ✅ FIX FONT AWESOME (413 warnings) + 🔒 REGLA: BACKUPS HASTA EL FINAL
- **Estado:** Hecho (el fix de FA) + Decisión del owner registrada (la regla de backups). Dos ítems.

- **1. 🔒 REGLA NUEVA (owner) — NO borrar backups hasta el FINAL del proyecto.** `_legacy_backup/` (y cualquier
  respaldo) se **conservan hasta el "final final" de todo**. Esto **MODIFICA el paso 5 de
  [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md):** el paso 5 ("retirar lo viejo") **sigue** retirando el flag booleano y
  borrando huérfanos / código muerto del artefacto verificado, **PERO el borrado de la carpeta de backups ya NO es parte
  del paso 5 — se difiere al cierre del proyecto.** Consecuencia: el **vertical Home queda CERRADO** salvo ese
  borrado-de-backup, que ahora explícitamente va al final (ya NO es un "pendiente cercano").

- **2. ✅ FIX de las 413 advertencias de Font Awesome — HECHO.** Eran todas el warning cosmético
  **"Glyph bbox was incorrect; adjusting"** del **Font Awesome 6.0.0** por CDN (bug conocido de esa versión del woff2). FA
  se usa muchísimo (**194 iconos en 28 vistas**), así que se **subió la versión, no se quitó**.
  - **Subido 6.0.0 → 6.7.2** en los **3 lugares** donde se carga: `resources/views/layouts/app.blade.php`,
    `resources/views/auth/login.blade.php`, `resources/views/auth/passwords/email.blade.php`.
  - **Removido el atributo `integrity` (SRI)** de los 3 links: el hash no se podía verificar offline (la red corporativa
    bloquea el TLS saliente de las tools), y un SRI equivocado bloquearía el CSS y haría **desaparecer TODOS los iconos**.
    Se conservó `crossorigin`/`referrerpolicy`/`rel`. **Tradeoff menor de seguridad, divulgado.**
  - **Coexisten** (independientes, sin tocar): **Bootstrap Icons 1.8.1** y **Google Material Icons**.
  - **Pendiente:** **Ctrl+F5** del owner para confirmar que las 413 advertencias desaparecen y que los iconos siguen
    renderizando.
  - **🔮 Mejora futura recomendada (backlog):** **self-hostear Font Awesome** (descargar el release a `public/`) — elimina
    la dependencia del CDN (ideal para la red corporativa / offline) y permite **restaurar `integrity`** con un hash propio
    verificable.
- **Archivos:** `resources/views/layouts/app.blade.php`, `resources/views/auth/login.blade.php`,
  `resources/views/auth/passwords/email.blade.php`, `REBUILD-PROTOCOL.md`, `RECIPE.md`, `PROGRESS.md`.
- **Riesgo/Notas:** Bajo riesgo. El fix de FA requiere `Ctrl+F5` por caché. La regla de backups es scheduling: el borrado de
  `_legacy_backup/` deja de ser pendiente cercano y pasa a diferido al cierre.

### 2026-06-24 — ✅ PASO 5 HOME COMPLETADO + 📋 PERFIL/PROFILE DESCIFRADO
- **Estado:** Hecho. Cerrado el **PASO 5** de limpieza/retiro del vertical Home (el owner **verificó el cropper en vivo, OK**),
  y **descifrado** el siguiente vertical (`profile`/`perfil`) en un dossier dedicado.

- **1. PASO 5 del Home (limpieza/retiro) — HECHO.**
  - **Retirado el `admin ||`:** el switch admin/crew ahora es `@if(auth()->user()->can('users.view'))` **puro** (RBAC,
    cero cambio de comportamiento — muere el cinturón de transición).
  - **Eliminadas 6 vars muertas** de `HomeController@index` (`$totalUnsafeActs`, `$totalUnsafeConds`, `$unsafeActsMonthly`,
    `$unsafeCondsMonthly`, `$injuryReportsByMonth`, `$weeklyInjuryReports`) + el `use Illuminate\Support\Facades\DB;`
    huérfano. **Conservadas** todas las que la vista usa (las 6 cards + `$weeklyUnsafeReports` de la gráfica, que sigue
    presente — su remoción es parte del **rediseño diferido** al cierre).
  - **Borrado el huérfano** `resources/views/home.blade.php` (confirmado sin referencias; respaldado en `_legacy_backup/`).
  - **BS4: no se borró nada** — el único BS4 vivo está en `profile.blade.php` + `perfil.blade.php` (siguiente vertical),
    correctamente intacto.
  - **Verificado:** `php -l` OK, `view:cache` compila, `route:list` limpio.
  - **`_legacy_backup/` NO se borró** — se conserva hasta que el owner confirme con un **Ctrl+F5** que el Home sigue bien
    tras limpiar el controlador. (Pendiente cercano: borrarlo tras ese OK.)

- **2. PERFIL/PROFILE descifrado — dossier creado** ([PERFIL-DOSSIER.md](PERFIL-DOSSIER.md)):
  - **`profile.blade.php` es la vista VIVA** (`/profile` → `PerfilController@indexb` → `view('profile')`; el header
    enlaza ahí vía `route('perfil')`). **`perfil.blade.php` es HUÉRFANO** (`PerfilController@index`, sin ruta) →
    CONTAMINACIÓN borrable junto con ese método.
  - **Misma patología que el Home:** ~23 líneas de `<head>` filtrado antes de `@extends` (causa Quirks), BS4 sobre BS5, y
    el **modal del cropper depende de la API jQuery de BS4** (load-bearing; portar a BS5 como en el Home).
  - **CONTAMINACIÓN identificada:** 2ª carga de jQuery (dup del layout), `perfil.blade.php` + `PerfilController@index`,
    `method_field('post')` redundante (form 100% AJAX), probablemente `PerfilController@update` y
    `cropimageController@uploadCropImages` sin emisor.
  - **Sin gating `admin`/`daytest` que migrar** (solo `encuestadiaria`, que es estado).
  - **INCIERTO — verificar en vivo/contra producción antes de tocar:** (i) posible **bug de anidamiento** en
    `profile.blade.php:96-105` (bloque "Responder cuestionario" dentro del `<div class="modal">` con un `</div>` de más →
    podría quedar oculto); (ii) ruta **`/profile` declarada dos veces** (`web.php:191` y `:206`).

- **Riesgo/Notas:** Todo el PASO 5 es retiro de lo viejo tras verificación OK (el cinturón `viejo || @can` ya cumplió su
  función). Solo queda borrar `_legacy_backup/` tras el Ctrl+F5 final del owner. El dossier de perfil es documentación
  (cero cambios de código en `profile`/`perfil`).

### 2026-06-24 — ✅ FIX ICONOS HOME + BACKFILL 75/88
- **Estado:** Hecho. Dos ítems cerrados: el fix de iconos del Home y un avance del backfill de títulos (70→75).

- **1. Fix de iconos del Home — HECHO.** Se corrigieron los 4 `.fondo-svg*` en `public/css/inicio.css`
  (valores sin unidad → `px`: `top: 12` → `top: 12px`, etc.). Es el **fix fiel-a-producción** que restaura los
  iconos SVG de las cards que se perdieron tras la reconstrucción BS4→BS5. **Requiere `Ctrl+F5`** (caché /
  service worker) para verse. **NO es rediseño** (ese sigue diferido al cierre del proyecto).

- **2. Roadmap paso (b) — backfill de títulos: 70/88 → 75/88.** Se resolvieron los huecos de catálogo de **alta
  confianza** sin inventar mapeos dudosos:
  - **4 puestos nuevos al catálogo global** (`production_id = NULL`, `is_hod = false`): **Scouter** (Locaciones),
    **Bodeguero** (Decoración), **Coordinador de Utilería** (Utilería), **Sastre** (Vestuario). Positions: **192 → 196**.
  - **5 usuarios salieron de NULL** (Scouter ×2, Bodeguero Decoración, Coordinacion Utileria, Sastre Vestuario).
  - **🔴 Bug real corregido — colisión de nombre "Bodeguero"** (existía en Construcción y Decoración): el matcher se
    extendió con forma `"Puesto @ Departamento"` + **índice secundario `name|department`** en
    `BackfillUserPositionsSeeder.php` para desambiguar. Verificado.
  - **Idempotente** (re-corrido, estable en **196 puestos / 75 matched**). Comandos:
    `php artisan db:seed --class=OrgCatalogSeeder --force` + `php artisan db:seed --class=BackfillUserPositionsSeeder --force`.
  - **13 títulos siguen NULL, DIFERIDOS a decisión de campo del owner:** Cast ×2 (actores, no crew), COVID TEST
    MANAGER (artefacto legacy, descartar), Arte ×2 (nombre de depto), Sonido (depto), Supervisor (genérico),
    Segundo Asistente (ambiguo 2nd AD vs 2nd AC), Ambientador Vestuario (señales en conflicto), Secretario
    Produccion, Oficina Transpo, Peon, Limpieza (ambiguos). Recomendaciones por título quedaron en
    [ORG-TAXONOMY.md](ORG-TAXONOMY.md) §6.B.
- **Archivos:** `public/css/inicio.css` (fix iconos), `database/seeders/OrgCatalogSeeder.php`,
  `database/seeders/BackfillUserPositionsSeeder.php`, `ORG-TAXONOMY.md` (§6 reescrita en 6.A resueltos / 6.B diferidos),
  `PROGRESS.md`, `RECIPE.md`.
- **Pendiente owner-gated (sin cambios):** verificación en vivo del Home (cropper crew) → habilita el **PASO 5** del Home.
- **Riesgo/Notas:** Bajo riesgo. El backfill es aditivo/idempotente. El fix de iconos es de producción (1 línea ×4),
  requiere `Ctrl+F5` por caché/SW.

### 2026-06-24 — 🅿️ REDISEÑO DEL DASHBOARD DIFERIDO AL CIERRE
- **Estado:** Decidido por el owner (decisión de scheduling, no código). **El rediseño visual del dashboard del
  Home se DIFIERE "al final final final de todo"** — es de las **ÚLTIMAS** cosas del proyecto, **NO se aborda ahora**.
- **Qué queda PARKED para el cierre del proyecto** (todo junto, como entregable de pulido final):
  - **Rediseño gráfico** de las cards / iconografía del dashboard.
  - **Replantear qué métricas muestra:** datos de coordinación de producción + rodaje (días/hojas filmadas,
    call sheets, calendario) + H&S clave.
  - **Eliminar la gráfica "Tendencia Semanal de Reportes Inseguros".**
- **Contexto investigado (para cuando se retome) — real vs sin-datos:**
  - **Mitad H&S = cableable con datos REALES hoy:** `location_report`, `cmedic`, `injury_reports`,
    `hazardnotifications`/`unsafeconds`.
  - **Mitad producción/rodaje = SIN tablas ni datos:** días de rodaje, hojas filmadas, call sheets, calendario
    **no tienen respaldo** → requieren **crear tablas nuevas antes** (incl. `productions`).
  - El **"Días de rodaje (21)"** actual es un **literal hardcodeado** de abril-2025; **"Días de producción (432)"**
    es un **proxy** (días desde el primer `location_report`).
- **🔧 PENDIENTE separado (NO es rediseño — es fix fiel-a-producción):** los iconos SVG de las cards **desaparecieron**
  tras la reconstrucción del Home por un **bug de CSS sin unidades** (`top: 12` en vez de `top: 12px`) en
  `public/css/inicio.css`, expuesto al migrar BS4→BS5. Es un **fix de 1 línea**, **pendiente del visto bueno del owner**
  (esto NO se difiere; queda como pendiente cercano del cierre del vertical Home).
- **Riesgo/Notas:** Solo decisión/planeación. Cero cambios de código. El rediseño NO es el siguiente paso (ver
  "▶️ SIGUIENTE PASO INMEDIATO" ajustado más abajo); va al final del proyecto.

### 2026-06-24 — 🔧 HOME RECONSTRUIDO — PENDIENTE VERIFICACIÓN EN VIVO
- **Estado:** En progreso. **PRIMER artefacto reconstruido bajo [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md):**
  el Home (`resources/views/inicio.blade.php`). **RECONSTRUIDO, compila OK** (`php artisan view:cache` exitoso),
  **PENDIENTE de verificación en vivo por el owner** (no se ha confirmado en navegador todavía; **NADA viejo se
  ha borrado aún** — todo es aditivo/reversible, el retiro va en el paso 5).
- **Cambios:**
  - **Backups en `_legacy_backup/`** (en la raíz, **no web-served**): `inicio.blade.php`, `layouts/app.blade.php`,
    `home.blade.php`. Se elimina al final (paso 5) cuando todo quede OK.
  - **Layout `app.blade.php`:** agregados `@stack('styles')` (en el `<head>`) y `@stack('scripts')` (antes de
    `</body>`) — mecanismo para cargar assets **por página** (no en el shell de todas).
  - **Creados** `public/css/inicio.css` (estilos extraídos del inline) y `public/js/inicio.js` (lógica del cropper extraída).
  - **Modal del cropper PORTADO BS4→BS5:** `$modal.modal('show')` → `bootstrap.Modal.getOrCreateInstance(el).show()`;
    markup `class="close" data-dismiss` → `class="btn-close" data-bs-dismiss`. Esto permitió **quitar de la vista el
    stack BS4** (CSS/JS + Popper 1.x) y el **jQuery duplicado**.
  - **`inicio.blade.php` reescrito:** `@extends` como **primera línea** (mata Quirks mode); assets vía
    `@push('styles')`/`@push('scripts')` (inicio.css, inicio.js, Cropper.js+css, chartjs-plugin-trendline);
    sin vars muertas referenciadas.
  - **Switch admin/crew** ahora `@if(auth()->user()->admin || auth()->user()->can('users.view'))` — el `admin ||`
    es **cinturón de transición**, se retira en el paso 5 tras verificar.
  - **CSRF:** se mantuvo el meta `_token` original (decisión conservadora). La URL de subida se expone como
    `window.uploadCropImageUrl`.
  - **NO tocado:** `HomeController.php` (las vars muertas se limpian luego), el BS4 de otras vistas, y el
    `home.blade.php` huérfano (se borra en el paso 5).
- **Checklist de verificación en vivo (pendiente, lo hace el owner en navegador):**
  - (crew) el **modal del cropper** abre / recorta / sube / cierra bajo BS5 y se ve bien.
  - (admin) **dashboard idéntico**: 6 tarjetas + gráfica con trendlines.
  - `document.compatMode === 'CSS1Compat'` (ya **no** `BackCompat` / Quirks).
  - **cero errores de consola**.
  - que `window.uploadCropImageUrl` **resuelva el AJAX** de subida.
- **Paso 5 (tras verificación OK):** retirar el `admin ||`; borrar el `home.blade.php` huérfano + el BS4 muerto;
  limpiar las queries muertas de `HomeController`; eliminar `_legacy_backup/`.
- **Riesgo/Notas:** Todo aditivo y reversible. Nada viejo retirado todavía. El backup vive fuera del web root.

### 2026-06-24 — 🧱 PROTOCOLO DE RECONSTRUCCIÓN POR ARTEFACTO DEFINIDO
- **Estado:** Documentado (método, no código). Detalle completo en [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md).
- Se fijó el **método estándar** para rehacer cada view/model/controller: **reconstruir, no parchar** —
  arquitectura real que **preserva la función exacta**, resolviendo el riesgo de "parches que funcionan al
  momento pero se rompen al escalar".
- **Regla inviolable:** **descifrar antes de tocar** — separar **contaminación** (se elimina) de **load-bearing**
  (se conserva intencional y documentado); **producción/base local = el oráculo** del comportamiento.
- **5 pasos:** descifrar → definir objetivo → reconstruir bloqueando comportamiento → verificar contra el oráculo →
  retirar lo viejo. El flag booleano (`admin`/`daytest`) muere solo cuando su reemplazo RBAC está probado; el wrap
  `@if(viejo || @can)` es transición, no estado final. Es la ejecución concreta del estrangulamiento por verticales.

### 2026-06-24 — 🧭 DIRECCIÓN DE PRODUCTO DEFINIDA
- **Estado:** Decidido por el owner (estrategia, no código). Detalle completo en [VISION-PRODUCT.md](VISION-PRODUCT.md).
- El producto se dirige a **administración / coordinación de producción** para cine/TV LATAM (estilo
  Scenechronize, más accesible): contratos, firmas, formatos, calendarios/recordatorios de pago, guiones/call sheets/sides.
- **H&S = núcleo pero EMBEBIDO como capa** sobre los datos que el producto ya captura ("caballo de Troya": se vende
  administración, se entrega además H&S). **No se lidera con H&S:** en LATAM no se percibe como indispensable; la cuña es lo administrativo.
- **Moat:** experiencia de campo del owner + relación directa con Coordinación de Producción (define qué ofertar y en qué orden).
- **Método FIRME:** reconstrucción incremental tipo estrangulamiento sobre la fundación nueva (RBAC/producciones/departamentos),
  por verticales y explicando el código — **NO** reescritura desde cero. La fundación ya construida ES el backbone multi-tenant que esto necesita.
- **Cautelas:** firmas/pagos con peso legal-fiscal por país → empezar por gestión documental + ruteo + recordatorios; mover dinero y firma legal-grade van con proveedor especializado después.

### 2026-06-24 — ✅ BACKFILL + CABLEADO @can 🧩🔌
- **Estado:** Hecho. Dos sub-tareas del strangler, ambas aditivas y reversibles (nada viejo retirado).

- **TASK A — Backfill título→departamento/puesto.**
  - **Archivo CREADO:** `database/seeders/BackfillUserPositionsSeeder.php` (idempotente, encadenado en
    `DatabaseSeeder` después de `MapExistingUsersSeeder`). Correr: `php artisan db:seed --class=BackfillUserPositionsSeeder --force`.
  - **Resultado: 70/88 usuarios** mapeados con confianza a `position_id` + `department_id` en el pivote
    `production_user`; **18/88 quedaron NULL** (sin match confiable — no se inventaron vínculos). 88 usuarios
    tienen título; 79 títulos distintos. DB íntegra: **0 filas** donde `department_id` discrepe del departamento
    del puesto.
  - **Matcher:** mapa de alias explícito + normalización (trim, colapsar espacios, minúsculas, quitar acentos,
    quitar sufijos numéricos tipo "Carpintero 3"→carpintero). Solo resuelve contra el catálogo global
    (`production_id` NULL); **nunca crea puestos**.
  - **18 títulos NO mapeados** (candidatos a alias / agregar al catálogo): Arte, Cast, Scouter(x2), Sonido,
    Supervisor, Segundo Asistente, Ambientador Vestuario, Bodeguero Decoración, Coordinacion Utileria,
    Sastre Vestuario, Secretario Produccion, Oficina Transpo, Peon, Limpieza, COVID TEST MANAGER.
  - **Huecos del catálogo a considerar** (NO se insertaron — anotados en [ORG-TAXONOMY.md](ORG-TAXONOMY.md)):
    `Scouter` (location scout, Locaciones), un `Bodeguero` bajo Decoración, y un Coordinador bajo Utilería.
  - Solo escribió en `production_user`; no tocó `users`/columnas legacy; no corrió `migrate` global.

- **TASK B — Cableado `@can` en menú + aliases de middleware (aditivo).**
  - **🔴 Hallazgo clave:** el menú se controla por `auth()->user()->admin`, **NO** por `daytest`. En
    `layouts/app.blade.php:60` todo el sidebar se incluye solo `@if(admin)`, así que los 86 crew **nunca** ven
    el sidebar; solo los 2 admin. Dentro del sidebar, `daytest` ramifica el menú, pero todo admin actual tiene
    `daytest=0` → siempre rama 1. Resultado: salida **byte-idéntica** para todo usuario actual.
  - **Aliases spatie registrados en `app/Http/Kernel.php`** (`$routeMiddleware`): `role`, `permission`,
    `role_or_permission`. NO existían antes; se agregaron. NO se adjuntaron a ninguna ruta (las rutas se migran
    por vertical después). **🔴 Corrección de namespace:** en spatie v5.11 el namespace correcto es
    `\Spatie\Permission\Middlewares\...` (**PLURAL**), no singular.
  - **Mapa flag→permiso** (cada uno envuelto como `condicion-vieja || @can`, **nunca reemplazado**):
    - `app.blade.php:60` sidebar include: `admin` → `users.view`
    - `header.blade.php:9` mini-nav admin: `admin` → `users.view`
    - sidebar rama 1 (desktop+offcanvas): `daytest != 1 && != 2` → `users.view`
    - sidebar rama 2: `daytest == 1` → `dsr.view`
    - sidebar rama 3: `daytest == 2` → `medical.view`
  - **Archivos editados:** `app/Http/Kernel.php`, `resources/views/layouts/app.blade.php`,
    `resources/views/.../header.blade.php`, `resources/views/.../sidebar.blade.php` (6 condiciones: 3 desktop + 3 offcanvas).
  - **Sin regresión:** el wrap `viejo || @can` garantiza visibilidad idéntica; super-admin pasa todo por
    `Gate::before`; el crew carece de esos permisos y ni llega al sidebar. Verificado: `route:list` OK,
    `view:cache` compila, Quirks sin cambios, `AdminMiddleware` intacto.
  - **⚠️ Pendiente flagged (NO menú, sino CONTENIDO de página):** `home.blade.php:8` e `inicio.blade.php:67`
    tienen `@if(admin)` que cambian el CONTENIDO de la landing (panel admin vs cuestionario crew). Se dejaron
    para migrar en su vertical.

- **Riesgo/Notas:** Todo aditivo/reversible. Nada viejo retirado. `daytest`/`admin` permanecen.

### 2026-06-24 — ✅ FUNDACIÓN RBAC CONSTRUIDA Y VERIFICADA 🏗️✅
- **Estado:** Hecho y verificado contra la BD. Aditivo, sin romper nada existente (strangler).
- **Entorno:** PHP 7.4.19 (binario de Laragon), Laravel 8.83.1, BD `crewcare` MySQL. Spatie teams mode = **false**.
  `users.id` = `bigint(20) unsigned` (las FKs de las tablas nuevas coinciden — sin el problema de PK tinyint).
- **Archivos CREADOS:**
  - **Modelos:** `app/Models/Department.php`, `app/Models/Position.php`, `app/Models/Production.php`.
  - **Migraciones** (corridas una a una por `--path`, todas OK): `2026_06_24_180001_create_departments_table.php`,
    `2026_06_24_180002_create_productions_table.php`, `2026_06_24_180003_create_positions_table.php`,
    `2026_06_24_180004_create_production_user_table.php`.
  - **Seeders:** `database/seeders/RolesAndPermissionsSeeder.php`, `OrgCatalogSeeder.php`, `ProductionDemoSeeder.php`,
    `MapExistingUsersSeeder.php`.
- **Archivos MODIFICADOS:**
  - `app/Models/User.php` — añadido trait `HasRoles` + relación `productions()` belongsToMany (aditivo).
  - `app/Providers/AuthServiceProvider.php` — `Gate::before` para que `super-admin` pase toda verificación de permiso.
  - `database/seeders/DatabaseSeeder.php` — encadena los 4 seeders de la fundación.
- **✅ Verificado contra la BD:**
  - **8 roles:** `super-admin` (39 perms, 2 usuarios), `line-producer` (36), `coordinator` (18), `hod` (13),
    `medic` (7), `safety-officer` (20), `crew` (5 perms, 86 usuarios), `auditor` (11, todos `*.view` — solo lectura confirmada).
  - **39 permisos** (catálogo granular, incl. `crew.register`).
  - **35 departamentos**, **192 puestos** (todos `production_id = NULL` = catálogo plantilla global; 35 marcados `is_hod`).
  - **1 producción:** "Producción Demo" (id 1).
  - **Pivote `production_user`: 88 filas** (todos los usuarios atados). Mapeo: **2 super-admin** (`admin=1`) + **86 crew**.
  - **Sanity checks OK:** el usuario admin tiene `super-admin` y `can()` devuelve true incl. permisos futuros
    (confirma que `Gate::before` funciona); el usuario crew queda limitado; el `auditor` tiene 0 permisos no-`.view`.
- **🧱 DECISIONES DE DISEÑO (registradas):**
  1. **Rol por producción = columna `production_user.role` varchar(40)** (NO spatie teams, que están deshabilitados).
     El rol spatie global = "qué puede hacer esta persona en general"; `pivot.role` = "qué es esta persona en ESTE
     proyecto" (HOD aquí, crew allá). El pivote también lleva `department_id`, `position_id` (nullable), `is_lead`,
     y `unique(production_id, user_id)`.
  2. **`positions.production_id` nullable = el requisito de flexibilidad.** NULL = plantilla global reutilizable;
     no-NULL = puesto propio de una sola producción. `unique(department_id, production_id, name)`.
  3. **🔴 HALLAZGO / hueco conocido (por diseño):** el mapeo del string organizacional dejó dept/puesto **NULL para
     los 88 usuarios A PROPÓSITO.** [AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) C.2 asumía que `puestodepartamento` =
     "Departamento-Puesto" con separador `-`, pero los datos reales son **títulos planos** ("Line Producer", "APOC")
     **sin separador**. El seeder correctamente dejó dept/puesto NULL en vez de fabricar vínculos. La asignación de rol
     + atadura al pivote sí funcionó para los 88. El **backfill título→departamento es un paso FUTURO**, cuando exista
     una tabla de lookup.
- **Restricciones honradas:** nunca se corrió `php artisan migrate` global (todo por `--path`); NO se tocó
  `daytest`/`admin`/`age`/`puestodepartamento` ni ninguna tabla/columna existente (strangler/aditivo); la nueva
  autorización es **por permiso** (`@can` / `$user->can`), nunca por rol.
- **Riesgo/Notas:** Todo aditivo y reversible. `users`=88 intacta. Producción no afectada.

### 2026-06-24 — EJECUCIÓN Fase 1: arranca la fundación (spatie instalado + tablas RBAC) 🏗️
- **Estado:** En progreso. Primer paso de la reconstrucción hecho y verificado (aditivo, sin romper nada).
- **Contexto:** El owner autorizó arrancar la fundación. Verificado antes: **composer alcanza packagist (HTTP 200)**
  — no está bloqueado como npm/git — y la **DB está arriba** (88 usuarios). Confirmado al owner que esta base es
  **offline y aislada**: no afecta a las instancias de clientes en producción (copias separadas en sus VPS).
- **Hecho:**
  1. `composer require spatie/laravel-permission:^5.11` → instalado **5.11.1** (compatible Laravel 8). Paquete descubierto.
  2. `vendor:publish` de spatie → `config/permission.php` + migración `2026_06_24_172743_create_permission_tables.php`.
  3. Corrida **solo** esa migración por `--path` (aditivo) → 5 tablas RBAC creadas (`roles`, `permissions`,
     `model_has_roles`, `model_has_permissions`, `role_has_permissions`). `users` sigue en 88, intacta.
- **🔴 Hallazgo (drift de migraciones, confirmado):** `migrate:status` muestra `2014_10_12_000000_create_users_table`
  como **No corrida**, pero la tabla `users` **existe** → `php artisan migrate` a secas **fallaría** ("table already
  exists"). **Por eso NO se corre `migrate` global.** Reconciliar el drift es parte de la fundación (decidir: marcar la
  migración como corrida, o escribir el esquema real en migraciones nuevas). Las migraciones de spatie y futuras se
  corren por `--path` mientras tanto.
- **Archivos:** `composer.json`/`composer.lock` (spatie), `config/permission.php` (nuevo), `database/migrations/2026_06_24_172743_create_permission_tables.php` (nuevo), `PROGRESS.md`.
- **✅ ROLES CONFIRMADOS por el owner (2026-06-24):** se aprueba el set de **7 roles** estándar tal cual:
  `super-admin`, `line-producer`, `coordinator`, `hod`, `medic`, `safety-officer`, `crew` (nombres en inglés).
- **🧱 PRINCIPIO DE DISEÑO (confirmado con el owner):** los roles son **datos, no código** → se pueden **agregar más
  roles en cualquier momento** (insertar fila + asignar permisos), sin refactor ni cambio de esquema. Por eso la
  inversión va en un **catálogo de PERMISOS granular y bien nombrado**; cada rol es solo una combinación de permisos.
  **Regla obligatoria:** el código verifica **permisos, NO roles** (`@can('reports.create')`, nunca `@role('...')`),
  para que los roles queden 100% flexibles/reasignables sin tocar código. (Futuro Fase 2: UI para que el owner gestione
  roles/permisos sin desarrollador.)
- **✅ CALL SHEET PROCESADO (2026-06-24) → [ORG-TAXONOMY.md](ORG-TAXONOMY.md)** (vía subagente, sin PII). Extraídos
  **~28 departamentos** (agrupados por los 16 canales de radio de la producción) y **~150 puestos** canónicos. La BD actual
  solo cubría 8 deptos / 18 puestos (casi todos datos de prueba de Construction + placeholders `adm`/`developer` a descartar)
  → el catálogo de `departments`/`positions` se reconstruye casi entero desde el call sheet.
- **✅ VEREDICTO DE ROLES:** los ~200 puestos colapsan SIN residuo a los **7 roles** ya diseñados (los puestos son DATOS en
  `positions`, NO roles). El call sheet **no exige roles nuevos**. El **`auditor`** se mantiene como **8º rol OPCIONAL de
  plataforma/compliance** (no viene del organigrama; es la necesidad del estudio de revisar en lectura). **SET FINAL = 8 roles**
  (recomendado incluir auditor). Trampa: "Seguridad" (vigilancia HG) ≠ `safety-officer` (H&S).
- **🔧 REQUISITO FIRME (owner 2026-06-24) — NADA preestablecido por producción:** La estructura **no se hardcodea** a una
  sola producción. (a) **Roles = globales** (8), reutilizables en toda producción; NO derivan del call sheet. (b)
  **Departments/positions = catálogo PLANTILLA** que cada producción **adapta** (usa subconjunto + agrega propios; ej. `PA`
  genérico ≠ `Set PA` puede existir en una y no en otra) → `positions` modelado como catálogo reutilizable **+ puestos propios
  por producción**, no lista cerrada. (c) **Onboarding de crew = permiso** (`crew.register`), asignable por rol → un
  `coordinator`/PA puede **dar de alta a externos** (ej. Sustentabilidad) y registrar **por** departamentos que no se
  autogestionan (Grips/Electrics, "flojos para registrarse"). (d) Soportar crew **externo** (vendors/sustentabilidad). El seed
  del call sheet es PLANTILLA por defecto, no constraint. El build de la fundación DEBE respetar esto.
- **▶️ SIGUIENTE PASO INMEDIATO** — *(SELF-CONTAINED: este bloque debe permitir resumir SIN re-preguntar tras `/compact`.)*
  **Contexto ya HECHO Y VERIFICADO (no rehacer):** fundación RBAC sembrada (8 roles, 42 permisos); backfill 75/88; **DOS
  verticales CERRADOS — Home y Profile** (reconstruidos, verificados en vivo, limpiados paso 5); **SEARCH GLOBAL CERRADO**
  (motor por preset users+idcard; idcard/gafetes **verificado en vivo OK por el owner**; los métodos viejos
  `AdminController@searchusers`/`@searchidcard` ya **BORRADOS**); **ROLES VISIBLES — INCREMENTO 1 HECHO** (RBAC fino del menú +
  rutas + 4 cuentas de prueba; ver la entrada 🧑‍🤝‍🧑 ROLES VISIBLES al inicio del historial); **MENÚ AGRUPADO POR SECCIONES (#1)
  HECHO** (CREW/LOCACIONES/SEGURIDAD/MÉDICO/ADMIN, cada sección en `@canany`, ítems `@can`); **PANTALLA DE ASIGNACIÓN DE ROL +
  DEPARTAMENTO (#3) HECHA** (`RoleAssignmentController` + `admin/roles-assign.blade.php` + rutas `roles.index`/`roles.update`
  gated `permission:users.assign-role`; ver la entrada 🧭 MENÚ AGRUPADO + 🗂️ PANTALLA ASIGNAR ROLES al inicio del historial).
  Los backups en `_legacy_backup/` se conservan hasta el final del proyecto.

  **━━ (a) ✅ SEARCH IDCARD VERIFICADO — CERRADO ━━**
  El owner verificó en vivo el idcard/gafetes (idéntico al previo; Network sin hash). Tras ese OK se **borraron** los métodos
  viejos `AdminController@searchusers`/`@searchidcard` (respaldados en `_legacy_backup/`). El search es ahora un motor por preset
  en `app/Http/Controllers/SearchController.php` (helper `runSearch($preset,Request,$valor)`): Eloquent + `select()` explícito
  (sin hash en payload), `OR` agrupado en closure, alcance por depto restrictivo, sentinela `"vacio"`, paginación 50. **Sin
  pendientes en el search** (salvo opcionales de fondo, abajo).

  **━━ (b) ✅ ROLES VISIBLES — INCREMENTO 1 HECHO, AWAIT verificación en vivo del owner ━━**
  Hecho esta sesión (verificado `php -l` / `route:list --json` / `view:cache`; seeders OK): los verticales **del menú** migrados
  del grupo binario `admin` a grupos `permission:` (spatie, cada uno con `auth`) — users.{view,create,update,deactivate,
  assign-role,assign-department}, crew.view, medical.{view,create}, locations.{view,create}, hazards.{view,create},
  dsr.{view,create,update}, injury.{view,create}; **sidebar reconstruido** a `@can` ítem-por-ítem (mismo permiso que protege la
  ruta → sin visible-pero-403, `daytest` retirado del menú); **retirado el lock `admin` del constructor de `AdminController`**; y
  **4 cuentas de prueba** (`TestAccountsSeeder`, `admin=0`, password `Test1234!`): `test.coordinador@crewcare.test` (coordinator),
  `test.hod@crewcare.test` (hod), `test.medico@crewcare.test` (medic), `test.crew@crewcare.test` (crew).
  - **PENDIENTE owner (en vivo, incógnito):** entrar como cada una de las 4 cuentas → confirmar que cada una ve su menú distinto y
    que sus links funcionan **sin 403**; y que el super-admin del owner sigue viendo el menú completo + todos los CRUD.

  **━━ (b2) ✅ MENÚ AGRUPADO POR SECCIONES (#1) HECHO ━━**
  El menú plano por permiso se **reorganizó en SECCIONES con encabezado** (CREW, LOCACIONES, SEGURIDAD, MÉDICO, ADMIN), cada
  sección envuelta en **`@canany([...])`** (el encabezado solo aparece si hay al menos un ítem visible) y cada ítem **sigue** con
  su `@can`. Desktop + espejo móvil en `layouts/sidebar.blade.php`. Verificado `view:cache`.

  **━━ (b3) ✅ PANTALLA DE ASIGNACIÓN DE ROL + DEPARTAMENTO (#3) HECHA — el sub-paso c2 ya NO está pendiente ━━**
  Nuevo `RoleAssignmentController` (single-purpose) + vista `admin/roles-assign.blade.php` (tabla BS5, `<select>` Rol + Depto por
  fila, patrón `form=`, Laravel-8-safe). Rutas `GET /rolescrud` (`roles.index`) + `POST /rolescrud/{id}` (`roles.update`) bajo
  `permission:users.assign-role`. `update()` hace `syncRoles` + upsert del pivote `production_user` en transacción (una sola
  "Producción Demo"). **Decisiones:** super-admin EXCLUIDO de asignables (anti-escalada); sin selector de producción
  (una instancia = una producción); v1 = rol + departamento (sin puesto aún); flujo coordinator solo-departamento = futuro. Link
  ADMIN→"Asignar Roles" en el sidebar (`@can('users.assign-role')`). Verificado `php -l` / `route:list --json` / `view:cache`;
  smoke test (35 deptos / 92 usuarios activos; `test.hod` aún sin fila de pivote).

  **━━ (c) ✅ #2 HOD SCOPING COMPLETO (ver + agregar) + ✅ CATÁLOGOS → catalogs.* — SIGUIENTE: botones catalogs.manage / COVID-DECOMMISSION ━━**
  - **(c1) ✅ #2(a) "VER ACOTADO" — HECHO Y VERIFICADO (2026-06-25).** La Crew List (`/usuarioscrud`) + `comcrud` + `idcardscrud` +
    `medicocrud` ya se acotan al departamento del HOD vía el helper único **`User::applyDepartmentScope($query,$viewer)`** (+
    `User::ownDepartmentIds()`), err-restrictivo (sin depto determinable → no devuelve nada, nunca degrada a "ver todo"). El
    `SearchController@runSearch` se **refactorizó al mismo helper** (DRY). Verificado por tinker: super-admin 92, viewer del depto
    19 → 3, `test.hod` sin pivote → 0. Ver la entrada 🔒 #2(a) al inicio del historial. **Pre-requisito operativo para PROBARLO:**
    el owner debe **asignar a `test.hod` un departamento vía `/rolescrud`** (hoy `test.hod` tiene rol `hod` pero **sin fila en
    `production_user`** → nada que filtrar) y luego ver la Crew List en incógnito.
  - **(c1.b) ✅ #2(b) "AGREGAR ACOTADO" — HECHO Y VERIFICADO (2026-06-25).** `AdminController@newuser` reescrito: para un HOD el
    departamento del nuevo usuario se **fuerza** al del propio HOD vía `$viewer->ownDepartmentIds()->first()` (se ignora el valor
    enviado; HOD sin depto → alta rechazada con flash de error); para roles all-departments el depto viene del form
    (`Department::firstOrCreate`). **Cerró dos huecos del alta:** ahora se asigna rol base `$user->syncRoles(['crew'])` y se
    **escribe el pivote `production_user`** (`department_id`, `role='crew'`, `is_lead=false`) — antes `newuser` solo escribía el
    string legacy `puestodepartamento` y nunca tocaba el pivote, dejando al crew nuevo invisible para las listas acotadas. Mail de
    bienvenida envuelto en try/catch (ya no 500ea tras crear); PRG con `redirect('/adduser')` + flash de éxito. `adduser` pasa
    `$lockedDept` (helper `AdminController::lockedDeptName($viewer)`); la vista `admin/newuser.blade.php` muestra flashes y un campo
    de departamento condicional (readonly fijo para HOD / `<select name="zone">` para all-departments). Verificado: `php -l`, render
    en ambos estados, transacción rollback (`test.hod` con depto 19 → dept-forzado), `view:cache`. Ver la entrada 🔒 #2(b) al inicio
    del historial. **Con esto #2 (ver + agregar) queda COMPLETO.** *(Nota legacy registrada: en este form `zone`=Departamento,
    `puestodepartamento`=solo el puesto, `labn`=Jerarquía → deuda de columnas reusadas, NO bloquea #2; la verdad del scoping es el
    pivote.)*
  - **(c1.d) ✅ ALTA DE CREW CONECTADA AL CATÁLOGO — HECHO Y VERIFICADO (2026-06-25).** El form de alta
    (`admin/newuser.blade.php`) **ya está cableado a las tablas normalizadas `departments`/`positions`**: `adduser` carga el
    catálogo real (`$departments` + `$positions` global = `production_id IS NULL`, filtrado al depto del HOD si aplica); la vista usa
    un **`<select name="department_id">`** (o hidden fijo para HOD) + un **`<select name="position_id">` dependiente** llenado por
    JS desde `@json($positions)` (puesto OPCIONAL); `newuser` resuelve `department_id`/`position_id` **contra el catálogo** (no
    confía en el form: rechaza un puesto de otro departamento) y escribe **FK REALES** al pivote `production_user` (`department_id`
    + `position_id` — antes `position_id` era siempre null), conservando `zone`/`puestodepartamento` por compat legacy. Verificado:
    `php -l`, render en ambos estados (35 deptos / HOD depto 19 con 13 puestos), 35 departamentos / 196 puestos, rechazo
    cross-departamento, `view:cache`. Ver la entrada 🔌 ALTA DE CREW CONECTADA AL CATÁLOGO al inicio del historial. **NOTA DE
    ALCANCE:** esto CONECTA el alta a tablas que ya existían; NO es la formalización completa del esquema (sigue siendo ROADMAP
    §3.5 — ver (c2) opción (c)).
  - **(c1.c) ✅ CATÁLOGOS → `catalogs.*` — HECHO Y VERIFICADO (2026-06-25).** Las rutas de catálogos
    (departamentos/puestos/notificaciones) salieron del grupo `admin` a **dos grupos `permission:` (cada uno con `auth`)**:
    **`catalogs.view`** = los tres index (`departamentocrud`, `positionscrud`, `notificacioncrud`); **`catalogs.manage`** = todas las
    mutaciones + GET de edición (`creardepartamento`, `activardepartamento`, `desactivardepartamento`, `editardepartamento`,
    `savedepartamento`, `crearpositions`, `editpositions`, `saveposition`, `crearnotificacion`, `activarnotificacion`,
    `desactivarnotificacion`, `editarnotificacion`, `savenotificacion`). Se añadió una sección "Catálogos" al sidebar gateada
    `@can('catalogs.view')` (Departamentos / Puestos / Notificaciones). Verificado: `php -l`, `route:list --json` (rutas con
    `permission:catalogs.*`, sin `AdminMiddleware`, sin dups nuevos), `view:cache`; acceso por rol (tinker): `catalogs.view` =
    super-admin/line-producer/coordinator/hod/safety-officer/auditor, `catalogs.manage` = super-admin/line-producer. Ver la entrada
    🗂️ CATÁLOGOS al inicio del historial. **Con esto el flag `admin` queda casi totalmente retirado** (lo que sobra es COVID +
    import/export + mail).
  - **(c1.e) ✅ POLISH DE BOTONES DE CATÁLOGOS — HECHO Y VERIFICADO (2026-06-25).** Los controles de gestión de las tres vistas
    legacy de CRUD de catálogos (`admin/departamentocrud`, `positionscrud`, `notificacioncrud`) ya se envuelven en
    **`@can('catalogs.manage')`**: la card de crear, el `<th>` de acciones y el `<td>` de acciones por fila. Un rol con solo
    `catalogs.view` ahora ve el LISTADO pero NO los botones (cierra el "visible-pero-403"); las columnas de listado + paginación
    siguen visibles. Verificado: `Blade::compileString` + `php -l` (sin desbalance), render de `departamentocrud` (super-admin ve
    todo; `test.hod` no ve ninguno pero conserva el listado), `view:cache`. Ver la entrada 🔘 al inicio del historial. **Con esto los
    catálogos quedan TOTALMENTE pulidos.**
  - **(c2) ▶️ SIGUIENTE — opciones (cualquiera/ambas).** *(El alta de crew YA quedó cableada al catálogo — ver (c1.d); los botones de
    catálogos YA quedaron gateados — ver (c1.e).)*
    - **(a) COVID-DECOMMISSION:** la mayor parte de lo que queda en el grupo `admin` es COVID/PCR/lab/queue → se elimina (no se
      "protege": desaparece) siguiendo [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md). Import/export + mail/reminders podrían recibir
      sus propios permisos después. Y/o continuar **partiendo `AdminController`** (God Object) por recurso.
    - **(b) FORMALIZACIÓN DE ESQUEMA (ROADMAP §3.5) — vertical mayor candidato:** migraciones para todas las tablas de dominio,
      renombrar las columnas reusadas (`zone`/`age`/`daytest`/`labn`), adelgazar el reporte médico a `medical_records`, FKs duras.
      El alta de crew ya escribe `department_id`/`position_id` reales al pivote (primer paso concreto en esta dirección); la
      formalización completa es el trabajo grande, en incrementos dedicados.
  - **(c3) BARRIDO ARQUITECTÓNICO POR ARTEFACTO (transversal)** — pasada sistemática sobre CADA artefacto (vistas, modelos,
    controllers, CSS, JS) para arreglar su arquitectura, reconstruir el código sin bugs y quitar parches del pasado;
    **incremental por verticales** (strangler, nunca rewrite de golpe), explicando el código. NO es una fase aparte al final:
    cada vertical que tocamos limpia sus views/models/controllers/CSS/JS. Ver [ROADMAP.md](ROADMAP.md) §1.5 y
    [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md).

  **━━ PENDIENTES DE FONDO (no bloquean; mantener a la vista) ━━**
  - **13 títulos del backfill** NULL — DIFERIDOS a decisión de campo del owner (Cast ×2, COVID TEST MANAGER, Arte ×2, Sonido,
    Supervisor, Segundo Asistente, Ambientador Vestuario, Secretario Produccion, Oficina Transpo, Peon, Limpieza); ver §6.B de
    [ORG-TAXONOMY.md](ORG-TAXONOMY.md). **No re-mapear sin esa decisión** (evitar inventar mapeos dudosos).
  - **Helper JS del search (OPCIONAL):** `searchTable()` con debounce + paginación AJAX real (hoy el `keyup` dispara en cada
    tecla y la paginación del parcial inyectado no está cableada a AJAX).
  - **Presets de `reportes`/`locaciones`** para el motor de search — cuando esos módulos se pulan en su vertical.
  - **Pasada de limpieza de los métodos search viejos: ✅ HECHO** — `AdminController@searchusers`/`@searchidcard` ya **borrados**
    tras la verificación en vivo del idcard. *(La pasada general de código muerto YA se ejecutó: 16 ítems eliminados, respaldados
    en `_legacy_backup/`; `searchcom`/`searchdoctor` ya borrados en la consolidación. 2 grupos quedaron flagged y **se quedan**:
    `correos/*` por uso dinámico en runtime; `admin/consultas` con ruta comentada.)*

  **📧 Correos: decisión CONFIRMADA — se quedan TODAS** (`correos/*` se envían al registrarse / eventualidades; el owner no está
  seguro de cuáles funcionan porque reutilizaban archivos → no se borran).
  **🅿️ SCHEDULING:** el **rediseño visual del dashboard del Home NO es el siguiente paso** — el owner lo difirió al **cierre del
  proyecto** (cards/iconografía, replanteo de métricas producción/rodaje + H&S, eliminar la gráfica "Tendencia Semanal de
  Reportes Inseguros"; ver [RECIPE.md](RECIPE.md) Bloque 5). El borrado de `_legacy_backup/` también va al cierre (regla del
  owner). Todo va con [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md) (strangler, aditivo/reversible).

### 2026-06-24 — Decisión de estrategia: reconstruir, no parchar + plan de RBAC 🧭
- **Estado:** Hecho (decisión registrada + plan de fundación creado por subagente).
- **Decisión de estrategia (ROADMAP §3, decisión #5):** se adopta **reconstrucción por verticales
  (strangler), no parcheo en sitio** de lo estructural. Detonante: el incidente del Home (un "arreglo"
  cosmético rompió la página porque el bug era carga estructural). Reglas:
  1. Parchar en sitio SOLO seguridad peligrosa (C2/C3) y borrar código muerto/COVID.
  2. Lo estructural (vistas, frameworks, esquema) se RECONSTRUYE; lo viejo se borra por vertical al verificar.
  3. **Producción = oráculo:** comparar cada pantalla reconstruida contra la instancia viva antes de retirar la vieja.
  4. El arranque real de la reconstrucción = **fundación: migraciones + roles spatie + producciones** (Fase 1).
  5. No pulir código que se va a reemplazar (no perseguir Quirks/BS4-BS5/duplicación en lo viejo).
- **Archivos:** `AUTH-RBAC-PLAN.md` (NUEVO — plan de fundación/RBAC, vía subagente), `ROADMAP.md` (decisión #5),
  `CLAUDE.md` (regla + enlace), `PROGRESS.md`.
- **Plan de RBAC (resumen):** hoy la autorización son solo 2 puntos reales (`admin` alias + `AdminMiddleware`);
  el "rol" vive en `admin` bool + `daytest` sobrecargado; la org en el string `puestodepartamento`. Objetivo:
  7 roles spatie + `Production`/`Department` + pivote `production_user` (rol por producción). Orden de operaciones
  y mapeo de datos viejos→nuevos en `AUTH-RBAC-PLAN.md`. Cierra C2/C3 de raíz (el rol deja de ser columna en `users`).
- **Riesgo/Notas:** Solo documentación/planeación. Próximo paso de EJECUCIÓN sería la fundación (Bloque 1.A + 1.C).

### 2026-06-24 — REVERTIDO el quirks-fix de inicio (rompía el Home) + logo quitado ⚠️↩️
- **Estado:** Hecho (reversión). El Home vuelve a verse como en producción.
- **Qué pasó:** El arreglo de Quirks mode del 2026-06-24 (mover `@extends`/`@section` al inicio de
  `inicio.blade.php`) tuvo un **efecto secundario**: el bloque que `inicio` carga por su cuenta incluye
  **Bootstrap 4.4.1 + jQuery 3.4.1**; al moverlo dentro del `<body>` pasó a cargar **después** del Bootstrap 5
  del layout, invirtiendo qué framework "gana" en esa página → la tarjeta de bienvenida/cuestionario se deformó.
  Además, el `<img src="logo.png">` que cambié a un logo real apareció **fuera de lugar** (en producción ese
  `logo.png` daba 404 invisible, nunca se mostró).
- **Decisión:** **revertir ambos cambios en `inicio`** para dejarlo idéntico a producción (código conocido-bueno):
  (1) `@extends`/`@section` vuelven a su sitio original (tras el `</style>`); (2) eliminado el `<img>` del logo.
- **Archivos:** `resources/views/inicio.blade.php` (revert), `PROGRESS.md`, `RECIPE.md`.
- **Consecuencia aceptada:** el **Quirks mode vuelve** como advertencia de consola, pero ahora es **inofensiva**
  porque TinyMCE (lo único que exigía standards mode) ya fue removido. El arreglo correcto (standards mode SIN
  romper Bootstrap) es la **migración BS4→BS5 de `inicio`/`perfil`/`profile`** — tarea cuidadosa con verificación
  visual, programada en Bloque 1.5.C. Verificado: `view:cache` compila.
- **Lección:** no "arreglar" el orden de assets de una vista que carga su propio Bootstrap sin migrar a la vez sus
  clases/modales BS4→BS5; el orden de carga es funcional, no cosmético.
- **Font Awesome (advertencias nuevas):** "glyf: Glyph bbox was incorrect; adjusting" del woff2 de FA 6.0.0 (CDN) —
  es **ruido del sanitizador de fuentes de Firefox**, no afecta el render. Sin acción (se puede subir FA a un 6.x
  más nuevo en la limpieza CSS si molesta).

### 2026-06-24 — TinyMCE removido + diagnóstico del icono PWA cacheado ⚙️
- **Estado:** Hecho y verificado. *(Subagente usado para la remoción — a pedido del owner, para preservar contexto.)*
- **Archivos:** `resources/views/layouts/app.blade.php` (quitado TinyMCE), `CLAUDE.md`, `RECIPE.md`, `PROGRESS.md`.
- **TinyMCE eliminado (decisión nueva del owner):** antes se había diferido; ahora pidió **quitarlo ya** porque el módulo
  de correos masivos (su único uso) aún no se construye y la API key de Tiny Cloud (atada al dominio real) lanzaba errores
  "invalid API key / read-only" en consola. Se quitó el `<script src="cdn.tiny.cloud/...">` y el bloque `tinymce.init({selector:'#MyEmail'})`
  de `layouts/app.blade.php`. El `<textarea>#MyEmail` (en `virtualqueue/scheduletest.blade.php`) queda como textarea plano.
  `php artisan view:cache` compila todas las vistas sin error; grep confirma 0 referencias activas. Se reintroduce un editor
  **self-hosted** al construir correos masivos (ver RECIPE Bloque 4).
- **Icono PWA 404 — diagnóstico (NO era el archivo):** los 18 iconos existen en disco (`icon-512x512.png` = PNG válido 512×512,
  37 KB) y el servidor los entrega con **HTTP 200** (verificado con `php artisan serve` fresco). El 404 que sigue viendo el
  owner viene del **service worker**: `public/serviceworker.js` hace `cache.addAll([... lista de iconos ...])` y quedó
  registrado ANTES de restaurar los iconos, cacheando el 404; el SW viejo persiste entre recargas. **Remedio (lado cliente):**
  en DevTools → Application → Service Workers → *Unregister* + *Clear storage*, luego recargar; o hard-reload. Ya con los
  iconos presentes, una instalación limpia del SW tendrá éxito. (La fragilidad de `cache.addAll` se resuelve de fondo en
  Bloque 3.5 con Workbox.)
- **Cosmético (sin acción):** "source map 404" de `chart.js` (el CDN no sirve `.map`) — solo aviso de devtools, no afecta a usuarios.
- **Riesgo/Notas:** Bajo riesgo. El 404 del icono es cache de SW/navegador, no defecto de código.

### 2026-06-24 — Errores de consola: Quirks mode, logo.png, iconos PWA ⚙️
- **Estado:** Hecho y verificado.
- **Contexto:** El owner reportó alertas/errores de consola en `/home`. Diagnóstico por error:
- **Archivos:** `resources/views/inicio.blade.php`, `public/images/icons/*` (restaurados), `PROGRESS.md`, `RECIPE.md`.
- **Qué se arregló:**
  1. **Quirks mode + TinyMCE "requires standards mode":** `/home` → `HomeController@index` → `view('inicio')`.
     `inicio.blade.php` tenía 62 líneas de `<meta>`/Bootstrap4/jQuery/cropper/`<style>` **antes** del `@extends`,
     así que Blade las imprimía **antes del `<!doctype html>`** del layout → Quirks mode. Movidas las directivas
     `@extends`/`@section` al inicio del archivo → el doctype ahora sale primero (standards mode). `view:cache` compila OK.
  2. **`GET /logo.png` 404:** `inicio.blade.php` tenía `<img src="logo.png">` (ruta relativa). → `{{ asset('img/logo-cc-usrs.png') }}`. Verificado 200.
  3. **`GET /images/icons/icon-512x512.png` 404 (PWA):** los 18 iconos estaban **borrados del disco pero rastreados en git**;
     restaurados con `git checkout -- public/images/icons/`. Verificado 200. (Cubre ítem 0.A.bis de la receta.)
- **NO se tocó (a petición del owner — diferido):** **TinyMCE**. Los errores "invalid API key / read-only" en localhost
  son porque la API key de Tiny Cloud está **atada al dominio real** (`crewcarer.mx`); en `127.0.0.1` es inválida →
  editor read-only. En producción funciona. TinyMCE se usa para el **constructor de correos tipo Mailchimp** (layouts
  de campaña personalizados, no correo plano). **Pendiente futuro:** eliminar la dependencia de Tiny Cloud (self-host o
  reemplazar el editor) preservando los layouts de correo. Ver RECIPE Bloque 4.
- **Cosmético (sin acción):** "source map 404" de `chart.js`/`chartjs-plugin-trendline` (CDN sin `.map`) — solo aviso de devtools.
- **Riesgo/Notas:** Bajo riesgo. Persiste la colisión Bootstrap 4 sobre BS5 en `inicio`/`perfil`/`profile` (limpieza en 1.5.C).

### 2026-06-24 — Auditoría JS/CSS + arreglo del 404 de app.js y jQuery roto ⚙️
- **Estado:** Hecho y verificado en navegador.
- **Contexto:** El owner notó que `public/js/app.js` seguía en 404 tras 0.0 y pidió auditar JS/CSS
  (nunca analizados) con subagentes, anticipando que el 404 podía resolverse vinculando el archivo a mano.
- **Archivos nuevos (auditoría):** `ASSETS-JS-AUDIT.md`, `ASSETS-CSS-AUDIT.md` (2 subagentes en paralelo).
- **Archivos de código tocados:** `public/js/app.js` (NUEVO, a mano), `resources/views/layouts/app.blade.php`,
  `resources/views/auth/login.blade.php`, `resources/views/auth/passwords/email.blade.php`.
- **Hallazgos clave:**
  1. **`npm install` bloqueado por red/SSL** (mismo MITM/proxy que git) → el build webpack no es viable en este entorno.
  2. **Nada del front depende del bundle** (0 usos de Vue/axios; `resources/js/app.js` es scaffold + código muerto de sidebar).
  3. **Bug peor que el 404:** `asset('https://code.jquery.com/...')` en 3 layouts deformaba la URL → **jQuery 404 app-wide**,
     causa real de que "el JS nunca funcionó". 
  4. **CSS:** 5 de 7 hojas en `public/css/` son huérfanas (~970 KB: `main.css`, Materialize×2, `appcc.css`, `newapp.scss`);
     stack real = Bootstrap 5 CDN; colisiones BS4-sobre-BS5 en perfil/inicio/profile y Tailwind CDN en dailyreports/show.
- **Qué se arregló:** (a) escrito `public/js/app.js` mínimo a mano → mata el 404 sin npm; (b) quitado el wrapper `asset()`
  del jQuery en los 3 archivos.
- **Verificación (php artisan serve):** `/login` → **200**; `GET /js/app.js` → **200, 721 B** (antes 404); el `<script>` de
  jQuery ahora sale como URL limpia en el HTML servido.
- **Riesgo/Notas:** Bajo riesgo. Pendiente (no hecho aún): borrar las 5 hojas CSS huérfanas y unificar BS4→BS5 (Bloque 1.5.C).

### 2026-06-24 — BLOQUE 0.0: quick-wins críticos (PRIMER cambio de código) ⚙️
- **Estado:** En progreso (4 de 5 hechos y verificados; build corriendo).
- **Contexto:** El owner autorizó pasar de planeación a ejecución del Bloque 0 y confirmó tener
  **backup** de la carpeta. Fin de la restricción "no tocar código".
- **Archivos:** `app/Jobs/ReminderEmail.php`, `app/Console/Commands/encuestasTask.php`,
  `app/Console/Kernel.php`, `config/mail.php`, `app/Http/Controllers/AdminController.php`, `RECIPE.md`, `PROGRESS.md`.
- **Qué se hizo:**
  1. **Parse error fatal** `ReminderEmail.php:45` (`from("...,"CrewCare")`) → eliminado el `from()` roto;
     ahora hereda el `from` global. Verificado con `php -l` (sin errores).
  2. **Scheduler** `Console/Kernel.php` `everyMinute()` → **`dailyAt('04:00')`** (antes reseteaba la
     encuesta y maileaba a TODOS los usuarios activos 1440 veces/día).
  3. **`from` centralizado:** default neutro `noreply@crewcare.mx` en `config/mail.php`; quitado el
     **Gmail personal** `al221511303@gmail.com` que firmaba el reporte diario (encuestasTask).
  4. **`findOrFail`** en 17 `User::find($id)` de `AdminController` (toggles tipo `activaradmin`,
     `activarusuario`, `checkgft`…): un id inexistente daba 500 (`null->update()`), ahora da 404 limpio.
  5. **Build** (`npm install && npm run prod`) — en curso (el build nunca se había corrido → `app.js` 404).
- **Pendiente del bloque:** centralización completa de los `from()` restantes (AdminController/Formularios)
  se difiere al refactor a Mailables (Bloque 1.6); los de archivos COVID se eliminan en 0.C.
- **Riesgo/Notas:** Cambios de bajo riesgo, sin tocar esquema. Backup existente. `php -l` OK en los 5 PHP.

### 2026-06-24 — Verificación contra el SQL real (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `DATABASE-SCHEMA.md` (sección "Del SQL real" + correcciones), `RECIPE.md` (1.A: charset + PK tinyint), `PROGRESS.md`.
- **Qué:** El owner compartió el dump real `crewcare2406.sql` (27 tablas, completo). Confirma la
  reconstrucción. **Datos confirmados 100% fake** (emails con sufijo `.comf` para no enviar; sin PII real).
- **Hallazgos nuevos del SQL:** (1) `hazardnotifications`/`unsafeconds` con PK `tinyint(3)` → **techo 255 filas**;
  (2) **charset mixto latin1/utf8mb4** → riesgo mojibake al migrar; (3) `ncreditos` varchar guarda nombres;
  (4) junctions todas vacías (la app usa solo el string desnormalizado); (5) sí existen FKs reales en tablas
  org/médicas (corrige "casi no hay FKs"); (6) el espacio en PK de `departamentousuario` es bug del modelo, no de la BD.
- **Riesgo/Notas:** Solo documentación. Cero cambios de código.

### 2026-06-24 — Cierre de auditoría: BD real, rutas, i18n, higiene, completitud (sin cambios de código)
- **Estado:** Hecho. **Auditoría declarada COMPLETA** (sin código/config de la app sin revisar).
- **Archivos:** `DATABASE-SCHEMA.md` (nuevo — esquema real capturado de MySQL vivo: 27 tablas + conteos
  + reconstrucción + objetivo), `ROUTES.md` (nuevo — matriz de acceso + punch-list de bugs de routing),
  `CODE-AUDIT.md` (§5 i18n/higiene + certificación de huecos boilerplate), `RECIPE.md` (0.A.bis higiene,
  0.B.bis routing, 1.5.G i18n), `CLAUDE.md`, `PROGRESS.md`.
- **Hallazgos nuevos:** (1) BD viva tiene **27 tablas**, 5 con migración; **2 tablas huérfanas** sin modelo
  (`userpcr`, `virtualqueue`); datos de prueba (users=88, prueba=51 COVID). (2) **Bug 500 al guardar
  locación V1** (redirect a name inexistente). (3) `.gitignore` vacío → `vendor`+`.env`+fotos de usuarios
  committeados; iconos PWA ausentes en disco. (4) i18n inexistente (locale `en` con español, claves
  desincronizadas). (5) Providers/Handler/seeders/factory = boilerplate estándar sin tocar.
- **Riesgo/Notas:** Solo documentación. Cero cambios de código. La BD se introspeccionó read-only (SHOW/SELECT).

### 2026-06-24 — Auditoría de backend/JS/config/PWA (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `CODE-AUDIT.md` (nuevo — 4 subagentes: backend, JS/assets, config/deps, PWA),
  `ROADMAP.md` (§4.6 Arquitectura objetivo), `RECIPE.md` (Bloque 0.0 quick-wins, 1.6 backend,
  3.5 PWA, 4 upgrade framework), `CLAUDE.md`, `PROGRESS.md`.
- **Críticos NUEVOS encontrados:** (1) **el build de Mix nunca se corrió** → `app.js` 404 en cada
  página y la app no ejecuta su propio JS; (2) **la PWA nunca instaló** (el SW cachea archivos
  inexistentes → install falla); (3) **`ReminderEmail.php:45` no compila** (parse error); (4)
  **scheduler `everyMinute()`** mailea a todos 1440×/día; (5) **Laravel 8 + PHP 7.3 EOL** → upgrade
  a L11/PHP8.2 (~4-6 sem); (6) multi-server bloqueado (session/cache=file, uploads en disco local, queue=sync).
- **Riesgo/Notas:** Solo documentación. Cero cambios de código. Muchos hallazgos peores desaparecen
  al ejecutar el desmantelamiento COVID.

### 2026-06-24 — Inventario completo de vistas (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `VIEWS-INVENTORY.md` (nuevo — 95 vistas auditadas por 5 subagentes en paralelo),
  `RECIPE.md` (Bloque 1.5 detallado con hallazgos concretos), `PROGRESS.md`.
- **Qué / Por qué:** Auditoría read-only de todas las vistas cruzada con rutas/controllers para
  evaluar funcionalidad real, arquitectura y performance (carga/consultas/N+1).
- **Hallazgos top:** (1) conviven 3 frameworks CSS (Bootstrap5 + Materialize + Tailwind-CDN) y
  3 jQuery; (2) el shell carga TinyMCE/Chart.js/5 fuentes en cada página; (3) bug app-wide de
  `asset('https://...')` que rompe jQuery; (4) ~30 vistas muertas/COVID; (5) plantillas clonadas
  6-7× (CrewList, search*, correos 18→4); (6) bugs reales: store de location V1 → 500, varios
  `<tr>`/`<select>` rotos; (7) `dailyreports/*` es el patrón de calidad a estandarizar.
- **Riesgo/Notas:** Solo documentación. Cero cambios de código.

### 2026-06-24 — Receta de ejecución + corrección de supuestos (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `RECIPE.md` (nuevo — checklist de ejecución por bloques), `ROADMAP.md`
  (§0 naturaleza del repo, §3.5 esquema rediseñable, §4.5 AWS como requisito),
  `CLAUDE.md` (modelo de despliegue + datos de prueba + enlaces), `PROGRESS.md`.
- **Qué / Por qué:** El owner aclaró que este repo es la base offline con datos de prueba
  (no producción), que cada cliente es una copia en VPS personalizada, y que AWS es
  requisito de Amazon. Esto habilita rediseñar el esquema libremente y exige diseñar para
  personalización por configuración y despliegue AWS. Se creó la "receta" ordenada para no
  olvidar detalles.
- **Riesgo/Notas:** Solo documentación. Cero cambios de código o esquema.

### 2026-06-23 — Decisiones de producto + estrategia de esquema (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `ROADMAP.md` (decisiones tomadas, motor documental por paquetes, §3.5
  formalización de esquema, nota AWS), `SECURITY.md` (recalibración git-desde-cero),
  `PROGRESS.md` (decisiones + backlog).
- **Qué / Por qué:** El owner respondió las 4 definiciones y validó formalizar la estructura.
  Se fijó la estrategia de esquema (strangler, no rehacer la BD de cero) y se reforzó el
  motor documental para soportar paquetes variables por producción.
- **Riesgo/Notas:** Solo documentación. Cero cambios de código o esquema.

### 2026-06-23 — Planeación de producto (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `ROADMAP.md` (nuevo — visión y plan por fases), `COVID-DECOMMISSION.md`
  (nuevo — inventario de desmantelamiento COVID), `SECURITY.md` (recalibración de red
  flags por git local), `PROGRESS.md` (este registro + backlog).
- **Qué / Por qué:** El owner decidió auditar y planear crecimiento antes de tocar código.
  Definición de rumbo: app tipo Scenechronize con Health & Safety + documentación de
  producción (contratos, NDA, Deal Memo, Hiring Form) + firma estilo DocuSign + roles
  reales por departamento + herramientas de set (sides, breakdown de guion, cast).
  Primer paso del plan: retirar todo lo COVID/PCR (ya no se usa).
- **Hallazgo que cambió el plan:** `formulario` NO es encuesta COVID sino historial
  médico (los síntomas COVID viven en métodos muertos) → se **reutiliza**, no se borra.
  `cmedic` será la base del módulo "Reportes Médicos". Detalle en `COVID-DECOMMISSION.md`.
- **Riesgo/Notas:** Solo documentación. Cero cambios de código o esquema.

### 2026-06-23 — Mapeo inicial de arquitectura (sin cambios de código)
- **Estado:** Hecho.
- **Archivos:** `CLAUDE.md` (reescrito + enlaces), `ARCHITECTURE.md` (nuevo),
  `SECURITY.md` (nuevo, auditoría), `PERFORMANCE.md` (nuevo, auditoría),
  `PROGRESS.md` (este archivo).
- **Qué / Por qué:** Exploración read-only por dominio (auth, modelos, controllers/rutas,
  vistas) para tener un mapa antes de cualquier refactor. Se corrigió la descripción del
  proyecto en CLAUDE.md (la app es más amplia que solo COVID; no existe sub-app React/Vite).
- **Riesgo/Notas:** Solo documentación. Cero cambios de código o esquema.
