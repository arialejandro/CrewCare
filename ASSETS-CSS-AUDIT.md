# Auditoría de CSS — crewcarerr (Laravel 8)

> Fecha: 2026-06-24. Solo lectura sobre la app. El build con `npm` está **BLOQUEADO** (red/SSL); todas las recomendaciones funcionan **sin** ejecutar `npm`.

---

## 1. Contenido de `public/css/`

| Archivo | Tamaño | Qué es | ¿Referenciado en Blade? | Veredicto |
|---|---|---|---|---|
| `main.css` | 580.970 B (~567 KB, 25.281 líneas) | **Tema admin hecho a mano / bundle legacy estilo AdminLTE sobre Bootstrap 3.** Contiene `.box.box-success`, `content-wrapper`, `main-sidebar`, `skin-blue`, FontAwesome **4.3.0** (`@font-face FontAwesome`), DataTables (`.dataTables_*`) y Select2 (`.select2-*`). El propio archivo dice *"CSS used here will be applied after bootstrap.css"*. **No** es output de Laravel Mix (Mix generaría `app.css`), **no** es Bootstrap puro y **no** contiene Materialize ni Tailwind. | **NO** (0 referencias en `resources/views/**`) | **REMOVE** (huérfano) |
| `materialize.css` | 188.312 B | Materialize CSS **v1.0.0** (framework completo, sin minificar). | **NO** | **REMOVE** |
| `materialize.min.css` | 141.854 B | Materialize CSS v1.0.0 minificado. | **NO** | **REMOVE** |
| `form-register.css` | 29.077 B | Estilos a mano del proyecto (sidebar `.bg-light.no-print`, `.link-dark`, formularios de registro/impresión). Pensado para complementar Bootstrap 5. | **SÍ** | **KEEP** |
| `login.css` | 4.008 B | Estilos a mano de la pantalla de login (`.wrapper-login`, `.login-box`, gradientes, usa `url(../img/main-login.jpg)`). | **SÍ** (solo `auth/login.blade.php`) | **KEEP** |
| `appcc.css` | 64 B | Una sola línea: `@import "../../node_modules/bootstrap/dist/css/bootstrap.css";`. Solo funciona si `node_modules` se sirve públicamente (no es el caso en prod). Resto de Bootstrap. | **NO** | **REMOVE** |
| `newapp.scss` | 59 B | Un `.scss` colocado por error dentro de `public/`: `@import "../../node_modules/bootstrap/scss/bootstrap.scss";`. El navegador **no** compila SCSS; archivo inútil sin un build. | **NO** | **REMOVE** |

**Resumen sección 1:** de 7 archivos, solo **2 se usan** (`form-register.css`, `login.css`). Los otros 5 (~970 KB combinados, dominados por `main.css` + las dos copias de Materialize ≈ 910 KB) son peso muerto.

---

## 2. `resources/sass/`

| Archivo | Contenido | Análisis |
|---|---|---|
| `app.scss` (166 B) | `@import url(Nunito font); @import 'variables'; @import '~bootstrap/scss/bootstrap';` | **Scaffold default de Laravel/UI** sin modificar. |
| `_variables.scss` (336 B) | Variables Bootstrap por defecto (`$body-bg`, `$font-family-sans-serif: 'Nunito'`, paleta `$blue/$indigo/...`). | Scaffold default. |

- **¿Compilaría a `public/css/app.css`?** Sí. `webpack.mix.js` define `mix.sass('resources/sass/app.scss', 'public/css')` → generaría `public/css/app.css`.
- **¿Se usa ese output?** **NO.** Existe `resources/css/app.css` pero está **vacío (0 bytes)** y **`public/css/app.css` ni siquiera existe**. Ninguna vista referencia `css/app.css`. Es decir, el pipeline de Mix está configurado pero su salida nunca fue generada ni enlazada. Bootstrap se carga en su lugar vía **CDN** (ver sección 3).
- **Conclusión:** `resources/sass/` es scaffold huérfano. No se puede recompilar sin `npm` (bloqueado), pero tampoco hace falta: la app no usa su output.

---

## 3. Referencias de CSS en Blade (por vista/layout)

**`<link rel="stylesheet">` encontrados:**

