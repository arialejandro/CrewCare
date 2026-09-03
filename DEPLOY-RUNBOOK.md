# CrewCare — Runbook de despliegue (primer deploy a producción)

> **Cuándo usar esto:** para levantar la app en un servidor **nuevo**, sobre una **base de datos vacía**.
> NO es para tu entorno local (en local aplicas los `owner-apply/*.sql` a mano; ver §9).
>
> **Idea central:** el esquema y los catálogos NO se aplican a mano SQL-por-SQL. Todo está horneado en
> **migraciones** + **seeders encadenados**. Un `migrate --force` + `db:seed --force` sobre una BD
> vacía deja el install de fábrica **completo**. Lo único manual es el **entorno** (`.env`, `storage:link`,
> **cron**, caché) y el **chequeo anti-demo**.
>
> **📌 Este archivo es la ÚNICA fuente de los pasos de deploy.** Cuando una racha agregue un requisito
> nuevo (variable de entorno, binario del host, cron, seeder, columna), se anota **aquí** — en el paso que
> corresponda y en el changelog de §10 — no en notas sueltas. Así no se duplica ni se desactualiza.

---

## 0 · Requisitos del host (una vez)
- **PHP 8.3** con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `curl`, `bcmath`.
- **MySQL 5.7+ / 8.x** (crea la base vacía en el paso 3).
- **Composer** y, si compilas assets, **Node 18+**.
- **Cron** disponible para el scheduler de Laravel (`schedule:run` cada minuto — se registra en §5·b).
  Sin él, el **envío masivo en segundo plano** (llamado / Distribución) no drena su cola.
- **Chrome/Chromium headless + Node** para los PDF por Browsershot (contrato firmado, certificados, wrap).
  Apunta las rutas en `.env` (`BROWSERSHOT_CHROME`, `BROWSERSHOT_NODE`). Sin esto, los PDF por Chrome fallan
  (la app degrada, pero no genera esos documentos).
- **HEIC (fotos de iPhone) — REQUISITO recomendado (respaldo del servidor):** ImageMagick con delegado
  `libheif` + `php-imagick`. El cliente convierte HEIC→JPEG en iPhone/iPad (WebKit) ANTES de subir, pero
  Chrome/Firefox de escritorio NO decodifican HEIC → caen a este respaldo del servidor. Con la verificación
  de vehículos, donde **la foto por punto es obligatoria**, sin este respaldo un HEIC de escritorio se
  **rechaza con mensaje claro** (nunca se guarda invisible). Verifica con
  `php -r "var_dump(extension_loaded('imagick'));"` y `(new Imagick())->queryFormats('HEIC')`.

## 1 · Código
```bash
# subir/clonar el repo al servidor, luego:
composer install --no-dev --optimize-autoloader
# si el front compila assets:
npm ci && npm run build
```
Los `public/js/vendor/*.min.js` y `public/fonts/*` (html2canvas, JSZip, pdf.js, fuentes autoalojadas) **viajan
con el repo** — no se bajan de CDN. Confirma que la carpeta `public/` subió completa.

## 2 · Variables de entorno (`.env`)
```bash
cp .env.example .env
php artisan key:generate            # genera APP_KEY
```
Luego edita `.env` y llena **como mínimo**:

| Llave | Valor | Nota |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_URL` | `https://tu-dominio` | **CON esquema** (`https://…`), nunca pelón. Manda los deep-links de correos/QR. |
| `APP_KEY` | (lo generó `key:generate`) | |
| `CREWCARE_SEAL_KEY` | clave aleatoria (ver abajo) | **Firma el sello HMAC de todos los documentos.** Fíjala **una vez** y NO la cambies (cambiarla invalida todos los sellos). |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` / `DB_HOST` | tu BD | La base debe existir vacía (paso 3). |
| `MAIL_*` | tu SMTP real | Sin correo real, los avisos "te toca"/cierre no salen. |
| `QUEUE_CONNECTION` | `sync` | Con `sync`, correos/render corren dentro de la petición (los enlaces salen bien). Si algún día pones cola real, necesitas un **worker** y que `APP_URL` sea correcto. |
| `INSTALL_ADMIN_EMAIL` | correo del 1er super-admin | Lo crea `InstallAdminSeeder`. |
| `INSTALL_ADMIN_NAME` | nombre del admin | |
| `INSTALL_ADMIN_PASSWORD` | contraseña fuerte | **Obligatoria** para que se cree el admin. |
| `INSTALL_PRODUCTION_NAME` | nombre real de la producción | Lo usa `ProductionDemoSeeder` (crea la fila de producción de la instancia; el "Demo" del nombre es solo el default). |
| `INSTALL_PRODUCTION_CODE` | código corto (p. ej. `PROD1`) | |
| `TELEMETRY_EMAIL` | correo del dev (opcional) | Telemetría de cédula; sin ella no truena, solo no envía. |
| `CEDULA_AUTO_VERIFY` | `false` | Dejar **false** hasta validar el robot SEP contra el PoC. |

Generar la `CREWCARE_SEAL_KEY` (elige una):
```bash
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
# o
openssl rand -hex 32
```

## 3 · Base de datos (automático — aquí está TODO el esquema)
```bash
# 1) crea la base VACÍA (una vez):
mysql -u root -p -e "CREATE DATABASE crewcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2) esquema + catálogos + permisos + producción + super-admin:
php artisan migrate --force
php artisan db:seed --force
```
`db:seed` encadena (ya, sin correr nada suelto): **roles y permisos** (incluye `contracts.author`, `payees.view`,
`periods.view`, `tools.inspect`, `permits.issue`, `epi.view`, `medevac.issue`, `riskmap.issue`, `pae.issue`,
`ambulance.*`, `transport.*`…), **catálogos** (departamentos/puestos —incluye *Contabilidad*—, normas, eventos, PPE, SPFX,
herramientas/permisos, medicamentos, ambulancias, **vehículos** (13 tipos/50 puntos), tipos de documento), la **fila de producción** y el **primer
super-admin**. **No** encadena los seeders de demo (ver §6).

> **📐 Regla ESQUEMA vs DATOS (obligatoria):**
> - **Esquema** (ALTER / CREATE TABLE) → **migración + gemelo owner-apply**. Un ALTER hay que poder aplicarlo a
>   una base **ya viva** (por eso el gemelo, ver §9).
> - **Datos de catálogo** (departamentos, puestos, normas, herramientas…) → **seeder idempotente** con el **CSV
>   como fuente de verdad**. Nunca INSERT a mano: 255 líneas escritas a pulso se **desincronizan del CSV** a la
>   primera corrección. Precedente: `ToolPermitCatalogSeeder`, `ResolveParkedStandardsSeeder`, `CatalogFusionSeeder`.
>   Los CSV viven **versionados** en `database/seeders/data/`; si el catálogo se corrige, se corrige el **CSV** y
>   se **re-siembra** (`db:seed --class=… --force`).

> ⚠️ **No corras `migrate` en tu LOCAL** (`crewcare`): ahí aplicaste los deltas a mano, la tabla `migrations`
> está atrasada y `migrate` intentaría re-crear columnas existentes. Esto de arriba es **solo para el servidor
> nuevo con BD vacía**.

## 4 · Archivos y enlaces
```bash
php artisan storage:link                 # sirve fotos/PDF de storage/app/public
# permisos de escritura para el usuario del web server:
chmod -R ug+rw storage bootstrap/cache   # (ajusta al SO/hosting)
```

## 5 · Caché de producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
# (si algo se ve raro tras un cambio: los *:clear equivalentes)
php artisan permission:cache-reset       # asienta el store de permisos Spatie
```

## 5·b · Programador de tareas (cron) — OBLIGATORIO para envíos en segundo plano
CrewCare corre trabajos periódicos con el **scheduler de Laravel**. En el host nuevo hay que registrar
**una** línea de cron que dispare `schedule:run` cada minuto:
```bash
# crontab -e  (del usuario del web server)
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```
Qué depende de esto **hoy**:
- **Envío del llamado + Distribución con marca de agua** (comando `deliveries:dispatch`): el envío masivo
  se **encola** y este cron lo drena en tandas (marca cada copia con el nombre de quien la recibe + correo).
  Sin el cron, al presionar "Enviar" solo sale la **ráfaga inline** (~20 personas) y el resto queda en cola
  **sin salir**. Con el cron, el resto sale solo, ~cada minuto. Funciona con `QUEUE_CONNECTION=sync` (no
  necesita worker de cola).

Es el cron estándar de cualquier Laravel; si mañana se agenda otra tarea, ya queda cubierto. Si por lo que
sea el cron no está, la app **no se rompe** — solo los envíos masivos no se completan solos.

