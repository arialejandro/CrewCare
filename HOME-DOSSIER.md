# HOME-DOSSIER — Descifrado del "Home" de CrewCare

> Paso 1 (Descifrar) del protocolo de reconstrucción. Análisis **READ-ONLY**.
> Objetivo: mapear honestamente qué es el Home post-login para poder reconstruirlo
> con arquitectura real **sin perder función**. Cada pieza se clasifica como
> **CONTAMINACIÓN** (seguro de eliminar) o **LOAD-BEARING** (hay que preservar).

---

## 1. Resumen

- La ruta `/` redirige a `/home` (`routes/web.php:16-18`).
- `/home` → `HomeController@index` con middleware `auth` (`routes/web.php:36`, `HomeController.php:23`).
- **`HomeController@index` devuelve `view('inicio', ...)`** (`HomeController.php:100`). **NO** devuelve `view('home')`.
- **`home.blade.php` es un archivo HUÉRFANO**: no se referencia desde ningún controlador ni vista (búsqueda global sin resultados). Es una versión vieja/abandonada del Home. **CONTAMINACIÓN.**
- La vista real (`inicio.blade.php`) tiene ~62 líneas de `<head>` filtrado (meta, CDN de Bootstrap 4, jQuery, cropper, estilos inline) **ANTES** de `@extends('layouts.app')` (línea 64), seguido de `@section('content')` (línea 65) y un switch `admin` vs `crew` (línea 67).
- El switch admin renderiza un **dashboard de métricas + gráfica Chart.js**; el switch crew renderiza una **tarjeta de perfil + widget de recorte de foto (cropper) + recordatorio de cuestionario**.
- La pieza **realmente LOAD-BEARING** del bloque filtrado es el **stack Bootstrap 4 + jQuery + cropper**, del que depende el **modal de recorte de foto de perfil** de la rama crew (usa la API JS de BS4: `data-dismiss`, `$modal.modal('show')`, eventos `shown.bs.modal`). El layout sólo trae BS5, cuya API es incompatible (`data-bs-dismiss`), por lo que ese modal no funcionaría sin BS4.

---

## 2. Routing / Controller

| Elemento | Archivo:línea | Detalle |
|---|---|---|
| Redirección raíz | `routes/web.php:16-18` | `/` → `redirect('/home')` |
| Ruta home | `routes/web.php:36` | `Route::get('/home', [HomeController::class,'index'])->name('home')` |
| Middleware | `HomeController.php:21-24` | `__construct()` aplica `auth` (sólo usuarios logueados) |
| Vista devuelta | `HomeController.php:100` | `return view('inicio', compact(...))` |

**Variables que el controlador pasa a la vista** (`HomeController.php:100-114`):

| Variable | Origen | Consumida en `inicio.blade.php` |
|---|---|---|
| `$totalAccidents` | `InjuryReport::count()` | sí — métrica "Total Accidentes" (línea 167) |
| `$daysSinceLastAccident` | diff último accidente | sí — métrica "Días sin accidentes" (línea 105) |
| `$totalUnsafeActs` | `hazardnotification::count()` | **NO** se imprime directo (sólo en gráfica vía `$weeklyUnsafeReports`) — posible CONTAMINACIÓN |
| `$totalUnsafeConds` | `unsafecond::count()` | **NO** se imprime directo — posible CONTAMINACIÓN |
| `$totalMedicalConsults` | `cmedic::count()` | sí — métrica "Total Consultas" (línea 176) |
| `$totalLocationReports` | `locationreport::count()` | sí — métrica "Locaciones revisadas" (línea 116) |
| `$productionDays` | `calculateProductionDays()` | sí — métrica "Días de producción" (línea 146) |
| `$shootingDays` | `calculateShootingDays()` (fechas HARDCODEADAS 2025-04-01 → 2025-04-22, `HomeController.php:117-122`) | sí — métrica "Días de rodaje" (línea 158) |
| `$unsafeActsMonthly` | query mensual | **NO** usada en la vista — CONTAMINACIÓN |
| `$unsafeCondsMonthly` | query mensual | **NO** usada en la vista — CONTAMINACIÓN |
| `$weeklyInjuryReports` | query semanal | **NO** usada en la vista — CONTAMINACIÓN |
| `$injuryReportsByMonth` | query mensual | **NO** usada en la vista — CONTAMINACIÓN |
| `$weeklyUnsafeReports` | bucle semanal | sí — alimenta la gráfica `weeklyUnsafeTrendChart` (`@json` línea 342) |

