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
- **Opcional — HEIC (fotos de iPhone):** ImageMagick con delegado `libheif` + `php-imagick`. Si no está, un
  HEIC crudo se **rechaza con mensaje claro** (nunca se guarda invisible). Verifica con
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
  tiene, en el orden de dependencia de `database/README.md`, + `permission:cache-reset`.

---

## 10 · Cambios por racha (más reciente arriba)

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
