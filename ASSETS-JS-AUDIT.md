# Auditoría de JavaScript — CrewCare (Laravel 8)

**Fecha:** 2026-06-24
**Alcance:** Todo el JS de la app (`resources/js/`, `public/js/`, scripts en `resources/views/**`).
**Restricción del entorno:** `npm install` / `npm run prod` BLOQUEADO por red/SSL. La solución NO puede depender de compilar con Mix/webpack.

---

## TL;DR — Veredicto del 404

- El 404 lo causa `resources/views/layouts/app.blade.php:19` → `<script src="{{ asset('js/app.js') }}"></script>`. Apunta a `public/js/app.js`, **que no existe** (Mix nunca se compiló: no hay `public/mix-manifest.json` ni bundle).
- `resources/js/app.js` es **el scaffold default de Laravel** (Vue + `window.Vue` + `ExampleComponent` + `bootstrap.js` con jQuery/axios/lodash) **más** un pequeño bloque propio de colapso de sidebar (líneas 36-56).
- **Nada de la app depende del bundle compilado:** ninguna vista usa `axios`, `new Vue`, `<example-component>` ni el global `$` proveniente de ese bundle. jQuery se carga aparte por CDN. Incluso el bloque custom de sidebar es **código muerto** (busca un `.collapse-btn` que no existe en ninguna vista).
- **Recomendación:** crear a mano un `public/js/app.js` mínimo (ver §7). Es la opción más segura, no requiere npm, elimina el 404 sin tocar Blade y deja un punto de extensión para JS global futuro.

---

## 1. `resources/js/` — código fuente

### `resources/js/app.js`
- **Líneas 1-32:** scaffold default puro de Laravel 8:
  - `require('./bootstrap')`
  - `window.Vue = require('vue').default`
  - `Vue.component('example-component', ...)`
  - `new Vue({ el: '#app' })`
- **Líneas 36-56 (añadido propio):** listener `DOMContentLoaded` que togglea clases de colapso de sidebar buscando `.sidebar-expanded`, `.collapse-btn`, `.main-content`.
  - **Es código muerto:** el guard `if (collapseBtn && sidebar && collapseIcon && mainContent)` exige un elemento `.collapse-btn`, que **no existe en ninguna vista** (verificado: `sidebar.blade.php` usa `.sidebar-expanded` pero ningún `.collapse-btn` ni `.main-content`). Nunca se ejecuta el cuerpo.

### `resources/js/bootstrap.js`
- Scaffold default sin modificar:
  - `window._ = require('lodash')`
  - `window.Popper`, `window.$ = window.jQuery = require('jquery')`, `require('bootstrap')` (Bootstrap 4, dentro de try/catch)
  - `window.axios = require('axios')` + header `X-Requested-With`
  - Echo/Pusher comentados.
- **Define globales** (`window.$`, `window.axios`, `window._`, `window.Vue`) — pero solo si el bundle se compila. Ver §4: ninguna vista los consume.

### `resources/js/components/ExampleComponent.vue`
- Componente de ejemplo default de Laravel. No se usa en ninguna vista (`<example-component>` no aparece en Blade).

**Conclusión §1:** `app.js` es scaffold default + un bloque propio inservible. No aporta nada que las vistas usen.

---

## 2. `public/js/` — archivos servidos

| Archivo | Estado | Descripción | ¿Referenciado en Blade? |
|---|---|---|---|
| `public/js/app.js` | **NO EXISTE** | El bundle que Mix debería generar. Origen del 404. | Sí — `layouts/app.blade.php:19` |
| `public/js/a2hs.js` | **VACÍO (0 bytes / 1 línea vacía)** | "Add to Home Screen" PWA, sin contenido. | Sí — `layouts/app.blade.php:26`, `auth/passwords/email.blade.php:22` (carga 200 OK pero no hace nada) |
| `public/js/departamentosdinamicos.js` | Existe | jQuery: on `change` de `id_departamentos` hace `fetch()` a `http://127.0.0.1:8000/cargarusuarios/{id}` e inyecta HTML en `#usuariosdepartamento`. **Depende de `$` global (jQuery por CDN).** URL `127.0.0.1:8000` hardcodeada. | Sí — `admin/consultas.blade.php:5`, `auth/register.blade.php:7` |
| `public/js/materialize.js` | Existe | Librería Materialize CSS (JS). | **NO** referenciado en ninguna vista — huérfano |
| `public/js/materialize.min.js` | Existe | Versión minificada de Materialize. | **NO** referenciado — huérfano |

