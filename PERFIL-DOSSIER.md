# PERFIL-DOSSIER — Descifrado del "Perfil/Profile" de CrewCare

> Paso 1 (Descifrar) del [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md). Análisis **READ-ONLY**.
> Objetivo: mapear honestamente la vertical de perfil para reconstruirla con
> arquitectura real **sin perder función**. Cada pieza se clasifica como
> **CONTAMINACIÓN** (seguro de eliminar) o **LOAD-BEARING** (hay que preservar y POR QUÉ).
> Espejo de [HOME-DOSSIER.md](HOME-DOSSIER.md): mismo rigor, misma estructura.

---

## 1. Resumen

- La ruta real es `/profile` → `PerfilController@indexb` → **`view('profile')`** (`web.php:191` y `:206`; `PerfilController.php:17-20`). Esta es la vista VIVA.
- **`perfil.blade.php` es un archivo HUÉRFANO.** Lo renderiza únicamente `PerfilController@index` (`PerfilController.php:12-15`), y **ese método NO tiene ninguna ruta** que lo invoque (búsqueda global: no hay `Route::...,'index'` para `PerfilController`, ni `view('perfil')` en otro lado, ni `@include('perfil')`). Es el equivalente exacto del `home.blade.php` huérfano del Home. **CONTAMINACIÓN.**
- Todos los enlaces del header apuntan a `route('perfil')`, que **resuelve a `/profile`** (name `perfil` está montado sobre la URL `/profile`, no sobre `/perfil`). Es decir: el botón "Perfil/Profile" del header lleva a `profile.blade.php`, nunca a `perfil.blade.php` (`header.blade.php:18,24,38`).
- **Misma patología que el Home (RECIPE ~1.5.C):** `profile.blade.php` inyecta ~23 líneas de `<head>` filtrado (meta `_token`, jQuery 3.4.1, **CSS+JS de Bootstrap 4**, Popper 1.x, cropper css/js, `<style>` inline) **ANTES** de `@extends('layouts.app')` (línea 24). Esto provoca **Quirks mode** (head emitido por delante del `<!doctype html>` del layout) — ver §7.
- La pieza **realmente LOAD-BEARING** del bloque filtrado es el stack **Bootstrap 4 JS + Popper 1.x + jQuery + Cropper.js**, del que depende el **modal de recorte de foto de perfil** (`#modal`), que usa la API jQuery de BS4 (`$modal.modal('show')`, eventos `shown.bs.modal` / `hidden.bs.modal`, `data-dismiss`). El layout sólo trae BS5, cuya API JS de modal fue eliminada — por eso sin BS4 el modal no abre/cierra. **Es el mismo modal/cropper que el de la rama crew del Home, copiado casi byte por byte.**

---

## 2. Routing / Controller

| Elemento | Archivo:línea | Detalle |
|---|---|---|
| Ruta profile (grupo admin) | `web.php:191` | `Route::get('/profile', [PerfilController::class,'indexb'])->name('perfil')` |
| Ruta profile (grupo auth) | `web.php:204-207` | **DUPLICADA** — misma URL `/profile`, mismo name `perfil`, mismo método `indexb`. Gana la del grupo `auth`, por lo que `/profile` queda accesible a **cualquier autenticado**, no sólo admin (ver `ROUTES.md:33-34`, `SECURITY.md:228`). |
| Subida AJAX de foto | `web.php:211` | `Route::post('/crop-image-upload', [cropimageController::class,'uploadCropImage'])->name('uploadCropImage')` — destino real del cropper y del `<form>`. |
| Cambiar contraseña | `web.php:200-201` | `GET /changepassword` → `PerfilController@changepassword` (`view('changepassword')`); `POST /updatepassword/{id}` → `updatepassword`. **Vista separada**, no parte de profile. |
| `update()` huérfano | `web.php:208` | `POST /update` → `PerfilController@update` (name `perfil.update`). **Ninguna vista postea a `route('perfil.update')`**: tanto `profile` como `perfil` postean a `route('uploadCropImage')`. `update()` parece un manejador viejo de subida directa. Posible CONTAMINACIÓN (verificar). |

### Métodos de `PerfilController` y su estado

