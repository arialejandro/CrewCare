# ROUTES.md — Matriz de rutas y control de acceso

Referencia del routing (auditoría read-only). El detalle fila-por-fila vive en `routes/web.php`
(~210 líneas); aquí está el **modelo de acceso, los bugs de routing y las listas completas** de
rutas problemáticas. Complementa [SECURITY.md](SECURITY.md) y [ARCHITECTURE.md](ARCHITECTURE.md).

## Modelo de control de acceso (importante)
- El grupo **`web`** aplica a todo `web.php`. Solo **2 controllers** ponen middleware en su
  constructor: `HomeController` (`auth`) y `AdminController` (`admin`). **Todos los demás**
  (location, queue, Injury, Hazard, DailyReport, Perfil, reset, encuestas, Formularios, cropimage)
  dependen **exclusivamente** del grupo de la ruta.
- Acceso efectivo a nivel ruta es **binario**: guest / auth / admin. El "rol" `daytest` (0/1/2)
  **no se valida en ninguna ruta** — solo ramifica menús en vistas. `AdminMiddleware` exige
  `admin` pero **no** `activo`. `niveldos` no está ni registrado.
- **`api.php`:** un solo endpoint `GET /api/user` (`auth:sanctum`); `EnsureFrontendRequestsAreStateful`
  comentado; rate limit 60/min (RouteServiceProvider). **`channels.php`:** canal privado por usuario
  (scaffold). **`console.php`:** solo `inspire` (el scheduler real está en `app/Console/Kernel.php`).

## Reparto aproximado
- ~150 rutas bajo `admin`; `/home` bajo `auth`; el resto (auth scaffolding, resets, perfil/password,
  formularios, locale, offline, crop) bajo **solo `web` (sin auth)**.

---

## 🔴 Punch-list de BUGS de routing (a corregir)
1. ~~**`locationController@store` → `redirect()->route('locationreport.index')` — name INEXISTENTE → HTTP 500**~~
   **✅ RESUELTO (2026-06-28)** — el redirect roto de locación V1 se corrigió en la pasada de verticales (ahora redirige al index válido).
2. ~~**`unsafecondNotificationController@store` redirige a `unsafenotifications.store` (ruta POST)** → 405/re-dispara el form.~~
   **✅ RESUELTO (2026-06-28)** — corregido a `unsafenotifications.index` en la pasada de verticales. (El **hazard** `@store` tenía el
   mismo patrón roto, `hazard_notifications.create`; también corregido a `.index`.)
3. **`Route::get('/admin/departamentocrud')` SIN acción** (`web.php:46`) — registrada sin destino → error si se visita. El CRUD real es `/departamentocrud`. Eliminar.
4. ~~**`daily_reports.pdf` → `DailyReportController@downloadPdf` NO implementado**; `Browsershot` importado sin usar.~~
   **✅ RESUELTO (2026-06-28)** — la **ruta huérfana `daily_reports.pdf` se ELIMINÓ** (apuntaba a un método inexistente = 500 latente;
   ninguna vista la enlazaba) y el import `Browsershot` se quitó. Export PDF queda como **feature pendiente, no ruteada**. Verificado con
   `route:list` (ya no existe).
5. **Rutas/nombres duplicados:** `/profile` (name `perfil`) definido 2× (`web.php:191` admin y `:206` auth) →
   gana el `auth`, así que `/profile` queda accesible a cualquier autenticado, no solo admin. El name
   `encuesta` también está 2× (`:37` y `:38`) → `route('encuesta')` sin parámetro falla.
6. Nombres internos con typo (no rompen, pero ensucian): `curdtest` (`:131`).

---

## 🔴 Rutas GET que MUTAN estado (CSRF — migrar a POST/PUT) — lista completa (~24)
`/checkgft/{id}`, `/uncheckgft/{id}`, `/notoday/{id}`, `/notodays/{id}`,
**`/activaradmin/{id}` (setea `admin=1`)**, `/desactivaradmin/{id}`,
`/activardepartamento/{id}`, `/activarnotificacion/{id}`, `/negativepcr`,
`/negativeantg`, `/usertested/{id}`, `/remindertest`.
> **Ya RESUELTAS / RETIRADAS de esta lista:** `/activarusuario/{id}` y `/activarencuesta/{id}` → **POST+CSRF**
> (2026-06-28). `/newdayRep`/`/PhotoReminder`/`/newTD`/`/newWR`/`/Nresult`/`/pruebachedule` → **ELIMINADAS**
> (duplicados del comando agendado `encuestas:task`). `/welcomeresend/{id}` y `/sendreminder` → **ELIMINADAS**,
> convertidas a comandos Artisan `crew:welcome-resend`/`crew:daily-reminder` (2026-06-28). `/reminder-mail`/`/negative-mail`
> siguen GET pero ya **gateadas a `admin`**. Verificado con `route:list`.

## 🔴 Rutas que MUTAN estado SIN autenticación (críticas — C2/C3 de SECURITY)
**`/updatepassword/{id}`** (cambia password por id), `/formularios/registro`,
`/formularios/medicos`, **`/update`** (mass-assign → auto-admin), `/crop-image-upload`,
`/changepassword`.
> **Ya RESUELTAS:** los resets globales `/newdayRep`/`/PhotoReminder`/`/newTD`/`/newWR`/`/Nresult`/`/pruebachedule`
> **ELIMINADOS**; `/reminder-mail` **gateado a `admin`**; `/nophoto` (CSV con PII) **gateado a `auth`+`permission:reports.export`**;
> el cluster password/perfil/formularios movido dentro de `auth`. Ver C3 de [SECURITY.md](SECURITY.md). Verificado con `route:list`.
> ~~Un anónimo puede auto-promoverse a admin vía `/update` o `/updatepassword/{id}`~~ — `admin` fuera de `$fillable` (C2 ✅) +
> rutas gateadas (C3 ✅).

## Rutas muertas / comentadas
- Comentadas: `/consultas`, `/cargarusuarios/{id}`, `/historial/{id}` (duplicada).
- Acciones huérfanas (no ruteadas): `encuestasController@checkfroms`, `FormulariosController@newformulario1`.

## Cambios de autorización por ruta
- **(2026-06-28)** `historialWR/{id}` (name `historialwr`, `cmedicController@historialWR`) **MIGRÓ del grupo legacy `admin` →
  `permission:medical.view`** (middleware verificado: `web,auth,permission:medical.view`). Antes lo gateaba el flag binario `admin=1`;
  ahora un **permiso spatie** (lo tienen `super-admin`/`coordinator`/`safety-officer`/médico, etc.). Los enlaces "Historial Médico" en
  `admin/usuarioscrud` y `componentes/search-results` se envolvieron en `@can('medical.view')` para no mostrar un link que daría 403.

## Verificación de `route('...')` cruzada
Se cruzaron 90+ usos de `route()` contra los names definidos: **solo `locationreport.index` está roto**
(bug #1). El resto (`account.update`, `pcrcrud`, `location2.show`, `daily_reports.*`, `hazard_notifications.*`,
auth, `laravelpwa.manifest`, etc.) resuelve correctamente.
