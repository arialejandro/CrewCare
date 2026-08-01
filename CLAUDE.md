# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es esta app
CrewCare (crewcarer.mx) es una plataforma de gestión de crew y **seguridad de
producción** audiovisual. Clientes incluyen Amazon MGM Studios y Pimienta Films.
Nació como app de tamizaje de salud COVID de set y **creció** a una plataforma de
seguridad más amplia. Módulos actuales:
- **Salud / COVID:** encuestas diarias, temperatura, pruebas PCR/antígeno, cola
  virtual de pruebas, consultas médicas (`cmedic`).
- **Seguridad de set:** reportes de lesiones (`InjuryReport`), notificaciones de
  peligro (`hazardnotification`) y condiciones inseguras (`unsafecond`), auditorías
  de locación (`locationreport` V1/V2) y reportes diarios de seguridad / DSR
  (`DailyReport`/`DailyLog` con catálogo `SafetyStandard`).
- **Admin:** CRUD de usuarios, departamentos, puestos, gafetes, notificaciones.

> **Mapa completo en [ARCHITECTURE.md](ARCHITECTURE.md)** — auth/permisos, modelos,
> controllers/rutas, vistas y hallazgos de seguridad/deuda técnica. Leerlo antes de
> auditar o refactorizar. Documentos hermanos: [ROADMAP.md](ROADMAP.md) (visión y plan
> por fases + decisiones + arquitectura objetivo §4.6), [RECIPE.md](RECIPE.md) (checklist de
> ejecución ordenado), [SECURITY.md](SECURITY.md), [PERFORMANCE.md](PERFORMANCE.md),
> [VIEWS-INVENTORY.md](VIEWS-INVENTORY.md) (95 vistas), [CODE-AUDIT.md](CODE-AUDIT.md)
> (backend/JS/config/PWA/i18n/higiene), [ASSETS-JS-AUDIT.md](ASSETS-JS-AUDIT.md) y
> [ASSETS-CSS-AUDIT.md](ASSETS-CSS-AUDIT.md) (estructura JS/CSS + 404 de app.js),
> [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md) (esquema real
> de la BD + objetivo), [ROUTES.md](ROUTES.md) (matriz de rutas + bugs),
> [AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md) (plan de fundación: roles + producciones),
> [ORG-TAXONOMY.md](ORG-TAXONOMY.md) (departamentos/puestos reales del call sheet),
> [COVID-DECOMMISSION.md](COVID-DECOMMISSION.md) y [PROGRESS.md](PROGRESS.md) (bitácora).
> **Auditoría completa** (2026-06-24): no queda código/config de la app sin revisar.
>
> **Hechos críticos (estado al 2026-06-24 — varios YA CORREGIDOS en Bloque 0.0):**
> ✅ El 404 de `public/js/app.js` se resolvió a mano (archivo mínimo; webpack no se usa — `npm` está
> bloqueado por red/SSL en este entorno). ✅ El bug `asset('https://...')` que rompía jQuery app-wide se
> corrigió en los 3 layouts. ✅ El **error de sintaxis fatal** de `ReminderEmail.php:45` se arregló.
> ✅ El scheduler pasó de `everyMinute()` a `dailyAt('04:00')`. ⚠️ Pendiente: la **PWA aún no instala**
> (el service worker cachea archivos inexistentes — Bloque 3.5). ⚠️ **Laravel 8 + PHP 7.3 siguen EOL**
> (upgrade en Bloque 4). Detalle de JS/CSS en [ASSETS-JS-AUDIT.md](ASSETS-JS-AUDIT.md)/[ASSETS-CSS-AUDIT.md](ASSETS-CSS-AUDIT.md).

> **Visión de producto (estrategia — 2026-06-24):** CrewCare se dirige a ser una **plataforma de
> administración / coordinación de producción** para cine/TV en LATAM (estilo Scenechronize, más
> accesible) — contratos, firmas, formatos, calendarios/recordatorios de pago, guiones/call sheets/sides.
> El **H&S es el núcleo pero va EMBEBIDO como capa** (no se lidera con él: en LATAM no se percibe como
> indispensable; la cuña de venta es la necesidad administrativa). **Método FIRME: reconstrucción
> incremental tipo estrangulamiento sobre la fundación nueva (RBAC/producciones/departamentos),
> explicando el código por verticales — NUNCA reescritura desde cero.** Detalle en
> [VISION-PRODUCT.md](VISION-PRODUCT.md) (estrategia de PRODUCTO, distinta del roadmap técnico).

> **Naturaleza del repo (clave):** este repo es la **BASE/plantilla, OFFLINE, con datos de
> PRUEBA** — NO es la app en producción. Se puede reestructurar a fondo (rehacer tablas,
> renombrar campos) sin dañar nada en producción. Modelo de negocio: cada cliente
> (productora/estudio) recibe una **copia de esta base subida a un VPS y personalizada**.
> La personalización debe tender a **configuración**, no a edición de código por copia.
> **Amazon exige despliegue en AWS** → la base debe ser AWS-desplegable cambiando config.