| Método | Renderiza / hace | ¿Routeado? | Veredicto |
|---|---|---|---|
| `index()` | `view('perfil', compact('users'))` (`:12-15`) | **NO** | **HUÉRFANO → CONTAMINACIÓN** (junto con `perfil.blade.php`). |
| `indexb()` | `view('profile', compact('users'))` (`:17-20`) | Sí (`web.php:191,206`) | **VIVO.** |
| `update()` | Subida directa de `imgperfil` al disco `imagesprf`, luego `redirect('/profile')` (`:22-45`) | Sí (`web.php:208`, name `perfil.update`) | Routeado pero **sin emisor**: ninguna vista postea aquí. Posible CONTAMINACIÓN (verificar que ningún form lo use). |
| `pruebachedule()` | Resetea `encuestadiaria=0` de todos los activos (`:46-54`) | Sí (`web.php:209`) | Job/cron manual, no es UI de perfil. Fuera de alcance de esta vista. |
| `changepassword()` | `view('changepassword')` (`:55-60`) | Sí (`web.php:200`) | Vista separada (cambio de contraseña). |
| `updatepassword()` | Actualiza datos/contraseña, `redirect('/home')` (`:61-73`) | Sí (`web.php:201`) | Lógica de `changepassword`, no de profile. |

**Variable que el controlador pasa a la vista** (`indexb`, `:18`):

| Variable | Origen | Consumida en `profile.blade.php` |
|---|---|---|
| `$users` | `User::findOrFail(auth()->user()->id)` (el usuario logueado, sólo el propio) | sí — foto (`:32`), nombre+apellido (`:37`), zona (`:38`), puesto/departamento (`:40`), edad calculada con Carbon (`:41`). |

> NOTA: el perfil sólo muestra/edita **el propio** usuario (`auth()->user()->id`). No hay edición de perfiles de terceros desde esta vista.
> NOTA: `index()`/`perfil.blade.php` recibe la misma `$users`, pero como no está routeado, da igual.

---

## 3. ¿`perfil.blade.php` vs `profile.blade.php`? (cuál es real / cuál huérfano)

- **`profile.blade.php` ES la vista real.** Se renderiza en `/profile` para cualquier usuario logueado. Es a donde llevan **todos** los enlaces "Perfil/Profile" del header (`route('perfil')` = `/profile`).
- **`perfil.blade.php` es un huérfano.** Sólo lo devuelve `PerfilController@index`, método **sin ruta**. No hay `view('perfil')` ni `@include`/`@extends` de `perfil` en ningún otro sitio (sólo la propia línea `:14` del controlador). Es un predecesor abandonado, análogo a `home.blade.php`.
- Ambas vistas son **casi idénticas**: mismo bloque `<head>` filtrado (líneas 1-23 byte-por-byte), mismo modal `#modal`, mismo `<script>` del cropper. Difieren sólo en el **markup de la tarjeta de perfil**:
  - `perfil.blade.php`: layout de dos columnas (`white-card-profile` + `app-widget-profile-card-night` + `circle-gft`), `<h1>..<h4>` con `borndate` crudo, y un `@switch($users->zone)` que pinta una insignia de zona (`profiles-1a`, `profiles-1b`, `profiles-2`, `profiles-3`, `profiles-nozone`) (`perfil.blade.php:40-55`).
  - `profile.blade.php`: tarjeta `profile-card-2` con logos (CrewCare + cliente REDRUM), nombre, zona, puesto y **edad calculada** (`Carbon::parse($users->borndate)->age`), más un bloque de recordatorio de **cuestionario de salud** (`encuestadiaria`) (`profile.blade.php:96-104`).

**Clasificación:** `perfil.blade.php` → **CONTAMINACIÓN** (borrable). `profile.blade.php` → la vista viva.

> RECOMENDACIÓN (no asumir, paso explícito): en la reconstrucción, conservar `profile.blade.php` como base, borrar `perfil.blade.php` y el método `index()`. Mantener el name de ruta `perfil` (lo usa el header) aunque la URL sea `/profile`, o renombrar ambos de forma coherente en un paso aparte.

---

## 4. Bloque filtrado de `profile.blade.php` (líneas 1-23, ANTES de `@extends`)

Estas líneas están **fuera** de toda sección Blade, por lo que se emiten **literalmente al principio del documento, ANTES de `<!doctype html>`** del layout. Causa de Quirks mode (§7). Es idéntico al bloque del Home (`inicio.blade.php`).

