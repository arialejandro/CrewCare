# PERFORMANCE.md — CrewCare

Auditoría de rendimiento **read-only** (sin cambios de código) del app Laravel 8.
Complementa `ARCHITECTURE.md`. Cada hallazgo está verificado contra el código fuente
con referencia `archivo:línea` y snippet. **No se aplicó ninguna corrección** — las
recomendaciones describen el fix, no lo ejecutan.

> Nota de contexto sobre severidad: el conteo real de filas no es reproducible desde el
> repo (no hay migraciones de las tablas de dominio, ver ARCHITECTURE §2). La severidad se
> estima por: ancho de tabla, ausencia de paginación, frecuencia del endpoint y si la
> operación corre dentro de un bucle. Tablas "anchas" confirmadas: `formularios` (~72 cols),
> `location_report` (~161 cols), `users`.

---

## Tabla resumen

| # | Categoría | Severidad | Ubicación | Resumen |
|---|---|---|---|---|
| 1 | N+1 | **Alta** | `DailyReportController.php:20` + `dailyreports/index.blade.php:62-63` | `index()` sin `with('logs')`; la vista hace `$report->logs->count()` por fila (2 veces) |
| 2 | N+1 / Bulk | **Alta** | `resetController.php:24-29,47-54,62-68,76-81,86-104,111-114` | `DB::table('users')->get()` y luego `User::find()` + `update()` por fila dentro del bucle |
| 3 | N+1 / Bulk | **Alta** | `AdminController.php:667-685` (negativepcr), `692-743` (negativeantg) | Igual patrón: colección + `find/update` por fila; negativeantg además genera 1 PDF Dompdf por usuario |
| 4 | Bulk op (mail sync) | **Alta** | `AdminController.php:586-599,655-665,696-706,803-813`; `queueController.php:208-221,234-247,253-264,270-281`; `resetController.php:32-42,117-127`; `FormulariosController.php:367-381,596-610` | `Mail::send()` dentro de `foreach` — envío síncrono por destinatario |
| 5 | Query efficiency | **Alta** | `locationController.php:21` | `locationreport::all()` (tabla ~161 cols) sin paginar ni `select()` |
| 6 | Query efficiency | Media | `HazardNotificationController.php:80`, `unsafecondNotificationController.php:67` | `::all()` sin paginar en listados |
| 7 | Query efficiency | Media | `HomeController.php:80-98` | Conteos semanales en bucle PHP (2 queries `count()` por semana) en vez de un `groupBy` |
| 8 | Query efficiency | Media | `AdminController.php:653,667,711,723`; `negativeantg` | Misma query `users where tested/resultpcr` repetida 2–3 veces por request |
| 9 | Query efficiency | Media | Search/CRUD: `AdminController.php:30,367-462,464-518`; `queueController.php:23-37,40-93` | `DB::table('users')->...->get()/paginate()` = `SELECT *` sobre tabla ancha; consultas de búsqueda con `where/orWhere` mal agrupados |
| 10 | N+1 | Media | `AdminController.php:273-284` (selectpuesto), `226-229` (idcard) | Múltiples `find()`/`first()` encadenados; patrón junction sin eager load |
| 11 | Query efficiency | Baja | `FormulariosController.php:80,448`; PCR flujos | `User::find()` ejecutado 2 veces sobre el mismo id en el mismo request |
| 12 | Frontend | Media | `layouts/app.blade.php:18,19,35` + `resources/js/bootstrap.js:11` | jQuery cargado **dos veces** (CDN + bundle); libs pesadas por CDN |
| 13 | Frontend | Media | `layouts/app.blade.php:15-44`; `public/css\|js/materialize*` | Materialize (CSS+JS) conviviendo con Bootstrap 5 (frameworks duplicados); `asset()` hardcodeado sin `mix()` |
| 14 | Frontend | Baja | `layouts/app.blade.php:20-21` | TinyMCE 6 y Chart.js cargados globalmente en todas las vistas, se usen o no |

**Conteo por categoría:** N+1 = 3 · Query efficiency = 6 · Bulk op = 2 · Frontend = 3 (total 14).
**Por severidad:** Alta = 5 · Media = 7 · Baja = 2.

