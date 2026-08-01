# SEARCH-DOSSIER.md — Patrón de "búsqueda" de CrewCare

> **Documento READ-ONLY de diagnóstico.** No se modificó ningún archivo de aplicación.
> Objetivo: descifrar el patrón actual de búsqueda AJAX para diseñar un reemplazo unificado, seguro y DRY.
> Fecha de análisis: 2026-06-24. Referencias citadas como `archivo:línea`.

---

## 1. Resumen

CrewCare expone **8 endpoints "search\*"** (7 que devuelven una tabla HTML para inyección AJAX + 1 JSON moderno):

- 6 en `AdminController` (`searchusers`, `searchcom`, `searchdoctor`, `searcheckpoint`, `searchidcard`, `searchlab`).
- 1 en `queueController` (`searchqueue`).
- 1 moderno/aislado en `InjuryReportController` (`searchUsers`, devuelve JSON, contrato distinto).

El patrón dominante (los 7 que devuelven HTML) es **casi idéntico**: misma firma `($valor)`, misma ruta `/{nombre}/{valor}/`, mismo "sentinela" `"vacio"`, mismo `DB::table('users')` con `LIKE` + cadena de `Orwhere`, misma paginación `paginate(50)`, y devuelven un **parcial Blade** (`componentes/search*.blade.php`) que el front inyecta con `$('#usertable').html(...)`. Es un caso de copy-paste masivo.

**Hallazgos críticos:**
1. **`searchcom` y `searchdoctor` son código MUERTO**: ningún `.blade.php` los invoca. Los CRUD `comcrud` y `medicocrud` llaman a `/searchusers/` (reciclaje), no a sus endpoints propios. (Ver §3 y §6.)
2. **`searchusers` recicla la vista** `componentes.searchusers` para 3 CRUD distintos (usuarios, com, médico).
3. **Fuga de datos**: las búsquedas usan `DB::table('users')` (query builder, **no Eloquent**), por lo que el `$hidden` del modelo `User` **NO aplica** y el `SELECT *` trae el hash `password` en el conjunto de resultados (no se imprime en el HTML actual, pero viaja en memoria/depuración y es trivial de exponer). Las vistas sí exponen **email, fecha de nacimiento, sexo, teléfono** (PII) en HTML sin minimización (cross-ref SECURITY.md M6 `:200-204`).
4. **Bug de precedencia OR en `searchlab`/`searchqueue`**: mezclan `where(...)->Orwhere(...)->where(...)` sin agrupar, lo que rompe los filtros clínicos COVID en la rama OR (devuelve usuarios que no deberían listarse). (Ver §4.)
5. **HTML roto** en `componentes/searchusers.blade.php` y `componentes/searcheckpoint.blade.php`: falta la etiqueta `<tr>` de apertura dentro del `@foreach`. (Ver §2.)
6. Restos COVID (`labn`, `resultpcr`, `daytest`, `tested`, `inline`, `temperatura`) embebidos en varias queries/vistas (cross-ref COVID-DECOMMISSION.md).

---

## 2. Endpoints de búsqueda (tabla maestra)

