# CODE-AUDIT.md — Auditoría de código (backend, JS, config, PWA)

Auditoría read-only de las capas que faltaban tras el inventario de vistas: **backend PHP**
(controllers/models/jobs/mail/commands), **JavaScript/assets**, **config + dependencias/versiones**
y **PWA**. Complementa [ARCHITECTURE.md](ARCHITECTURE.md), [SECURITY.md](SECURITY.md),
[PERFORMANCE.md](PERFORMANCE.md) y [VIEWS-INVENTORY.md](VIEWS-INVENTORY.md). Sin cambios de código.

---

## 🔴 Hallazgos CRÍTICOS nuevos (no estaban en auditorías previas)

1. **El build de assets NUNCA se corrió.** No existe `public/js/app.js` ni `public/mix-manifest.json`
   (solo el fuente `resources/js/app.js`). Pero el layout lo carga con `asset('js/app.js')`
   (`layouts/app.blade.php:19`) → **404 en cada página**; el JS propio de la app jamás se ejecuta.
   La app "funciona" solo porque cada librería se carga por CDN. *Fix inmediato:* `npm install && npm run prod`.
2. **La PWA nunca funcionó — y ahora sabemos por qué.** El service worker hace `cache.addAll()` de
   archivos que **no existen** (los iconos nunca se publicaron; `app.js` nunca se compiló).
   `addAll` es atómico: un solo 404 → `install` falla → el SW **nunca se activa**. Cero offline, no instalable.
3. **Error de sintaxis PHP fatal:** `app/Jobs/ReminderEmail.php:45` →
   `$msj->from("covid@crewcare.tech,"CrewCare");` (comilla sin cerrar). Cualquier dispatch de ese job
   es un fatal parse error. La ruta `/reminder-mail` está rota.
4. **Scheduler corriendo cada minuto:** `app/Console/Kernel.php:31` ejecuta `encuestas:task`
   con `->everyMinute()`. El comando resetea `encuestadiaria=0` de TODA la plantilla **y envía un
   correo a cada usuario activo** — 1440 veces al día, con `from` a un Gmail personal
   (`al221511303@gmail.com`). Casi seguro debía ser `->daily()`. Spam masivo + saturación SMTP + 2N+1 queries/min.
5. **Laravel 8 + PHP 7.3 están en FIN DE VIDA** (sin parches de seguridad desde 2023). Es deuda de
   estabilidad de fondo: hay que subir a Laravel 11 + PHP 8.2+ (ruta por etapas abajo).

---

## 1. Backend (controllers / models / jobs / mail / commands)

### Fat controllers / SRP
- **`AdminController` = God Object** (~892 líneas, ~50 métodos) con **7 responsabilidades** mezcladas:
  CRUD usuarios+flags, deptos, puestos, notificaciones, flujo PCR (COVID), 6 búsquedas duplicadas,
  exports CSV, y mailing+PDF. Debe partirse en `UserController`, `DepartmentController`,
  `PositionController`, `NotificationRecipientController`, etc.
- **`FormulariosController`** (~667 líneas): 3 copias casi idénticas del algoritmo de encuesta
  (`newformulario1/2`, `checkform`); `registrarformulario` recibe **49 parámetros posicionales**.

### Validación
- **No existe `app/Http/Requests` (0 Form Requests).** ~16 mutaciones persisten `$request->all()/except()`
  sin validar (lista completa en la sección backend). Solo 4 controllers validan bien
  (`InjuryReport`, `DailyReport`, `cmedic`, `RegisterController`) → **patrón a replicar**.
- Anti-patrón en hazard/unsafe/location: validan con `$request->validate([...])` pero **persisten
  `$request->all()`** (descartan el resultado validado). En hazard, la regla de imagen ni aplica
  (valida `main_image_path` pero el archivo llega como `main_image`).

### Acceso a BD inconsistente
- Mezcla `DB::table('users')` (raw) y Eloquent `User` para el mismo modelo → se **pierden casts**
  (`borndate` sale string crudo por raw, casteado por Eloquent), eventos y se hace `SELECT *`.
- `testqueue` se lee con `DB::table('testqueue')->find(1)` en 6 sitios en vez de `queuevirtual`.
- **Field-overloading semántico** embebido en queries (deuda grave, requiere migración con cuidado):
  `age` = "tiene foto"; `daytest` = rol (0/1/2) **y** día de prueba (3); `labn` = contador de cola
  **y** nº de laboratorio; `enfermo` = flag clínico **y** derivado de PCR. Además `self::$counter`
  estático en `queueController@userqueue` se resetea por request → siempre arranca en 1 (bug latente).