| Pieza | Línea | Clasificación | Por qué | Quién la usa |
|---|---|---|---|---|
| `<meta name="_token" content="{{ csrf_token() }}">` | 1 | **LOAD-BEARING** | El AJAX del cropper lo lee: `$('meta[name="_token"]').attr('content')` (`:162`) para el POST. El layout sólo define `meta name="csrf-token"` (**otro nombre**, `app.blade.php:9`), así que este meta es necesario tal cual — salvo que se reescriba el JS para leer `csrf-token`. | JS del cropper |
| `<script ...jquery/3.4.1/jquery.js>` | 2 | **CONTAMINACIÓN (duplicado)** | El layout ya carga jQuery 3.3.1 (`app.blade.php:35`). Esta es una **segunda** carga. El cropper necesita jQuery, pero el del layout basta. Eliminable SIN romper, siempre que el layout siga trayendo jQuery. | redundante |
| `<link ...twitter-bootstrap/4.4.1/css/bootstrap.min.css>` | 3 | **LOAD-BEARING (parcial / colisión)** | CSS de **Bootstrap 4**. El layout trae **BS5** (`app.blade.php:16`). El modal usa markup BS4 (`.close`, `data-dismiss`, header sin `btn-close`). El CSS BS4 lo estiliza. (Bug menor: el tag cierra con `</script>` sobrante — inofensivo.) **Colisión BS4-sobre-BS5.** | modal cropper |
| `<script ...popper.js/1.14.3/umd/popper.min.js>` | 4 | **LOAD-BEARING (dependencia de BS4 JS)** | Popper 1.x es dependencia del BS4 JS (línea 5). BS5 trae su propio Popper en el bundle, pero el BS4 JS necesita Popper 1.x global. | BS4 JS |
| `<script ...bootstrap/4.1.3/js/bootstrap.min.js>` | 5 | **LOAD-BEARING (crítico)** | **JS de Bootstrap 4.** El cropper llama `$modal.modal('show')` / `$modal.modal('hide')` y escucha `shown.bs.modal` / `hidden.bs.modal` (`:119,137,143,165`) — la **API jQuery de BS4**, que **BS5 eliminó**. Sin este BS4 JS, el modal no abre ni cierra. **Razón concreta de "BS4 sobre BS5".** | modal cropper |
| `<link ...cropperjs/1.5.6/cropper.css>` | 6 | **LOAD-BEARING** | Estilos del recortador. | widget cropper (`.preview`, `#image`) |
| `<script ...cropperjs/1.5.6/cropper.js>` | 7 | **LOAD-BEARING** | Librería Cropper.js; se instancia con `new Cropper(image, {...})` (`:138`). | widget cropper |
| `<style>` inline (`.imgs`, `.preview`, `.modal-lg`) | 8-23 | **LOAD-BEARING** | Estilan el modal/preview del cropper: `.imgs` (imagen a recortar), `.preview` (recuadro de vista previa 160×160 con borde rojo), `.modal-lg` (ancho 1000px). | modal cropper |

### Por qué BS4 gana sobre BS5 (la pregunta crítica)
1. **JS:** el cropper usa la API jQuery `.modal()` y los eventos `*.bs.modal` que **sólo existen en Bootstrap 4** (BS5 los quitó). Por eso el BS4 JS (línea 5) es indispensable para que el modal abra/cierre.
2. **Markup:** el modal usa `data-dismiss="modal"` y `<button class="close">` con `×` (`:73,90`) — sintaxis BS4. En BS5 sería `data-bs-dismiss` y `<button class="btn-close">`. El CSS BS4 (línea 3) renderiza ese markup correctamente.
3. **Orden de carga:** el bloque filtrado se emite al tope del documento; el `<link>` BS4 aparece en el flujo y por orden/especificidad sus reglas aplican al markup BS4 del modal.

> INCERTIDUMBRE A VERIFICAR EN NAVEGADOR: confirmar que (a) el modal del cropper abre y recorta correctamente con un usuario real, (b) quitar el BS4 JS efectivamente rompe el modal, y (c) el BS4 CSS no rompe visualmente la tarjeta `profile-card-2` ni el bloque de cuestionario (que usan utilidades BS5 como `text-center`, `display-4`, `btn-outline-info`).

---

## 5. Funcionalidad / forms / JS

**¿Qué hace la página de perfil?** Es una vista de **sólo lectura del propio perfil** + **un único acción de escritura: cambiar la foto de perfil mediante recorte**. NO hay edición de nombre/datos, NO hay cambio de contraseña aquí (eso es `/changepassword`), NO hay subida de documentos.

### Forms