## 5·c · Sesión en base de datos (para revocar sesiones desde el perfil)
Para que "Perfil → Sesiones activas" liste y **cierre** sesiones (caso del teléfono perdido), la sesión
debe vivir en la base, no en archivos. En el `.env` del host:
```bash
SESSION_DRIVER=database
```
La tabla `sessions` la crea `migrate` (o el gemelo `owner-apply/2026-08-30-sessions-table.sql`). Al activarlo,
**todas las sesiones vigentes se invalidan UNA vez** (los usuarios re-inician sesión) — hazlo en el primer
deploy, no después, para que ese re-login único no le cueste a nadie. Si se queda en `file`, la app funciona
igual pero la pantalla de sesiones avisa que está inactiva. Tras cambiarlo: `php artisan config:clear` (o
`config:cache` de nuevo).

## 6 · ⛔ Chequeo anti-demo (OBLIGATORIO antes de abrir)
Confirma que la instancia NO trae datos de demostración ni cuentas de prueba:
```bash
mysql -u root -p crewcare < database/owner-apply/2026-07-24-detectar-produccion-demo.sql
```
La fila **`>>> TOTAL <<<` debe dar 0.** Si no, corre `2026-07-24-borrar-produccion-demo.sql`.
(Los seeders `DemoProductionSeeder`/`TestAccountsSeeder` tienen candado `SoloEnLocal` y **no** están en el
`db:seed`; solo llegarían si alguien los invocó a mano.)

## 7 · Verificación de humo
- Entra con `INSTALL_ADMIN_EMAIL` / la contraseña que pusiste.
- Sube una foto de perfil → confirma que `storage:link` sirve la imagen.
- Abre cualquier documento sellado y su verificador público `/verificar/{tipo}/{uuid}` → debe decir **íntegro**.
- Manda un contrato de prueba de punta a punta (fire → firmar) → el PDF firmado y el certificado se generan
  (esto valida Chrome/Browsershot en el host).

---

## 8 · Higiene del repo antes del PRIMER push a GitHub (si aplica)
Verifica que `.env` **no** esté versionado y que los secretos estén rotados:
```bash
git ls-files | grep -i "^\.env$"     # NO debe listar .env (solo .env.example)
```
Si alguna vez estuvo rastreado, rota `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD` en el entorno del que salieron.

## 9 · Local vs Producción (para no confundirte)
- **Local (`crewcare`):** aplicas los `database/owner-apply/*.sql` **a mano** cuando te paso uno nuevo:
  ```bash
  "C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root crewcare -e "source C:/laragon/www/crewcarerr/database/owner-apply/ARCHIVO.sql"
  ```
  Nunca `migrate` en local.
- **Producción (BD nueva):** `migrate --force` + `db:seed --force`. Nunca a mano SQL-por-SQL.
- **Producción ya poblada que parchas en sitio** (caso raro): aplicas solo los owner-apply que esa BD aún no
  tiene, en el orden de dependencia de `database/README.md`, + `permission:cache-reset`, + los **seeders de
  catálogo idempotentes** que la racha indique (p. ej. `php artisan db:seed --class=CatalogFusionSeeder --force`).

---

## 10 · Cambios por racha (más reciente arriba)

### Racha 2026-08-30 · Endurecimiento (seguridad + estabilidad) · deltas #117–#121

**Deltas de BD (owner-apply nuevos, `CREATE TABLE IF NOT EXISTS`, aditivos; deploy fresco entran con `migrate`):**
- `#117` `2026-08-29-idempotency-keys.sql` — envío diferido offline idempotente.
- `#118` `2026-08-30-catalog-cleanup.sql` — limpieza del catálogo (name-based; correr el archivo COMPLETO).
- `#119` `2026-08-30-signature-timestamps.sql` — sello de tiempo TSA.
- `#120` `2026-08-30-clinical-read-logs.sql` — bitácora de lectura clínica.
- `#121` `2026-08-30-sessions-table.sql` — sesión en base (habilita listar/revocar sesiones).

**Crons nuevos (ya en el schedule; solo necesitan el `schedule:run` de §5·b):**
- `tsa:stamp` (cada 5 min) — timbra en freeTSA los sellos sin sello de tiempo. Best-effort; si freeTSA no responde, reintenta. Cubre sellos viejos y nuevos.
- `clinical-log:prune` (mensual) — retención de 3 años de la bitácora clínica.
- latido del cron (cada minuto) — deja la marca que vigila `/healthz`.

