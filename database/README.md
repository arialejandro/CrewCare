# Esquema de CrewCare — cómo levantarlo y cómo actualizarlo

> **⚠ ACTUALIZADO 2026-08-11 — ESTE DOC ES EN SU MAYORÍA HISTÓRICO.** Desde la **Fase 1** del
> plan de puesta a punto (ver `PROGRESS.md` / `ROADMAP.md`), el esquema se reconstruyó como
> **migraciones limpias POR TABLA** (`database/migrations/*.php`, una por tabla desde el DDL
> vivo). Hoy **`php artisan migrate` levanta TODO el esquema** — verificado 2026-08-11: 64
> migraciones, install de fábrica arranca limpio + **475 tests de QA en verde**. El viejo
> `database/schema/mysql-schema.dump` fue **BORRADO** y ya NO se usa. La carpeta
> `database/owner-apply/` y el índice de deltas de más abajo quedan como **referencia
> histórica**: su contenido YA está horneado en las migraciones (auditado 2026-08-11, cero
> gaps; sólo `scouting_canvases`/`canvas_pins` del delta #48 quedaron fuera A PROPÓSITO por
> estar superseded por RiskMap). Los seeders de catálogo/permiso ya están **encadenados en
> `DatabaseSeeder`** (un solo `php artisan db:seed` deja la app usable) → la sección "Seeders
> uno-por-uno" de abajo también es histórica.
>
> **Estado original (2026-07-25 · v3.5, histórico):** la app corría SOLO EN LOCAL.

Este archivo existe porque hasta entonces no había ninguno: `database/owner-apply/` tenía SQL
sueltos sin orden documentado. (**Actualizado 2026-08-21:** el índice llegó a listar sólo **45
deltas**; hoy `owner-apply/` tiene **105 archivos** y `database/migrations/` **127 migraciones**.
El índice de abajo ya cubre los **105** en orden de aplicación, con las cadenas de orden duro.)

---

## Hay DOS caminos, y confundirlos hace daño

### A · Base de datos NUEVA (producción desde cero, otra máquina, un entorno de pruebas)

```
php artisan migrate
```

Y ya. Laravel detecta `database/schema/mysql-schema.dump`, lo carga entero y luego aplica las
migraciones que falten. **No hay que tocar ni un archivo de `owner-apply/`**: el baseline es una
foto del esquema COMPLETO y ya trae dentro todo lo que esos SQL fueron agregando.

Después, los datos semilla (ver *Seeders* abajo).

### B · Base de datos que YA EXISTE (el caso de producción cuando llegue el momento)

Ahí el baseline **no sirve** y **no se debe usar** (ver la advertencia). Lo que aplica son los
deltas de `owner-apply/`, en orden, y sólo los que falten.

---

## ⚠️ ADVERTENCIA — el baseline BORRA

`database/schema/mysql-schema.dump` empieza cada tabla con `DROP TABLE IF EXISTS`. Es como
funciona `mysqldump` y no es un defecto.

**Laravel lo carga ÚNICAMENTE cuando la tabla `migrations` está vacía**, o sea sólo en una base
nueva. Ejecutarlo A MANO contra una base con datos **destruye la base entera sin preguntar**.

Regla: el baseline se usa con `php artisan migrate` sobre una base vacía. Nunca se pega en un
cliente de MySQL apuntando a producción.

---

## Cómo se regenera el baseline

Cuando se apliquen deltas nuevos en local y se quiera actualizar la foto:

```
# mysqldump debe ser el de MySQL 5.7 (el mismo que el servidor), no el de 8.0
export PATH="/c/laragon/bin/mysql/mysql-5.7.33-winx64/bin:$PATH"
php artisan schema:dump          # NUNCA con --prune: borraría las migraciones del repo
```

Verás `mysqldump: [ERROR] unknown variable 'column-statistics=0'`. **Es ruido esperado**:
esa bandera es de MySQL 8, Laravel reintenta sin ella y el dump sale completo. Confirma que
terminó con `Database schema dumped successfully.`

Verificado el 2026-07-22 levantando el dump en una base temporal y comparando contra la viva:
**56 tablas, 770 columnas, 27 llaves foráneas y 145 índices, todo idéntico.**

---

## Los deltas de `owner-apply/` (105, en ORDEN DE APLICACIÓN)

Se aplican **a mano, fuera de Laravel** — nunca `php artisan migrate` — y en este orden. Todos son
**idempotentes**: comprueban `information_schema` (o usan `IF NOT EXISTS`) antes de tocar nada, así
que re-ejecutar uno ya aplicado no hace daño. El orden es por FECHA, salvo el grupo `2026-07-24`,
que se REORDENA por dependencia real (ver *Dependencias* abajo). Cada archivo lleva en su cabecera
el porqué del cambio y su **reversión** (el `DROP` correspondiente, comentado).