Existe un proyecto hermano, **BlackHouse**, agencia de locaciones cinematográficas
premium para CDMX. Está planeado un puente de integración entre CrewCare y
BlackHouse como moat competitivo — ambos ecosistemas eventualmente comparten datos.

## Stack técnico (verificado contra el código)
- **Backend:** Laravel 8.54, PHP `^7.3|^8.0`.
- **Frontend:** Blade + Bootstrap 4 + jQuery. Vue 2 está instalado vía Laravel Mix
  pero solo hay scaffolding (`resources/js/components/ExampleComponent.vue`); la UI
  real es Blade. **TinyMCE removido** (2026-06-24): era editor enriquecido de un futuro
  constructor de correos masivos aún no construido y su API key de Tiny Cloud lanzaba
  errores en consola. Se reintroduce (self-hosted) al construir el módulo de correos masivos.
- **Build de assets:** Laravel **Mix** (webpack), NO Vite. Ver `webpack.mix.js`
  (compila `resources/js/app.js` → `public/js`, `resources/sass/app.scss` → `public/css`).
- **DB:** MySQL (`DB_DATABASE=crewcare`). Queue driver `database`/`sync`, cache `file`.
- **Auth:** `laravel/ui` (scaffold Bootstrap) + Sanctum (la API casi no se usa: solo
  el endpoint `/user` por defecto en `routes/api.php`).
- **PWA:** `silviolleite/laravelpwa` (`public/serviceworker.js`, ruta `/offline`).
- **Paquetes clave:** `barryvdh/laravel-dompdf` (PDFs), `maatwebsite/excel`
  (import/export Excel — ver `exportController`/`importController`), `picqer/php-barcode-generator`
  (`public/barcode.png`), `mailersend/laravel-driver` (correo).
- **Hosting:** VPS con Plesk. Entorno local: Laragon (`C:\laragon\www\crewcarerr`).

> NOTA: una versión previa de este archivo describía un sub-app React/Vite montado
> en `/ordenar/` y un flujo de contratación con aprobación HOD → Coordinator →
> Line Producer, firma de documentos y export a contabilidad. **Nada de eso existe
> en este repo** (verificado: no hay `public/ordenar`, ni rutas/refs a "ordenar", ni
> React/Vite). Si esos módulos son reales, viven en otro repo/rama. No asumir que
> existen aquí.

## Arquitectura y dominio
La app es CRUD-pesada y orientada a controladores tradicionales de Laravel (sin
capa de servicios ni repositorios). Rutas casi todas en `routes/web.php` (~210
líneas, muchas con verbos GET para acciones que mutan estado — patrón heredado;
mantener el estilo existente al editar, no "arreglar" a REST sin pedir).

Modelos principales (`app/Models/`) y de qué tratan:
- **User** — crew. Flags relevantes: `admin`, `activo`, `encuestadiaria`,
  `enfermo`, `ultimatemperatura`, `lastpcr`, `lastwr`, `puestodepartamento`.
- **DailyLog / DailyReport** — encuestas y reportes diarios de salud.
- **InjuryReport** — reportes de lesión.
- **cmedic / usuariopcr / pcr / prueba** — historial clínico, consultas médicas y
  pruebas PCR. **Trato sensible: incluye datos médicos con peso legal.** Cualquier
  cambio aquí (esquema, validación, borrado) requiere cuidado extra y plan previo.
- **hazardnotification / unsafecond** — notificaciones de peligro / condiciones inseguras.
- **departamento / departamentousuario / puesto / userpuesto** — depto y puestos del crew.
- **queuevirtual** — fila virtual (control de acceso/aforo).
- **formulario / userform / encuestas** — formularios y encuestas configurables.
- **message / usuariosnotificacione / SafetyStandard / locationreport** — mensajería,
  notificaciones, estándares de seguridad y reportes de locación.

### Autorización
> **✅ RBAC spatie AHORA EN VIVO (2026-06-24 — fundación construida y verificada).** Se sembraron
> **8 roles** (`super-admin`, `line-producer`, `coordinator`, `hod`, `medic`, `safety-officer`,
> `crew` + `auditor` solo-lectura) y **39 permisos** granulares vía `spatie/laravel-permission`
> (teams mode = false). Existen las tablas nuevas **`departments`, `positions`, `productions`,
> `production_user`** (+ modelos `Department`/`Position`/`Production`). `User` tiene el trait
> **`HasRoles`**; `AuthServiceProvider` tiene un **`Gate::before`** que deja pasar al `super-admin`
> en toda verificación de permiso. El **rol por producción vive en `production_user.role`** (varchar,
> NO spatie teams) → un usuario puede ser HOD en una producción y crew en otra. **`positions.production_id`
> nullable** = catálogo plantilla reutilizable (NULL) + puestos propios por producción.
> **REGLA OBLIGATORIA: el código nuevo verifica PERMISOS, no roles** (`@can('reports.create')` /
> `$user->can(...)`, **nunca** `@role('...')`) — los roles son datos reasignables sin tocar código.
> Detalle y verificación en [PROGRESS.md](PROGRESS.md) y [AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md).

