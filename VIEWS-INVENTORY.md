# VIEWS-INVENTORY.md — Inventario y auditoría de vistas

Auditoría read-only de las **95 vistas Blade** (+2 archivos backup), repartida en 5 cortes
y cruzada con rutas/controllers para evaluar **funcionalidad real, arquitectura y
performance** (carga, consultas, N+1). No se modificó código.

> ## ⚠ ACTUALIZACIÓN 2026-08-06 (catch-up post-compact — deltas #40-#51)
> El inventario de abajo (95 vistas, 2026-06-24) quedó atrás: hoy son **188 archivos `.blade.php`** (casi el doble).
> Los módulos nuevos NO reintroducen los 3 males del snapshot (3 frameworks CSS / jQuery roto / shell pesado):
> corren sobre el **design system "Cinematic Dark Glass"** con tokens y el **chrome de reporte v2 compartido**
> (`resources/views/componentes/_report-v2-head`, `_report-v2-toolbar`, `_doc-hero`, `_report-v2-foot`) — un solo
> lugar para fuentes/CSS/motor de impresión. Directorios de vistas nuevos:
> - **Top-level nuevos:** `epi/`, `inspection/`, `permits/`, `avisos/` (aviso de privacidad), `accesos/`.
> - **Bajo `admin/`:** `scoutings/`, `dailyreports/`, `riskmaps/`, `medevac/`, `hazard-events/`, `standards/`,
>   `sfx/`, `sfx-effects/`, `consumables/`, `lite/`, `features/`, `badge/`, `wrap/`, `partials/`.
> - **`componentes/`** creció con parciales compartidos: `_report-v2-*`, `_doc-hero(-styles)`, `_rm-icon`,
>   `_hazard-activity-picker`, `_risk-matrix`, `_typeahead`, `_icon` (Lucide), `_confirm-submit`, `_doc-hero`.
> - **`medical/`** y **`correos/`** también crecieron (bitácora, materialidad, consulta compartida crew+lite; magic links).
> Cada vista de reporte sellable termina en `_report-v2-foot` (cierra `</article></div>` + footer + JS de tema/impresión).
> Detalle vivo por módulo en la **memoria** (`/memory/*.md`).

Leyenda de clasificación: **KEEP** (sirve, conservar) · **REFACTOR** (sirve pero deuda) ·
**DEDUPE** (colapsar con su gemela en un parcial/componente) · **REMOVE** (muerta/COVID/backup).

---

## Resumen ejecutivo

- **~30 vistas se ELIMINAN** (COVID/PCR + muertas + backups) — la mayor parte del recorte.
- **~20 vistas COLAPSAN** en pocos componentes parametrizados (CrewList, search*, CRUD catálogos, correos).
- **~19 se CONSERVAN** y **~28 piden REFACTOR**.
- **El módulo `dailyreports/*` es el benchmark de calidad** — sirve de patrón a estandarizar.

### Los 3 problemas de arquitectura más graves (transversales)
1. **Conviven TRES frameworks CSS** + **TRES versiones de jQuery**:
   - CSS: **Bootstrap 5** (mayoría) + **Materialize** (CRUD catálogos, pcrcrud/pcredit, consultas)
     + **Tailwind por CDN** inyectado en `dailyreports/show`.
   - jQuery: CDN 3.3.1 (roto, ver abajo) + el del bundle + **1.11.2** en vistas de impresión +
     **Bootstrap 4 + jQuery 3.4.1 recargados** por CDN dentro de `perfil`/`profile`/`inicio`.
2. **El "shell" (`layouts/app`) carga librerías pesadas en CADA página** aunque casi nunca se usen:
   TinyMCE 6, Chart.js, Font Awesome, Bootstrap Icons y **5 cargas solapadas de Google Fonts**,
   todo render-blocking en `<head>`, sin Laravel Mix ni versionado. Es el mayor costo de carga app-wide.
3. **Bug app-wide:** `<script src="{{ asset('https://code.jquery.com/...') }}">` — envolver una URL
   absoluta con `asset()` la rompe (genera `tudominio/https://...` → 404). jQuery 3.3.1 nunca carga;
   la app "funciona" por el jQuery del bundle. Es bug + redundancia repetidos en casi todas las vistas.

---

## Inventario por corte (tablas compactas)

