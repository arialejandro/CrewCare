# DATABASE-SCHEMA.md — Esquema real de la BD + reconstrucción

> ## ⚠ ACTUALIZACIÓN 2026-08-06 (catch-up post-compact — deltas #40-#51)
> El cuerpo de abajo es el **snapshot de auditoría del 2026-06-24 (27 tablas)** y quedó atrás.
> **Estado REAL hoy (BD local `crewcare`, `SHOW TABLES`): 84 tablas.** El dump versionado
> `database/schema/mysql-schema.dump` está en **56 tablas** (baseline 2026-07-22) → **NO tiene
> las 28 tablas de los deltas #41-#50** (se aplicaron por SQL local, el dump no se regeneró).
> **🔴 Pendiente owner:** regenerar el dump para que prod arranque completo (afecta bootstrap → territorio owner).
>
> **28 tablas nuevas desde el baseline de 56, por módulo (con su delta):**
> - **Wrap report:** `wrap_reports`.
> - **Herramientas — catálogo + inspección (#41/#42/#43):** `tools`, `tool_families`, `tool_variants`,
>   `tool_check_points`, `tool_inspections`, `check_point_tool`, `check_point_standard`, `tool_standard`.
> - **Permisos de trabajo — catálogo + emisión (#44):** `permits`, `permit_points`, `issued_permits`,
>   `permit_standard`, `permit_tool`.
> - **Vigilancia epidemiológica (#45):** `indicator_terms`, `outbreak_studies`.
> - **Registro LITE + médico:** `lite_patients`, `health_record_addendums`, `medical_access_grants`.
> - **MEDEVAC (#46):** `medevac_posters`.
> - **Mapeo de riesgos (#50):** `risk_maps`, `risk_map_views`, `risk_map_markers`.
> - **Aviso de privacidad:** `privacy_consents`. **Catálogo:** `catalog_pending_standards`.
> - **⛔ SIN USO (superseded #48 por [[risk-map-module]]):** `scouting_canvases` (1 fila prueba), `canvas_pins` (0).
> - **⚠ Residual beta (0 filas, drop candidato):** `clinic_attestations` (quedó tras eliminar BETA, delta `3b52b69a`).
>
> Modelos reales de estas tablas: `WrapReport, Tool, ToolFamily, ToolVariant, ToolCheckPoint, ToolInspection,
> Permit, PermitPoint, IssuedPermit, IndicatorTerm, OutbreakStudy, LitePatient, HealthRecordAddendum,
> MedicalAccessGrant, MedevacPoster, RiskMap, RiskMapView, RiskMapMarker, PrivacyConsent, CatalogPendingStandard`.
> El detalle vivo por módulo está en la **memoria** (`/memory/*.md`, índice `MEMORY.md`) y en `PROGRESS.md`.
> Las notas del snapshot de abajo (charset mixto, PK tinyint, huérfanas COVID) **siguen vigentes** para las tablas viejas.

---

Capturado de la **BD local en vivo** (`crewcare`, MySQL 5.7) el 2026-06-24 + reconstruido
desde modelos/controladores (no hay migraciones para las tablas de negocio). Es la base
para escribir las migraciones reales (Bloque 1 de [RECIPE.md](RECIPE.md)).

> ⚠️ La BD viva tiene **27 tablas**; las migraciones del repo solo crean 5 (las base de
> Laravel). Las 22 de negocio se crearon a mano en MySQL → **el esquema no es reproducible
> desde el código**.
>
> **Datos = 100% de prueba/fake (confirmado por el owner).** Todos los emails terminan en
> `.comf` a propósito (neutralizados para que ningún módulo dispare correos reales). Los
> ~88 usuarios, historiales médicos, reportes de lesión/peligro, etc. son datos cargados
> para probar módulos — **no hay PII real ni nadie comprometido**. Podemos rediseñar y
> recrear libremente; al normalizar el esquema, repoblar con seeders/factories.
>
> Verificado contra el dump real `crewcare2406.sql` (2026-06-24): la reconstrucción de
> abajo coincide con el SQL. Hallazgos adicionales que solo el SQL revela ↓ (§ "Del SQL real").

---

## Tablas en vivo + conteo de filas (SHOW TABLES + information_schema)

| Tabla | Filas | Modelo | Clasificación |
|---|---|---|---|
| `users` | 88 | `User` | KEEP (limpiar columnas COVID) |
| `departamentos` | 12 | `departamento` | KEEP → `departments` |
| `puestos` | 18 | `puesto` | KEEP → `positions` |
| `departamentousuario` | 0 | `departamentousuario` | KEEP/refactor (junction) |
| `userpuesto` | 0 | `userpuesto` | KEEP/refactor (junction) |
| `formularios` | 1 | `formulario` | REPURPOSE → `medical_records` |
| `userform` | 0 | `userform` | REPURPOSE (junction) |
| `cmedic` | 2 | `cmedic` | REPURPOSE (consultas médicas) |
| `location_report` | 1 | `locationreport` | KEEP (H&S) |
| `hazardnotifications` | 3 | `hazardnotification` | KEEP (H&S) |
| `unsafeconds` | 4 | `unsafecond` | KEEP (H&S) |
| `injury_reports` | 0 | `InjuryReport` | KEEP (H&S) |
| `daily_reports` | 0 | `DailyReport` | KEEP (H&S, benchmark) |
| `daily_logs` | 2 | `DailyLog` | KEEP (H&S) |
| `safety_standards` | 22 | `SafetyStandard` | KEEP (catálogo) |
| `usuariosnotificaciones` | 0 | `usuariosnotificacione` | REMOVE (alerta positivos COVID) |
| `pcr` | 5 | `pcr` | **REMOVE (COVID)** |
| `prueba` | 51 | `prueba` | **REMOVE (COVID)** |
| `usuariopcr` | 37 | `usuariopcr` | **REMOVE (COVID)** |
| `testqueue` | 0 | `queuevirtual` | **REMOVE (COVID)** |
| `message` | 2 | `message` | **REMOVE (COVID — cola)** |
| `userpcr` | 0 | *(sin modelo)* | **REMOVE — tabla huérfana** (el modelo usa `usuariopcr`) |
| `virtualqueue` | 0 | *(sin modelo)* | **REMOVE — tabla huérfana** (el modelo `queuevirtual` apunta a `testqueue`) |
| `users/password_resets/failed_jobs/personal_access_tokens/migrations` | — | base Laravel | KEEP |

> **Hallazgo nuevo:** existen **dos tablas huérfanas** sin modelo (`userpcr`, `virtualqueue`)
> además de las que sí se usan (`usuariopcr`, `testqueue`) — residuo de renombrados históricos.
> La tabla `jobs` que esperaría la migración `2022_02_27_create_jobs_table` **no aparece** en la
> lista viva (la cola es `sync`, nunca se usó). Confirma que `migrations` solo tiene 4 registros.

---

## Inventario de columnas (reconstruido) — solo lo no obvio

### `users` (88 filas) — la migración base solo define name/email/password/timestamps; **el resto se añadió a mano**
- Identidad/contacto: `name, lname, lname2, borndate(date), sex, email, phone, zone, imgperfil, workstation, device_token`.
- Acceso: `admin, activo` (+ `password`).
- Org (desnormalizado): `puestodepartamento` (string "Depto-Puesto"), `ncreditos`.
- **Columnas sobrecargadas (renombrar):** `age` = "tiene foto/credencial" (NO edad); `daytest` = rol(0/1/2) **y** día de prueba(3); `labn` = contador de cola **y** nº lab.
- **Nota (2026-06-25) — el form de alta de crew reusa columnas con nombre equivocado** (más deuda de "columnas reusadas", flagged para el cleanup de esquema): en `admin/newuser` el campo `name="zone"` guarda el **Departamento** (no un zone/código de gafete), `puestodepartamento` guarda **solo el puesto** (no "Depto-Puesto"), y `labn` (COVID) se reusa como **"Jerarquía"**. No bloquea el scoping del HOD (#2): la verdad del scoping es el pivote `production_user.department_id`, que el alta ya escribe.
- **Nota (2026-06-25, actualización) — el form de alta de crew ya escribe FK REALES al catálogo.** El form `admin/newuser` se cableó a las tablas normalizadas `departments`/`positions` (catálogo global = `positions.production_id IS NULL`): ahora envía `department_id` + `position_id` (no los strings `zone`/`puestodepartamento`), `AdminController@newuser` los resuelve **contra el catálogo** (rechaza un puesto que no pertenezca al departamento elegido; fuerza el depto del HOD) y escribe **FK reales** a `production_user` (`department_id` + `position_id` — antes `position_id` era siempre null). Para compat con vistas legacy que aún leen estas columnas, se mantienen pobladas **`zone`** (= nombre del departamento, display legacy del perfil) y **`puestodepartamento`** (= `"Depto-Puesto"`, o solo el depto si no se eligió puesto), pendientes del rename completo de §3.5.
- **Columnas COVID (eliminar):** `encuestadiaria, lastwr, lastpcr, enfermo, resultpcr, tested, inline, inlined, ultimatemperatura`.
- **Bugs:** `temperatura` se escribe en `QueueImport:34` pero NO está en `$fillable` (mass-assignment la descarta); `inlined` se escribe por atributo en `queueController` pero falta en `$fillable`.

### `formularios` (1 fila) → futura `medical_records`
~50 columnas planas: signos (`blod_type, height, size, rythm, pregnant`), contacto emergencia
(`c_emer, relation, p_emer`), hospitales (`hospitals` + `hsp1..hsp10`), antecedentes
(`cirugy, pathology, alergy, trauma, momdat1..7, daddat1..7, pers_nopat1..3`), vacunas
(`vacci1..5`), `crt19` (COVID). **Normalizar** a tabla núcleo + hijas (hospitales, antecedentes,
vacunas como filas), quitar `crt19`.

### Tablas H&S (ya en inglés, casi limpias)
`location_report` (~150 cols, PK `id_loc`, bloques `owner_*`/`inst_*`/`vent_*`/…),
`hazardnotifications`, `unsafeconds`, `injury_reports`, `daily_reports`+`daily_logs`,
`safety_standards` (catálogo: `category_name, regulation_badge, regulation_code`).
Solo `InjuryReport`, `DailyReport`, `DailyLog` declaran relaciones Eloquent reales.

---

## Del SQL real — hallazgos que NO se ven en el código (importantes para las migraciones)
1. **🔴 `hazardnotifications.id` y `unsafeconds.id` son `tinyint(3) unsigned` → TECHO de 255 filas.**
   Son tablas KEEP del módulo de seguridad: con uso real **dejan de aceptar registros al llegar a 255**.
   Al migrar, su PK debe ser `bigint unsigned auto_increment` como el resto.
2. **🔴 Charset MIXTO:** ~mitad de tablas `utf8mb4`, ~mitad **`latin1`** (hazard, injury, location_report,
   unsafeconds, puestos, message, pcr, prueba, junctions…). Guardan español con acentos → **riesgo de
   mojibake al consolidar a utf8mb4**. Las migraciones deben fijar `utf8mb4` y convertir con cuidado.
3. **`ncreditos` es `varchar(255)` y guarda NOMBRES** (ej. `'Ari Rómulo'`), no créditos → columna mal
   nombrada/sobrecargada adicional (parece "creado por").
4. **Junctions TODAS vacías** (`userpuesto`, `departamentousuario`, `userform`, `userpcr`, `virtualqueue` = 0 filas):
   la app **nunca usó las relaciones normalizadas**; la organización vive solo en el string `users.puestodepartamento`.
5. **`virtualqueue` (huérfana) tiene esquema propio distinto** (`name_user, lname_user, email_user, statustest`) —
   una cola desnormalizada vieja, no confundir con `testqueue` (la que usa el modelo `queuevirtual`).
6. **Migración de `users` en la BD = `2021_10_20_100050_create_users_table`**, distinta del archivo del repo
   (`2014_10_12_000000…`) → drift de migraciones.

## Mismatches esquema-vs-código (a resolver al migrar)
1. **PK con espacio — SOLO en el modelo:** `departamentousuario` define `$primaryKey = 'id_departamentousuario '`
   (con espacio) pero la **columna real es `id_departamentousuario`** (limpia). Bug del modelo (apunta a columna
   inexistente); no mordió porque la tabla está vacía.
2. **FK con typo (real en la BD):** `usuariopcr.id_usrio` (con FK real a `users`). Decidir corregir a `id_usuario` o conservar.
3. **`temperatura` vs `ultimatemperatura`:** `QueueImport` escribe `temperatura`, columna que **no existe** en `users`
   (solo `ultimatemperatura`) → el valor se descarta silenciosamente.
4. **PK dentro de `$fillable`** en ~10 modelos (mass-assignment de PK).
5. **FK llamado `id`** (no `user_id`) en `pcr`, `userpuesto`, `departamentousuario`.
6. **`$dates` deprecado** en ~12 modelos → migrar a `$casts`.
7. **Clases de modelo en minúscula** (`cmedic`, `pcr`, `user` import…) → rompen autoload en Linux/AWS.
8. **Doble fuente de verdad:** `userpuesto` (vacía) + el string `users.puestodepartamento` (el que sí se usa).
9. **Tablas huérfanas:** `userpcr` (limpia pero sin uso), `virtualqueue` (esquema viejo) — sin modelo, 0 filas.

> **Matiz sobre FKs (corrección):** sí existen FKs reales en las tablas org/médicas (cmedic→users,
> formularios→users, userform, userpuesto, departamentousuario, puestos→departamentos, usuariopcr→prueba+users,
> daily_logs→daily_reports CASCADE). Las que **no** tienen FK son las H&S (hazard, injury, unsafe, location) y `pcr`/`prueba`.

---

## Esquema objetivo (alto nivel, post-COVID + Fase 1)
- **`users`** limpio (sin columnas COVID; `age`→`has_badge_photo`; quitar `daytest`/`labn`).
- **`departments`, `positions`, `position_user`** (consolidar junctions + el string desnormalizado).
- **`productions`** (NUEVA) — hoy el nombre de producción es texto libre repetido en cada reporte H&S → centralizar con FK.
- **`medical_records`** normalizada + `medical_consultations` (ex `cmedic`).
- **Roles reales** (spatie) — hoy el "rol" vive disperso en `admin`/`daytest`.
- Reportes H&S: estandarizar PK a `id`, añadir FK `user_id`/`production_id`.
- **Drop:** `pcr, prueba, usuariopcr, testqueue, message, usuariosnotificaciones` + las huérfanas `userpcr, virtualqueue`.

> Al rehacer el esquema, crear **seeders/factories** (hoy `DatabaseSeeder` y `UserFactory` están
> vacíos/scaffold) para repoblar datos de prueba y poder levantar la base con `migrate:fresh --seed`.