- **NO existe `public/mix-manifest.json`** → confirma que Mix nunca corrió. Por eso `mix('js/app.js')` fallaría; la app usa `asset()` (no `mix()`), así que al menos no lanza la excepción "Unable to locate Mix file".

---

## 3. Referencias `<script>` en `resources/views/**`

Clasificación: **CDN** (externo OK) · **LOCAL OK** (archivo existe) · **404** (archivo local inexistente) · **BUG `asset(https)`** (URL rota) · **VACÍO** (carga pero 0 bytes).

| Archivo:línea | `src` | Helper | Clasificación |
|---|---|---|---|
| `layouts/app.blade.php:18` | `cdn.jsdelivr.net/.../bootstrap@5.1.3/.../bootstrap.bundle.min.js` | — | CDN |
| `layouts/app.blade.php:19` | `asset('js/app.js')` | `asset()` | **404** ← bug principal |
| `layouts/app.blade.php:20` | `cdn.tiny.cloud/.../tinymce/6/tinymce.min.js` | — | CDN |
| `layouts/app.blade.php:21` | `cdn.jsdelivr.net/npm/chart.js` | — | CDN |
| `layouts/app.blade.php:26` | `asset('js/a2hs.js')` | `asset()` | **VACÍO** (0 bytes) |
| `layouts/app.blade.php:35` | `asset('https://code.jquery.com/jquery-3.3.1.min.js')` | `asset()` | **BUG `asset(https)`** |
| `auth/login.blade.php:19` | `bootstrap@5.1.3 bundle.min.js` | — | CDN |
| `auth/login.blade.php:27` | `asset('https://code.jquery.com/jquery-3.3.1.min.js')` | `asset()` | **BUG `asset(https)`** |
| `auth/register.blade.php:7` | `js/departamentosdinamicos.js` (ruta relativa, sin `asset()`) | ninguno | LOCAL OK (frágil: ruta relativa, rompe en subrutas) |
| `auth/passwords/email.blade.php:18` | `bootstrap@5.1.3 bundle.min.js` | — | CDN |
| `auth/passwords/email.blade.php:22` | `asset('js/a2hs.js')` | `asset()` | **VACÍO** |
| `auth/passwords/email.blade.php:30` | `asset('https://code.jquery.com/jquery-3.3.1.min.js')` | `asset()` | **BUG `asset(https)`** |
| `profile.blade.php:2,4,5,7` | jquery 3.4.1 / popper 1.14.3 / bootstrap 4.1.3 / cropper 1.5.6 | — | CDN |
| `perfil.blade.php:2,4,5,7` | idéntico a profile | — | CDN |
| `inicio.blade.php:2,4,5,7` | jquery 3.4.1 / popper / bootstrap 4.1.3 / cropper | — | CDN |
| `inicio.blade.php:336` | `chartjs-plugin-trendline` | — | CDN |
| `componentes/historiamr.blade.php:455,456` | jquery 1.11.2 / html2canvas | — | CDN |
| `admin/consultas.blade.php:5` | `asset('js/departamentosdinamicos.js')` | `asset()` | LOCAL OK |
| `admin/dailyreports/show.blade.php:3` | `cdn.tailwindcss.com` | — | CDN |
| `admin/idcard.blade.php:39,40` | jquery 1.11.2 / html2canvas | — | CDN |
| `admin/idcard.bladeOLD.php:81,82` | jquery 1.11.2 / html2canvas | — | CDN (archivo `.bladeOLD`, no en uso) |