### Transacciones / integridad
- **Ningún `DB::transaction()` en todo el backend.** Flujos multi-paso que dejan estado parcial si
  fallan: `positivepcr`, `negativepcr`, `negativeantg` (3 bucles sin atomicidad), `selectpuesto`
  (doble fuente de verdad `userpuesto` + string `users.puestodepartamento`), encuesta.

### Duplicación (faltan servicios/traits)
- **Subida de imágenes** copy-paste en 5 controllers (existe un helper `storeImage` que **no se usa**).
- **Loops `Mail::send()`** idénticos en ~12 sitios (debería ser Mailable + `queue()`).
- **6 métodos de búsqueda** byte-a-byte iguales salvo la vista de retorno.
- Servicios que faltan: `ImageUploadService`, `BulkMailService`, `UserSearchService`, `SurveyService`, `CsvExportService`.

### Manejo de errores
- `dd()`/`die` en código ruteado: `MailController:20`, `ReminderMailController:22`,
  `FormulariosController@newformulario1:112` (método muerto), `locationController@store:205`.
- `find()` sin guarda (NPE) dominante en `AdminController` (vs `findOrFail` correcto en los módulos nuevos).
- `encuestasController@checkfroms:27`: `findOrFail(id)` con constante indefinida → fatal si se rutea.

### Modelos
- **Anémicos:** todos son data bags (sin lógica, accessors ni scopes). Solo 2 tienen relaciones reales
  (`InjuryReport→user`, `DailyReport→logs`). Las junctions (`userpuesto`, `usuariopcr`, `userform`,
  `departamentousuario`) y `User` no declaran relaciones → todo se navega con `find()` manual o joins raw.
- Bugs de esquema en modelos: PK con espacio (`departamentousuario`), FK typo `id_usrio` (`usuariopcr`),
  PK en `$fillable` (`formulario`), `admin/activo/daytest` en `$fillable` (escalada de privilegios),
  `$dates` deprecado, **nombres de clase en minúscula** (`cmedic`, `user` import) → romperían en Linux
  (hoy funcionan por case-insensitivity de Windows). ⚠ Relevante para desplegar en AWS/Linux.

### Jobs / Mail / Commands
- **No hay clases Mailable.** Todo es `Mail::send('correos.x', ...)` inline con `from` hardcodeado
  e inconsistente (4 direcciones distintas, una es Gmail personal). Las colas no se usan en la práctica.
- Los 2 Jobs (`SendEmail`, `ReminderEmail`) recargan la lista de usuarios dentro de `handle()`
  (lógica en el job, no datos serializados) y `ReminderEmail` no compila (error de sintaxis, arriba).
- **Imports/Exports frágiles:** `QueueImport` tiene `return view()` inalcanzable, referencia clases sin
  `use`, mapea campos inexistentes (`temperatura`); `AntigenTestExport` exporta **renderizando una vista
  Blade** que asume `auth()` — debería usar `FromQuery`.

---

## 2. JavaScript / Assets