### Corte 1 — Shell / Auth / Dashboard (19)
| Vista | Clase | Nota |
|---|---|---|
| layouts/app · header · sidebar | **REFACTOR** | Shell carga todo; menú duplicado desktop/móvil ×3 roles; `href="#"` rotos en rol supervisor; branding cliente quemado ("REDRUM") |
| auth/login | KEEP | Login real; mixed-content `http://` + jQuery roto |
| auth/register | **REMOVE** | Sin alta pública |
| auth/verify · passwords/confirm | **CONSERVAR** | *(2026-06-24)* Antes "REMOVE", ahora **se CONSERVAN**: referenciadas por los traits de Laravel UI (`VerifiesEmails`→`view('auth.verify')`, `ConfirmsPasswords`→`view('auth.passwords.confirm')`); aunque sus rutas estén deshabilitadas, borrarlas arriesga un 500 en auth. **Reevaluar en el vertical `auth`.** |
| auth/passwords/email · reset | **REFACTOR** | `email` duplica el `<head>`; `reset` **sin `@extends`** (estructura rota) |
| home | **REMOVE** | Vista muerta — `/home` renderiza `inicio`, no `home` |
| inicio | **REFACTOR** | Dashboard vivo; CSS inline, recibe ~5 colecciones que no usa, fecha rodaje **hardcoded 2025-04** |
| perfil | **REMOVE** | `index()` no ruteado; casi idéntica a `profile` |
| profile | **REFACTOR** | Viva pero recarga BS4+jQuery sobre BS5; bloque cropper repetido ×4 |
| changepassword | KEEP | Inputs Materialize → migrar a BS |
| welcome · selectdepartamentos · modal/modelformulario | **REMOVE** | Default Laravel / huérfana / parcial COVID sin uso |
| vendor/laravelpwa/meta | KEEP | Vendor PWA |
| vendor/laravelpwa/offline | **REFACTOR** | Hereda layout que exige `auth()` + CDNs → falla sin red |

### Corte 2 — Salud / Médico / COVID-PCR (16)
| Vista | Clase | Nota |
|---|---|---|
| formulario | **REPURPOSE** | Historial clínico (~50 campos). Quitar `vacci1`/`crt19` COVID; **doble guardado** (POST + fetch GET) a unificar |
| admin/cmedica | **REPURPOSE** | Núcleo Medical Reports. Bug typo `ovservations`; IMC con riesgo div/0; quitar fila COVID |
| admin/medicocrud | **REPURPOSE** | Entrada del módulo médico. Bug `<tr>` faltante |
| accesos/autorizado · noautorizado | **REMOVE** | Solo las renderizan métodos COVID muertos |
| admin/comcrud | **DEDUPE** | Duplicado de usuarioscrud; desacoplar `enfermo` |
| admin/consultas | **REMOVE** | Huérfana (ruta comentada, sin método) |
| admin/checkpoint | **REMOVE** | Checkpoint temperatura COVID |
| admin/checkpoint-mobile | **REMOVE** | Copia byte-a-byte de checkpoint, sin ruta |
| admin/pcrcrud · pcredit | **REMOVE** | PCR puro (Materialize) |
| pcrtest/listcrew · mainlab | **REMOVE** | Lab COVID |
| virtualqueue/antigentest · crudtest · scheduletest | **REMOVE** | Cola de pruebas COVID (scheduletest = único uso vivo de TinyMCE) |