| # | Archivo | Qué hace (tablas/columnas) |
|--:|---------|----------------------------|
| 1 | `2026-06-28-cuatro-items-safety` | **Crea `scouting_reports`** y **`settings`**; `safety_standards.reference_url`; snapshot `regulation_*` en los 3 reportes; `created_by_id` en los 4; `risk_level`/`action_status`; **`sort_order` en `departments`/`positions`**. |
| 2 | `2026-07-06-badge-prints` | **Crea `badge_prints`** (impresión de gafete por usuario). |
| 3 | `2026-07-06-badge-templates` | **Crea `badge_templates`** (plantilla configurable, `config` JSON). |
| 4 | `2026-07-06-medical-consults` | **Crea `medications`**; `cmedic` +`created_by_id`/`consultation_date`/`medication_items`. |
| 5 | `2026-07-06-scouting-gps` | `scouting_reports` +`latitude`/`longitude`. |
| 6 | `2026-07-06-settings` | Crea `settings` IF NOT EXISTS. **Redundante con #1** (no-op si #1 ya corrió). |
| 7 | `2026-07-07-amazon-ra` | `scouting_reports` +`production_type`/`manager_name`/`safety_rep_name`. |
| 8 | `2026-07-07-safety-gps` | `hazardnotifications`/`unsafeconds`/`injury_reports` +GPS (`latitude`/`longitude`/`gps_address`). |
| 9 | `2026-07-07-users-covid-cleanup` | DROP 7 columnas COVID muertas de `users`. |
| 10 | `2026-07-09-structural-upgrades` | **Crea `action_items` y `standardables`**; `injury_reports` (OSHA 300/301, RCA, EPP, 5×5); `likelihood`/`consequence`; `scouting_reports.sb132_details`. |
| 11 | `2026-07-12-modules-6-14` | **Crea `digital_signatures` y `witnesses`**; EPP/clima/`uuid`/notif en los 5 reportes; `name_en` en catálogos; backfill `uuid`. |
| 12 | `2026-07-13-coherencia-fixes` | Purga `safety_standards` id 1..22; +`unsafeconds.involved_department`, `daily_logs.created_by_id`; UNIQUE `regulation_code`. |
| 13 | `2026-07-13-hazard-events-catalog` | **Crea `hazard_events` y `hazard_event_standard`**; +`hazard_event_id` en hazard/unsafe/injury/`daily_logs`. |
| 14 | `2026-07-13-pillars-1-5` | **Crea `feature_flags`, `consumables`, `sfx_events`, `addendums`**; `daily_logs` sourceable, `daily_reports.production_id`, override/pending, `action_items` (magic links), `cmedic.injury_report_id`. |
| 15 | `2026-07-16-sds-verification` | `consumables` +`verified_at`/`verified_by_id` + sellado de semillas. |
| 16 | `2026-07-16-sfx-effect-types` | **Crea `sfx_effect_types` y `consumable_sfx_effect_type`** (FK dura →`consumables` y →`sfx_effect_types`). |
| 17 | `2026-07-17-consumables-sds16` | `consumables` +13 col HDS-16/GHS + UNIQUE `code`. |
| 18 | `2026-07-17-consumables-soft-delete` | `consumables` +`deleted_at` (soft delete). |
| 19 | `2026-07-18-catalog-sort-order` | ⛔ **SUPERSEDED por #114 + `CatalogFusionSeeder` (2026-08-28) — NO CORRER.** El bloque de PUESTOS pisaría el `sort_order` de la fusión (volverían los ceros); el de DEPARTAMENTOS ya se aplicó. No se borra por su historia. |
| 20 | `2026-07-18-departments-animalero-equipo` | INSERT deptos/puestos + reasigna `sort_order`. **Correr tras #19.** |
| 21 | `2026-07-18-effect-standard-bridge` | **Crea `effect_standard`** (FK dura →`sfx_effect_types` y →`safety_standards`). |
| 22 | `2026-07-18-normas-eventos-verification` | `safety_standards`/`hazard_events` +`verified_at`/`verified_by_id` + sellado. **Antes del seeder de eventos nuevos.** |
| 23 | `2026-07-18-safety-standards-is-active` | `safety_standards` +`is_active` (vigente/retirada, NO SoftDelete). |
| 24 | `2026-07-19-daytest-neutralizar-valor-2` | UPDATE `users.daytest`=NULL WHERE =2 (mata el falso grupo "Médico"). |
| 25 | `2026-07-19-materiality-photos` | **Crea `materiality_photos`** (evidencia fiscal). |
| 26 | `2026-07-19-medic-credentials` | **Crea `medic_credentials`** (cédula profesional; nace vacía, sin sellado). |
| 27 | **`2026-07-21-dsr-safety-meeting`** | **`daily_reports` +`safety_meeting_held`/`safety_meeting_photo_path`** + UPDATE una-vez. **⚠️ INVALIDA SELLOS DSR — ver nota roja abajo.** |
| 28 | `2026-07-22-epp-por-evento` | `hazard_events.required_ppe` y `daily_logs.required_ppe` (snapshot). |
| 29 | `2026-07-24-gemelos-vinculos-contenido` | `hazardnotifications`/`unsafeconds` +4 col c/u (scouting link, involucrado, enlace gemelo, factor humano/recurrencia). |
| 30 | `2026-07-24-medical-hardening` | (Médico 1/3) `cmedic` +`uuid`/`medic_cedula`/`medic_name`/`medic_cedula_verified`; **crea `medical_access_grants`**; quita `medical.view` a safety-officer. |
| 31 | `2026-07-24-medical-key-medic` | (Médico 2/3) permiso `medical.consolidate` (KEY MEDIC). Requiere `permission:cache-reset`. |
| 32 | `2026-07-24-medical-triage` | (Médico 3/3) `cmedic` +`management`/`without_record`. |
| 33 | `2026-07-24-health-record-fixes` | (Expediente 1/2) `formularios`: `height`/`size`→NULLABLE, +`vacci2_date`, DROP `crt19`. **Estabiliza antes de sellar.** |
| 34 | `2026-07-24-health-record-seal-consent` | (Expediente 2/2) `formularios.uuid`, `cmedic.intake_*`; **crea `health_record_addendums` y `privacy_consents`**. **Requiere #33.** |
| 35 | `2026-07-24-beta-lite-patients` | **Crea `lite_patients` y `clinic_attestations`**; `cmedic` (`id_user` nullable, `lite_patient_id`+FK, `seal_version`), `medications.beta_flagged`. **Requiere #34 y #4.** |
| 36 | `2026-07-24-beta-lite-refinamientos` | TRIGGER XOR en `cmedic`; CHECK 8.0+; `clinic_attestations.production_id` + UNIQUE compuesto. **Requiere #35.** |
| 37 | `2026-07-24-wrap-reports` | **Crea `wrap_reports`** (reporte de cierre sellable, payload congelado). |
| 38 | `2026-07-24-detectar-produccion-demo` | **Sólo SELECT (diagnóstico).** Cuenta filas `[DEMO]` + cuentas `@crewcare.test`. En BD nueva debe dar TOTAL = 0. |
| 39 | `2026-07-24-borrar-produccion-demo` | **BORRA (purga condicional).** Sólo si #38 no dio 0. No es un delta de esquema. |
| 40 | `2026-07-25-dsr-scouting-link` | `daily_reports.scouting_report_id` (hereda hospital del Scouting). Toca la tabla firmada pero es **seguro** (null-hash-excluded). |
| 41 | `2026-07-26-tools-permits-catalog` | **Crea 12 tablas** de los catálogos de HERRAMIENTA (Capa B) y PERMISOS (Capa C): `tool_families`, `tools`, `tool_variants`, `tool_check_points`, `check_point_tool`, `tool_standard`, `check_point_standard`, `permits`, `permit_points`, `permit_standard`, `permit_tool`, `catalog_pending_standards`. Los 3 puentes `*_standard` llevan **FK dura a `safety_standards`** (errno 150 si falta). Aditivo puro; nada existente cambia. Datos vía `ToolPermitCatalogSeeder`. |
| 42 | `2026-07-26-tool-inspections` | **Crea `tool_inspections`** (el ACTA de inspección de herramienta: buscar→ejecutar→veredicto sellado). FK-soft, aditiva. **Requiere el #41** (referencia `tools`). Sella con `HasDigitalSignatures`; entra al verificador público como `'insp'`. Permiso nuevo `tools.inspect` (`ToolInspectionPermissionsSeeder`). |
| 43 | `2026-07-26-inspection-preventive` | **ALTER aditivo idempotente** (procedure + information_schema): `tools.inspection_regime` (por_jornada/por_colocacion/por_evento — la vigencia como DEUDA) y en `tool_inspections` el `inspection_moment` + `origin_*` (hasheados) y `retired_*`/`superseded_by_id` (HASH-EXCLUIDOS: retirar no re-sella). **Requiere el #42**. Régimen vía `ToolInspectionRegimeSeeder`. |
| 44 | `2026-07-30-issued-permits` | **Crea `issued_permits`** (el PERMISO DE TRABAJO emitido: emitir→verificar en sitio→cerrar). FK-soft, aditiva. **Requiere el #41** (referencia `permits`/`permit_points`). Snapshot congelado + doble firma; autorización externa DECLARADA; site_scope (reverificación/ligado); cierre con fire-watch + action item; suspensión HASH-EXCLUIDA. Sella con `HasDigitalSignatures`; entra al verificador público como `'perm'`. Permiso nuevo `permits.issue` (`PermitIssuancePermissionsSeeder`). |
| 45 | `2026-07-31-epi-surveillance` | **Crea `indicator_terms`** (diccionario de ~36 términos por grupo indicador, `IndicatorTermSeeder`) y **`outbreak_studies`** (estudio de brote NOM-017 sellado, verificador `'brote'`). Aditivas puras; SOLO LEE las consultas (`cmedic` intacto). Panel silencioso (sin correos/umbrales). Permiso nuevo `epi.view` (`EpiPermissionsSeeder` → safety-officer/medic/super-admin). |
| 46 | `2026-07-31-medevac-poster` | **Crea `medevac_posters`** — 1ª plantilla del MOTOR DE DOCUMENTOS (scouting→sella, verificador `'mdvc'`). Permiso `medevac.issue`. |
| 47 | `2026-08-01-hazard-control-measure` | (delta #49) Captura fluida: **medida de control PRE-PROPUESTA** (`hazard_events`/`daily_logs` +`control_measure`). |
| 48 | `2026-08-01-location-mapping` | (delta #48) Crea `scouting_canvases`/`canvas_pins`. **⛔ SUPERSEDED por #50 (risk-map) — NO aplicar en prod; fuera de las migraciones a propósito.** |
| 49 | `2026-08-01-medevac-map-persist` | (delta #47) MEDEVAC: persiste el **mapa de ruta** en el scouting (ALTER `scouting_reports`). |
| 50 | `2026-08-03-risk-map` | (delta #50) **Crea `risk_maps`, `risk_map_views`, `risk_map_markers`** (Mapeo de riesgos y recursos, sellado `'rmap'`). Reemplaza al #48. |
| 51 | `2026-08-04-hazard-risk-icon` | (delta #51) `hazard_events.risk_icon` — icono curado por evento del catálogo. |
| 52 | `2026-08-06-pae-emergency-action-plans` | **Crea `emergency_action_plans`** (PAE · 2º doc del motor; UNO por llamado, sellado `'pae'`). Permiso `pae.issue`. |
| 53 | `2026-08-06-pae-versioning` | PAE · versionado (ALTER `emergency_action_plans`). **Requiere #52.** |
| 54 | `2026-08-08-ambulance-catalog` | **Crea las tablas del catálogo de verificación de ambulancias** (tipos + puntos NOM-034). |
| 55 | `2026-08-08-ambulance-evidence-photos` | Evidencia fotográfica múltiple del acta de ambulancia. **Requiere #57.** |
| 56 | `2026-08-08-ambulance-location` | Locación (GPS-back) de la verificación de ambulancia. **Requiere #57.** |
| 57 | `2026-08-08-ambulance-verification` | **Crea `ambulance_verifications`** (recurso del día, proveedor, acta sellada `'ambu'`). **Requiere #54.** |
| 58 | `2026-08-08-scouting-has-ambulance` | `scouting_reports` +bandera "¿habrá ambulancia?" (Parte D del bloque ambulancias). |
| 59 | `2026-08-08-tool-inspection-serial-owner-photo` | `tool_inspections` +unidad física (serie/dueño) + foto real. **Requiere #42.** |
| 60 | `2026-08-09-permit-photos` | Fotografías adjuntas al emitir un permiso de trabajo (ALTER `issued_permits`). **Requiere #44.** |
| 61 | `2026-08-13-ambulance-provider-payee-link` | PASO 5: migra el proveedor de ambulancias a la base única (payee). **Requiere #73 (payees) y #57.** ⚠ *depende de algo posterior en orden alfabético — ver Dependencias.* |
| 62 | `2026-08-13-beneficiary-phone` | Infosheet F1: teléfono del beneficiario mortis causa (ALTER `payee_contracts`). **Requiere #73.** |
| 63 | `2026-08-13-contract-annexes` | **Crea la biblioteca de ANEXOS** del contrato (Paso C). |
| 64 | `2026-08-13-contract-clauses` | **Crea la biblioteca de CLAUSULADOS** (Paso B). ⚠ El clausulado se RETIRÓ luego (histórico); la tabla queda. |
| 65 | `2026-08-13-contract-emit-fields` | Lo que el contrato CONGELA al emitir (ALTER `payee_contracts`). **Requiere #73.** |
| 66 | `2026-08-13-contract-envelopes` | **Crea `contract_envelopes` + destinatarios + consentimiento** (Paso C · el SOBRE). |
| 67 | `2026-08-13-contract-work-dates` | **Crea la tabla hija de fechas de trabajo** de `payee_contracts`. **Requiere #73.** |
| 68 | `2026-08-13-crew-contract-fields` | Campos de la carátula (crew_work) en `payee_contracts`. **Requiere #73.** |
| 69 | `2026-08-13-external-access-expiry` | Infosheet F4: caducidad del token de acceso externo. |
| 70 | `2026-08-13-external-auth-period-link` | El documento cuelga del PERIODO de pago. **Requiere #78.** |
| 71 | `2026-08-13-infosheet-authorizations` | **Crea `infosheet_authorizations`** (autorización del paso 2 del Infosheet). |
| 72 | `2026-08-13-infosheet-fee-breakdown` | Importe por fase + desglose fiscal (ALTER `payee_contracts`). **Requiere #73.** |
| 73 | `2026-08-13-payee-base` | **Crea `payees` y `payee_contracts`** (BASE ÚNICA DE QUIEN COBRA · la entidad). **Base de todo el bloque payee/contrato.** |
| 74 | `2026-08-13-payee-document-downloads` | **Crea la bitácora de descargas** de documentos fiscales del payee. **Requiere #73.** |
| 75 | `2026-08-13-payee-foreigner` | Delta de extranjero en `payees`. **Requiere #73.** |
| 76 | `2026-08-13-payee-intake` | Paso 3: intake autoservicio + estado RECIBIDO. **Requiere #73.** |
| 77 | `2026-08-13-payee-packages` | Cierre Paso 1 (régimen fiscal) + Paso 2 (paquetes). **Requiere #73.** |
| 78 | `2026-08-13-payment-periods` | **Crea `payment_periods`** (ventana de recepción por periodo de pago). |
| 79 | `2026-08-13-recipient-signature-image` | Firma autógrafa en el sobre (DocuSign). **Requiere #66.** |
| 80 | `2026-08-13-representante-legal-position` | INSERT puesto 'Representante Legal' (firmante que obliga a la moral). |
| 81 | `2026-08-13-user-adopted-signature` | `users` +firma ADOPTADA reutilizable (tipo DocuSign). |
| 82 | `2026-08-13-user-external-flags` | `users` +la persona no-crew como usuario único sin credenciales (Infosheet F1). |
| 83 | `2026-08-14-contract-template-format` | Formato como dato de la plantilla. **Requiere #86.** |
| 84 | `2026-08-14-contract-template-initials` | Rúbrica del contratado por página. **Requiere #86.** |
| 85 | `2026-08-14-contract-template-page-size` | Tamaño de página del documento. **Requiere #86.** |
| 86 | `2026-08-14-contract-templates` | **Crea `contract_templates`** (Contract Builder F1 · plantilla con anclas de firma). **Base del bloque template.** |
| 87 | `2026-08-14-contracts-author-permission` | Gating del builder + figura legal (permiso). |
| 88 | `2026-08-14-recipient-anchor-key` | Ancla de firma por destinatario. **Requiere #66 y #86.** |
| 89 | `2026-08-15-contract-envelope-escape-paths` | Caminos de escape del sobre (Paso C F2). **Requiere #66.** |
| 90 | `2026-08-15-contract-envelope-events` | **Crea `contract_envelope_events`** (bitácora append-only del sobre). **Requiere #66.** |
| 91 | `2026-08-15-contract-envelope-signed-document` | Documento FIRMADO real del sobre (Paso C F3). **Requiere #66.** |
| 92 | `2026-08-15-contract-envelope-uuid` | UUID público del sobre (verificador). **Requiere #66.** |
| 93 | `2026-08-15-contract-template-font-family` | Tipografía base del contrato. **Requiere #86.** |
| 94 | `2026-08-15-contract-template-font-size` | Tamaño de letra base (pt). **Requiere #86.** |
| 95 | `2026-08-16-contract-recipient-copy` | Destinatarios de copia / entrega-certificada (B1). **Requiere #66.** |
| 96 | `2026-08-16-contract-template-category` | Categoría (contrato\|anexo) + orden de la plantilla. **Requiere #86.** |
| 97 | `2026-08-16-contract-template-pdf-source` | PDF fillable (subir PDF + colocar etiquetas). **Requiere #86.** |
| 98 | `2026-08-16-envelope-signed-annexes` | Sobre multi-documento: anexos firmados. **Requiere #66.** |
| 99 | `2026-08-16-payee-contact-legalrep` | Datos de contacto del contratado para el contrato (ALTER `payees`). **Requiere #73.** |
| 100 | `2026-08-16-payee-emergency-relationship` | Parentesco del contacto de emergencia (intake). **Requiere #73/#76.** |
| 101 | `2026-08-17-hazard-events-catalog-227` | Catálogo de eventos + medidas de control — base de producción (DATOS). |
| 102 | `2026-08-17-sfx-effect-image` | `sfx_effect_types` +imagen principal por tipo. **Requiere #16.** |
| 103 | `2026-08-17-sfx-effects-all-verified` | SPFX de fábrica nacen VERIFICADOS de origen (UPDATE de datos). |
| 104 | `2026-08-20-recipient-rubrica-image` | Marca de rúbrica aparte de la firma en el sobre. **Requiere #66 y #79.** |
| 105 | `2026-08-21-payee-contract-asset-ref` | `payee_contracts.asset_ref` — referencia mínima del ACTIVO en el contrato de renta (hook Transportación). **Requiere #73.** |
| 106 | `2026-08-24-transport-catalog` | Transportación · Bloque 1 — catálogo `vehicle_types` + `vehicle_check_points` (2 tablas NUEVAS). Sin dependencias. |
| 107 | `2026-08-24-transport-vehicles` | Transportación · Bloque 1 — `vehicles` + `vehicle_inspections` (2 tablas NUEVAS) + puente `payee_contracts.vehicle_id` (ALTER idempotente). **Requiere `payee_contracts`.** |
| 108 | `2026-08-24-transport-drafts` | Transportación · Bloque 1 §1 — `vehicle_inspection_drafts` (1 tabla NUEVA, borrador del checklist del autor). Sin dependencias. El ajuste §3 (is_towed/REM-004 → 51 puntos) NO es SQL: re-correr `VehicleCatalogSeeder`. |
| 109 | `2026-08-24-transport-order` | Transportación · Bloque 2 — **ORDEN de transportación**: 7 tablas NUEVAS (`transport_parties`/`transport_equipment`/`transport_addresses`/`transport_address_viewers`/`transport_orders`/`transport_order_runs`/`transport_run_occupants`). NO se sella. Luego `db:seed --class=TransportEquipmentSeeder` (12 íconos). §0 CALZAS (REM-005 → 52 puntos) NO es SQL: re-correr `VehicleCatalogSeeder`. Sin dependencias. |
| 110 | `2026-08-24-transport-supersedes` | Transportación · Bloque 2 §0 — `vehicle_check_points.supersedes` (ALTER idempotente, mismo mecanismo que `tool_check_points.supersedes`). REM-005 SUSTITUYE a CAR-004 en `has_cargo_box`+`is_towed`. Luego re-correr `VehicleCatalogSeeder` (fija `supersedes`). **Requiere #106.** El seeder degrada limpio si la columna falta. |
| 111 | `2026-08-25-transport-run-key` | Transportación · Bloque 2 §4 — `transport_order_runs.run_key` (UUID, ALTER idempotente + backfill). Identidad estable de la corrida: persiste al editar, se copia al clonar la versión; el diff empareja por ella. **Requiere #109.** |
| 112 | `2026-08-26-transport-pickup-matrix` | Transportación · Pick up derivado FASE 1 — `transport_pickup_points` + `transport_travel_times` (2 tablas NUEVAS). Catálogo de orígenes con coordenadas + tiempo por par origen→destino (destino = `scouting_reports` geolocalizado; `scouting_id` referencia BLANDA por id). `CrewGeo.driveEta` (OSRM navegador) propone, transpo corrige, persiste por par (unique). Aditivo, NO toca la corrida/pick up existentes. Sin dependencias nuevas. |

> **Nota (2026-08-24):** entre el #105 y el #106 existen también los deltas `2026-08-23-*` (calendario/departamentos/
> call-sheet/back/hotel) y `2026-08-24-call-packages`/`-file-deliveries` del bloque Llamado/Distribución, no numerados
> aquí; en un deploy fresco TODOS entran solos por migración + chain (esta tabla es referencia para parchar BD poblada).

> Los deltas **#38/#39 no son de esquema**: en una BD NUEVA (camino A) se OMITEN; sólo importan al
> entregar una instancia que arrastró datos demo. Se numeran al final del bloque `07-24` por completitud.

### Dependencias (aristas *requiere →*)

Regla: un `ALTER`/`SEED` requiere el `CREATE` de su tabla; un pivote con FK requiere sus dos padres.

- **#5, #7, #11** (ALTER `scouting_reports`) → **#1** (la crea).
- **#10** (ancla `AFTER risk_level`; `scouting_reports.sb132_details`) → **#1**.
- **#14** (ALTER `action_items`) → **#10** (lo crea).
- **#15, #17, #18** (ALTER `consumables`) → **#14** (lo crea).
- **#16** (`consumable_sfx_effect_type` FK→`consumables`) → **#14** (si no, errno 150).
- **#20** (numeración por brecha) → **#19**.
- **#21** (`effect_standard` FK→`sfx_effect_types`) → **#16**.
- **#22, #28** (ALTER `hazard_events`) → **#13** (lo crea).
- **#34** → **#33** · **#35** → **#34** y **#4** · **#36** → **#35** (los tres con `-- ORDER` explícito en el archivo).
- **#30 → #31 → #32** (Médico 1/3 → 2/3 → 3/3, secuencia declarada).
- **#41** (los 3 puentes `tool_standard`/`check_point_standard`/`permit_standard` con FK→`safety_standards`) → `safety_standards`, que es del **baseline** (no de un delta); si por lo que sea faltara, esos 3 `CREATE` abortan con errno 150 y el resto se crea igual (se autorrepara re-ejecutando). Sin dependencia con otros deltas.
- **#53** (ALTER PAE) → **#52** (crea `emergency_action_plans`).
- **#55, #56** (evidencia/locación del acta de ambulancia) → **#57**; **#57** (crea `ambulance_verifications`) → **#54** (catálogo).
- **#59** (ALTER `tool_inspections`) → **#42** · **#60** (ALTER `issued_permits`) → **#44**.
- **CADENA payee/contrato (bloque 08-13 → 08-16):** **#73** (`payee-base`, crea `payees`/`payee_contracts`) es la BASE de todo el resto de payee/contrato — **#61, #62, #65, #67, #68, #72, #74, #75, #76, #77, #99, #100, #105** lo requieren. **#66** (`contract-envelopes`) es la base del SOBRE → **#79, #88, #89, #90, #91, #92, #95, #98, #104** lo requieren. **#86** (`contract-templates`) es la base de las PLANTILLAS → **#83, #84, #85, #88, #93, #94, #96, #97** lo requieren. **#70** → **#78** (`payment_periods`).
- **#102** (ALTER `sfx_effect_types`) → **#16**.

**Reordenado respecto al orden alfabético:** el grupo `2026-07-24`, alfabéticamente, pondría
`beta-lite-*` primero, pero **debe correr DESPUÉS de `health-record-seal-consent`** (necesita
`cmedic.intake_*`). Orden usado: gemelos → médico (1/3,2/3,3/3) → expediente (1/2,2/2) →
beta-lite (patients, refinamientos) → wrap → demo. Sin dependencia (movibles): #9, #24, #25, #26,
#29, #37, #40.

**⚠ El grupo `2026-08-13` también se REORDENA por dependencia (camino B):** por FECHA/orden
alfabético, `ambulance-provider-payee-link`, los `contract-*`, `crew-contract-fields`,
`external-*`, `infosheet-*` y `payee-document-downloads` caen ANTES de `payee-base`, pero **la
tabla base `payees`/`payee_contracts` (#73) tiene que existir primero**. Regla práctica para un
apply incremental sobre una BD que ya existe: aplicar primero las tablas base del bloque —
**#73 (payee-base) → #78 (payment-periods) → #66 (contract-envelopes) → #86 (contract-templates,
08-14)** — y sólo después sus ALTER/hijas. En **camino A (`migrate` sobre BD nueva) esto no
aplica**: las migraciones ya traen el orden correcto y este bloque owner-apply es sólo referencia.
El **#48** (`location-mapping`) está **SUPERSEDED por #50** y NO se aplica.

### ⚠️🔴 El #27 `2026-07-21-dsr-safety-meeting` PUEDE INVALIDAR SELLOS DSR YA EMITIDOS

Hace `ALTER TABLE daily_reports` y agrega DOS columnas a la tabla cuya fila se firma:

```
safety_meeting_held        TINYINT(1)   NULL DEFAULT NULL   (AFTER safety_meeting_time)
safety_meeting_photo_path  VARCHAR(255) NULL DEFAULT NULL   (AFTER safety_meeting_topics)
```

El sello SHA-256 se calcula sobre `attributesToArray()` (la fila entera). Estas columnas **NO** están
en `NULLABLE_HASH_EXCLUDES`, así que entran en el payload **aunque valgan NULL** → un DSR firmado
ANTES de aplicar el delta pasaría a mostrar **"FIRMA NO COINCIDE · POSIBLE ALTERACIÓN"** sin que nadie
lo tocara. El delta además corre un `UPDATE ... WHERE safety_meeting_topics LIKE 'No hubo…'` de una vez.

**Ventana segura: aplicarlo ANTES de que se firme cualquier DSR real en producción — es decir, en el
despliegue inicial.** En local fue inocuo porque `digital_signatures` tenía CERO filas de
`App\Models\DailyReport` al aplicarlo. Antes de producción, contar:

```sql
SELECT COUNT(*) FROM digital_signatures
 WHERE documentable_type = 'App\\Models\\DailyReport';
```

* **0** → adelante, no hay nada que invalidar.
* **> 0** → decisión del owner: **re-sellar** esos DSR, o declarar `$signatureExcludes` para las 2
  columnas (ojo: eso **también** cambia el payload). Ninguna es gratis.

**Contraste:** el **#40** (`dsr-scouting-link`) también toca `daily_reports`, pero **NO** tiene este
problema — el modelo excluye `scouting_report_id` del hash cuando es NULL (patrón *null-hash-exclude*
que el #27 no usa), así que los DSR ya sellados conservan su hash. **#11** y **#14** también agregan
columnas a `daily_reports`, pero se aplicaron junto con / antes de crear `digital_signatures` (#11),
así que ninguna firma las precede. El **#28** tampoco: toca `hazard_events`/`daily_logs`, fuera de todo hash.

---

## Seeders

Orden importante: los catálogos primero, los vínculos después.

```
php artisan db:seed --class=RolesAndPermissionsSeeder     # roles y permisos (aditivo)
php artisan db:seed --class=SafetyCatalogSeeder           # 83 normas
php artisan db:seed --class=HazardEventSeeder             # 207 eventos
php artisan db:seed --class=EnrichedCatalogSeeder         # categorías finas + vínculos N:M
php artisan db:seed --class=SpfxCatalogSeeder             # 25 tipos de efecto SFX
php artisan db:seed --class=HazardEventPpeSeeder          # EPP por familia de riesgo
php artisan db:seed --class=ToolPermitCatalogSeeder       # 73 herramientas + 15 permisos (requiere las 83 normas)
php artisan db:seed --class=VehicleCatalogSeeder          # Transportación: 13 tipos + 52 puntos (requiere #106)
php artisan db:seed --class=TransportPermissionsSeeder    # transport.manage/view (aditivo; luego cache:clear)
php artisan db:seed --class=TransportEquipmentSeeder      # Transportación Bloque 2: 12 equipamientos (requiere #109)
php artisan db:seed --class=DocumentTypeSeeder            # +4 tipos VEH_* (idempotente)
```

> `ToolPermitCatalogSeeder` **requiere que `SafetyCatalogSeeder`/`EnrichedCatalogSeeder` ya
> hayan corrido** (une las normas de los catálogos contra `safety_standards`; lo que no resuelve
> queda en `catalog_pending_standards`, sin inventar la norma). Idempotente (`updateOrCreate` por
> `code`); NO se encadena en `DatabaseSeeder` — igual que el resto de catálogos de fondo, se invoca a mano.

Todos son **aditivos**: no pisan lo que el owner haya editado a mano.

---

## Después de desplegar código

```
php artisan view:clear
php artisan route:clear      # sólo si el paso agregó rutas
php artisan config:clear     # si cambió algo de config/ (p.ej. la versión en config/crewcare.php)
```

---

## Deuda conocida — tablas candidatas a retiro

De las 56 tablas, **14 son legado muerto** (COVID y módulos retirados) que siguen en el esquema
y por lo tanto también en el baseline. No estorban, pero engordan cualquier instalación nueva:

```
pcr · prueba · usuariopcr · userpcr · userform · formularios · virtualqueue
testqueue · message · departamentousuario · userpuesto · usuariosnotificaciones
departamentos · puestos
```

Las dos últimas son especiales: **`departamentos` (12 filas) y `puestos` (18) son los catálogos
VIEJOS en español**, y ya existen sus reemplazos `departments` (38) y `positions` (201). Conviven.

**Se dejaron dentro del baseline a propósito.** Un baseline cuyo trabajo es levantar producción
tiene que reproducir lo que HOY funciona, no lo que creemos que debería ser; podarlo es un paso
aparte, con su propia verificación de que nada las referencia. Ver `COVID-DECOMMISSION.md`.