| Vista / Layout | href | Clasificación |
|---|---|---|
| `layouts/app.blade.php:15` | `asset("css/form-register.css")` | Local **existe** OK |
| `layouts/app.blade.php:16` | `cdn.jsdelivr.net/.../bootstrap@5.1.3` | CDN (Bootstrap 5) |
| `layouts/app.blade.php:17` | `cdn.jsdelivr.net/.../bootstrap-icons@1.8.1` | CDN |
| `layouts/app.blade.php:30-44` | Google Fonts (Nunito, Material Icons, Lato, Poppins), FontAwesome 6 CDN | CDN |
| `layouts/header.blade.php` | — (sin `<link>` ni `<style>`; es el navbar) | n/a |
| `layouts/sidebar.blade.php` | — | n/a |
| `auth/login.blade.php:15` | `asset("css/login.css")` | Local **existe** OK |
| `auth/login.blade.php:16` | `asset("css/form-register.css")` | Local **existe** OK |
| `auth/login.blade.php:17-18,23-36` | Bootstrap 5 CDN, bootstrap-icons CDN, varias Google Fonts, FontAwesome 6 CDN, `http://fonts.googleapis.com` (Open Sans, **http** no https) | CDN |
| `auth/passwords/email.blade.php:15-39` | `asset("css/form-register.css")` + Bootstrap 5 CDN + icons + fonts | Local existe + CDN |
| `perfil.blade.php:3,6` | `cdnjs.../twitter-bootstrap/4.4.1` (**Bootstrap 4**) + `cropperjs/1.5.6` | CDN — **etiqueta malformada** (cierra con `</script>`) |
| `inicio.blade.php:3,6` | `cdnjs.../twitter-bootstrap/4.4.1` + cropperjs | CDN — mismo bug `</script>` |
| `profile.blade.php:3,6` | `cdnjs.../twitter-bootstrap/4.4.1` + cropperjs | CDN — mismo bug `</script>` |
| `welcome.blade.php:10` | Google Fonts Nunito | CDN |
| `admin/dailyreports/show.blade.php:3-4` | `cdn.tailwindcss.com` (script) + Google Fonts | CDN — **Tailwind** (ver sección 4) |
| `componentes/historiamr.blade.php:472-475` | `form-register.css` + Bootstrap 5 + icons (escritos vía `document.write` en iframe de impresión) | Local + CDN (iframe) |
| `virtualqueue/antigentest.blade.php:109-112` | igual (iframe de impresión) | Local + CDN (iframe) |
| `admin/idcard.blade.php:56-59` | igual (iframe) | Local + CDN (iframe) |
| `admin/idcard.bladeOLD.php:98-101` | igual — **archivo `.bladeOLD.php` muerto** | Local + CDN (iframe) |

**Notas:**
- **No hay ninguna referencia local a `main.css`, `materialize*.css`, `appcc.css`, `newapp.scss` ni `css/app.css`** en todo `resources/views/**`. Confirmado por búsqueda global (solo aparecen en otros `.md` de auditoría y dentro de los propios archivos css).
- La mayoría de las ~59 vistas que hacen `@extends('layouts.app')` heredan el stack de `layouts/app.blade.php` (Bootstrap 5 CDN + form-register.css + iconos/fuentes).

---

## 4. Frameworks CSS simultáneos

| Framework | Versión | Origen | Dónde |
|---|---|---|---|
| **Bootstrap 5** | 5.1.3 | CDN (jsdelivr) | `layouts/app.blade.php` → casi todas las vistas; login; email; iframes de impresión |
| **Bootstrap 4** | 4.4.1 | CDN (cdnjs) | `perfil.blade.php`, `inicio.blade.php`, `profile.blade.php` |
| **Bootstrap 3** | embebido | local | dentro de `main.css` (no cargado) |
| **Materialize** | 1.0.0 | local `public/css` | **ninguna vista lo carga** (huérfano) |
| **Tailwind** | runtime CDN | `cdn.tailwindcss.com` | `admin/dailyreports/show.blade.php` |

**Vistas que cargan 2+ frameworks a la vez (riesgo de colisión de clases):**
- `perfil.blade.php`, `inicio.blade.php`, `profile.blade.php`: heredan **Bootstrap 5** del layout **+** cargan **Bootstrap 4** propio → dos versiones de Bootstrap pisándose (`.row`, `.btn`, grid, utilidades). Además el `<link>` está malformado (cierra con `</script>`).
- `admin/dailyreports/show.blade.php`: **Bootstrap 5** (del layout) **+ Tailwind CDN** → utilidades en conflicto (`.flex`, `.p-2`, reset de Tailwind sobre componentes BS5). El Tailwind runtime CDN además es lento y no recomendado para producción.

Materialize **no** colisiona hoy porque nadie lo carga, pero su sola presencia en `public/` engaña (≈330 KB de las dos copias).

---

## 5. `<style>` inline

- **23 vistas** contienen bloques `<style>` (25 ocurrencias).
- Más voluminosos (líneas dentro de `<style>`), candidatos a extraer a `.css`:

| Vista | ~Líneas de `<style>` |
|---|---|
| `admin/unsafecond.blade.php` | 231 |
| `admin/hazard.blade.php` | 231 |
| `admin/location.blade.php` | 211 |
| `inicio.blade.php` | 204 |
| `admin/locationreport.blade.php` | 170 |
| `admin/injuryreport.blade.php` | 146 |
| `componentes/historiamr.blade.php` | 116 |
| `admin/locationcrud.blade.php` | 81 |

Estos bloques repiten estilos de tarjetas/tablas/impresión y son buenos candidatos a consolidar en `form-register.css` (o un nuevo `app-custom.css`) sin tocar el build.