### Corte 3 — Health & Safety (KEEP/core) (18)
| Vista | Clase | Nota |
|---|---|---|
| dailyreports/create | **KEEP** ⭐ | **Benchmark**: BS5 puro, validación, secciones, sin inline |
| dailyreports/show | KEEP/**REFACTOR** | Rica (heatmap, lock 24h) pero **inyecta Tailwind por CDN** |
| dailyreports/index | KEEP | **N+1**: `logs->count()` ×2 por fila → usar `withCount` |
| injuryreports · injuryreport(show) · injuryreportcreate | KEEP/REFACTOR | Funcionan; JS monolítico; `$user` muerto; agrupado vs paginación |
| injury_report_preview | **REMOVE** | Dump de debug `print_r`, sin ruta |
| hazardnotification · unsafecondnotification | **DEDUPE** | Gemelas; bloque JS imágenes idéntico (también en injury) |
| hazards · unsafeconds | **DEDUPE** | Copia exacta entre sí; `::all()` **sin paginar**; hazards muestra campo equivocado |
| unsafecond(show) | KEEP | — |
| location · locationreport (V1) | KEEP/**REFACTOR** | 161 campos hardcodeados → iterar por array; **store V1 redirige a ruta inexistente → 500 al guardar** |
| location2 · locationreport2 (V2) | **DEDUPE** | Prototipo incompleto (~9/161 campos), fuera del menú; consolidar con V1 |
| locationcrud | KEEP/**REFACTOR** | `locationreport::all()` (161 cols) sin paginar ni `select` — perf alta |

### Corte 4 — Admin CRUD + Search/History + Imports (26 incl. 2 backups)
| Vista | Clase | Nota |
|---|---|---|
| usuarioscrud | **REFACTOR** | `SELECT *` (expone hash); `<tr>` roto; menú duplicado con searchusers |
| useredit | **REFACTOR** | `password` en `type=text`; lista de deptos **hardcoded** (duplicada/desincronizada con newuser) |
| newuser | KEEP | La más limpia del corte; deptos a fuente única |
| departamentocrud · positionscrud · notificacioncrud | **REFACTOR/DEDUPE** | Mismo patrón CRUD; **Materialize**; confirms copy-paste ("desactivar usuario" en depto) |
| editardepartamento · editarposition · editarnotificacion | **REFACTOR/DEDUPE** | `editarposition` con **bug `<select>` duplicado** |
| idcard | **REFACTOR** | 2 queries inútiles (`puesto::all`/junction) no usadas; jQuery 1.11.2; ya **no** genera barcode |
| idcardscrud | **REFACTOR** | `SELECT *`; JS búsqueda duplicado |
| imports/import | KEEP | typo `containe`; sin validar tipo de archivo |
| imports/uploadbulk | **REMOVE** | Archivo vacío |
| componentes/departamentousuarios | **REMOVE** | Huérfano |
| componentes/historia | KEEP(revisar) | Ruta viva pero ya casi no enlazada |
| componentes/historiamr | KEEP/**REFACTOR** | La única "historia" viva (médico); `->get()` sin select sobre tabla ancha |
| componentes/historiawr | **REMOVE** | Supersedida por historiamr |
| componentes/searchcom | **ELIMINADA** | *(2026-06-24)* Huérfana: `AdminController@searchcom` (ruta `/searchcom`) devuelve `view("componentes.searchusers")` (AdminController.php:403), **no** `searchcom`. Respaldada en `_legacy_backup/`. **🚩 Bug marcado para el owner:** `searchcom` renderiza la vista de `searchusers` (copy-paste): o `/searchcom` es redundante con `/searchusers`, o debería hacer algo distinto. |
| componentes/search{users,idcard,doctor} | **ELIMINADAS** | *(2026-06-24)* Parciales huérfanos tras la consolidación del `SearchController` por preset (sin caller vivo; reemplazados por `componentes/search-results.blade.php` y `componentes/search-results-idcard.blade.php`). Respaldados en `_legacy_backup/`. |
| componentes/search{lab,eckpoint} | **ELIMINADAS** | *(2026-06-24)* Búsqueda COVID (`searchlab`/`searcheckpoint`): método + ruta + parcial borrados. Respaldados en `_legacy_backup/`. Páginas host (`pcrtest/listcrew`, `admin/checkpoint`) siguen pendientes de decommission COVID. |
| componentes/searchqueue | KEEP (COVID) | Sigue viva (`queueController@searchqueue`); se retira con la cola en el decommission COVID |
| admin/idcard.bladeOLD · searchusers.bladeBKP | **ELIMINADAS** | *(2026-06-24)* Backups (no compilan / no renderizables por Laravel). Ambas respaldadas en `_legacy_backup/`. |

### Corte 5 — Correos (18) → sobreviven **3** (+1 reaprovechada como layout)
| Plantilla | Clase | Nota |
|---|---|---|
| welcomeuser | **KEEP** | Alta de crew (canónica); limpiar firmas placeholder/typo URL |
| recordatorio | **KEEP/REPURPOSE** | Recordatorio de Reporte de Salud (formulario); limpiar branding COVID; bug `from` |
| photo | **KEEP** | Recordatorio de foto de gafete |
| CrewCareTemplate | **REPURPOSE** | Única no-COVID; convertir en **layout base** de correos |
| bienvenida | **REMOVE/DEDUPE** | Redundante con welcomeuser |
| negative · negativean · positive · notifypositive · notificaciones · certificados · pruebas · pruebasentrada · avisos · avisosg · nuevoingreso · personalizado · testnotification | **REMOVE (COVID)** | Resultados/convocatorias/cola de pruebas (8 son resultado de prueba) |

> **Correos:** no hay layout maestro — el HTML está **copy-pasteado en 18 archivos** (dos familias
> de boilerplate, ~7.000 líneas totales). `negative`≡`negativean`, `avisos`≡`personalizado`,
> `avisosg`≡`pruebasentrada` son duplicados casi exactos. Branding/firmas/from hardcodeados en 4
> dominios distintos.

---

## Bugs reales encontrados (no solo deuda)
1. **`location` V1 `store()` redirige a `route('locationreport.index')` inexistente → error 500 al guardar.**
2. **`asset('https://...')` roto** para jQuery 3.3.1 en casi todas las vistas (app-wide).
3. `admin/cmedica`: input `name="ovservations"` vs validación `observations` (el `old()` nunca repuebla) + IMC con división por cero si `size=0`.
4. `medicocrud` y `usuarioscrud`/`searchusers`: falta `<tr>` de apertura en el `@foreach` → HTML inválido.
5. `editarposition`: `<select>` de departamentos con opción duplicada y label = id.
6. `formulario`: **doble guardado** (POST a `/formularios/registro` + fetch GET a `/registrarformulario`).
7. `hazards`: la tarjeta muestra `description_unsafe_cond` (campo de otra tabla → siempre vacío).
8. `ReminderEmail` job: `from("covid@crewcare.tech,"CrewCare")` comilla sin cerrar; `encuestasTask` usa from Gmail personal.
9. **Exposición:** los listados pasan `SELECT *` de `users` (incluye `password` hash y tokens) a la vista.

## Performance (de las vistas + sus controllers)
- **N+1:** `dailyreports/index` (`logs->count()` ×2/fila) → `withCount('logs')`.
- **Sobre-selección:** `locationcrud` (`::all()` de 161 columnas), `historiamr` (`->get()` sin select), `SELECT *` en todos los CrewList/search.
- **Sin paginar:** `hazards`, `unsafeconds`.
- **Queries desperdiciadas:** `idcard()` ejecuta `puesto::all()` + junction que la vista no usa; `HomeController` calcula ~5 colecciones que `inicio` no renderiza + bucle `while` con 2 `count()` por semana.
- **Lógica en vista:** contador `{{$nc=$nc+1}}` en `antigentest`; cálculos `Carbon::age`/IMC en `historiamr`/`cmedica`.

---

## Veredicto de arquitectura: ¿es idónea? (No — pero el camino es claro)

**Hoy no es idónea:** 3 frameworks CSS + 3 jQuery, shell pesado en cada carga, ~30 vistas
muertas/COVID como ruido, plantillas clonadas 6-7×, `SELECT *` y N+1 dispersos. Pero **no requiere
reinventar** — requiere **estandarizar**. Objetivo:

1. **Un solo stack visual: Bootstrap 5.** Retirar Materialize y el Tailwind-CDN; un jQuery (o ninguno) cargado una vez.
2. **Un layout + parciales/componentes** en vez de copiar markup:
   - `_crew-table` (parametrizado por columnas + acción) ← colapsa los **7 search\*** + el cuerpo de usuarioscrud.
   - `_print-script`, `_image-fields`, `_zone-badge`, `_group-badge` ← parciales repetidos hoy a mano.
   - CRUD genérico de catálogos ← departamento/posición/notificación + sus `editar*`.
   - **Correos:** 1 `layouts/base` (de CrewCareTemplate) + 3-4 secciones de contenido + branding/from en **config** (clave para el modelo per-cliente).
3. **Datos sanos en los controllers:** `select()` de columnas (nunca `SELECT *` con hash), `with()/withCount()` (eager load), `paginate()` siempre.
4. **Carga condicional de assets:** TinyMCE/Chart.js/etc. solo en las vistas que los usan (`@push/@stack`), pasar a **Laravel Mix + `mix()`** (bundle + cache-busting).
5. **Branding/textos por cliente → config**, no quemado en header/login/correos (habilita la "receta" de despliegue por productora).

**Patrón de referencia:** estandarizar sobre **`dailyreports/create` + el controlador DSR**
(BS5 puro, validación explícita, eager load, catálogo `SafetyStandard`), corrigiendo antes su N+1
de index y el Tailwind-CDN de show.

### Borrado seguro inmediato (vistas muertas, sin dependencias)
*(2026-06-24 — pasada de limpieza de código muerto CERRADA: ~~tachadas~~ = eliminadas y respaldadas en `_legacy_backup/`.)*
~~`home`~~, ~~`welcome`~~, ~~`selectdepartamentos`~~, ~~`modal/modelformulario`~~,
`auth/verify` *(CONSERVADA — referenciada por trait Laravel UI `VerifiesEmails`; reevaluar en vertical `auth`)*,
`auth/passwords/confirm` *(CONSERVADA — referenciada por trait Laravel UI `ConfirmsPasswords`; reevaluar en vertical `auth`)*,
~~`injury_report_preview`~~, ~~`imports/uploadbulk`~~,
~~`componentes/departamentousuarios`~~, ~~`componentes/historiawr`~~, ~~`componentes/searchcom`~~ *(bug marcado: el método devuelve `searchusers`)*,
`admin/consultas` *(flagged: ruta comentada → se deja)*, ~~`admin/checkpoint-mobile`~~, ~~`admin/idcard.bladeOLD.php`~~,
~~`componentes/searchusers.bladeBKP.php`~~.
