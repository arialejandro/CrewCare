  # ARCHITECTURE.md — CrewCare

Mapa de arquitectura levantado por exploración read-only del código (sin cambios).
Cuatro dominios: **Auth/Permisos · Modelos/Datos · Controllers/Rutas · Vistas/Frontend**.
Al final hay una sección de **hallazgos de seguridad / deuda técnica** consolidada.

> Alcance real de la app: empezó como tamizaje de salud COVID de set y **creció** a
> una plataforma más amplia de **seguridad de producción**: además del módulo de
> salud/PCR, hoy incluye reportes de lesiones, notificaciones de peligro/condiciones
> inseguras, auditorías de locación y reportes diarios de seguridad (DSR). Confirmado:
> **no hay** sub-app React/Vite ni ruta `/ordenar/`. Frontend = Blade + Bootstrap 5 +
> jQuery; Vue 2 está instalado pero solo como scaffold sin usar.

> ## ⚠ ACTUALIZACIÓN 2026-08-06 (catch-up post-compact — deltas #40-#51)
> El cuerpo de abajo es del **2026-07-07** y describe bien Auth/COVID/scouting, pero **NO refleja
> los ~12 módulos H&S nuevos**. Estado real hoy: **53 controllers · 51 modelos · 26 servicios · 226 rutas · 84 tablas · 188 vistas**.
> **La autorización YA es RBAC Spatie real** (no solo el flag `admin`): roles + permisos (`spatie/laravel-permission`),
> `Gate::before` = super-admin pasa todo, permisos por módulo (ver [ORG-TAXONOMY.md](ORG-TAXONOMY.md) y `route:list`).
> `daytest` como "rol" está **neutralizado**; la fuente de rol médico es `User::isMedic()` (rol Spatie `medic`).
>
> **Módulos nuevos → controller · modelos · servicio(s) · vistas** (índice de rutas/símbolos; detalle vivo en `/memory`):
> - **Scouting H&S:** `ScoutingReportController` · `ScoutingReport` · `ScoutingLocator`,`HazardActivities` · `admin/scoutings/*`.
> - **Reportes sellables (motor de documentos):** chrome compartido `componentes/_report-v2-*` + `_doc-hero`; sello vía
>   trait `HasDigitalSignatures` + `DigitalSignature` + servicio `SealVerifier` (verificador público `SealVerificationController`).
> - **Injury/Addendum:** `InjuryReportController`,`AddendumController` · `InjuryReport`,`Addendum`,`Witness` · `InvolvedResolver`.
> - **DSR:** `DailyReportController` · `DailyReport`,`DailyLog`,`ActionItem` · `DsrHub`,`DsrContext`,`ImageCompressor`.
> - **Catálogo de eventos/normas:** `HazardEventController`,`SafetyStandardController` · `HazardEvent`,`SafetyStandard`,`CatalogPendingStandard` · `StandardCodeResolver`.
> - **SFX/Consumibles:** `SfxController`,`SfxEffectTypeController`,`ConsumableController` · `SfxEffectType`,`SfxEvent`,`Consumable`.
> - **Herramientas + inspección (#41-#43):** `InspectionController` · `Tool`,`ToolFamily`,`ToolVariant`,`ToolCheckPoint`,`ToolInspection` · `InspectionVerdict`.
> - **Permisos de trabajo (#44):** `PermitController` · `Permit`,`PermitPoint`,`IssuedPermit`.
> - **MEDEVAC (#46):** `MedevacController` · `MedevacPoster` · `MedevacPosterBuilder`,`MedevacContacts`.
> - **Vigilancia epi (#45):** `EpiController` · `IndicatorTerm`,`OutbreakStudy` · `EpiSurveillance`,`ClinicalTextNormalizer`.
> - **Mapeo de riesgos (#50):** `RiskMapController` · `RiskMap`,`RiskMapView`,`RiskMapMarker` · `RiskSigns` (librería de 80 señales ISO en `resources/rm-signs.generated.php`).
> - **Wrap report:** `WrapReportController` · `WrapReport` · `WrapReportBuilder` (solo-lectura, payload congelado).
> - **Médico/LITE:** `MedicalReportController`,`LitePatientController`,`cmedicController`,`HealthRecordAddendumController`,`MedicCredentialController` ·
>   `LitePatient`,`MedicalAccessGrant`,`HealthRecordAddendum`,`MedicCredential`,`Medication`,`cmedic` · `SepRegistry`,`CedulaVerifier`.
> - **Cédula profesional (robot APAGADO):** `CedulaVerifier`,`CedulaResult`,`CredentialTelemetry` (flag `CEDULA_AUTO_VERIFY=false`).
> - **Privacidad:** `PrivacyConsentController` · `PrivacyConsent` · `PrivacyNotice`.
> - **Transversales:** `CurrentProduction` (fuente única de producción), `ProductionCalendar` (día de rodaje derivado),
>   `Features` (feature flags), `Branding`/`Avatar`/`SafetyAlertRecipients`; `Api\SyncController` (borradores offline `api/sync/up`).

---

## 1. Auth & Permisos

**Paquetes:** `laravel/ui ^3.3` (scaffold de auth, `Auth::routes()` en `routes/web.php:35`)
+ `laravel/sanctum ^2.11` (prácticamente sin usar: único endpoint `/api/user`).

**Guard único:** `web` (sesión) — `config/auth.php`. No hay guard de API propio.

### Middleware (alias en `app/Http/Kernel.php`)
| Alias | Clase | Qué hace |
|---|---|---|
| `auth` | `Authenticate` | Redirige a `route('login')` si no autenticado. |
| `admin` | `AdminMiddleware` | **Único control de rol real.** `if (auth()->check() && auth()->user()->admin) next(); else redirect('/')`. NO verifica `activo`. |
| `guest` | `RedirectIfAuthenticated` | Redirige a HOME si ya logueado. |
| `locale` / `localization` | `localization` / `LocaleMiddleware` | i18n. `localization` lee `session('locale')`; `LocaleMiddleware` lee `$request->locale` sin validar. |
| `niveldos` | `niveldos` | **STUB no-op** — `return $next($request)` sin lógica. No aplica ningún control. No confiar en él. |

- `AuthenticateSession` está **comentado** en el grupo `web` (Kernel) → sin detección de sesiones concurrentes.
- `EnsureFrontendRequestsAreStateful` (Sanctum) **comentado** en el grupo `api`.

### Modelo de roles
**No hay RBAC ni Policies ni Gates.** `AuthServiceProvider` tiene el array `$policies`
vacío y `boot()` sin gates. La autorización es **por flags en `User`**:
- `admin` (booleano) → acceso al panel admin vía middleware `admin`.
- `daytest` se reusa como **rol funcional** dentro del panel (lo setean `putga/putgb/putgg`):
  `0` = admin/coordinador completo, `1` = supervisor/coordinador, `2` = médico/doctor.
  Las vistas (`layouts/sidebar`, `header`) ramifican el menú por `daytest`.
- Otros flags de estado del usuario: `activo`, `encuestadiaria`, `enfermo`, `tested`,
  `inline`, `resultpcr`, `lastpcr`, `lastwr`, `ultimatemperatura`, `age` (= tiene foto de gafete).

### Protección de rutas
- Bloque **`admin`** (`routes/web.php:44-197`): ~150 rutas — todo el CRUD, scheduling de
  pruebas, reportes. Es el grueso de la app.
- Bloque **`auth`** (`routes/web.php:204-207`): solo `/profile`.
- **Scouting H&S** (`/scoutings`, NUEVO 2026-06-28): reutiliza permisos `locations.*`. Es el **ÚNICO scouting** desde el 2026-06-28
  (tarde): el scouting viejo (`/location*`) fue **decomisionado** y sus rutas eliminadas. **Call Sheets** (`/call-sheets`): **EN PAUSA
  (2026-06-28 tarde)** — rutas y permisos `call_sheets.*` **eliminados**; el módulo se retiró a `_legacy_backup/` (rediseño con
  dirección, ver §3 tabla de Controllers y ROADMAP Fase 3).
- **Sin middleware:** auth scaffolding, `/`, `/locale/{locale}`, `/offline`, `/negative-mail`,
  varias de `PerfilController`/`formularios`/`crop-image`. (Los resets de sistema
  `/newdayRep`/`/newTD`/`/newWR`/`/Nresult`/`/PhotoReminder` **fueron ELIMINADOS** en sesiones previas —
  ver §5; el reset diario real es el comando agendado `encuestas:task`. `/reminder-mail` fue
  **gateado a `admin`** el 2026-06-27.) Ver §5.

---

## 2. Modelos & Datos

MySQL (`DB_DATABASE=crewcare`). 21 modelos en `app/Models/`. **Aviso importante:** las
migraciones (`database/migrations/`) solo crean las tablas base de Laravel (users,
password_resets, failed_jobs, personal_access_tokens, jobs). **No existen migraciones
para las tablas de dominio** (`cmedic`, `pcr`, `formularios`, `injury_reports`, etc.):
el esquema se creó fuera de Laravel. Seeders y factories están vacíos/default. Reproducir
la BD desde el código **no es posible** hoy — es una deuda real.

### Clusters funcionales

**A. Crew & organización**
- `User` (tabla `users`) — miembro de crew; PK `id`.
- **`Department`/`Position` (tablas `departments`/`positions`, spatie/RBAC) = catálogo CANÓNICO** — los que usan el alta de crew
  (`adduser`), el call sheet y, desde 2026-06-28 (tarde, fase 3), el **CRUD de catálogos** (`CatalogController`). Ganan columna
  **`sort_order INT NOT NULL DEFAULT 0`** (`$fillable`+`$casts`) → orden canónico que fluye a **Crew List / call sheet**, reemplazando
  el hack muerto **por-persona** `users.labn` ("Jerarquía"). Las dropdowns de `adduser` ya ordenan por `sort_order`.
- ~~`departamento` (PK `id_departamentos`), `puesto` (PK `id_puestos`)~~ — **modelos LEGACY** que el CRUD de catálogos **ya no usa**
  (nadie los leía); el catálogo se unificó sobre `departments`/`positions`. Quedan en código pero fuera del flujo de catálogos.
- Junctions: `departamentousuario` (⚠ nombre de PK con espacio al final: `id_departamentousuario `),
  `userpuesto`. `User.puestodepartamento` guarda además un string "DEPTO-PUESTO" (doble fuente de verdad).

**B. Salud & tamizaje COVID** *(sensible)*
- `formulario` (PK `id_formulario`, ~72 campos: signos vitales, antecedentes familiares/genéticos,
  vacunas, alergias, COVID) + junction `userform`.
- `cmedic` (consultas médicas: `diagnosis`, `medication`, `observations`).
- `pcr` (`estado`/`resultado`), `prueba` (resultado de lab), junction `usuariopcr` (⚠ FK `id_usrio`, typo).
- `queuevirtual` (tabla `testqueue`, registro único con `mainday` 2/3/4 que controla el día de prueba global).

**C. Incidentes & seguridad** *(sensible/legal)*
- `InjuryReport` (tabla `injury_reports`, único con `belongsTo(User)` real vía `user_id`;
  casts a array `injury_type`/`additional_images_paths`).
- `hazardnotification`, `unsafecond` (sin FK; usan campo string `make_by`).
- **✍️ Autofirma (sistema cerrado) en LAS 5 TABLAS DE REPORTES (2026-06-28 tarde, fase 2):** `daily_reports`, `hazardnotifications`,
  `unsafeconds`, `injury_reports` y `scouting_reports` tienen una columna **`created_by_id BIGINT UNSIGNED NULL`** (en `$fillable`, seteada
  con `auth()->id()` en `store`, escritura gateada con `Schema::hasColumn`). El **autor y la fecha de elaboración ya NO vienen del form**:
  se fijan server-side con el usuario autenticado — DSR usa `author_name = auth()->user()->name`; los 3 incidentes + Scouting usan
  `make_by`/`make_date`. Atribución a prueba de manipulación (quién/cuándo no falsificable). En local el esquema ya está aplicado (vía
  `DB::statement`, sin migrate); el owner replica el mismo SQL en sus instancias.
- `locationreport` (tabla `location_report`, PK `id_loc`, ~161 campos — checklist de inspección de locación). **El modelo se CONSERVA**
  pero sus controllers/vistas (`locationController`/`locationController2`, vistas `location*`) fueron **DECOMISIONADOS** (2026-06-28
  tarde, movidos a `_legacy_backup/decommission-2026-06-28/`, rutas `/location*` eliminadas). El modelo sigue **solo** porque
  `HomeController` aún lo usa en el dashboard (`locationreport::count()` + `::oldest()` para "días de producción") → **follow-up:** migrar
  esa métrica a `scouting_reports`.
- **`ScoutingReport`** (tabla **`scouting_reports`**, **NUEVA 2026-06-28**) — scouting H&S combinado; desde el 2026-06-28 (tarde) es el
  **ÚNICO scouting** (reemplaza al de ~161 campos, ya decomisionado): encabezado de emergencia + **13 categorías de riesgo** (Sí/No/N-A +
  nivel + catálogo normativo) + **gatillo SB132** (`requires_specific_ra`) + capa operativa (viabilidad/acuerdos en JSON). **AUTOFIRMA
  (sistema cerrado):** `make_by`/`make_date` se fijan **server-side** + columna **`created_by_id`** (`auth()->id()`) para atribución
  inmutable. Tabla creada por el SQL del owner (`database/owner-apply/2026-06-28-cuatro-items-safety.sql`, incluye `created_by_id`).
- ~~**`CallSheet`** (tabla **`call_sheets`**)~~ **EN PAUSA / retirado (2026-06-28 tarde)** — el modelo `CallSheet`, su controller y vistas
  se movieron a `_legacy_backup/decommission-2026-06-28/`; el `CREATE TABLE call_sheets` queda **EN PAUSA** (no aplicar). Se reconstruirá
  con dirección (guion + cast list + números de personaje + necesidades por depto). Ver §3 tabla de Controllers y ROADMAP Fase 3.

**D. Reporte diario de seguridad (DSR)**
- `DailyReport` `hasMany` `DailyLog` (`belongsTo`). `SafetyStandard` = catálogo de normas;
  al crear un log se auto-asigna `regulation_badge`/`regulation_code` por lookup de categoría.
- ~~Los tres usan `$guarded = []` (todo mass-assignable).~~ **(2026-06-28)** `DailyReport` y `DailyLog` pasaron a **`$fillable` explícito**
  (verificado que cubre lo que el controller escribe); `SafetyStandard` aún con `$guarded=[]` (pendiente).
- **Catálogo `safety_standards` (2026-06-28):** **67 filas** (de 22 originales conservadas) sembradas por
  **`database/seeders/SafetyCatalogSeeder.php`** (idempotente, `firstOrCreate` por `regulation_code`): **45 AMPTP/CSATF Safety
  Bulletins** (badge `CSATF`) + **6 OSHA/Cal-OSHA** (badge `OSHA`) + **10 NOMs STPS** (badge `STPS`). **Mecanismo
  category→badge/code:** al crear un log, `DailyReportController@storeLog` resuelve `regulation_badge`/`regulation_code` por lookup de
  `category_name` (este es el patrón a replicar para conectar el dropdown a Hazard/Cond. Insegura/Lesión). 🐞 Pendiente manual: fila
  legacy "Vías Férreas" rotulada Bulletin #29, el oficial es #28 (Railroad). **Plan "los 4 items" IMPLEMENTADO (2026-06-28) — el
  CÓDIGO está en el repo, las COLUMNAS las aplica el owner** (SQL consolidado en
  `database/owner-apply/2026-06-28-cuatro-items-safety.sql`; escrituras DEFENSIVAS, gateadas con `Schema::hasColumn`):
  - **`safety_standards.reference_url VARCHAR(500) NULL`** — seeder poblado (45 URLs CSATF + 6 OSHA + 10 STPS); liga "Ver boletín"
    en el DSR (`admin/dailyreports/show`). Tras el `ALTER`, **re-correr `SafetyCatalogSeeder`**.
  - **`regulation_badge VARCHAR(20) NULL` + `regulation_code VARCHAR(50) NULL`** en **`hazardnotifications`/`unsafeconds`/
    `injury_reports`** — `create` con select de `category_name`; `store()` snapshotea el badge/code; `show()` resuelve
    `reference_url`. (Se optó por **snapshot** badge/code, no por FK `safety_standard_id`.)
  Ver entrada **"Los 4 items … IMPLEMENTADOS"** en [PROGRESS.md](PROGRESS.md) / [ROADMAP.md](ROADMAP.md).

**E. Mensajería & config**
- `message` (plantilla de correo, campo `mensaje`), `usuariosnotificacione` (lista de destinatarios
  de alertas: `nombre`, `correo`, `activo`).

### Relación central (texto)
`User` es el hub: 1:N hacia `formulario`, `cmedic`, `usuariopcr`/`pcr`, `InjuryReport`, y N:M
hacia `departamento`/`puesto` vía las junctions. El pipeline de salud:
`formulario` (encuesta diaria) → si síntomas `User.enfermo=1` → cola `queuevirtual` →
`usuariopcr`+`prueba` registran resultado → se actualizan `User.resultpcr/tested/lastpcr`.

### Modelos clínico/legalmente sensibles (tratar con cuidado extra)
`cmedic`, `pcr`, `usuariopcr`, `prueba`, `formulario` (datos médicos y de antecedentes
familiares), `InjuryReport` (implicaciones de workers-comp). Cambios de esquema, validación
o borrado aquí requieren plan previo y revisión — no es "un form más".

---

## 3. Controllers & Rutas

`routes/web.php` (~211 líneas) concentra casi todo. `api.php` mínimo, `channels.php` define
un canal de notificación por usuario, `console.php` solo `inspire`. **Scheduler
(`app/Console/Kernel.php`): `encuestas:task` agendado a diario a las 04:00** (resetea
`encuestadiaria=0` + manda el recordatorio "DAILY REPORT"). **Comandos Artisan adicionales
(2026-06-28, NO agendados — invocación manual):** `crew:welcome-resend {id}` (reenvía el correo
de bienvenida a un crew member) y `crew:daily-reminder` (variante manual de `encuestas:task`:
notifica a los activos con `encuestadiaria=0` **sin** resetear). Reemplazan las antiguas rutas
web GET `/welcomeresend/{id}` y `/sendreminder` (eliminadas).

### Controllers principales
| Controller | Responsabilidad | Notas |
|---|---|---|
| `HomeController` | Dashboard de KPIs de seguridad (`auth`) | ⚠ días de rodaje **hardcodeados** 2025-04-01→04-22. **(2026-06-28 tarde, fase 3) Dashboard rediseñado** (rama admin de `inicio.blade.php`): banner CrewCare (`#ff9900`), **6 KPIs** en grid responsivo 2→3→6 columnas, gráfica de tendencia con alto fluido (`clamp`), Tailwind CDN homologado al estilo del DSR. Intactos: rama crew (cropper), scripts (Chart.js + trendline + Cropper.js + `inicio.js`) y el canvas `weeklyUnsafeTrendChart`. Ya cuenta `scouting_reports` (no `locationreport`). |
| ~~`AdminController`~~ | ~~CRUD de catálogos~~ | **RETIRADO (2026-06-28 tarde, fase 3) — God Object 100% desmantelado.** Carveado su último bloque (catálogos) a `CatalogController`, quedó vacío → **movido a `_legacy_backup/decommission-2026-06-28/app/Http/Controllers/`** + `composer dump-autoload`. Cero referencias vivas en `route:list`. Era el último corte del strangler (§6). |
| `CatalogController` | CRUD de **catálogos**: departamentos, puestos, notificaciones | **NUEVO (2026-06-28 tarde, fase 3)** — paso #2 del strangler (§6), carveado **junto con su rediseño**. **Decisión del owner:** opera sobre las **tablas NUEVAS** `departments`/`positions` (las que usa `adduser`/call sheet), **NO** las legacy `departamento`/`puesto`; Notificaciones siguen en `usuariosnotificacione` legacy. Catálogos ganan **`sort_order`** (orden canónico a Crew List/call sheet, reemplaza el hack muerto `users.labn`). **Endurecido:** validación en create/update (antes `$request->all()`), activaciones **GET→POST+CSRF** (+ nuevas `activarposition`/`desactivarposition`), `findOrFail`, `whereNumber` en rutas, `@can('catalogs.manage')` en las 6 vistas. |
| `CrewListController` | Listados read-only de crew (`usuarioscrud`/`comcrud`/`medicocrud`/`idcardscrud`) + `idcard` | **NUEVO (2026-06-27)** — primer corte de `AdminController` (§6). **Lecturas** de crew. Movimiento puro, mismas vistas/rutas. Listas acotadas por depto vía `User::applyDepartmentScope`. |
| `CrewController` | Alta (`adduser`/`newuser`) + perfil (`useredit`/`acountupdate`) de crew | **NUEVO (2026-06-28)** — pasos #3 y #4 de `AdminController` (§6). **Escrituras** de crew. Alta = movimiento puro; **perfil endurecido al moverse**: scope por depto vía `User::canManageCrewMember` (cierra H2) + whitelist validada en `acountupdate` (cierra H4). ✅ (2026-06-28, endurecimiento de creación) `newuser` **ahora VALIDA** (antes no validaba nada): whitelist del form `admin.newuser`; `email` **required|email|unique:users**, `password` **min:8|confirmed**. |
| `CrewStatusController` | Toggles de estatus + rol/grupos/puesto de crew (`checkgft`/`uncheckgft`/`activarusuario`/`activarencuesta`/`desactivarusuario`/`activaradmin`/`desactivaradmin`/`putga`/`putgb`/`putgg`/`selectpuesto`) | **NUEVO (2026-06-28)** — pasos #5 y #6 de `AdminController` (§6). **Endurecido al moverse**: cada acción sobre `{id}` hace `findOrFail`+`abort_unless canManageCrewMember` (**cierra H2** en todas las acciones admin sobre `{id}`); `activarusuario`/`activarencuesta` pasaron a **POST+CSRF** (H1). |
| `encuestasController` | Acceso a encuesta diaria de salud | `checkfroms()` muerto y con bug (`findOrFail(id)` sin `$`). |
| `FormulariosController` | Envío de encuesta de salud → flag `enfermo` + alerta | `newformulario1()` muerto (empieza con `dd()`). |
| `cmedicController` | CRUD de consultas médicas por usuario + historial WR (`historialWR`) | Sensible. `historialWR` movido aquí desde `AdminController` (paso #7, 2026-06-28); **(2026-06-28) su ruta MIGRÓ del gate legacy `admin` → `permission:medical.view`** (cambio de autorización; enlaces gateados con `@can('medical.view')`). `index()` muerto **eliminado**; `store()` valida el objetivo con `User::findOrFail($id_user)`. |
| `queueController` | Cola virtual de pruebas PCR/antígeno, scheduling, correos | Usa `message` + `queuevirtual`. |
| `InjuryReportController` | Reportes de lesión + autocompletar usuario (JSON search) | Sube imágenes. (2026-06-28) método muerto `storeImage()` **eliminado**; `searchUsers()` valida `term` (`nullable|string|max:255`). Lista pagina (10). ✅ (2026-06-28, endurecimiento de creación) `store()`: quitado el **doble `json_encode`** de `additional_images_paths` (BUG: rompía la lectura; el modelo ya castea a `array`); nombre de archivo anti-colisión `time().'_'.uniqid().'_main.'`. ✍️ (2026-06-28 fase 2) **AUTOFIRMA:** `store()` fija `make_by`/`make_date`/`created_by_id` server-side. 🎨 **vista SHOW (`injuryreport.blade.php`) REESCRITA al estándar del DSR** (homologada con hazard/unsafecond/Scouting). |
| `HazardNotificationController` / `unsafecondNotificationController` | Peligros / condiciones inseguras | ✅ (2026-06-28) redirects rotos **corregidos** (`unsafecond@store` y `hazard@store` ahora a su `.index`); `index()` **pagina(15)+links** en ambos. En hazard, la validación de imagen usaba `main_image_path` (columna) en vez de `main_image` (input) → **nunca se aplicaba**; ya **corregido**. ✅ (2026-06-28, endurecimiento de creación) `store()` en ambos: **mass-assignment cerrado** (`$request->all()` → array validado + `unset` de inputs de archivo); **doble `json_encode` de `additional_images_paths` corregido** (BUG: rompía la lectura); `try/catch` con `Log::error`+`back()`; en unsafecond, validación de imagen `mimes`/`max:` añadida; en hazard, la regla de imágenes adicionales apuntaba a `additional_images_paths.*` (nunca aplicaba) → corregida a `additional_images[]`. ✍️ (2026-06-28 fase 2) **AUTOFIRMA:** `store()` fija `make_by`/`make_date`/`created_by_id` server-side (fuera del form, inputs del create readonly). 🎨 **vistas SHOW (`hazard.blade.php`/`unsafecond.blade.php`) REESCRITAS al estándar del DSR** (Tailwind, hero band, mismas clases de badge, "📄 Ver boletín", print-footer de firma). |
| ~~`locationController` / `locationController2`~~ | Scouting viejo (checklist de ~161 campos, V1/V2) | **DECOMISIONADOS (2026-06-28 tarde)** — ambos controllers + las 5 vistas `location*` **movidos a `_legacy_backup/decommission-2026-06-28/`**; rutas `/location*` eliminadas. Reemplazados por `ScoutingReportController` (el ÚNICO scouting). El modelo `locationreport` se conserva solo por el dashboard (follow-up). |
| `DailyReportController` | Reporte diario de seguridad estilo "revista" — **estándar de oro** de presentación (las 3 vistas show de incidentes + Scouting se homologan a su `show.blade.php`) | Edición bloqueada tras 24 h (candado intacto; `storeLog()` ahora hace fail-fast del candado antes de validar). ✅ (2026-06-28) import muerto `Browsershot` **eliminado** y ruta huérfana `daily_reports.pdf` (→ `downloadPdf()` inexistente) **eliminada**. **Export PDF = feature pendiente, no ruteada.** (2026-06-28) `show` añade liga **"Ver boletín"** vía `safety_standards.reference_url`. ✍️ (2026-06-28 fase 2) **AUTOFIRMA:** `store()` fija `author_name = auth()->user()->name` + `created_by_id` server-side (`author_name` fuera del `required`, input del create readonly). |
| `ScoutingReportController` | Scouting H&S combinado (`/scoutings`: index/create/store/show) | **NUEVO (2026-06-28)** — el **ÚNICO scouting** desde el 2026-06-28 (tarde); reemplaza al `location_report` legacy (decomisionado). 13 categorías de riesgo + gatillo SB132 + capa operativa JSON; show estilo DSR. Reutiliza permisos `locations.*`. **(2026-06-28 tarde) AUTOFIRMA:** `store()` fija `make_by`/`make_date`/`created_by_id` **server-side** (no del form) → atribución a prueba de manipulación. Modelo `ScoutingReport` (tabla `scouting_reports`, la crea el owner). |
| ~~`CallSheetController`~~ | Call sheet "el llamado" | **EN PAUSA / retirado (2026-06-28 tarde)** — controller + modelo `CallSheet` + vistas `admin/callsheets/*` **movidos a `_legacy_backup/decommission-2026-06-28/`**; rutas `/call-sheets` y permisos `call_sheets.*` eliminados. El módulo inicial no refleja un callsheet real; se reconstruirá con dirección (guion + cast list + números de personaje + necesidades por depto). Ver ROADMAP Fase 3. |
| `exportController` / `importController` | Excel (maatwebsite): export antígeno / import de crew | Clases `AntigenTestExport`, `QueueImport`. |
| `PerfilController` | Perfil, cambio de password, subida de foto | `pruebachedule()` resetea `encuestadiaria` de todos. |
| `resetController` | **Operaciones batch de sistema** (reset de encuestas/cola, CSV) | ⚠ **sin auth** — ver §5. |
| `ReminderMailController` | Despacha el job de correo "REMINDER DAILY REPORT" (`/reminder-mail`) | El `dd()` ya **se removió** (2026-06-27): ahora hace `redirect` con flash. Ruta **gateada a `admin`**. (`MailController` ya no existe — movido a `_legacy_backup/` en los lotes COVID 2026-06-25.) |
| `cropimageController` | Subida de foto de perfil con crop base64 | Devuelve JSON. |

### Flujos de usuario (rutas → controller → modelo)
1. **Encuesta diaria:** `GET /dailyreport` → `encuestasController@viewencuesta` (gate por `encuestadiaria`)
   → `POST /formularios/registro` → `FormulariosController@newformulario` (crea `formulario`,
   set `enfermo`, alerta por correo). Reset diario: comando agendado **`encuestas:task`** (04:00).
   (La antigua ruta `GET /newdayRep` de resetController fue **ELIMINADA** — era un duplicado sin auth; ver §5.)
2. **Gestión PCR/antígeno:** `/virtualqueue`, `/crudtest`, `/userqueue/{id}`, `/antigentest`,
   `/usertested/{id}` (queueController). *(Las rutas `/positivepcr`/`/negativepcr`/`/negativeantg` fueron
   **ELIMINADAS** en el Lote 3 de COVID-DECOMMISSION, 2026-06-25; el ex-`AdminController` ya no existe.)*
3. **Consulta médica:** `/medicocrud` → `/consulta/{id}` → `POST /cmedica/{id_user}` (cmedicController).
4. **Lesiones / peligros / condiciones / locación / DSR:** patrón CRUD `index/create/store/show`
   con subida de imágenes a `Storage` (public disk).
5. **Admin de usuarios:** `/usuarioscrud`, `/adduser`→`/newuser` (correo de bienvenida),
   `/selectpuesto/{id}` (crea `userpuesto` + actualiza string en `User`).

---

## 4. Vistas & Frontend

**Build:** Laravel **Mix** (`webpack.mix.js`): `resources/js/app.js`→`public/js`,
`resources/sass/app.scss`→`public/css`. **No Vite, no React.** (verificado: sin `.tsx/.jsx`,
sin `vite.config`, sin `public/ordenar`).

**Stack de UI real:** Blade + **Bootstrap 5** (vía CDN jsDelivr) + jQuery + JS vanilla.
- Vue 2 registrado en `app.js` pero `ExampleComponent.vue` **nunca se instancia** en ninguna vista.
- CDNs en `layouts/app.blade.php`: Bootstrap 5.1.3, Bootstrap Icons, **Chart.js** (dashboards),
  **TinyMCE 6** (composición de correos en `virtualqueue/scheduletest`), Font Awesome 6, jQuery
  (redundante, también lo carga `bootstrap.js`), Google Fonts.
- Hay CSS **legacy** de Materialize en `public/css|js` conviviendo con Bootstrap 5.

**Estructura de vistas (`resources/views/`):**
- `layouts/` — `app` (wrapper), `header`, `sidebar` (colapsable + offcanvas móvil; menú ramificado por `daytest`).
- `auth/` — login/register/passwords/verify (custom, con branding, no scaffold default).
- Dashboard: `home`, `inicio`, `perfil`/`profile`, `changepassword`.
- Salud: `formulario`, `accesos/autorizado`·`noautorizado` (resultado del tamizaje).
- PCR: `pcrtest/*`, `admin/pcrcrud`·`pcredit`, `virtualqueue/*`.
- Admin CRUD: `admin/*` (usuarios, deptos, posiciones, idcards, checkpoint —incluye `checkpoint-mobile`—,
  notificaciones, hazards, unsafeconds, injuryreports, locations, `dailyreports/*`).
- `componentes/*` — parciales de búsqueda e historial (cargados por endpoints de search).
- `correos/*` — ~18 plantillas de email. `imports/*`, `modal/*`.

**i18n:** `resources/lang/en` (PHP) y `es` (`es.json` + `messages.php`). Cambio de idioma por
`session('locale')` (ruta `/locale/{locale}`). No hay selector visible en el header (comentado).

**PWA:** `silviolleite/laravelpwa` + `public/serviceworker.js` (cache-first, sirve `offline` sin red).
Config en `config/laravelpwa.php` (name "CrewCare | DEMO", theme `#35a8df`). Vista `vendor/laravelpwa/*`.

---

## 5. Hallazgos de seguridad & deuda técnica (consolidado)

> Solo mapeo — **no se corrigió nada**. Priorizar antes de tratar la app como producción dura.

**Críticos / Alto**
- **Resets de sistema sin auth:** `/newdayRep`, `/newTD`, `/newWR`, `/Nresult`, `/PhotoReminder`,
  `/nophoto` (resetController) son llamables sin login y **mutan estado global** (resetean encuestas,
  cola de pruebas, crean registros `prueba`). Cualquiera con la URL puede dispararlos. Proteger con `auth`+`admin`.
  > **✅ RESUELTO (2026-06-26):** `/newdayRep`/`/newWR`/`/PhotoReminder` (y `/newTD`/`/Nresult`/`/pruebachedule`)
  > **ELIMINADAS** — eran duplicados del comando agendado `encuestas:task` (verificado con `route:list`: ya no
  > existen). `/nophoto` quedó **gateado** a `auth`+`permission:reports.export`. Ver C3 en [SECURITY.md](SECURITY.md)
  > y `PROGRESS.md` (entradas **🔒 Seguridad (2/n)** y **🧹🔒 Seguridad (2b)**).
- **Mutaciones de estado por GET:** ~20+ rutas admin usan GET para cambiar datos
  (`/activaradmin/{id}`, `/activarusuario/{id}`, `/checkgft/{id}`, `/positivepcr`...). Riesgo CSRF
  vía links / prefetch. Migrar a POST/PUT con `@csrf`.
- **Sin Policies/Gates ni RBAC:** autorización dispersa en flags y middleware. Un solo booleano `admin`.

**Medio**
- `AdminMiddleware` no verifica `activo` → un admin desactivado conserva acceso.
- `niveldos` es un stub que no protege nada (engañoso).
- Operaciones multi-paso (ej. `positivepcr`) **sin transacciones DB** → estados parciales en fallo.
- Tokens Sanctum sin expiración (`config/sanctum.php: 'expiration' => null`).
- `AuthenticateSession` deshabilitado (sesiones concurrentes).
- Direcciones "from" de correo inconsistentes (`covid@crewcare.tech`, `noreply@crewcare.mx`,
  `notofications@crewcare.app`) → riesgo SPF/DKIM. Centralizar en config.
- **Sin migraciones de las tablas de dominio** → esquema no reproducible desde el repo.

**Bajo / limpieza**
- Fechas de rodaje hardcodeadas en `HomeController` (2025-04). Romperá fuera de ese rango.
- ~~`DailyReportController@downloadPdf` ruteado pero no implementado; `Browsershot` importado sin usar.~~
  > **✅ RESUELTO (2026-06-28):** ruta huérfana `daily_reports.pdf` e import `Browsershot` **eliminados** (era un 500 latente; ninguna
  > vista la enlazaba). El export PDF queda como **feature pendiente, no ruteada**.
- Código muerto: `encuestasController@checkfroms` (con bug), `FormulariosController@newformulario1` (`dd()`).
  > **✅ RESUELTO (2026-06-25/27):** el `dd()` de `ReminderMailController` se removió (ahora `redirect` con flash);
  > `MailController` y `newformulario1` ya no existen (removidos en los lotes COVID).
- ~~`locationController` vs `locationController2` duplican propósito sobre la misma tabla.~~
  > **✅ RESUELTO POR DECOMISIÓN (2026-06-28 tarde):** ambos controllers + las 5 vistas `location*` se movieron a
  > `_legacy_backup/decommission-2026-06-28/` y sus rutas `/location*` se eliminaron; el `ScoutingReportController` (`/scoutings`) es el
  > **único scouting**. El modelo `locationreport` se conserva solo por el dashboard (follow-up: migrar a `scouting_reports`).
- ~~**Doble `json_encode` de `additional_images_paths`** en hazard/unsafecond (controller hace `json_encode` + el modelo castea a `array`).~~
  > **✅ RESUELTO (2026-06-28):** era un **BUG real** que rompía la lectura de las imágenes adicionales; se quitó el `json_encode` manual
  > en `Injury`/`Hazard`/`unsafecond@store` (array directo, deja que el cast del modelo codifique). Ver entrada **🛡️ Endurecimiento de
  > métodos de creación** en [PROGRESS.md](PROGRESS.md).
- **Trait `HandlesImageUploads` (DRY) + job `DeleteOrphanedImages` (atomicidad)** — el patrón de subida de imágenes está duplicado en
  ~5 controladores (`Injury`/`Hazard`/`unsafecond`/`location`/`location2`); extraerlo a un trait. Además, hoy los archivos se guardan
  **antes** del `create()` → si el `create()` falla queda una **imagen huérfana**: pendiente un job `DeleteOrphanedImages` (o transacción
  con cleanup). Detectado en la pasada **🛡️ Endurecimiento de métodos de creación** (2026-06-28).
- Inconsistencias de nombres en esquema: PK con espacio (`departamentousuario`), FK `id_usrio` (`usuariopcr`).
- `scheduler encuestas:task` corre **cada minuto** — verificar que sea intencional.

---

## 6. Partición de `AdminController` (strangler) — plan de descomposición

`AdminController` era el **God Object** del repo (~581 líneas, 26+ métodos: CRUD de users, deptos, puestos, PCR,
notificaciones, idcards). Se partió **por strangler** (cortes incrementales, nunca big-bang) hacia **~6-7
controllers cohesivos** (NO 12 micro-controllers). Cada corte preferentemente fue un **movimiento puro** primero (misma
lógica/vistas/rutas, cero cambio de comportamiento) y el **endurecimiento** (scope por depto, validación) llegó en un paso
separado y explícito. **✅ ESTADO FINAL (2026-06-28 tarde, fase 3): STRANGLER CERRADO — God Object 100% desmantelado.** Tras el
**paso #2** (catálogos → `CatalogController`, carveado junto con su rediseño), `AdminController` quedó **vacío** y fue **RETIRADO** a
`_legacy_backup/decommission-2026-06-28/app/Http/Controllers/` (+ `composer dump-autoload`); **cero referencias vivas** en `route:list`.
Los 7 pasos #1-#7 están hechos. La bitácora cronológica de cada corte va en [PROGRESS.md](PROGRESS.md) (entradas **🏗️ Estructura** /
**🏗️ Rediseño de catálogos**).

### Roadmap (ordenado, riesgo-rateado)
| # | Corte | Métodos | Riesgo | Estado / nota |
|---|---|---|---|---|
| 1 | **CrewListController** | `usuarioscrud`, `comcrud`, `medicocrud`, `idcardscrud`, `idcard` | **muy bajo** (read-only, movimiento puro) | ✅ **DONE (2026-06-27)**. |
| 2 | **Catalog CRUD** → `CatalogController` | deptos (`creardepartamento`/`departamentocrud`/`activardepartamento`/`desactivardepartamento`/`editardepartamento`/`savedepartamento`), puestos (`positionscrud`/`crearpositions`/`editpositions`/`saveposition`), notificaciones (`crearnotificacion`/`notificacioncrud`/`activarnotificacion`/`desactivarnotificacion`/`editarnotificacion`/`savenotificacion`) | bajo-medio | ✅ **DONE (2026-06-28 tarde, fase 3)** — carveado a **`CatalogController`** **junto con su rediseño**: opera sobre las **tablas NUEVAS** `departments`/`positions` (+ `sort_order`), no las legacy; Notificaciones siguen en `usuariosnotificacione`. Endurecido: validación (cierra **H4** en catálogos), GET→POST en activaciones, `findOrFail`, `whereNumber`, `@can('catalogs.manage')`. Con esto `AdminController` quedó vacío y **RETIRADO**. |
| 3 | **Crew create** → `CrewController` | `adduser`, `newuser` | **bajo** (mecánico) | ✅ **DONE (2026-06-28)** — extraído verbatim a **`CrewController`** (escrituras de crew); movimiento puro. El perfil (paso 4) se le suma. |
| 4 | **Crew profile edit** (→ `CrewController`) | `useredit`, `acountupdate` | **ALTO** | ✅ **DONE (2026-06-28)** — sumado a **`CrewController`** y **endurecido**: scope por depto vía el helper **`User::canManageCrewMember`** (`findOrFail`+`abort_unless 403`) → cierra **H2** en este path; `$request->except('password')` → **whitelist validada** (11 campos del form) → cierra **H4** en `acountupdate`. |
| 5 | **Crew status toggles** → `CrewStatusController` | `checkgft`, `uncheckgft`, `activarusuario`, `activarencuesta`, `desactivarusuario` | medio | ✅ **DONE (2026-06-28)** — extraídos a **`CrewStatusController`** y endurecidos: guard de scope por depto (H2) vía `findOrFail`+`canManageCrewMember`; `activarusuario`/`activarencuesta` GET→POST+CSRF (H1). |
| 6 | **Admin-grant + grupos de prueba** → `CrewStatusController` | `activaradmin`, `desactivaradmin`, `putga`, `putgb`, `putgg`, `selectpuesto` | medio | ✅ **DONE (2026-06-28)** — extraídos a **`CrewStatusController`** con guard de scope por depto (H2); el grant admin conserva `$producto->admin=1` explícito (no mass-assignable). |
| 7 | **Sueltos a su dominio** | `historialWR` → `cmedicController`, `welcomeresend`/`sendreminder` → `CrewMailController`; ~~limpiar export CSV muerto (`expCsv`/`exportCsv`)~~ | bajo | ✅ **DONE (2026-06-28)** — `historialWR` movido verbatim a **`cmedicController`** (dominio médico); `welcomeresend`/`sendreminder` a NUEVO **`CrewMailController`** (gate `admin`); export CSV muerto ya borrado (el vivo es `resetController@expCsv`→`/nophoto`). |

### Caveat — rediseño de catálogos (paso 2) → ✅ RESUELTO (2026-06-28 tarde, fase 3)
Era: los catálogos tenían un **rediseño pendiente** (normalización, `sort_order`, **homologar** con `adduser`/`Department`/`Position`),
por lo que carvearlos antes arriesgaba doble trabajo → se prefería carvear el paso 2 **junto con** el rediseño. **Eso es justo lo que se
hizo:** el paso #2 se carveó a `CatalogController` **operando sobre las tablas nuevas** `departments`/`positions` (+ `sort_order`,
reemplazando el hack muerto `users.labn`) → catálogo y formulario de alta por fin coinciden. Caveat cerrado.

### Nota H2 (IDOR / scope por depto en acciones admin sobre `{id}`) — defensa en profundidad, NO explotable hoy
Las acciones admin que cargan un `User::find($id)` arbitrario (incl. `idcard` movido en el paso 1, y los pasos 4-6) **no**
verifican que el `{id}` pertenezca al departamento del actor. **Hoy NO es explotable:** verificado contra
`database/seeders/RolesAndPermissionsSeeder.php`, los roles que tienen `users.update`/`users.assign-role`/`users.deactivate`
**también tienen `crew.view.all-departments`** → nadie que pueda invocar esas acciones está restringido por departamento. H2
es por tanto un **gap latente de defensa en profundidad** — importaría solo si un rol futuro recibiera esas perms **sin**
`all-departments`. El scope se añade cuando se carven/endurezcan los métodos de mutación (pasos 4-6), no en los cortes de
movimiento puro. **Avance (2026-06-28):** el **paso #4** (perfil, `useredit`/`acountupdate`) cerró H2 en ese path, y los **pasos
#5/#6** (toggles/roles → `CrewStatusController`) lo **cerraron en TODAS las acciones admin sobre `{id}`** — todas vía el helper
**`User::canManageCrewMember`** (`findOrFail`+`abort_unless 403`). H2 deja de ser un gap latente en estos paths.

---

## Cómo navegar este repo rápido
- **Lógica de negocio:** repartida tras desmantelar `AdminController` — `CrewController`/`CrewStatusController`/`CrewListController` (crew),
  `DepartmentController`/`PositionController`/`NotificationController` (catálogos — partidos de `CatalogController` el 2026-07-07, ahora en `_legacy_backup/`) y `queueController.php` (pruebas). (`AdminController` ya **no existe** — retirado a `_legacy_backup/`.)
- **Reglas de acceso:** `app/Http/Kernel.php` + `app/Http/Middleware/AdminMiddleware.php` + flags en `app/Models/User.php`.
- **Rutas:** todo en `routes/web.php`; mapear ruta→método ahí.
- **Pantallas:** `resources/views/admin/*` (panel) y `resources/views/layouts/sidebar.blade.php` (navegación por rol).
- **Build front:** `webpack.mix.js` + `resources/js`/`resources/sass` → `npm run dev/prod`.