- **Pipeline muerto (ver crítico #1).** Mix nunca compiló; ninguna vista usa `mix()`; todo es `asset()`
  fijo o CDN. El bundle es decorativo.
- **Vue 2 = scaffold muerto:** `#app` es un `<div>` vacío; 0 vistas usan directivas Vue ni
  `<example-component>`. Axios configurado pero la app usa `fetch()`/`$.ajax`. Echo comentado.
- **4 jQuery + 3 Bootstrap conviviendo en runtime:** jQuery 3.3.1 (roto por `asset('https://...')`),
  3.4.1 (recargado en profile/inicio/perfil), 1.11.2 (vistas de impresión), + el del bundle;
  Bootstrap 5.1.3 (CDN app-wide) + 4.4.1 (CDN en profile/perfil) + 4.6 (package.json). Más Materialize
  (CRUD catálogos) y Tailwind-CDN (`dailyreports/show`).
- **Scripts custom:** `departamentosdinamicos.js` (URL `127.0.0.1` hardcoded, selector roto, función
  vacía → muerto), `a2hs.js` (**0 bytes**), `materialize.js` (legacy, retirar tras migrar CRUD a BS5).
- **6 patrones JS inline copy-paste** → módulos: print-to-iframe (`print.js`), html2canvas (`capture.js`),
  cropper (`image-crop.js`), adder de imágenes (`image-fields.js`), búsqueda (`crew-search.js`), TinyMCE
  (`editor.js`, por-vista).
- **Recomendación:** NO SPA (no hay reactividad). Blade + **un único bundle** (Mix o Vite) versionado con
  `mix()`/`@vite`, **un solo Bootstrap (5)**, jQuery una vez o eliminarlo, librerías pesadas condicionales
  por vista. Quitar Vue 2/Echo. Estabilidad > sofisticación.

---

## 3. Config + Dependencias / Versiones

### Bloqueantes de multi-servidor / AWS (HOY)
| Área | Estado | Fix para AWS |
|---|---|---|
| Sesión | `file` | `redis`/`database`/`dynamodb` (file no sobrevive load balancer) |
| Caché | `file` | `redis`/`dynamodb` |
| **Uploads** | disco `public` local + `public_path()` directo en varios controllers | **disco `s3` configurable** (existe `s3` en `filesystems.php` pero **ningún código lo usa**). Pieza #1. |
| Cola | `sync` (correo inline) | `database`/`sqs`/`redis` |
| Mail | SMTP Mailgun | SES (driver ya es env-driven; falta centralizar `from`) |
| Rutas frágiles | `storage_path('../../...')` en `filesystems.php:49,94` | rutas absolutas/config |

### Bloqueantes del modelo config-driven por cliente
- `from()` **hardcodeado** en código (no usa `config('mail.from')`) en ~8 ubicaciones, 4 direcciones distintas.
- Branding quemado: `laravelpwa.php:4 'CrewCare | DEMO'`, timezone/locale fijos en `app.php`.
- Bug: `.env.example` define `MAIL_FROM_ADRESS` (typo) → nunca alimenta `MAIL_FROM_ADDRESS`.

### Dependencias EOL / a cambiar
- **Laravel 8.83 (EOL ene-2023)**, **PHP 7.3–8.0 (EOL)**, `minimum-stability: dev` (riesgo).
- Quitar: `fruitcake/laravel-cors` (nativo en L9+), `facade/ignition` (abandonado → `spatie/laravel-ignition`),
  probablemente `mailersend/*` (si se va a SES). Subir: sanctum 2→3/4, laravel/ui 3→4, phpunit 9→11, dompdf.
- **package.json describe BS4+Vue2+jQuery** — no coincide con el runtime (BS5 CDN). Migrar a BS5 + (Vite o Mix corrido).

### Ruta de upgrade (por etapas, ~4-6 semanas + QA)
- **Etapa 0 (2-4 días):** PHP→8.1 local, correr suite, `minimum-stability: stable`, ignition→spatie, parches 8.x.
- **Etapa 1 — L8→9 (1-2 sem):** PHP 8.0→8.1, Symfony 5→6, **Flysystem 1→3** (afecta S3 — encadenar aquí el
  refactor de uploads a disco configurable), quitar fruitcake-cors, sanctum 2→3, Mix→Vite.
- **Etapa 2 — L9→10 (1 sem):** PHP 8.1+, tipados, subir collision/phpunit.
- **Etapa 3 — L10→11 (1-1.5 sem):** PHP 8.2+, estructura slim, sanctum 4.
- Riesgo concentrado en **Flysystem 3 (uploads/S3)** y el **triple-Bootstrap** del frontend.

---

## 4. PWA — por qué nunca funcionó

**Causa raíz #1:** `serviceworker.js:6-13` cachea `/js/app.js` + 8 iconos que **no existen en `public/`**
(iconos nunca publicados desde `vendor/`, `app.js` nunca compilado) → `install` falla atómicamente cada vez.
Otras causas: manifest sin iconos válidos ≥192px → no instalable; `offline.blade.php` hace
`@extends('layouts.app')` que exige `auth()` + 10 CDNs → inútil sin red; fallback `caches.match('offline')`
no coincide con la clave cacheada `'/offline'`; `APP_URL=127.0.0.1` sin esquema/HTTPS; `scope:'.'` en vez
de `/`; `a2hs.js` vacío; `staticCacheName` usa `Date.now()` → reinstala en cada carga.

**To-do mínimo para PWA real:** (1) `php artisan laravelpwa:publish` (iconos) + `npm run prod` (app.js);
(2) `offline.blade.php` standalone sin auth/CDNs; (3) SW tolerante a fallos + network-first para navegación
+ runtime caching de CDNs (o autohospedarlos); (4) `APP_URL` con `https://`, `scope:'/'`, HTTPS;
(5) `a2hs.js` real o quitarlo. **Recomendación:** mantener `silviolleite/laravelpwa` para el manifest,
reemplazar el service worker por uno generado con **Workbox** (precache automático de assets versionados +
runtime caching) en vez de listas hardcodeadas que se desincronizan.

---

## 5. i18n, higiene del repo y cierre de huecos

### Internacionalización (no hay i18n real)
- Solo **~14% de las vistas (13/95)** usan `__()/@lang`; las otras ~82 tienen strings hardcodeados
  (español/spanglish). Traducir de verdad exige extraerlos.
- El locale **`en` está contaminado con español** (`en/messages.php`: `hello => "Hola"`, etc.); clave
  `answersecond` **duplicada**; `en` y `es` **desincronizados** (claves distintas); `es.json` usa otra
  convención (claves-frase) incompatible con el resto (`messages.bback`).
- **`LocaleMiddleware.php` es código muerto** (no registrado); el vivo es `localization.php`.
  `config/app.php:83 locale='en'` hardcodeado.

### Higiene del repo (crítico para el modelo per-cliente y AWS)
- **`.gitignore` vacío** → `vendor/` (~8.992 archivos) + `node_modules/` versionados; **`.env` con
  secretos reales committeado** (DB, mail, **AWS_SECRET**, Pusher). Causa raíz del C1 de seguridad.
- **Fotos de perfil de usuarios reales committeadas** en `public/imagesprf/**` (privacidad + bloat),
  en 3-4 carpetas distintas para lo mismo (`imagesprf/`, `imagesprf/users/`, `imagesprf/usrs/`, `images/usrs/`).
- **`public/images/icons/` está trackeado en git pero AUSENTE en disco** → iconos/splash del manifest PWA
  rotos (otra causa de que la PWA no instale).
- Basura para borrar: `app.rar`/`resources.rar`/`routes.rar`, `*.bladeOLD/*BKP`, `injury_report_preview`,
  archivos 0 bytes (`a2hs.js`, `imports/uploadbulk`, `resources/css/app.css`, `favicon.ico`, `web.config`/`.htaccess` raíz),
  dir vacío `crewcarerr/`, `public/img/*old*`/`*copia*`/`id_card_template.ai`, artefactos en `storage/framework`.
- **`config/filesystems.php`:** los discos `images`/`imagesprf`/`imagesperf` apuntan los 3 al mismo root
  local (uno con ruta rota `../../`); **ninguno usa el disco `s3`** que sí está definido. URLs absolutas
  a otro entorno hardcodeadas en `dailyreports/show` (`https://eneg.crewcare.mx/...`).

### Cierre de huecos (boilerplate — verificado, sin sorpresas)
Revisados para certificar "nada omitido": **todos son estándar de Laravel sin tocar** →
`AppServiceProvider` (vacío), `RouteServiceProvider` (rate-limit api 60/min, HOME=/home),
`Handler.php` (estándar), `bootstrap/app.php`, `DatabaseSeeder` (vacío), `UserFactory` (scaffold),
y los config no revisados antes (`session/cache/queue/database/broadcasting/logging/services/app/auth/view/hashing`).
Único matiz: `EventServiceProvider` registra el listener de verificación de email, pero `User` **no**
implementa `MustVerifyEmail` → inerte. **Cobertura de tests reales: 0%** (solo `ExampleTest` scaffold).

> Con esto la auditoría está **completa**: no queda código/config de la app sin revisar.

## Top quick-wins (horas, bajo riesgo, alto impacto)
1. `npm install && npm run prod` + desplegar `public/js`+`public/css` (arregla 404 app-wide y desbloquea PWA).
2. Corregir parse error `ReminderEmail.php:45`.
3. Scheduler `everyMinute()` → `daily()` (`Console/Kernel.php:31`).
4. Quitar `dd()` ruteados y métodos muertos (`newformulario1`, `checkfroms`).
5. Centralizar `from` de correo en `config/mail.php` (corrige Gmail personal + typo `notofications`).
6. `find()` → `findOrFail()` en los toggles de `AdminController`.

## Deep-refactors (con plan; muchos se resuelven al retirar COVID)
Split de `AdminController`/`FormulariosController`; capa Form Requests + Services/Actions; Eloquent en todo
+ relaciones en junctions; Mailables + colas; renombrar campos sobrecargados (`age`/`daytest`/`labn`);
eliminar doble fuente de verdad `userpuesto`↔`puestodepartamento`; upgrade de framework; uploads→S3.
> Nota: gran parte de lo peor (3 bucles sin transacción en `negativeantg`, scheduler cada minuto,
> `usuariopcr` typo, los Jobs rotos) **desaparece al ejecutar el desmantelamiento COVID**.