> NOTA: 5 de las 13 variables calculadas (`unsafeActsMonthly`, `unsafeCondsMonthly`, `weeklyInjuryReports`, `injuryReportsByMonth`, y los conteos `totalUnsafeActs`/`totalUnsafeConds`) **no se renderizan**. Son cómputos muertos en el controlador. CONTAMINACIÓN (eliminables en la reconstrucción, ahorran queries).
> NOTA: `$shootingDays` usa fechas hardcodeadas de abril 2025 — no es dinámico. Marcar para revisión de producto.

---

## 3. Relación `home.blade.php` vs `inicio.blade.php`

- **`inicio.blade.php` ES el Home real.** Es lo que se renderiza para cualquier usuario logueado en `/home`.
- **`home.blade.php` es un huérfano** (no referenciado en ningún `view()`, `@include`, `@extends`). Contiene el mismo patrón `@if(auth()->user()->admin)` (línea 8) pero con un cuerpo mucho más simple y antiguo: sólo mensajes de bienvenida, sin dashboard ni cropper. Es claramente un predecesor abandonado de `inicio.blade.php`.
- Ambos tienen el switch admin/crew, lo que confunde, pero **sólo `inicio.blade.php` se ejecuta**.

**Clasificación:** `home.blade.php` → **CONTAMINACIÓN** (borrable). `inicio.blade.php` → la vista viva.

> RECOMENDACIÓN: en la reconstrucción, renombrar la vista real a `home.blade.php` (coherente con la ruta `name('home')`) y borrar la duplicada — pero hacerlo en un paso explícito, no asumir.

---

## 4. Bloque filtrado de `inicio.blade.php` (líneas 1-62, ANTES de `@extends`)

Estas líneas están **fuera** de cualquier sección Blade, por lo que se emiten **literalmente al principio del documento, ANTES de `<!doctype html>`** del layout (porque el contenido renderizado por `@yield('content')` se inyecta dentro del `<body>`, pero todo lo que está antes de `@extends` se emite como salida cruda al tope del HTML). Esto es la causa del **Quirks mode** (ver §7).

| Pieza | Línea | Clasificación | Por qué | Quién la usa |
|---|---|---|---|---|
| `<meta name="_token" content="{{ csrf_token() }}">` | 1 | **LOAD-BEARING** | El JS del cropper lo lee: `$('meta[name="_token"]').attr('content')` (línea 578) para el POST AJAX. El layout sólo define `meta name="csrf-token"` (otro nombre), así que este meta es necesario tal cual. | JS del cropper (rama crew) |
| `<script ... jquery/3.4.1/jquery.js>` | 2 | **CONTAMINACIÓN (duplicado)** | El layout ya carga jQuery 3.3.1 (`app.blade.php:35`). Esta es una **segunda** carga de jQuery. El cropper necesita jQuery, pero el del layout basta. Se puede eliminar SIN romper, siempre que el layout siga trayendo jQuery. | redundante |
| `<link ... twitter-bootstrap/4.4.1/css/bootstrap.min.css>` | 3 | **LOAD-BEARING (parcial / colisión)** | CSS de **Bootstrap 4**. El layout trae **Bootstrap 5** (`app.blade.php:16`). El modal de recorte usa clases/markup de BS4 (`.close`, `.modal-header` sin `btn-close`, `data-dismiss`). El CSS BS4 da el aspecto correcto al modal. **Carga DESPUÉS del BS5 del layout en el orden final del DOM** → gana en cascada para ese markup. **Esta es la colisión BS4/BS5 que el dueño identificó como "parece basura pero sostiene".** | modal cropper (rama crew) |
| `<script ... popper.js/1.14.3/umd/popper.min.js>` | 4 | **LOAD-BEARING (dependencia de BS4 JS)** | Popper 1.x es dependencia del JS de Bootstrap 4 (línea 5). BS5 trae su propio Popper en el bundle, pero el BS4 JS de la línea 5 necesita Popper 1.x global. | BS4 JS |
| `<script ... bootstrap/4.1.3/js/bootstrap.min.js>` | 5 | **LOAD-BEARING (crítico)** | **JS de Bootstrap 4.** El widget de recorte llama `$modal.modal('show')` / `$modal.modal('hide')` y escucha `shown.bs.modal`/`hidden.bs.modal` (líneas 535, 553, 559, 581) — la **API jQuery de BS4**. BS5 eliminó la API jQuery del modal (`$(...).modal()` ya no existe) y usa `data-bs-dismiss`. **Sin este BS4 JS, el modal del cropper no abre ni cierra.** ESTA es la razón concreta de "BS4 sobre BS5". | modal cropper (rama crew) |
| `<link ... cropperjs/1.5.6/cropper.css>` | 6 | **LOAD-BEARING** | Estilos del recortador de imagen. | widget cropper (`.preview`, `#image`) |
| `<script ... cropperjs/1.5.6/cropper.js>` | 7 | **LOAD-BEARING** | Librería Cropper.js; se instancia con `new Cropper(image, {...})` (línea 554). | widget cropper |
| `<style>` inline (`.imgs`, `.preview`, `.modal-lg`, `.metric-card`, etc.) líneas 8-62 | 8-62 | **MIXTO** | `.imgs`/`.preview`/`.modal-lg` (8-22) → **LOAD-BEARING** (estilan el modal/preview del cropper). `.metric-card`/`.dashboard-wrapper`/`.chart-containeredit` (23-61) → **LOAD-BEARING** para la rama admin (estilan las tarjetas de métrica), aunque parte se **redefine** en el segundo `<style>` (líneas 184-332). | cropper + dashboard admin |