| Form | Acción | Método | Driver real | Notas |
|---|---|---|---|---|
| Subir foto | `route('uploadCropImage')` = `/crop-image-upload` (`profile.blade.php:48`) | POST `multipart/form-data` | **No se envía como form** — es sólo un `<input type="file" class="image">` (`:57`) que dispara el cropper vía JS. El `<form>` nunca hace submit (no hay botón submit). | `method_field('post')` (`:50`) es redundante (POST ya es POST). |

### JS del cropper (`profile.blade.php:110-173`) — mismo patrón que el Home

1. `$("body").on("change", ".image", ...)` (`:115`): al elegir archivo, carga la imagen en `#image` y abre el modal (`$modal.modal('show')`).
2. `$modal.on('shown.bs.modal', ...)` (`:137`): instancia `new Cropper(image, {aspectRatio:1, viewMode:3, preview:'.preview'})`. Al cerrar (`hidden.bs.modal`) destruye el cropper.
3. `$("#crop").click(...)` (`:147`): recorta a **800×800**, convierte a base64, y hace `$.ajax` POST a `route('uploadCropImage')` con `{_token: <meta _token>, image: base64}`.
4. Al éxito (`:163-168`): `console.log`, cierra el modal, `alert("Crop image successfully uploaded")`, y `window.location.href = "/profile"` (recarga con la foto nueva).

### Backend del recorte (`cropimageController@uploadCropImage`, `:32-50`)
- Decodifica el base64 (`explode(";base64,", ...)`, `base64_decode($image_parts[1])`), genera `uniqid().'.png'`, lo escribe en `public_path('imagesprf/usrs/')` con `file_put_contents`, actualiza `$users->imgperfil` y devuelve `response()->json(['success'=>...])`.
- La foto se muestra desde `asset("imagesprf/usrs/$users->imgperfil")` (`profile.blade.php:32`).

> El método `cropimageController@uploadCropImages` (con "s", `:14-30`) y `PerfilController@update` (`:22-45`) son **rutas/manejadores alternativos de subida directa que ninguna vista viva usa** — el flujo real es 100% AJAX vía `uploadCropImage`. Posible CONTAMINACIÓN de backend (fuera del alcance de esta vista, pero anotado).

### Bloque de cuestionario de salud (`profile.blade.php:96-104`)
- Si `auth()->user()->encuestadiaria` → "Gracias responder su cuestionario de salud."
- Si no → saludo + botón "Responder cuestionario de salud" → `href="/dailyreport"`.
- **BUG DE ANIDAMIENTO (verificar):** este bloque está **dentro del `<div class="modal">`** (abre en `:68`, el cuestionario va en `:96-105`, y el modal cierra en `:106`). El `</div>` que cerraría `modal-content` está en `:94`, pero luego el cuestionario queda en un `col-8` suelto **dentro de `modal-dialog`**. El `@if/@else/@endif` además cierra un `</div>` de más (`:102`). Esto puede hacer que el recordatorio de cuestionario **no se vea** (está dentro de un `.modal fade` oculto) o rompa el DOM. **MARCAR PARA VERIFICACIÓN EN NAVEGADOR** — en `perfil.blade.php` este bloque no existe, así que es exclusivo de la vista viva.

---

## 6. Duplicación con el layout (`layouts/app.blade.php`)

Lo que el **layout ya provee** (y la vista NO debería re-declarar):

| Recurso | Layout (línea) | Re-declarado en profile.blade.php | Veredicto |
|---|---|---|---|
| `<!doctype html>` + `<html>` + `<head>` | 1-4 | — | el layout es dueño del documento |
| meta CSRF | 9 (`csrf-token`) | 1 (`_token`, **otro nombre**) | el de profile es load-bearing por el nombre `_token` que lee el JS |
| **Bootstrap CSS** | 16 (**BS5**) | 3 (**BS4**) | **COLISIÓN DE VERSIONES** — ambas cargan |
| **Bootstrap JS** | 18 (**BS5 bundle**) | 5 (**BS4**) | **COLISIÓN** — coexisten; el cropper depende del BS4 |
| **jQuery** | 35 (**3.3.1**) | 2 (**3.4.1**) | **DUPLICADO** — jQuery cargado dos veces |
| `form-register.css` (estilos `profile-card-2`, etc.) | 15 | — | provisto por layout; la vista lo consume sin re-declararlo |
| app.js | 19 | — | provisto por layout |
| a2hs.js (PWA) | 26 | — | provisto por layout |
| Bootstrap Icons / FontAwesome | 17, 32 | — | provisto por layout |
| Fuentes (Nunito, Poppins, Roboto, Lato) | 30-44 | — | provisto por layout |
| Header (con nav de perfil) | 59 (`@include('layouts.header')`) | — | provisto por layout |
| Sidebar (gate RBAC `admin || can('users.view')`) | 68-71 | — | provisto por layout |
| `@stack('styles')` / `@stack('scripts')` | 52, 91 | — | **ya existen** los puntos de inyección correctos — la vista debería usarlos en vez de filtrar `<head>` |