---

## 1. N+1 Queries

### 1.1 [Alta] DailyReportController@index — `logs` sin eager-load, contado en la vista
- **Categoría:** N+1
- **Ubicación:** `app/Http/Controllers/DailyReportController.php:20` y `resources/views/admin/dailyreports/index.blade.php:62-63`
- **Evidencia:**
  ```php
  // Controller (sin ->with('logs'))
  $dailyReports = DailyReport::latest()->paginate(10);
  ```
  ```blade
  // Vista, dentro del @foreach de reportes
  @if($report->logs->count() > 0)
      <span ...>{{ $report->logs->count() }} Logs</span>
  ```
- **Impacto:** Por cada reporte de la página se dispara una consulta para cargar `logs`,
  y se invoca **dos veces** por fila (`count()` en el `@if` y en el badge). Con 10 reportes
  paginados = hasta ~20 consultas extra por carga. El modelo `DailyReport` sí define
  `logs() { hasMany(DailyLog::class) }` (`app/Models/DailyReport.php:15-18`), pero no se
  precarga. La vista `show.blade.php:119` repite el patrón (`$report->logs->count()`), aunque
  ahí `show()` sí hace `with('logs')` (`DailyReportController.php:75`), así que show está OK.
- **Recomendación:** En `index()` usar `DailyReport::withCount('logs')->latest()->paginate(10)`
  y en la vista leer `$report->logs_count` en lugar de `$report->logs->count()` (elimina la
  carga de la colección y deduplica el conteo). Si se necesita la colección, `with('logs')`.

### 1.2 [Media] selectpuesto / idcard — cadenas de find()/first() sobre tablas junction
- **Categoría:** N+1
- **Ubicación:** `app/Http/Controllers/AdminController.php:273-284` (selectpuesto), `226-229` (idcard)
- **Evidencia:**
  ```php
  $puesto = puesto::find($request->id_puesto);
  $namedepto = departamento::find($puesto->id_departamento);
  ... userpuesto::create([...]);
  $usuario = user::find($id);
  ```
- **Impacto:** No es un bucle, pero las tablas junction (`userpuesto`, `usuariopcr`,
  `departamentousuario`) no definen relaciones Eloquent (`grep` confirma que **ningún** modelo
  de junction declara `belongsTo/hasMany`; solo `DailyReport` e `InjuryReport` tienen
  relaciones). Cualquier listado que necesite el puesto del usuario tendría que resolverlo
  con lookups manuales = N+1 latente. Hoy se evita guardando el string `puestodepartamento`
  duplicado en `users` (doble fuente de verdad, ver ARCHITECTURE §2).
- **Recomendación:** Definir relaciones Eloquent en los modelos junction y usar `with()` en
  los listados que muestren puesto/departamento, en lugar de lookups manuales o del string
  desnormalizado.

### 1.3 [Alta] Bucles de reset/resultado con find()+update() por fila
> Ver hallazgo **2.x** (Bulk ops) — el patrón `colección -> find()+update() por fila` es a la
> vez N+1 y operación batch ineficiente. Se documenta consolidado en §3 para no duplicar.

---

## 2. Query & Data efficiency

### 2.1 [Alta] locationController@index — `all()` sobre tabla de ~161 columnas, sin paginar
- **Categoría:** Query efficiency
- **Ubicación:** `app/Http/Controllers/locationController.php:21`
- **Evidencia:**
  ```php
  $reports = locationreport::all(); // Obtener todos los reportes
  ```
  La vista itera con `@foreach($reports as $report)` (`locationcrud.blade.php:96`).
- **Impacto:** `location_report` tiene ~161 columnas (ARCHITECTURE §2). `all()` trae **todas
  las filas y todas las columnas** a memoria; el listado solo necesita unos pocos campos
  (nombre, fecha, locación). A medida que crecen los reportes esto satura memoria y ancho de
  banda de la BD. Sin paginación.