**Variables `.env` nuevas (todas con default; opcionales):**
- `SESSION_SECURE_COOKIE` — si no se pone, la cookie es `Secure` en producción y no-secure en local.
- `CREWCARE_HSTS`, `CREWCARE_CSP_REPORT` (default `true`).
- `CREWCARE_TSA_ENABLED` (`true`), `CREWCARE_TSA_URL` (`https://freetsa.org/tsr`), `CREWCARE_TSA_TIMEOUT` (`8`).
- `SESSION_DRIVER=database` — SOLO si quieres activar "Sesiones activas" del perfil (ver abajo).

**HTTPS/HSTS (solo producción):** la app fuerza https (`URL::forceScheme`) + redirige http→https + HSTS (middleware `SecurityHeaders`). Requiere que el reverse-proxy TLS mande `X-Forwarded-Proto` — `TrustProxies` confía `*` (la app en el VPS solo es alcanzable por el proxy; si no fuera así, acota a la IP del proxy). **El local sigue en http, sin cambios.** La CSP va en modo REPORTE (observación) — revisa las violaciones en el log (`/csp-report`) antes de pasarla a bloqueo.

**Healthcheck:** `GET /healthz` (público, mínimo) → 200 sano / 503 degradado (app/base/cola/cron). **Apúntale el monitor externo** para que alerte. Detecta `schedule:run` muerto (el latido envejece → `stale`).

**Sesiones revocables (perfil → "Sesiones activas"):** requiere **`SESSION_DRIVER=database`** + la tabla `sessions` (#121). Al activarlo, TODOS re-inician sesión UNA vez (las sesiones `file` vigentes dejan de valer). Sin el flip, la página lo explica y no rompe nada. La rotación del id de sesión al iniciar sesión YA ocurre (laravel/ui).

**🔴 CLAVE DEL SELLO — NO ROTAR CON SELLOS VIVOS.** `CREWCARE_SEAL_KEY` (HMAC de los sellos) NO se puede rotar en una instancia con documentos ya sellados: al cambiarla, TODOS los sellos vivos dejan de casar (el verificador los marca **ALTERADO**). Solo se fija en un deploy fresco (prod nace sin sellos) o re-sellando todo. Mitigación: el **sello de tiempo TSA (#119)** es lo único que sobrevive si la llave se filtra — nadie puede forjar un timbre fechado en el pasado.

**Cifrado a nivel almacenamiento (RECOMENDACIÓN, no aplicado — es infra del VPS).** Cubre discos dados de baja / snapshots / respaldos robados; **NO** un servidor comprometido EN CALIENTE (con la BD montada, los datos están en claro para la app y para root). Para MySQL 5.7.33:
- **A) Disco cifrado (LUKS/dm-crypt del volumen, o el cifrado del proveedor de VPS). ✅ RECOMENDADO.** Transparente para MySQL y para la app, cifra TODO (datos + binlogs + tmp + respaldos que vivan en ese disco), cero configuración del motor. Costo operativo: mínimo — se define al provisionar el volumen; en el arranque hay que aportar la passphrase (o usar el key-management del proveedor). Es lo que pide el caso ("transparente para la app").
- **B) InnoDB tablespace encryption (`keyring_file` + `innodb-encrypt-tables`).** Cifra por-tablespace DENTRO de MySQL. Más partes móviles: el archivo de llaves NO debe vivir en el mismo disco que los datos; en 5.7 no cubre binlog/undo/redo sin flags extra; complica los respaldos físicos. Más superficie de error para el mismo objetivo.
- **Recomendación: A.** Más simple, cobertura total, sin tocar MySQL. B solo si un requisito externo exige cifrado dentro del motor.

**Respaldos y llaves (procedimiento).**
- Respaldo lógico cifrado (diario, cron propio del VPS):
  ```
  mysqldump --single-transaction --routines --triggers crewcare | gzip | gpg -c > crewcare-$(date +%F).sql.gz.gpg
  ```
- 🔴 **Respaldo y llave en LUGARES y CUENTAS DISTINTAS.** El respaldo cifrado va a un almacenamiento (cuenta A); la passphrase/llave del respaldo va a OTRA cuenta (cuenta B, gestor de contraseñas). Si acaban juntos, el cifrado no separa nada.
- 🔑 **Las llaves (sello + app) viven en el `.env` del VPS mientras corre.** Al dar de baja el servicio, archívalas junto a un respaldo lógico final en la nube, con la MISMA separación respaldo≠llave.
- 🔑 **Restauración PROBADA.** Un respaldo sin restaurar no es respaldo: `gpg -d ... | gunzip | mysql crewcare_restore` en una base limpia y corre el humo (§7). El dump es **lógico** (no físico) a propósito: debe poder leerse en un MySQL NUEVO dentro de 3 años, sin atarse a la versión/ruta de datos del motor.