**Flags VIEJOS (aún presentes — strangler, NO retirados todavía):** roles vía middleware con alias
en `app/Http/Kernel.php`:
- `auth` (Authenticate), `guest`, `admin` (`AdminMiddleware`: exige
  `auth()->user()->admin`, si no redirige a `/`).
- `locale`/`localization` (`LocaleMiddleware` + `localization`) para i18n
  (`resources/lang`, ruta `/locale/{locale}`).
- `niveldos.php` existe pero es un **stub pass-through** (no aplica reglas). No
  confiar en él como control de acceso.

El control de acceso **viejo** sigue siendo el booleano `User.admin` + flags del usuario, y aún corre
en paralelo al RBAC nuevo (no se ha retirado). `AdminMiddleware` y el menú todavía no migran a permisos
(pendiente: reescribirlos por `@can` en paralelo a los flags). Verificar siempre el middleware de cada
ruta antes de asumir quién puede ejecutarla.

## Comandos
```bash
# Dependencias
composer install
npm install

# Build de assets (Laravel Mix)
npm run dev        # desarrollo (one-off)
npm run watch      # recompila al guardar
npm run prod       # build de producción (minificado)

# App Laravel
php artisan serve              # o usar el host de Laragon (crewcarerr.test)
php artisan migrate            # migraciones
php artisan db:seed            # seeders
php artisan tinker             # REPL
php artisan key:generate
php artisan optimize:clear     # limpiar cache de config/rutas/vistas

# Tests (PHPUnit 9)
php artisan test                          # toda la suite
php artisan test --testsuite=Feature      # solo Feature
php artisan test --filter ExampleTest     # un test específico
vendor/bin/phpunit                        # alternativa directa
```
> Estado de tests: solo existen los scaffolds por defecto
> (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`). No hay cobertura
> real todavía. Al agregar features con riesgo (clínico/PCR), escribir Feature
> tests es especialmente valioso.

## Estilo de código
StyleCI con preset **Laravel** (≈PSR-12), regla `no_unused_imports` deshabilitada
(ver `.styleci.yml`). `.editorconfig` define la indentación. No reformatear archivos
masivamente; ajustarse al estilo del archivo que se edita.

## Reglas para Claude al trabajar en este repo
- **ESTRATEGIA (2026-06-24): reconstruir por verticales, NO parchar lo estructural en sitio.**
  Parchar en sitio solo (a) seguridad peligrosa en vivo (C2/C3) y (b) borrar código muerto/COVID.
  Vistas, frameworks CSS/JS y esquema NO se parchan: se **reconstruyen** sobre la fundación nueva
  (migraciones + roles spatie + producciones) y lo viejo se retira **por vertical** al verificar contra
  **producción como oráculo**. No perseguir cosméticos (Quirks mode, colisión BS4/BS5, duplicación) en
  código que se va a reemplazar — un "arreglo" cosmético ya rompió el Home una vez (el bug era carga
  estructural). Ver ROADMAP §3 decisión #5 y [AUTH-RBAC-PLAN.md](AUTH-RBAC-PLAN.md).
  La reconstrucción **por artefacto** (view/model/controller) sigue [REBUILD-PROTOCOL.md](REBUILD-PROTOCOL.md)
  (descifrar→objetivo→reconstruir→verificar→retirar; el wrap `@if(viejo || @can)` es transición, NO el estado final).
- **Antes de auditar o proponer mejoras grandes:** usar subagentes para explorar por
  dominio (auth/middleware, modelos, rutas/controllers, vistas Blade) y escribir
  hallazgos a un archivo (ej. `ARCHITECTURE.md`) en vez de leer todo dentro de la
  sesión principal.
- **Refactors en lotes de 5-20 archivos** lógicamente relacionados. Nunca aceptar
  "mejora toda la app" sin acotar el alcance primero.
- **Cambios de arquitectura o de esquema de BD:** usar plan mode y mostrar el plan
  antes de tocar archivos.
- **Módulo clínico/PCR (`cmedic`, `pcr`, `usuariopcr`, `prueba`):** son declaraciones
  con peso legal. No es "un form más" — extra cuidado en validación y borrados.
- **Build de assets (estado real 2026-06-24):** `npm install` está **bloqueado por red/SSL** en este
  entorno → el bundle webpack NO se compila. `public/js/app.js` se proveyó **a mano** (nada del front
  depende del bundle). Si tocas `resources/js`/`resources/sass`, ese pipeline está inactivo; reactivarlo
  (o migrar a Vite) es Bloque 4. El deploy en Plesk sube `public/`.
- Hay archivos `*.rar` en la raíz (`app.rar`, `resources.rar`, `routes.rar`) —
  son backups, ignorarlos.

## Política de /compact
Al resumir esta conversación, preservar siempre:
- Cambios de esquema de base de datos y su razón.
- Cambios de contratos entre Laravel y cualquier cliente JS / integración BlackHouse.
- Decisiones de arquitectura ya tomadas y su justificación.
- Lista de archivos modificados en la sesión.
Resumir brevemente (no detallar) los intentos de exploración que no llevaron a nada.