- **Recomendación:** `locationreport::select('id_loc','production_name','name_loc','created_at', ...)->paginate(20)`.
  Seleccionar solo columnas del listado y paginar.

### 2.2 [Media] Hazard / Unsafecond index con `::all()` sin paginar
- **Categoría:** Query efficiency
- **Ubicación:** `HazardNotificationController.php:80`, `unsafecondNotificationController.php:67`
- **Evidencia:**
  ```php
  $hazardNotifications = HazardNotification::all();   // hazards
  $unsafenotifications = unsafecond::all();           // unsafeconds
  ```
- **Impacto:** Listados sin paginar; crecen sin cota. Menos grave que location porque las
  tablas son más estrechas, pero el patrón es el mismo. Contrasta con
  `InjuryReportController@inicial:13` y `DailyReportController@index:20` que **sí** paginan.
- **Recomendación:** `->latest()->paginate(15)` y seleccionar columnas del listado.

### 2.3 [Media] HomeController — conteos semanales en bucle PHP
- **Categoría:** Query efficiency
- **Ubicación:** `app/Http/Controllers/HomeController.php:80-98`
- **Evidencia:**
  ```php
  while ($currentDate->lte($endDate)) {
      $unsafeActsCount = hazardnotification::whereBetween('created_at', [...])->count();
      $unsafeCondsCount = unsafecond::whereBetween('created_at', [...])->count();
      ...
      $currentDate->addWeek();
  }
  ```
- **Impacto:** El dashboard (`/home`, ruta `auth`, alta frecuencia) ejecuta **2 queries
  `count()` por semana** del mes en curso (4–6 semanas → 8–12 queries solo para esta gráfica),
  además de los ~6 `count()` de KPIs (`:34-41`) y 4 agregados mensuales/semanales (`:46-72`).
  Los KPIs y agregados están bien resueltos con `groupBy`, pero este bloque semanal hace el
  trabajo en PHP.
- **Recomendación:** Resolver con un solo `selectRaw` agrupando por semana (`WEEK(created_at)`)
  con `groupBy`, igual que ya se hace para los datos mensuales (`:46-64`). Considerar cachear
  el dashboard (KPIs cambian poco intra-día).

### 2.4 [Media] Queries idénticas repetidas dentro del mismo request
- **Categoría:** Query efficiency
- **Ubicación:** `AdminController.php:653,667` (negativepcr); `694,711,723` (negativeantg);
  `queueController.php:114-116,119-122,126-128` (scheduletest), `141-143,147-149,153-155` (antigentest)
- **Evidencia (negativeantg):**
  ```php
  $notificaciones = User::where('activo','=',1)->where('tested','=',1)->where('resultpcr','=',0)->get(); // :694
  ...
  $users = DB::table('users')->where('activo','=',1)->where('resultpcr','=',0)->where('tested','=',1)->get(); // :711
  ...
  $users = DB::table('users')->where('activo','=',1)->where('resultpcr','=',0)->where('tested','=',1)->get(); // :723  (idéntica a :711)
  ```
- **Impacto:** En `negativeantg` la **misma** consulta de usuarios se ejecuta 3 veces
  (`:694` para correos, `:711` para PDFs, `:723` para updates). Es el mismo conjunto de filas;
  se recorre la BD tres veces. `negativepcr` la corre 2 veces.
- **Recomendación:** Ejecutar la consulta una sola vez, guardar la colección y reutilizarla en
  los tres bucles.

### 2.5 [Media] `DB::table('users')` = SELECT * sobre tabla ancha en listados/búsquedas
- **Categoría:** Query efficiency
- **Ubicación:** `AdminController.php:30,36,42,48,69` (cruds), `367-462` (search*),
  `464-518` (searchlab); `queueController.php:23-37` (curdtest), `40-93` (searchqueue),
  `116,122,128,143,149,155` (schedule/antigen)
- **Evidencia:**
  ```php
  $usuarios = DB::table('users')->where('activo','=',1)->orderBy('users.id','desc')->paginate(50);
  ```
  Las búsquedas además agrupan mal los `orWhere` (sin closure), p.ej. `AdminController.php:474-478`:
  ```php
  ->where('users.name','LIKE','%'.$valor.'%')->where('activo','=',1)->where('daytest','=',3)...
  ->Orwhere('users.lname','LIKE','%'.$valor.'%')->where('activo','=',1)... // los where post-orWhere no se agrupan
  ```