### Racha 2026-08-27 · Catálogo organizacional FUSIONADO (semilla declarada + vivo) · delta #114
- **Esquema (migración `2026_08_27_000001` + gemelo `owner-apply/2026-08-27-catalog-fusion-schema.sql`):**
  `positions` +6 columnas (`catalog_key`, `rank`, `binding`, `hod_capable`, `grade`, `existence`) y `departments`
  +3 (`catalog_key`, `existence`, `account_hint`), + 2 tablas nuevas (`catalog_aliases`, `catalog_ambiguous_aliases`).
  Deploy fresco: entran solas con `migrate`. BD ya poblada: aplica el gemelo owner-apply (§9).
- **Datos por SEEDER (no SQL a mano):** `CatalogFusionSeeder` **ya encadenado** en `DatabaseSeeder` → corre con
  `db:seed`. Fusiona semilla+vivo: **48 departamentos / 255 puestos** (los 202 vivos conservan su **id numérico y
  sus asignaciones**; 53 puestos y 4 departamentos nuevos), + 1154 alias es/en + 2 alias ambiguos con su regla.
  Fuente de verdad = CSV versionados en `database/seeders/data/`. **Idempotente** (2× no duplica).
- **Para BD ya poblada (§9):** gemelo owner-apply del esquema + `php artisan db:seed --class=CatalogFusionSeeder --force`.
- **Reemplaza** la derivación de jefatura por regex y absorbe el `owner-apply/2026-07-18-catalog-sort-order` (el
  orden/rango los trae la fusión; ese delta ya **no** se aplica). Regla ESQUEMA-vs-DATOS escrita en §3.