| Método | Controlador:línea | Ruta (web.php) | URI / params | Query (tabla · columnas · filtros) | Vista devuelta | Middleware / Seguridad |
|---|---|---|---|---|---|---|
| `searchusers` | `AdminController:367` | `web.php:106` | `GET /searchusers/{valor}/` | `DB::table('users')` · `SELECT *` · `WHERE name LIKE` **OR** `lname` OR `lname2` OR `email`; `paginate(50)`; sentinela `vacio` → últimos 50 por `id desc` | `componentes.searchusers` | `admin` (grupo `web.php:44`) |
| `searchcom` ⚠ **MUERTO** | `AdminController:387` | `web.php:107` | `GET /searchcom/{valor}/` | Idéntica a `searchusers` (`SELECT *`, mismas 4 columnas LIKE) | `componentes.searchusers` (**recicla**) | `admin` |
| `searchdoctor` ⚠ **MUERTO** | `AdminController:407` | `web.php:108` | `GET /searchdoctor/{valor}/` | `DB::table('users')` · `SELECT *` · `name`/`lname`/`lname2` LIKE (sin email) | `componentes.searchdoctor` | `admin` |
| `searcheckpoint` | `AdminController:426` | `web.php:110` | `GET /searcheckpoint/{valor}/` | `DB::table('users')` · `SELECT *` · `name`/`lname`/**`labn`** (COVID) LIKE | `componentes.searcheckpoint` | `admin` |
| `searchidcard` | `AdminController:445` | `web.php:111` | `GET /searchidcard/{valor}/` | `DB::table('users')` · `SELECT *` · `name`/`lname`/**`labn`** (COVID) LIKE | `componentes.searchidcard` | `admin` |
| `searchlab` | `AdminController:464` | `web.php:109` | `GET /searchlab/{valor}/` | `DB::table('users')` con `switch($diatest->mainday)`; filtros clínicos `activo=1 daytest=N inline=0 tested=1 resultpcr=0`; LIKE `name`/`lname`/**`labn`**. **Bug OR** (ver §4) | `componentes.searchlab` | `admin` |
| `searchqueue` | `queueController:40` | `web.php:112` | `GET /searchqueue/{valor}/` | Igual estructura que `searchlab` (`switch mainday`, filtros COVID `tested=0`), LIKE `name`/`lname`/**`labn`** | `componentes.searchqueue` | `admin` |
| `searchUsers` (moderno) | `InjuryReportController:130` | `web.php:189` | `GET /users/search?term=` | **Eloquent** `User::query()` · `->get([...])` **columnas explícitas** (`id,name,lname,lname2,position,borndate,phone,zone`) · `limit(10)` · `name`/`lname`/`lname2` LIKE agrupado correctamente con closure | **JSON** (no Blade) | `admin` |

Notas:
- Todos los endpoints HTML están dentro del grupo `Route::group(['middleware' => 'admin'], ...)` (`web.php:44`), por lo que **sí** exigen rol admin. No están abiertos (a diferencia de `/nophoto` `web.php:201`, que está fuera del grupo — fuera de alcance de este dossier).
- `searchusers`/`searchcom`/`searchdoctor`/`searcheckpoint`/`searchidcard` usan `whereDate`/fechas **no**; sólo `historial`/`historialWR` (`AdminController:328-365`) aplican ventana de 90 días + datos clínicos (cross-ref SECURITY.md M6/H2). No son "search\*" pero comparten el patrón de parcial Blade y se incluyen como contexto en §4.

---

## 3. Vistas componentes (duplicación)

Archivos bajo `resources/views/componentes/`:

| Vista | Columnas que renderiza | Acciones por fila | Estado |
|---|---|---|---|
| `searchusers.blade.php` | Nombre, Apellido, Depto, **F.Nac**, **Teléfono**, **Sexo**, **Email** + dropdown enorme de acciones (notsick, idcard, useredit, historialWR, activar/desactivar admin, putga/putgb/putgg, activar encuesta, des/activar) | Muchas (roles + estado) | ⚠ **HTML roto**: `@foreach` (línea 19) abre directo con `<td>` sin `<tr>` |
| `searchdoctor.blade.php` | Nombre, Apellido, Apellido2, Depto, **F.Nac**, **Teléfono**, **Email**, Historial | Sólo "Historial" (`/historialWR`) | Vista para endpoint **muerto** |
| `searcheckpoint.blade.php` | Zone (badge), F.Name, L.Name, L.Name2, Auth (encuesta diaria) | — | ⚠ **HTML roto**: falta `<tr>` (línea 18-19); `<tr>` mal cerrado dentro de `@if` (línea 47) |
| `searchidcard.blade.php` | Zone (badge), Photo (`imgperfil`), F.Name, L.Name, L.Name2, View (idcard), Printed (check/uncheck gafete `age`) | idcard, checkgft/uncheckgft | OK estructural |
| `searchlab.blade.php` | Zone (badge), F.Name, L.Name, **puestodepartamento**, Actions (`positivepcr` COVID) | Reportar positivo PCR | COVID |
| `searchqueue.blade.php` | Zone (badge), F.Name, L.Name, L.Name2, Consecutive (**`labn`**), Group (`daytest`), Actions (`userqueue`) | Queue | COVID |
| `historia.blade.php` / `historiamr.blade.php` | Historial PCR / médico (no es búsqueda) | — | Contexto |

**Duplicación visible:**
- El bloque `@switch($user->zone)` con badges (1A/1B/2/3/default) está **copiado literalmente** en `searcheckpoint`, `searchidcard`, `searchlab`, `searchqueue` (≈18 líneas idénticas ×4).
- El bloque de paginación `<div>{!! $usuarios->links() !!}</div>` se repite en las 7 vistas.
- La cabecera `Nombre/Apellido/Email/F.Nac/Teléfono` se repite con variaciones triviales entre `searchusers` y `searchdoctor`.
- Ninguna usa `@forelse`/estado-vacío; el "no encontrado" lo decide el JS del front por string vacío (frágil).
- Existen además stale: `componentes/searchusers.bladeBKP.php` y `componentes/searchcom.blade.php` mencionados en COVID-DECOMMISSION.md `:162,:164` (backup/legacy).

---

## 4. Exposición de datos (por endpoint) — cross-ref SECURITY.md M6 (`:200-204`)

**Vector común — `DB::table('users')` + `SELECT *`:** ninguno de los 7 endpoints HTML selecciona columnas; todos hacen `SELECT *`. El modelo `User` declara `$hidden = ['password', ...]` (`User.php:56-57`), pero **`$hidden` sólo aplica a Eloquent**, no al query builder `DB::table`. Por tanto el hash `password` (y todo campo sensible: `resultpcr`, `ultimatemperatura`, `labn`, etc., `User.php:41-48`) **está presente en cada fila** del resultado. Hoy el Blade no imprime `password`, pero:
  - cualquier `dd()`, log, o futura edición de la vista lo filtra;
  - viaja innecesariamente en memoria.

**PII renderizada en HTML (se envía al navegador):**
- `searchusers` / `searchdoctor`: **email, fecha de nacimiento, sexo, teléfono, depto** de TODOS los usuarios que coincidan, paginados de 50 en 50. Búsqueda con término vacío (`vacio`) lista a TODA la plantilla.
- `searchidcard`: foto de perfil + nombres (PII de imagen).
- `searchqueue`: `labn` (consecutivo de laboratorio COVID).

**Datos clínicos COVID embebidos (cross-ref COVID-DECOMMISSION.md `:74,:104`):**
- `searcheckpoint`/`searchidcard` filtran por `labn` (LIKE).
- `searchlab`/`searchqueue` filtran por `daytest`, `tested`, `inline`, `resultpcr`, `activo` y exponen acción `positivepcr`.

**Bug de seguridad/correctitud — precedencia OR sin agrupar** (`searchlab:476-478`, `searchqueue:51-56` y rama vacío de otras):
```php
->where('users.name','LIKE','%'.$valor.'%')->where('activo','=',1)->where('daytest','=',3)...
->Orwhere('users.lname','LIKE','%'.$valor.'%')->where('activo','=',1)...   // OR rompe el grupo
->Orwhere('users.labn','LIKE','%'.$valor.'%')...
```
Sin un `where(function($q){...})` que agrupe los `OR`, SQL evalúa `A AND B AND C OR D AND E ...`, de modo que la rama `Orwhere('lname'...)` puede devolver usuarios que **no cumplen** los filtros clínicos (p. ej. ya con `resultpcr=1`), exponiendo registros fuera del subconjunto esperado. Es un fallo de correctitud **y** de minimización.

**Autorización:** correcta a nivel de ruta (todos bajo `admin`). **No hay** control de pertenencia ni de mínimo privilegio por columna; cualquier admin ve todo de todos. No hay auditoría de búsqueda/exportación (SECURITY.md M6 lo recomienda).

---

## 5. Contrato AJAX por CRUD (qué pide / qué espera / dónde inyecta)

Patrón **idéntico** en los 7 CRUD (jQuery `keyup` sobre `#search` → `fetch` GET → `$('#usertable').html(text)`):

| CRUD (vista) | Endpoint que invoca | URL exacta | Espera | Inyecta en |
|---|---|---|---|---|
| `admin/usuarioscrud.blade.php:178` | **searchusers** | `'/searchusers/'+valor+'/?page=1'` | `response.text()` (HTML parcial de filas) | `$('#usertable').html(...)` |
| `admin/comcrud.blade.php:125` | **searchusers** ⚠ (no searchcom) | `'/searchusers/'+valor+'/?page=1'` | HTML | `#usertable` |
| `admin/medicocrud.blade.php:88` | **searchusers** ⚠ (no searchdoctor) | `'/searchusers/'+valor+'/?page=1'` | HTML | `#usertable` |
| `admin/idcardscrud.blade.php:82` | **searchidcard** | `'/searchidcard/'+valor+'/?page=1'` | HTML | `#usertable` |
| `admin/checkpoint.blade.php:84` | **searcheckpoint** | `'/searcheckpoint/'+valor+'/?page=1'` | HTML | `#usertable` |
| `pcrtest/listcrew.blade.php:95` | **searchlab** | `'/searchlab/'+valor+'/?page=1'` | HTML | `#usertable` |
| `virtualqueue/crudtest.blade.php:102` | **searchqueue** | `'/searchqueue/'+valor+'/?page=1'` | HTML | `#usertable` |
| `admin/injuryreport*.blade.php` | **searchUsers** (`/users/search?term=`) | distinto (JSON, autocomplete) | JSON array | widget JS (no `#usertable`) |

**Detalles del contrato a preservar:**
- Disparador: `keyup` sobre `<input id="search">`. Input vacío → se manda el literal `"vacio"` (sentinela) que el controller traduce a "listar todo".
- Respuesta: **HTML crudo** (parcial Blade renderizado: `<table>...<tbody>...</tbody></table>` + `{!! $usuarios->links() !!}`). El front hace `$('#usertable').html(htmlContent)`; si `htmlContent === ""` muestra "No se encontraron usuario." (frágil: depende de string vacío exacto).
- Paginación: el `fetch` fija `?page=1`. **No** hay manejo AJAX de los enlaces `->links()` devueltos (los enlaces de página apuntan a la URL `/searchusers/{valor}/?page=N` y recargarían como GET completo o romperían el contexto del CRUD). Esto es deuda: la paginación dentro del parcial inyectado no está cableada para AJAX.
- Sin CSRF (son GET). Sin debounce (dispara en cada `keyup`).

> **Este es el acoplamiento que un refactor no puede romper sin migrar el JS:** URL `/{endpoint}/{valor}/?page=1`, respuesta = HTML de filas, destino = `#usertable`, sentinela `"vacio"`.

---

## 6. Mapa de duplicación (agrupación para diseño unificado)

**Grupo A — "lista de usuarios genérica" (idénticos al 99%):**
- `searchusers` ≡ `searchcom` (byte-por-byte salvo el nombre del método; ambos devuelven `componentes.searchusers`).
- `searchdoctor` (misma query sin email; vista propia pero muerta).
- → Un solo servicio de búsqueda de usuarios cubre los tres. `searchcom`/`searchdoctor` son **eliminables** (sin callers).

**Grupo B — "lista con badge de zona + acción específica" (mismo esqueleto, distinta columna/acción):**
- `searcheckpoint`, `searchidcard`, `searchqueue`, `searchlab` comparten el `@switch zone`, cabecera y paginación; difieren en 1-2 columnas y la acción de fila.

**Grupo C — clínico/COVID (a decomisionar, COVID-DECOMMISSION.md):**
- `searchlab`, `searchqueue`, `searcheckpoint` (por `labn`/temperatura). `searchidcard` arrastra `Orwhere('labn')` a limpiar (`:187,:213`).

**Grupo D — moderno y correcto (modelo a seguir):**
- `searchUsers` de `InjuryReportController`: Eloquent, **columnas explícitas**, LIKE agrupado en closure, `limit`, salida JSON. Es el patrón "bien hecho" que el resto debería imitar.

Resultado: ~6 endpoints + ~7 vistas colapsan conceptualmente a **1 servicio de búsqueda parametrizado por entidad/preset** + **1 componente de tabla reutilizable**.

---

## 7. Propuesta de diseño unificado

### 7.1 Backend — un servicio/endpoint de búsqueda parametrizado

Crear un **`SearchController@users`** (o `UserSearchService`) único, parametrizado por un *preset* (perfil de búsqueda), en vez de 7 métodos:

```
GET /search/users/{preset}/{valor}?page=N
   preset ∈ { directory, idcard }   // (lab/queue/checkpoint sólo si NO se decomisiona COVID)
```

Cada preset define declarativamente (array de configuración, no copy-paste):
- **columnas seguras a seleccionar** (`select([...])` EXPLÍCITO — nunca `SELECT *`; nunca `password`).
- **columnas buscables** (LIKE), siempre **agrupadas** en `where(function($q){...})` para evitar el bug OR.
- **filtros fijos** del preset (p. ej. estado/rol), también dentro de su propio grupo.
- **vista parcial** y **acciones** permitidas por fila.
- **permiso** requerido (ver 7.3).

Usar **Eloquent con `$hidden`** (o `select` explícito) para que el hash nunca entre al conjunto. Validar `$valor` (longitud máx, sanitizar). Mantener `paginate()`.

### 7.2 Frontend — un componente Blade de resultados reutilizable + helper JS

- **Un parcial** `components/search-results.blade.php` que reciba `$rows`, `$columns` (config), `$actions` y renderice tabla + estado vacío con `@forelse` + paginación. Elimina la duplicación del `@switch zone`, cabeceras y `->links()`. Corrige de paso el `<tr>` faltante.
- **Un helper JS** (`searchTable(endpoint, inputSel, targetSel)`) con **debounce** y manejo AJAX de la paginación (delegación de click sobre `.pagination a` para `fetch` en vez de recarga). Reemplaza los 7 bloques `keyup` duplicados.

### 7.3 Autorización y minimización

- Autorizar por **permiso/policy** (`can:users.search`, `can:users.search.medical`) además del rol `admin`, para que datos clínicos requieran privilegio extra (hoy cualquier admin ve todo).
- **Minimizar columnas**: el preset `directory` NO debe devolver email/fecha-nac/sexo salvo necesidad real; ofrecer una variante "reducida" por defecto.
- Añadir **auditoría** de búsquedas/exportaciones (SECURITY.md M6 lo pide).
- Quitar restos COVID (`labn`, `resultpcr`, `daytest`, `tested`, `temperatura`) según COVID-DECOMMISSION.md antes de tocar columnas DB.

### 7.4 Plan de migración por CRUD (preservando el contrato AJAX)

Estrategia **strangler**, sin romper el contrato `/{endpoint}/{valor}/?page=1 → HTML → #usertable`:

1. **Fase 0 (no-op observable):** crear el nuevo servicio + parcial + helper JS, sin tocar rutas viejas. Producción sigue intacta (REBUILD-PROTOCOL: producción = oráculo).
2. **Eliminar muertos:** borrar `searchcom`, `searchdoctor` y sus rutas/vista (`searchdoctor.blade.php`) — confirmado sin callers (§5). Riesgo nulo.
3. **Aliasing controlado:** apuntar las rutas legacy (`/searchusers`, `/searchidcard`, ...) al nuevo controller con el preset correspondiente, devolviendo el **mismo HTML** (mismo `#usertable`, mismas columnas) para no tocar el JS de los CRUD todavía. Verificar paridad visual contra producción.
4. **Migrar JS CRUD a CRUD:** sustituir cada bloque `keyup` por el helper `searchTable(...)` (con debounce + paginación AJAX). Empezar por `usuarioscrud` (mayor uso), luego `idcardscrud`; `comcrud`/`medicocrud` ya apuntan a `searchusers`, así que sólo cambian de helper.
5. **Endurecer:** activar `select` explícito + permisos + minimización por preset. Validar que ningún parcial imprime campos retirados.
6. **COVID-dependientes (`searchlab`/`searchqueue`/`searcheckpoint`):** seguir COVID-DECOMMISSION.md — o decomisionar la ruta+vista, o (si se conserva) migrar igual pero con el bug OR corregido y filtros agrupados.

### 7.5 INCIERTO / a verificar antes de actuar

- ⚠ **Paginación AJAX**: no está claro qué hace hoy el usuario al pulsar un enlace de página dentro del parcial inyectado (¿recarga completa? ¿pierde el CRUD?). **Verificar en producción** antes de "preservar" ese comportamiento; podría ser un bug latente que conviene corregir, no replicar.
- ⚠ **¿`searchcom`/`searchdoctor` se invocan desde JS no-Blade** (assets compilados, `public/js`)? El grep cubrió `resources/views`; conviene un grep adicional sobre `public/` y `*.js` antes de borrarlos.
- ⚠ **`checkpoint`/`searcheckpoint`**: COVID-DECOMMISSION.md `:104-105` lo marca funcionalmente huérfano (era control de temperatura COVID). Confirmar con el dueño si la vista checkpoint sigue en uso real.
- ⚠ **Stale**: `componentes/searchusers.bladeBKP.php` y `componentes/searchcom.blade.php` (COVID-DECOMMISSION.md `:162,:164`) — confirmar que no se referencian antes de limpiar.
- ⚠ El `$valor === "vacio"` choca con un usuario que busque literalmente la palabra "vacio". Edge case menor; el nuevo diseño debería usar query string vacío real, no un sentinela mágico (cambio de contrato a coordinar con el JS).

---

## Estado: Limpieza COVID + huérfanos del search (2026-06-24)

**Cierre de limpieza del search.** Se retiraron los restos que quedaban tras la consolidación por preset:
- **🗑️ ELIMINADOS — endpoints COVID `searchlab` y `searcheckpoint`** (método en `AdminController` + ruta + parcial Blade
  `componentes/searchlab.blade.php` / `componentes/searcheckpoint.blade.php`). Salen del alcance del motor por preset (eran
  COVID); sus **páginas host** (`pcrtest/listcrew`, `admin/checkpoint`) siguen vivas y se retiran en el decommission COVID.
- **🗑️ ELIMINADOS — parciales huérfanos `componentes/searchusers.blade.php`, `componentes/searchidcard.blade.php`,
  `componentes/searchdoctor.blade.php`** (sin caller vivo; el motor por preset ya sirve `componentes/search-results.blade.php`
  y `componentes/search-results-idcard.blade.php`). `searchdoctor`/`searchcom` (métodos+rutas) ya estaban borrados.
- **✅ ÚNICO PARCIAL `search*` VIVO restante: `componentes/searchqueue.blade.php`** (`queueController@searchqueue`, COVID) —
  permanece hasta el decommission de la cola.
- Todo respaldado en `_legacy_backup/`. Verificado: `php -l` (AdminController, web.php), `route:list` (searchlab/searcheckpoint = 0),
  `view:cache` compila.

---

## Estado: Consolidado — motor por preset (users+idcard) (2026-06-24)

**Consolidado: motor por preset (users+idcard); muertos borrados; COVID (lab/queue) a decommission; pendiente verificación
idcard + borrado de métodos viejos.** `SearchController` es ahora un **motor parametrizado por preset** (helper protegido
`runSearch(array $preset, Request, $valor)`): `users(...)` → `componentes/search-results.blade.php` (verificado en vivo OK) e
`idcards(...)` → **nuevo `componentes/search-results-idcard.blade.php`** (Zone/Foto/F.Name/L.Name/L.Name2/link gafete/Printed,
tinte verde si `age==1`; **columnas COVID `labn` eliminadas**). Ruta `/searchidcard` re-apuntada (alias strangler) a
`SearchController@idcards`. **`searchcom`/`searchdoctor` BORRADOS** (métodos + rutas, cero callers). **COVID** (`searchlab`/
`searchqueue`/`searcheckpoint`) **a decommission** (fuera del motor, COVID-DECOMMISSION). **Pendiente:** verificación en vivo del
idcard por el owner (idéntico + Network sin hash) → **borrar los métodos viejos `searchusers`/`searchidcard`** de `AdminController`.

---

## Estado previo: Incremento 1 implementado (2026-06-24)

**Incremento 1 del rediseño HECHO** (ver `PROGRESS.md` y `RECIPE.md` 1.5.D): se construyó `SearchController@users`
(`app/Http/Controllers/SearchController.php`) con Eloquent + `select()` explícito (sin hash en el payload), `OR` agrupado en
closure, autorización `can('crew.view')` y alcance por departamento (restrictivo si el rol no tiene `crew.view.all-departments`);
el parcial reutilizable `componentes/search-results.blade.php` (arregla el `<tr>` faltante y dedupe badges/paginación); y +3
permisos en `RolesAndPermissionsSeeder.php` (`crew.view.contact`/`crew.view.personal`/`crew.view.all-departments` → 42 totales;
médico habilitado para reportar accidentes y buscar). La ruta `/searchusers` se re-apuntó (alias strangler) a
`SearchController@users` manteniendo nombre y grupo `admin`; lo viejo quedó intacto. **Pendiente:** verificación en vivo del owner
y migrar el resto de endpoints search CRUD por CRUD (luego borrar los métodos muertos de `AdminController`).