- **Impacto:** (a) `SELECT *` trae todas las columnas de `users` (incluye hash de password,
  tokens) cuando el listado/partial solo muestra nombre, email, labn, flags. Los partials
  (`componentes/searchusers.blade.php` etc.) iteran `$usuarios` usando solo columnas planas, así
  que no hay N+1 ahí, pero sí sobre-selección. (b) El `orWhere` sin closure puede producir
  resultados lógicamente incorrectos **y** planes de consulta peores (full scans con `LIKE %...%`
  sin índice utilizable). Sí paginan (bien), pero a 50–100 por página con `SELECT *`.
- **Recomendación:** Usar `->select('id','name','lname','lname2','email','labn','activo', ...)`
  con solo las columnas que la vista consume; agrupar las condiciones OR en un closure
  (`->where(fn($q)=>$q->where(...)->orWhere(...))`) y dejar los filtros fijos fuera.

### 2.6 [Baja] User::find() duplicado sobre el mismo id
- **Categoría:** Query efficiency
- **Ubicación:** `FormulariosController.php:80 y 448` patrón; `AdminController.php:583 y 619`
  (positivepcr), `:553/559/566` zonas de PCR
- **Evidencia (positivepcr):**
  ```php
  $producto = User::find($id);   // :583
  ...
  $producto = User::find($id);   // :619 (mismo id, re-consultado)
  ```
- **Impacto:** Una consulta extra innecesaria por request. Bajo impacto individual, pero es un
  patrón repetido (cada acción "set flag" hace find+update).
- **Recomendación:** Reutilizar la instancia ya cargada; refrescar solo si fue modificada por
  un `create()` intermedio.

---

## 3. Bulk operations (updates en bucle + mail síncrono)

### 3.1 [Alta] Reset de sistema: colección + find()+update() por fila
- **Categoría:** Bulk op / N+1
- **Ubicación:** `resetController.php:24-29` (newdayRep), `47-54` (newTD), `62-68` (CleanR),
  `76-81` (newWR), `86-104` (Nresult), `111-114` (PhotoReminder)
- **Evidencia (newTD):**
  ```php
  $users = DB::table('users')->where('activo','=',1)->get();
  foreach ($users as $user) {
      $producto = user::find($user->id);   // 1 SELECT por usuario
      $producto->resultpcr=0; $producto->inline=0; $producto->tested=0;
      $producto->update();                 // 1 UPDATE por usuario
  }
  ```
- **Impacto:** Para N usuarios activos: **1 query de carga + N SELECT + N UPDATE** (2N+1).
  Con cientos de usuarios son cientos de viajes a la BD por una operación que es un único
  cambio de columnas. `Nresult` (`:86-104`) además hace por fila `prueba::create()` +
  `usuariopcr::create()` (4 queries/fila). Estas rutas además **no tienen auth**
  (ARCHITECTURE §5) y `encuestas:task` corre cada minuto, lo que amplifica el costo.
  `PhotoReminder:111-114` tiene un bucle que carga `find()` y **no hace nada** con el resultado
  (código muerto que igual consulta la BD por fila).
- **Recomendación:** Reemplazar el bucle por un único `UPDATE` masivo:
  `User::where('activo',1)->update(['resultpcr'=>0,'inline'=>0,'tested'=>0])`.
  Para `Nresult`, separar el update masivo de la inserción de pruebas (insert batch).
  Eliminar el bucle inerte de `PhotoReminder`.

### 3.2 [Alta] AdminController negativepcr / negativeantg: por fila + PDF por fila
- **Categoría:** Bulk op / N+1
- **Ubicación:** `AdminController.php:668-685` (negativepcr), `712-740` (negativeantg)
- **Evidencia (negativeantg, generación de PDF en bucle):**
  ```php
  foreach ($users as $user) {
      $pdf = new Dompdf();
      $pdf->loadHtml(view('correos/negativean', compact('nombre'))->render());
      $pdf->render();
      $pdf->save($public_folder.$pdf_name);   // 1 PDF render por usuario, síncrono
  }
  ```
  Y luego otro `foreach` con `find()+update()+prueba::create()+usuariopcr::create()` por fila.