### El bug `asset('https://...')`
`asset('https://code.jquery.com/jquery-3.3.1.min.js')` antepone la URL base de la app a la URL absoluta, produciendo algo como `https://midominio.com/https://code.jquery.com/...` → **404 / jQuery NO se carga** por esa vía. Afecta:
- `layouts/app.blade.php:35`
- `auth/login.blade.php:27`
- `auth/passwords/email.blade.php:30`

---

## 4. ¿Algo depende del bundle compilado? — NO

Verificado por búsqueda en `resources/views/**`:
- `axios` → **0 usos** en vistas.
- `new Vue` / `Vue.` → **0 usos**.
- `<example-component>` → **0 usos**.
- El global `$` (jQuery) usado por `departamentosdinamicos.js` e inline scripts **proviene del CDN**, no del bundle. (Aunque, ojo: en `layouts/app.blade.php` el único jQuery es el de la línea 35 rota por `asset(https)` — ver §5.)
- El bloque custom de sidebar dentro de `app.js` es **código muerto** (no hay `.collapse-btn`).

**Conclusión:** quitar/dejar vacío `public/js/app.js` **no rompe nada**. Todo el JS real es jQuery por CDN + librerías por CDN (TinyMCE, Chart.js, cropper, html2canvas, Bootstrap) + scripts inline. `app.js` es prescindible.

---

## 5. jQuery — fuentes y conflicto

jQuery se carga desde **múltiples versiones y fuentes** según la vista:

| Vista | Versión jQuery | Fuente | Estado |
|---|---|---|---|
| `layouts/app.blade.php:35` | 3.3.1 | `asset('https://code.jquery.com/...')` | **ROTO** (bug asset/https) → en las vistas que extienden este layout, jQuery **no se carga**, pero los inline scripts y `departamentosdinamicos.js` asumen `$`. |
| `profile/perfil/inicio.blade.php:2` | 3.4.1 | cdnjs | OK |
| `componentes/historiamr.blade.php:455` | 1.11.2 | googleapis | OK (versión muy antigua) |
| `admin/idcard.blade.php:39` | 1.11.2 | googleapis | OK |
| `auth/login.blade.php:27`, `email.blade.php:30` | 3.3.1 | `asset('https://...')` | **ROTO** |

**Conflicto/problema real:**
- **Versiones inconsistentes** (1.11.2, 3.3.1, 3.4.1) entre vistas.
- En el layout principal, el único jQuery es el de la línea rota → **cualquier inline script que use `$` en una vista que solo extiende `layouts/app.blade.php` fallará** salvo que la propia vista cargue jQuery por su cuenta (profile/perfil/inicio sí lo hacen; otras no).
- Bootstrap se carga en dos majors a la vez según vista (Bootstrap 5 bundle en el layout; Bootstrap 4.1.3 en profile/perfil/inicio) → posible doble carga de Bootstrap JS.

> Nota: esto es independiente del 404 de `app.js`, pero es la causa probable de "el JS no funciona" en algunas pantallas. **Arreglar el `asset('https://...')`** (cambiarlo a `<script src="https://code.jquery.com/jquery-3.3.1.min.js" ...>` sin `asset()`) restauraría jQuery global en el layout.

---

## 6. Inline scripts repetidos

- **TinyMCE init:** `layouts/app.blade.php:77-86` inicializa `tinymce.init({ selector: '#MyEmail', ... })` global. (Solo 1 init en layout; el CDN de TinyMCE está en línea 20.) Otras vistas que usan editor dependerían de este selector — patrón centralizado, OK.
- **html2canvas (captura a imagen):** patrón duplicado en `admin/idcard.blade.php`, `admin/idcard.bladeOLD.php` y `componentes/historiamr.blade.php` (mismo par jquery 1.11.2 + html2canvas para impresión/exportación de gafetes/reportes).
- **jQuery + Bootstrap 4 + Popper + cropper:** bloque idéntico repetido en `profile.blade.php`, `perfil.blade.php`, `inicio.blade.php` (líneas 2-7). Candidato a extraer a un partial.
- **Selects dinámicos:** `departamentosdinamicos.js` reutilizado en `admin/consultas.blade.php` y `auth/register.blade.php`.