- 🪤 **`owner-apply/2026-07-18-catalog-sort-order.sql` quedó ⛔ SUPERSEDED (2026-08-28), con nota en su cabecera
  y en `database/README.md` (#19). NO CORRERLO:** su bloque de PUESTOS pisaría el `sort_order` que el seeder ya
  pobló (volverían los ceros). El bloque de DEPARTAMENTOS ya se aplicó (por eso no se borra). El estado correcto
  lo dejan #114 + `CatalogFusionSeeder`.
- **F4 · vista admin + typeahead + permiso acotado:** vista `/catalogo` (gate `catalogs.view`/`catalogs.manage`),
  typeahead del alta busca por es/en/**alias** y crea puestos en línea (Opción B). Permiso nuevo
  **`catalogs.manage.own-department`** (crear puestos solo del depto propio) → sembrado a **hod** y **coordinator**;
  en fresh lo trae `RolesAndPermissionsSeeder`, en **BD poblada**: `php artisan db:seed --class=CatalogOwnDeptPermissionSeeder --force` (§9) + `permission:cache-reset`. Consolidadores (`crew.view.all-departments`) crean en todos; `catalogs.manage` global (super-admin/line-producer) sigue igual.
- **Suite: 868 verde.**

### Racha 2026-08-24 · Transportación · Bloque 1 · AJUSTES (borrador, licencia, is_towed, HEIC global)
- **§1 Borrador del checklist:** 1 tabla nueva `vehicle_inspection_drafts` (guardado parcial en servidor, del
  autor, retomable desde otro dispositivo; al sellar se borra). Fresh = migración `2026_08_24_000015`; BD poblada
  = `owner-apply/2026-08-24-transport-drafts.sql`.
- **§3 is_towed / REM-004:** solo DATO del catálogo — re-corre `db:seed --class=VehicleCatalogSeeder` (→ **51 puntos**,
  agrega REM-004 y las exclusiones de núcleo para unidades remolcadas). Sin SQL de esquema.
- **§2 Licencia con la persona + §4 HEIC global:** puro código (viaja con el deploy). La licencia del conductor
  sale de los docs del vehículo y vive en el paquete del driver (payee); la conversión HEIC en cliente ahora es
  global (todas las páginas, `cc-photo-auto.js` idempotente).
- **🆕 Requisito del host reforzado:** con foto obligatoria por punto, `Imagick`+`libheif` pasa de opcional a
  **respaldo requerido** (ver §0). **Suite: 815 verde.**

### Racha 2026-08-24 · Transportación · Bloque 1 (entidad Vehículo + verificación de seguridad)
- **Esquema:** 4 tablas (`vehicle_types`, `vehicle_check_points`, `vehicles`, `vehicle_inspections`) + puente
  `payee_contracts.vehicle_id`. En deploy fresco **entran solas con `migrate`** (migraciones `2026_08_24_000010..000014`)
  y el catálogo (13 tipos/50 puntos) + permisos `transport.*` **entran solos con `db:seed`** (`VehicleCatalogSeeder`
  + `TransportPermissionsSeeder` ya encadenados). Los 4 tipos de documento `VEH_*` entran con `DocumentTypeSeeder`.
- **Para parchar una BD ya poblada (§9):** gemelos `owner-apply/2026-08-24-transport-catalog.sql` + `-vehicles.sql`
  + `db:seed --class=VehicleCatalogSeeder` + `--class=TransportPermissionsSeeder` + `--class=DocumentTypeSeeder` +
  `cache:clear`. Backfill opcional `TransportBackfillVehiclesSeeder` (promueve `payee_contracts.asset_ref`).
- **RBAC HÍBRIDO:** `transport.manage` (safety + super-admin), `transport.view` (producción → vista lite), y el
  **departamento de Transportación** levanta el checklist por pertenencia (sin rol nuevo; `App\Support\TransportAccess`).
  Transpo es su **propia sección** de menú (≠ Seguridad).
- **A prueba de fallos:** verificador público `'veh'` nunca filtra el nivel interno; el acta sella HMAC (estado
  hash-excluido). ⚠ Fotos OBLIGATORIAS por punto → host sin Imagick+libheif rechaza HEIC (usuario sube JPG).
  Puro código lo demás (acta, vistas, rutas). **Suite: 810 verde.**

### Racha 2026-08-24 · Envío de archivos con marca de agua (Distribución + envío del llamado)
- **🆕 Requisito PERMANENTE nuevo:** el **cron** de §5·b. Sin él, los envíos masivos no se completan solos.
- **Esquema:** 2 tablas (`file_deliveries`, `file_delivery_recipients`) + columna `call_packages.extra_docs`.
  En deploy fresco **entran solas con `migrate`** (migraciones `2026_08_24_000002` y `000003`). Los gemelos
  `owner-apply/2026-08-24-file-deliveries.sql` (+ los `2026-08-23/24-*` del llamado/paquete) son solo para
  parchar una BD ya poblada (§9).
- **Flag** `callsheet_extra_docs` (adjuntar PDF adicional al paquete): **apagado** por default, viaja en código.
- **A prueba de fallos:** si las tablas/columna no están (BD sin la actualización), el módulo de Distribución
  se muestra "no disponible", el botón de envío avisa y el cron imprime "0" — **NO rompe el resto de la app**
  (guardas `Schema::hasTable`/`hasColumn`). El PDF **congelado/firmado** nunca se toca: la marca de agua se
  aplica a la copia de cada persona al enviar.
- Puro código lo demás (motor `PdfWatermarker`, outbox + despachador, pantalla `/distribucion`, gate
  `settings.manage`). Nombre marcado = créditos, o primer nombre + primer apellido. **Suite: 787 verde.**

### Racha anterior · contratos / firmas
Cambios de esa racha:
- **Sin SQL** (puro código, viaja con el deploy): nav de firma secuencial, pantalla final con acciones,
  bloqueo de **autoaprobación** (+ cotejo por correo para payees sin usuario), botón **Copiar enlace**,
  **panel de consulta de contratos** por departamento (`ContractVisibility`).
- **Único delta de BD:** columna `contract_envelope_recipients.rubrica_image` — **ya es migración**
  (`2026_08_20_000001_add_rubrica_image_...`), así que en el deploy fresco entra sola con `migrate`. El
  gemelo `owner-apply/2026-08-20-recipient-rubrica-image.sql` es solo para parchar una BD ya poblada.

Commits clave: `af1b03f2` (fuga autoaprobación) · `0a295c00` (panel consulta) · `a381e16c` (autoaprobación +
copiar enlace) · `556b8811` (pantalla final) · `2f2aedfb` ("Siguiente" secuencial). Suite de contratos: **127 verde**.

> **Opcionales apagados (próxima versión):** ruteo paralelo, escalera de auth por importe, instanciación masiva
> a N sobres, tipos copia/entrega-certificada. No requieren nada en este deploy.