- **Impacto:** Render de Dompdf es caro (CPU/memoria); hacerlo por usuario de forma síncrona
  en un request HTTP puede agotar el tiempo de ejecución / memoria con muchos usuarios. Sumado
  a 2N+ queries de updates e inserts por fila y a los correos del §3.3.
- **Recomendación:** Mover el render de PDFs y los correos a **jobs en cola** (uno por usuario
  o por lote). Sustituir el update por fila por update masivo y los inserts por `insert()` batch.

### 3.3 [Alta] Envío de correo dentro de foreach (síncrono)
- **Categoría:** Bulk op (mail)
- **Ubicación:**
  `AdminController.php:586-599` (positivepcr → notificadores), `655-665` (negativepcr),
  `696-706` (negativeantg), `803-813` (sendreminder);
  `queueController.php:208-221` (enviarCorreoTest), `234-247/253-264/270-281` (remindertest);
  `resetController.php:32-42` (newdayRep), `117-127` (PhotoReminder);
  `FormulariosController.php:367-381` (newformulario2), `596-610` (checkform)
- **Evidencia (sendreminder):**
  ```php
  $notificaciones = User::where('activo','=',1)->where('encuestadiaria','=',0)->get();
  foreach ($notificaciones as $value) {
      Mail::send('correos.recordatorio',$data, function($msj) use($subject,$for){ ... });
  }
  ```
- **Impacto:** `Mail::send()` (no `queue()`) envía **inline**: bloquea el request hasta que el
  SMTP responde a cada destinatario. Con `QUEUE_CONNECTION=sync` (verificar `.env`/`config/queue.php`)
  cualquier `->queue()` también correría inline, así que el problema es estructural. Para N
  destinatarios, el request tarda N × (latencia SMTP); con decenas/cientos de usuarios esto
  agota el timeout de PHP y degrada toda la app. `positivepcr:586-599` además hace
  `userpuesto::where(...)->first()` **dentro** del bucle de notificadores (query por iteración,
  cuyo resultado `$puesto` ni se usa) — N+1 incrustado en el bucle de correos.
- **Recomendación:** Usar `Mail::to($for)->queue(new Mailable(...))` con `QUEUE_CONNECTION`
  asíncrono (database/redis) y un worker. Sacar la query `userpuesto::...->first()` fuera del
  bucle (o eliminarla, ya que no se usa). Para envíos masivos, considerar `Bus::batch` o un
  job que itere en background.

---

## 4. Frontend / Asset performance

### 4.1 [Media] jQuery cargado dos veces (CDN + bundle) + libs por CDN
- **Categoría:** Frontend
- **Ubicación:** `resources/views/layouts/app.blade.php:18,19,35` y `resources/js/bootstrap.js:11`
- **Evidencia:**
  ```blade
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/.../bootstrap.bundle.min.js"></script> // :18
  <script src="{{ asset('js/app.js') }}"></script>                                                 // :19 (bundle Mix)
  ...
  <script src="{{asset('https://code.jquery.com/jquery-3.3.1.min.js') }}" ...></script>           // :35  jQuery CDN
  ```
  ```js
  // resources/js/bootstrap.js (compilado dentro de app.js)
  window.$ = window.jQuery = require('jquery');   // :11  jQuery #2
  require('bootstrap');                            // :13  Bootstrap JS #2 (además del CDN :18)
  ```
- **Impacto:** jQuery se descarga/parsea dos veces (CDN 3.3.1 + el `require('jquery')` empaquetado
  en `app.js`). Bootstrap JS también se carga dos veces (bundle CDN `:18` + `require('bootstrap')`
  en `bootstrap.js`). Peso de descarga y tiempo de parse duplicados; riesgo de conflictos de
  versión. Además `asset('https://code.jquery.com/...')` envuelve una URL absoluta en `asset()`
  (no-op/anti-patrón). El app declara `webpack.mix.js` pero la plantilla usa `asset()` hardcodeado
  en vez de `mix()`, así que **no hay cache-busting por hash** para `app.js`/CSS propios.