**Duplicaciones a limpiar en la reconstrucción (con cuidado):**
- jQuery cargado **2 veces** (layout 3.3.1 + profile 3.4.1). El de profile es CONTAMINACIÓN si el layout mantiene jQuery.
- Bootstrap cargado **2 veces con versiones distintas** (BS5 layout + BS4 profile). **NO eliminar BS4 a ciegas:** el modal/cropper depende de él. Portar el modal a BS5 (`data-bs-dismiss`, `btn-close`, API JS de BS5 o vanilla) ANTES de quitar BS4.
- El layout **ya ofrece `@stack('styles')` y `@stack('scripts')`** (comentados como el remedio del Quirks): mover el cropper css/js y el `<style>` ahí, y extraer a `public/css/perfil.css` / `public/js/perfil.js`.

---

## 7. Causa del Quirks mode

- El layout **SÍ** declara `<!doctype html>` correctamente en su línea 1 (`app.blade.php:1`).
- **PERO** `profile.blade.php` emite ~23 líneas de HTML (meta, `<script>`, `<link>`, `<style>`) **antes** de `@extends('layouts.app')` (línea 24). En Blade, todo lo que está fuera de una sección y antes de `@extends` se imprime **al inicio absoluto de la salida**, **por delante del `<!doctype html>`** del layout.
- Resultado: el documento final empieza con `<meta>...<script>...` y el `<!doctype html>` queda relegado → los navegadores caen en **Quirks mode** (`document.compatMode === 'BackCompat'`).
- **Causa raíz:** el doctype no está ausente, está **tarde** porque la vista filtra `<head>` por encima del `@extends`. **CONTAMINACIÓN estructural**, idéntica al Home.

**Arreglo de la reconstrucción:** que la vista empiece directamente con `@extends('layouts.app')`; mover lo necesario a `@push('styles')` / `@push('scripts')` (o al layout). Así el `<!doctype html>` es el primer byte y sale de Quirks mode.

---

## 8. JS / CSS realmente usado por Profile

**Realmente consumido:**
- jQuery (una sola carga basta) — usado por el cropper.
- Bootstrap 4 JS + CSS + Popper 1.x — **usado por el modal del cropper**. LOAD-BEARING hasta migrar el modal a BS5.
- Cropper.js + cropper.css — widget de recorte de foto.
- meta `_token` — leído por el AJAX del cropper.
- `<style>` inline (`.imgs`, `.preview`, `.modal-lg`) — estilan el modal/preview.
- `form-register.css` (layout) — clases `profile-card-2`, `profile-logo*`, `profile-name`, `profile-icons`, `data-basic` (vista viva); y `white-card-profile`, `app-widget-profile-card-night`, `circle-gft`, `profiles-*` (vista huérfana). **Confirmado: todas viven en `public/css/form-register.css`.**
- Bootstrap 5 (layout), FontAwesome, Bootstrap Icons, fuentes, header — chrome del layout.

**Incluido pero NO usado (CONTAMINACIÓN):**
- Segunda carga de jQuery (línea 2) — duplica el del layout.
- `method_field('post')` en el form (`:50`) — redundante.
- El `<form>` mismo no envía (no hay submit) — el flujo es AJAX; el form es un contenedor del `<input file>`.
- Comentarios muertos: bloque `project-gft` comentado (`:52-55`), `borndate` comentado en perfil.
- **Toda la vista `perfil.blade.php`** y el método `PerfilController@index` — huérfanos.

---

## 9. RBAC / flags

- **La vista `profile.blade.php` NO tiene gating `@if(admin)` ni `daytest`** — es idéntica para todo usuario autenticado. (A diferencia del Home, no hay switch admin/crew dentro de la vista.)
- Único flag de UI que ramifica: `auth()->user()->encuestadiaria` (`:97`) — muestra "Gracias…" vs botón "Responder cuestionario". **No es rol**, es estado de cuestionario diario (mismo flag que en home/inicio). No requiere `@can`.
- El **gating de rol vive en el chrome del layout**, no en la vista:
  - Header: `@if(auth()->user()->admin || auth()->user()->can('users.view'))` decide el mini-nav admin vs el nav de crew (`header.blade.php:12`).
  - Sidebar: mismo gate `admin || can('users.view')` (`app.blade.php:68`).