### Por qué BS4 gana sobre BS5 (la pregunta crítica)
1. **JS:** el código del cropper usa la API jQuery `.modal()` y los eventos `*.bs.modal` que **sólo existen en Bootstrap 4** (BS5 los quitó). Por eso el BS4 JS (línea 5) es indispensable para la rama crew.
2. **Markup:** el modal usa `data-dismiss="modal"` y `<button class="close">` con `&times;` (líneas 503-505, 520) — sintaxis BS4. En BS5 sería `data-bs-dismiss` y `<button class="btn-close">`. El CSS BS4 (línea 3) es lo que renderiza ese markup correctamente.
3. **Orden de carga:** el bloque filtrado se emite al tope del documento, pero el `<link>` BS4 termina apareciendo en el flujo y, por especificidad/orden, sus reglas aplican al markup BS4 del modal. El dueño tenía razón: el orden BS4/BS5 es load-bearing **para la rama crew**.

> INCERTIDUMBRE A VERIFICAR EN NAVEGADOR: confirmar que (a) el modal del cropper abre y recorta correctamente con un usuario crew real, y (b) que romper/quitar el BS4 JS efectivamente rompe el modal. También verificar si el BS4 CSS rompe visualmente algo de la rama admin (las tarjetas usan utilidades como `text-end` que son BS5; `text-end` NO existe en BS4, así que conviven utilidades de ambas versiones — revisar visualmente).

---

## 5. Switch admin / crew (`@if(auth()->user()->admin)` línea 67)

### Rama ADMIN (líneas 68-440)
Dashboard de seguridad/salud. Bloques:
- **Saludo + estado de cuestionario** (líneas 82-95): "Gracias por responder" o botón "Responder cuestionario" → `href="/dailyreport"`, condicionado por `auth()->user()->encuestadiaria`.
- **Tarjetas de métrica** (`.metric-card`, con SVG de fondo decorativo):
  - Días sin accidentes → `$daysSinceLastAccident` (105)
  - Locaciones revisadas → `$totalLocationReports` (116)
  - Días de producción → `$productionDays` (146)
  - Días de rodaje → `$shootingDays` (158)
  - Total Accidentes → `$totalAccidents` (167)
  - Total Consultas → `$totalMedicalConsults` (176)
- **Gráfica de tendencia semanal** (líneas 123-131, 336-439): `<canvas id="weeklyUnsafeTrendChart">` alimentado por Chart.js + plugin trendline (`<script ...chartjs-plugin-trendline>` línea 334). Dataset desde `@json($weeklyUnsafeReports)` (línea 342): "Actos Inseguros" y "Condiciones Inseguras" con línea de tendencia punteada.
- **Sin formularios** en esta rama (sólo enlace a `/dailyreport`). Chart.js viene del layout (`app.blade.php:21`); el plugin trendline se carga aquí.

### Rama CREW (líneas 442-495)
- **Formulario de subida de foto** (líneas 448-460): `<form action="{{ route('uploadCropImage') }}" method="POST" enctype="multipart/form-data">` con `<input type="file" name="imgperfil" class="image">`. **Acción real:** ruta `uploadCropImage` → `cropimageController@uploadCropImage` (`web.php:211`). Nota: el form HTML es un fallback; el flujo real es vía AJAX (ver cropper §abajo).
- **Tarjeta de perfil** (`.profile-card-2`, líneas 464-480): foto (`imagesprf/usrs/{imgperfil}`), logo CrewCare, logo cliente (redrum.png), nombre+apellido, zona, puesto/departamento y edad calculada con Carbon.
- **Estado de cuestionario** (líneas 485-494): "Gracias responder su cuestionario de salud" o botón "Responder cuestionario de salud" → `/dailyreport`, según `encuestadiaria`.