- **Recomendación:** Elegir **una** fuente de jQuery y de Bootstrap (preferible el bundle Mix o
  el CDN, no ambos). Quitar `require('jquery')`/`require('bootstrap')` si se usa el CDN, o quitar
  los `<script>` CDN si se usa el bundle. Quitar el `asset()` alrededor de la URL CDN. Migrar a
  `mix('js/app.js')`/`mix('css/app.css')` para versionado.

### 4.2 [Media] Materialize conviviendo con Bootstrap 5 (frameworks duplicados)
- **Categoría:** Frontend
- **Ubicación:** `public/css/materialize.css`, `materialize.min.css`; `public/js/materialize.js`,
  `materialize.min.js`; junto a Bootstrap 5 en `layouts/app.blade.php:16,18`
- **Evidencia:** Existen ambos sets de framework en `public/` (confirmado por listado de
  directorio) mientras la plantilla principal carga Bootstrap 5.1.3.
- **Impacto:** Dos sistemas de CSS/JS de UI duplican reglas y peso si alguna vista carga
  Materialize. Mantenimiento y tamaño de descarga innecesarios. (Verificar qué vistas referencian
  `materialize.*`; si ninguna activa lo usa, es peso muerto en `public/`.)
- **Recomendación:** Confirmar qué vistas dependen de Materialize y migrarlas a Bootstrap 5;
  eliminar los assets de Materialize una vez sin referencias.

### 4.3 [Baja] TinyMCE 6 y Chart.js globales en todas las vistas
- **Categoría:** Frontend
- **Ubicación:** `layouts/app.blade.php:20,21`
- **Evidencia:**
  ```blade
  <script src="https://cdn.tiny.cloud/1/.../tinymce/6/tinymce.min.js" ...></script> // :20
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>                      // :21
  ```
  Además `app.blade.php:78-86` inicializa `tinymce.init({selector:'#MyEmail'})` en el layout,
  aunque `#MyEmail` solo existe en `virtualqueue/scheduletest`.
- **Impacto:** TinyMCE (pesado) y Chart.js se descargan en **cada** página que extiende el
  layout, aunque el editor solo se use en composición de correos y las gráficas solo en el
  dashboard. Coste de red y JS en páginas que no los necesitan.
- **Recomendación:** Cargar TinyMCE solo en la vista de correos y Chart.js solo en el dashboard
  mediante una sección `@push('scripts')` / `@yield`. Mover el `tinymce.init` a la vista que
  contiene `#MyEmail`.

---

## Apéndice — Qué está bien (para no "corregir" de más)

- **Sí paginan:** `usuarioscrud/comcrud/medicocrud/idcardscrud/checkpoint` (`paginate(50)`),
  `departamentocrud/notificacioncrud/positionscrud` (`paginate(7)`), `historial` (`paginate(20)`),
  `pcrcrud` (`paginate(12)`), `InjuryReport@inicial` y `DailyReport@index` (`paginate(10)`),
  colas (`paginate(50/100)`).
- **Agregados DB-side correctos:** `HomeController` KPIs (`count()` directos, `:34-41`) y series
  mensuales/semanales con `groupBy` (`:46-72`) — solo el bloque semanal en bucle (`:80-98`) debe migrarse.
- **Eager load correcto:** `InjuryReportController@show:125` (`with('user')`) y
  `DailyReportController@show:75` (`with('logs')`).
- **searchUsers (InjuryReport):** `InjuryReportController.php:134-141` ya selecciona columnas
  específicas y limita a 10 — buen ejemplo a replicar en los demás search.
- **Partials de búsqueda:** `componentes/search*.blade.php` iteran solo columnas planas (sin
  acceder relaciones), así que no introducen N+1 propio; el problema en esas rutas es el
  `SELECT *` (§2.5), no N+1 en la vista.