- **A nivel de ruta:** `/profile` está duplicada en el grupo `admin` (`:191`) y en el grupo `auth` (`:206`); gana `auth`, así que cualquier autenticado entra (ver `ROUTES.md:33-34`). No es un agujero (el perfil es propio), pero el duplicado debe resolverse en la limpieza.

> Conclusión RBAC: en la reconstrucción de **esta vista** no hay booleano `admin`/`daytest` que migrar a `@can` (no los usa). El trabajo RBAC pertinente es del header/sidebar/rutas (ya en transición aditiva), no del cuerpo de `profile.blade.php`. El único cambio de rutas es **eliminar la `/profile` duplicada**.

---

## 10. Contrato de comportamiento a preservar (oráculo — verificar sin regresión)

### Checklist (cualquier usuario autenticado, vista única — no hay rama admin/crew)
- [ ] Al entrar a `/profile` ve su **tarjeta de perfil** `profile-card-2`: foto (`imagesprf/usrs/{imgperfil}`), logo CrewCare, logo cliente (redrum.png), nombre+apellido, zona, puesto/departamento y **edad** (Carbon `borndate->age`).
- [ ] Encabezado "MI PERFIL".
- [ ] Bloque "Cambia tu foto de perfil" con `<input type="file" class="image">`.
- [ ] Al seleccionar una imagen, **se abre el modal "Corta tu foto"** con vista previa (recuadro `.preview` con borde rojo).
- [ ] El recorte (Cropper.js, 1:1, 800×800) funciona; "Actualizar" (`#crop`) hace POST AJAX a `uploadCropImage` con el `_token` del meta, muestra `alert("Crop image successfully uploaded")` y **redirige/recarga `/profile`** con la nueva foto.
- [ ] "Cancelar" / la `×` cierran el modal (BS4 `data-dismiss`) y destruyen el cropper.
- [ ] Recordatorio de cuestionario: "Gracias responder su cuestionario de salud" si `encuestadiaria`, o botón "Responder cuestionario de salud" → `/dailyreport` si no. **⚠ Verificar que este bloque sea VISIBLE** — actualmente está anidado dentro del `.modal` (ver §5, bug de anidamiento); puede estar oculto en producción. **Comparar contra el oráculo (producción) antes de "arreglarlo".**
- [ ] Header con nav según rol (admin mini-nav vs crew: Atras / Change Password / Profile) — provisto por el layout, no por la vista.
- [ ] **NO** se rompe nada visual al quitar el BS4 CSS (la tarjeta y el cuestionario usan utilidades BS5) — verificar.

> Puntos más frágiles de la migración:
> 1. El **modal del cropper** (depende de BS4 JS/CSS) — reproducir exactamente: abrir al elegir archivo, previsualizar, recortar 1:1 800×800, subir AJAX con `_token`, alertar y recargar.
> 2. El **bloque de cuestionario mal anidado** — decidir, contra producción, si debe verse fuera del modal (probablemente sí) o si está intencionalmente oculto. NO asumir.

---

## 11. Incertidumbres a verificar en navegador en vivo

1. Confirmar que el **modal del cropper abre/cierra/recorta** con un usuario real, y que romper/quitar el BS4 JS efectivamente lo rompe.
2. Confirmar el **Quirks mode** en DevTools (`document.compatMode === 'BackCompat'`) atribuible al bloque filtrado antes del doctype.
3. **Anidamiento del bloque de cuestionario** (`profile.blade.php:96-105`): ¿se ve en producción o queda oculto dentro del `.modal`? El `@if` cierra un `</div>` de más (`:102`). Verificar comportamiento real ANTES de tocar.
4. Confirmar que **`perfil.blade.php` y `PerfilController@index` son inalcanzables** (no hay ruta) — corroborar con `php artisan route:list` que no aparece ninguna ruta que invoque `index`.
5. Confirmar que **`PerfilController@update` (`/update`, name `perfil.update`) y `cropimageController@uploadCropImages`** no tienen emisor vivo (ningún form/JS postea a ellos) → CONTAMINACIÓN de backend.
6. Resolver la **ruta `/profile` duplicada** (`web.php:191` vs `:206`) en la limpieza.