### Compartido (fuera del switch, líneas 498-589) — se renderiza para AMBAS ramas
- **Modal de recorte** `#modal` (498-525): markup BS4. Aunque está fuera del `@if`, el disparador (input `.image`) sólo existe en la rama crew, así que en la práctica sólo funciona para crew.
- **JS del cropper** (526-589): escucha cambios en `.image`, abre el modal, instancia `new Cropper(...)`, y al hacer clic en `#crop` recorta a 800×800, convierte a base64 y hace `$.ajax` POST a `uploadCropImage` con el `_token` del meta; al éxito redirige a `/home`.

> INCERTIDUMBRE: el modal/cropper se emite para admin también (está fuera del `@if`), pero admin no tiene el input `.image`. Verificar que no cause efectos visuales para admin.

---

## 6. Duplicación con el layout (`layouts/app.blade.php`)

Lo que el **layout ya provee** (y la vista NO debería re-declarar):

| Recurso | Layout (línea) | Re-declarado en inicio.blade.php (línea) | Veredicto |
|---|---|---|---|
| `<!doctype html>` + `<html>` + `<head>` | 1-4 | — | el layout es el dueño del documento |
| meta CSRF | 9 (`csrf-token`) | 1 (`_token`, **otro nombre**) | el de inicio es load-bearing por el nombre `_token` que lee el JS |
| **Bootstrap CSS** | 16 (**BS5**) | 3 (**BS4**) | **COLISIÓN DE VERSIONES** — ambas cargan |
| **Bootstrap JS** | 18 (**BS5 bundle**) | 5 (**BS4**) | **COLISIÓN** — BS5 bundle + BS4 JS coexisten; el cropper depende del BS4 |
| **jQuery** | 35 (**3.3.1**) | 2 (**3.4.1**) | **DUPLICADO** — jQuery cargado dos veces |
| Chart.js | 21 | — (sólo plugin trendline en 334) | OK, sin duplicar el core |
| app.js | 19 | — | provisto por layout |
| a2hs.js (PWA) | 26 | — | provisto por layout |
| Bootstrap Icons / FontAwesome | 17, 32 | — | provisto por layout |
| Fuentes (Poppins, Nunito, Roboto, Lato) | 30-44 | — | provisto por layout |
| Sidebar (con gate RBAC) | 64-67 (`@if admin || can('users.view')`) | — | provisto por layout |
| Header | 55 | — | provisto por layout |
| `@yield('content')` dentro de `#app` / `main.col-lg-10` | 71-77 | — | el contenido de inicio entra aquí |

**Duplicaciones a limpiar en la reconstrucción (con cuidado):**
- jQuery cargado **2 veces** (layout 3.3.1 + inicio 3.4.1). El de inicio es CONTAMINACIÓN si el layout mantiene jQuery.
- Bootstrap cargado **2 veces con versiones distintas** (BS5 layout + BS4 inicio). **NO eliminar BS4 a ciegas**: la rama crew depende de él. La reconstrucción debe portar el modal/cropper a BS5 (`data-bs-dismiss`, `btn-close`, API JS de BS5 o vanilla) ANTES de quitar BS4.

---

## 7. Causa del Quirks mode

- El layout **SÍ** declara `<!doctype html>` correctamente en su **línea 1** (`app.blade.php:1`).
- **PERO** `inicio.blade.php` emite ~62 líneas de HTML (meta, `<script>`, `<link>`, `<style>`) **antes** de `@extends('layouts.app')` (línea 64). En Blade, todo lo que está fuera de una sección y antes de `@extends` se imprime **al inicio absoluto de la salida**, **por delante del `<!doctype html>`** del layout.
- Resultado: el documento final comienza con `<meta>...<script>...` y **el `<!doctype html>` queda relegado**, ya no es el primerísimo byte. Los navegadores exigen que el doctype sea lo primero; al no serlo, **caen en Quirks mode**.
- **Causa raíz:** el doctype no está ausente, está **tarde** porque la vista filtra `<head>` por encima del `@extends`. **CONTAMINACIÓN estructural.**