---

## 7. VEREDICTO Y RECOMENDACIÓN CONCRETA (404 de `app.js`)

### Opciones evaluadas
- **(a) Compilar con webpack/Mix** → ❌ DESCARTADA: requiere `npm install`/`npm run prod`, bloqueado por red/SSL. Además traería Vue/axios/lodash que nadie usa.
- **(b) Escribir `public/js/app.js` a mano** → ✅ **RECOMENDADA**. No requiere npm, elimina el 404, no toca Blade, y deja un archivo real donde poner JS global futuro. Riesgo cero porque nada depende del bundle.
- **(c) Quitar la referencia** (borrar `layouts/app.blade.php:19`) → ✅ válida y también correcta, pero pierdes el punto de extensión y hay que editar Blade. Equivalente funcional a (b) con archivo vacío.

### Recomendación: opción (b)

Crear el archivo **`public/js/app.js`** a mano con el siguiente contenido. Incluye el bloque de colapso de sidebar (rescatado de `resources/js/app.js`) por si más adelante se añade el botón `.collapse-btn`; hoy es inofensivo (guard que no se cumple). **No** incluye Vue/axios/lodash porque ninguna vista los usa.

```javascript
/**
 * public/js/app.js
 * JS global de CrewCare servido directamente (sin Laravel Mix).
 *
 * Nota: este proyecto NO compila el bundle de Mix (no hay mix-manifest.json
 * y npm está bloqueado en este entorno). Las dependencias reales — jQuery,
 * Bootstrap, TinyMCE, Chart.js — se cargan por CDN desde los layouts/vistas.
 * Vue/axios/lodash del scaffold por defecto NO se usan en ninguna vista,
 * por lo que se omiten a propósito.
 */

// Colapso/expansión del sidebar (activo solo si existe un .collapse-btn).
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar-expanded');
    const collapseBtn = document.querySelector('.collapse-btn');
    const collapseIcon = collapseBtn ? collapseBtn.querySelector('i') : null;
    const mainContent = document.querySelector('.main-content');

    if (collapseBtn && sidebar && collapseIcon && mainContent) {
        collapseBtn.addEventListener('click', function () {
            sidebar.classList.toggle('sidebar-collapsed');
            sidebar.classList.toggle('sidebar-expanded');
            collapseIcon.classList.toggle('fa-chevron-left');
            collapseIcon.classList.toggle('fa-chevron-right');
            mainContent.classList.toggle('margin-left-expanded');
            mainContent.classList.toggle('margin-left-collapsed');
        });
    }
});
```

> Versión mínima alternativa: si se prefiere lo más simple posible, el archivo puede ser **solo un comentario** (`/* CrewCare global JS — intencionalmente vacío; libs por CDN. */`). Ambas eliminan el 404. **No** hace falta `window.axios` porque ninguna vista lo usa.

### Acciones adicionales recomendadas (no bloqueantes del 404)
1. **Arreglar `asset('https://...')` de jQuery** en `layouts/app.blade.php:35`, `auth/login.blade.php:27`, `auth/passwords/email.blade.php:30`: quitar `asset()` y dejar la URL absoluta. Esto restaura jQuery global y arregla los inline scripts del layout.
2. **`a2hs.js` vacío:** o bien rellenar la lógica PWA "Add to Home Screen", o quitar las dos referencias (`layouts/app.blade.php:26`, `email.blade.php:22`) para evitar requests inútiles.
3. **Materialize huérfano:** `public/js/materialize*.js` no se referencian — eliminar si no se usan.
4. **Unificar versiones de jQuery/Bootstrap** entre vistas para evitar doble carga y conflictos.