---

## 6. 404 / rutas rotas

- **Archivos referenciados que NO existen en disco:** ninguno crítico. Todos los `asset("css/...")` apuntan a archivos que sí existen (`form-register.css`, `login.css`).
- **Bug `asset('https://...')`:** aplicado a **JS, no a CSS**, pero presente y roto en 3 layouts:
  - `auth/login.blade.php:27`, `layouts/app.blade.php:35`, `auth/passwords/email.blade.php:30`:
    `<script src="{{ asset('https://code.jquery.com/jquery-3.3.1.min.js') }}">`. `asset()` antepone la URL base de la app a una URL ya absoluta → genera algo como `https://miapp/https://code.jquery.com/...` → **jQuery 404**. (Para CSS no se detectó este patrón.)
- **`<link>` malformados:** `perfil`, `inicio`, `profile` cierran el `<link rel="stylesheet">` con `</script>` (línea 3). El navegador tolera el `</script>` huérfano pero es HTML inválido.
- **`http://` no seguro:** `auth/login.blade.php:31` carga Google Fonts por `http://` (mixed content si la app va por HTTPS).
- **`appcc.css` / `newapp.scss`:** apuntan a `node_modules/` que no se sirve en producción → romperían si alguien los enlazara. Hoy nadie los enlaza.

---

## 7. VEREDICTO Y RECOMENDACIÓN

### Stack CSS objetivo
**Bootstrap 5 único** (ya es el framework dominante vía CDN en `layouts/app.blade.php`) + `form-register.css` + `login.css` como CSS propio. **Retirar Materialize** (huérfano), **retirar el Tailwind CDN** (`dailyreports/show`), y **unificar las 3 vistas con Bootstrap 4** (perfil/inicio/profile) a Bootstrap 5.

### Borrable sin riesgo (huérfanos, 0 referencias)
| Archivo | Por qué |
|---|---|
| `public/css/materialize.css` | Nadie lo carga |
| `public/css/materialize.min.css` | Nadie lo carga |
| `public/css/main.css` | Tema AdminLTE/BS3 legacy, nadie lo carga (~567 KB) |
| `public/css/appcc.css` | Import a node_modules, sin referencias |
| `public/css/newapp.scss` | SCSS dentro de public/, inútil sin build |
| `resources/css/app.css` | 0 bytes |
| `resources/views/admin/idcard.bladeOLD.php` | Vista vieja `.bladeOLD.php` |

> Eliminar estos 5 css/scss libera ~970 KB del directorio público sin afectar el render (verificado: ninguno está enlazado).

### Sobre `main.css`
**NO es esencial.** Es un bundle hecho a mano de un tema de admin antiguo (AdminLTE sobre Bootstrap 3 + FontAwesome 4 + DataTables + Select2). No salió de Laravel Mix (Mix produciría `app.css`); fue copiado a mano al repo. Como ninguna vista lo enlaza, su CSS no se aplica hoy. Se puede archivar/borrar.

### Pasos concretos (sin `npm`)
1. **Borrar huérfanos:** `materialize.css`, `materialize.min.css`, `main.css`, `appcc.css`, `newapp.scss` de `public/css/`; `resources/css/app.css`; e `idcard.bladeOLD.php`. (Operación de archivos, no requiere build.)
2. **Unificar Bootstrap:** en `perfil.blade.php`, `inicio.blade.php`, `profile.blade.php` quitar el `<link>` a Bootstrap 4.4.1 (y el `</script>` sobrante); ya heredan Bootstrap 5 del layout. Verificar que sus clases sigan correctas en BS5.
3. **Quitar Tailwind:** en `admin/dailyreports/show.blade.php` eliminar `<script src="https://cdn.tailwindcss.com">` y reescribir esas utilidades con clases Bootstrap 5 o con el `<style>` local que ya tiene la vista.
4. **Arreglar el bug `asset('https://...')`** en los 3 layouts: cambiar a `<script src="https://code.jquery.com/jquery-3.3.1.min.js">` (sin `asset()`), o servir jQuery local. (Es JS pero rompe el front.)
5. **Consolidar `<style>` inline** de las vistas más pesadas (unsafecond/hazard/location/inicio) en `form-register.css` o un nuevo `public/css/app-custom.css` enlazado directamente — editar archivos a mano, sin compilación.
6. **Opcional:** dejar de depender de CDNs (Bootstrap/icons/fonts) descargando copias a `public/` y enlazándolas con `asset()`, para que la app no quede atada a red externa. Esto **no** requiere `npm` (solo copiar archivos).

---

*Archivos relevantes:* `public/css/` (7 archivos), `resources/sass/{app,_variables}.scss`, `resources/css/app.css` (0 B), `webpack.mix.js`, `resources/views/layouts/app.blade.php`, `resources/views/auth/login.blade.php`, `resources/views/admin/dailyreports/show.blade.php`, `resources/views/{perfil,inicio,profile}.blade.php`.