**Arreglo de la reconstrucción:** mover todo lo verdaderamente necesario del bloque filtrado a `@section('scripts')`/`@push('head')` (o al layout), de modo que la vista empiece directamente con `@extends('layouts.app')` y el `<!doctype html>` del layout sea el primer byte → sale de Quirks mode.

---

## 8. JS / CSS realmente usado por el Home

**Realmente consumido:**
- jQuery (una sola carga basta) — usado por el cropper.
- Bootstrap 4 JS + CSS + Popper 1.x — **usado por el modal del cropper** (rama crew). LOAD-BEARING hasta migrar a BS5.
- Cropper.js + cropper.css — widget de recorte de foto (rama crew).
- meta `_token` — leído por el AJAX del cropper.
- Chart.js (layout) + chartjs-plugin-trendline (línea 334) — gráfica de tendencia (rama admin).
- `<style>` inline: estilos de tarjetas/dashboard (admin) y del modal/preview (crew).
- Bootstrap 5 (layout), FontAwesome, Bootstrap Icons, fuentes, sidebar/header — chrome del layout.

**Incluido pero NO usado (CONTAMINACIÓN):**
- Segunda carga de jQuery (línea 2) — duplica el del layout.
- Variables del controlador no renderizadas: `unsafeActsMonthly`, `unsafeCondsMonthly`, `weeklyInjuryReports`, `injuryReportsByMonth`, y los conteos `totalUnsafeActs`/`totalUnsafeConds` (no impresos).
- Bloque de saludo comentado (líneas 69-80).
- Regla `.dashboard-card-b` comentada (244-251).

---

## 9. Contrato de comportamiento a preservar (oráculo — verificar sin regresión)

### Checklist ADMIN (usuario con `admin = 1`)
- [ ] Al entrar a `/home` ve el **dashboard** (no la tarjeta de perfil).
- [ ] Saludo con nombre + estado de cuestionario: si `encuestadiaria` está marcado, "Gracias por responder…"; si no, botón "Responder cuestionario" → `/dailyreport`.
- [ ] 6 tarjetas de métrica con valores correctos: Días sin accidentes, Locaciones revisadas, Días de producción, Días de rodaje, Total Accidentes, Total Consultas.
- [ ] Gráfica "Tendencia Semanal de Reportes Inseguros" (Chart.js, línea + tendencia punteada) con datos de actos y condiciones inseguras por semana del mes actual.
- [ ] Ve el **sidebar** (gate `admin || can('users.view')`) y el mini-nav admin del header.

### Checklist CREW (usuario con `admin = 0`)
- [ ] Al entrar a `/home` ve la **tarjeta de perfil** (foto, logos, nombre, zona, puesto/departamento, edad) — NO el dashboard.
- [ ] Bloque "Cambia tu foto de perfil" con `<input type="file" class="image">`.
- [ ] Al seleccionar una imagen, **se abre el modal "Corta tu foto"** con vista previa.
- [ ] El recorte (Cropper.js, 1:1, 800×800) funciona; "Actualizar" hace POST a `uploadCropImage` con el token, muestra alerta de éxito y **redirige a `/home`** con la nueva foto.
- [ ] Estado de cuestionario: "Gracias…" o botón "Responder cuestionario de salud" → `/dailyreport`, según `encuestadiaria`.
- [ ] **NO** ve el sidebar (gate falla) — ve el header con nav de crew (Atras, Change Password, Profile).

> El punto más frágil de la migración es el **modal del cropper** (depende de BS4 JS/CSS). La reconstrucción debe reproducir exactamente: abrir modal al elegir archivo, previsualizar, recortar 1:1, subir vía AJAX con `_token`, alertar y redirigir. Verificar en navegador con un crew real.

---

## 10. Incertidumbres a verificar en navegador en vivo

1. Confirmar visualmente que el **modal del cropper abre/cierra/recorta** con un crew real y que falla si se quita BS4 JS.
2. Confirmar que el **BS4 CSS no rompe** las tarjetas de la rama admin (que usan utilidades BS5 como `text-end`, inexistentes en BS4).
3. Confirmar el **Quirks mode** en DevTools (`document.compatMode === 'BackCompat'`) atribuible al bloque filtrado antes del doctype.
4. Confirmar que las **variables no renderizadas** del controlador efectivamente no se usan (revisar que no haya JS oculto que las lea).
5. `$shootingDays` con **fechas hardcodeadas (abril 2025)** — decisión de producto, no técnica.
