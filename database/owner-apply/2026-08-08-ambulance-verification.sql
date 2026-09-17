-- ============================================================================
-- CrewCare — VERIFICACIÓN DE AMBULANCIAS: recurso del día, proveedor, padrón,
--            documentos externos y ACTA sellada.
-- (2026-08-08) — delta #52. Sigue del #51 (catálogo de tipos/puntos NOM-034).
--
-- QUÉ ES: las 5 tablas del bloque "Verificación de ambulancias". Reusan los
--   motores ya probados: el acta SE SELLA con HasDigitalSignatures y es verificable
--   por QR (como tool_inspections/issued_permits); los documentos del proveedor se
--   validan MANUALMENTE registrando "quién validó" (como medic_credentials).
--
--   1. `ambulance_providers`      — la EMPRESA prestadora (se califica 1 vez).
--   2. `ambulance_crew`           — el PADRÓN de tripulantes (alta en el momento).
--   3. `external_authorizations`  — los DOCUMENTOS (empresa o persona), validados
--                                    a mano; polimórficos al titular (holder_*).
--   4. `ambulance_day_resources`  — el RECURSO DE TRASLADO DEL DÍA (3 estados).
--   5. `ambulance_inspections`    — el ACTA sellada de verificación en sitio.
--
-- ── LA REALIDAD QUE MANDA ────────────────────────────────────────────────────
--   No siempre hay ambulancia fija: llega solo para escenas específicas. Sin ella
--   se traslada en vehículo de producción (no grave) o se llama por teléfono
--   (grave). La AUSENCIA es el caso COMÚN, no una excepción → `ambulance_day_
--   resources.state` tiene 3 valores y solo uno lleva badge. La verificación se ata
--   a LA PRIMERA VEZ QUE SE NECESITA (no a una fase): si ya ocurrió, el día la lee.
--
-- ── VALIDACIÓN = DECLARACIÓN MANUAL (no verificación automática) ──────────────
--   `external_authorizations` calca el sello manual de medic_credentials:
--   `validated_at` NULL = PENDIENTE; con fecha + `validated_by_id` = validado por
--   esa persona. `validation_method` distingue 'documents_reviewed' (hoy siempre:
--   alguien vio el papel) de 'registry_checked' (futuro: consulta real). NO hay QR
--   ni consulta a registro por ahora. `validated_snapshot` guarda la atestación
--   (attested/rol/ip), igual que la cédula. El que captura ≠ el que valida.
--
-- ── EL ACTA CONGELA Y SE SELLA (fail-safe: compuerta caída jamás favorable) ───
--   `ambulance_inspections` es hermana de tool_inspections: `checklist_snapshot`
--   (puntos ejecutados congelados), veredicto DERIVADO del dato, sello SHA-256
--   sobre columnas propias. Las columnas de ESTADO (is_active + retiro) van
--   HASH-EXCLUIDAS (retirar no invalida el sello); TODO lo demás es contenido y
--   entra al hash. Verificador público nuevo tipo 'ambu' (1 línea en SealVerifier).
--   ENCUADRE: es CONSTANCIA DE VERIFICACIÓN DE RECURSO DE EMERGENCIA EN SITIO, NO
--   inspección sanitaria (eso lo hace la autoridad).
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). `checklist_snapshot`/`crew_snapshot`/
--   `validated_snapshot`/`notes`/`state` son seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-08-ambulance-verification.sql
--   Luego: php artisan db:seed --class=AmbulancePermissionsSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → los *_snapshot van JSON NULL.
-- Referencias BLANDAS (sin FK dura) a users/productions/ambulance_types: son
--   documentos/registros históricos que sobreviven cambios de catálogo o bajas.
-- ============================================================================

-- 1. EMPRESA prestadora — se califica LA PRIMERA VEZ que se necesita. ----------
CREATE TABLE IF NOT EXISTS `ambulance_providers` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(255) NOT NULL,          -- razón social / nombre comercial
    `rfc`                VARCHAR(20)  NULL,
    `contact_phone`      VARCHAR(50)  NULL,
    `sanitary_manager`   VARCHAR(255) NULL,              -- responsable sanitario declarado
    `notes`              TEXT         NULL,
    `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by_id`      BIGINT UNSIGNED NULL,           -- FK-soft a users
    `created_at`         TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ambulance_providers_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PADRÓN de tripulantes — roster no estable; alta EN EL MOMENTO con foto. ----
CREATE TABLE IF NOT EXISTS `ambulance_crew` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_id`        BIGINT UNSIGNED NULL,           -- FK-soft a ambulance_providers
    `full_name`          VARCHAR(255) NOT NULL,
    `crew_role`          VARCHAR(60)  NULL,              -- TAMP | médico | operador | ...
    `id_photo_path`      VARCHAR(500) NULL,              -- foto de credencial (alta en el momento)
    `notes`              TEXT         NULL,
    `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by_id`      BIGINT UNSIGNED NULL,
    `created_at`         TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ambulance_crew_provider_idx` (`provider_id`),
    KEY `ambulance_crew_active_idx`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DOCUMENTOS externos — validación MANUAL con "quién validó" (calca cédula). --
--    Polimórfico al titular: empresa (ambulance_providers) o persona (ambulance_crew).
CREATE TABLE IF NOT EXISTS `external_authorizations` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Titular polimórfico (holder_type = FQCN, holder_id = su PK). FK-soft. --------
    `holder_type`        VARCHAR(191) NOT NULL,          -- App\Models\AmbulanceProvider | AmbulanceCrew
    `holder_id`          BIGINT UNSIGNED NOT NULL,
    `level`              VARCHAR(20)  NOT NULL DEFAULT 'empresa',  -- empresa | persona (rótulo)

    -- Qué documento -----------------------------------------------------------
    `document_type`      VARCHAR(120) NOT NULL,          -- aviso de funcionamiento | dictamen | póliza | TAMP | CONOCER | cédula ...
    `authority`          VARCHAR(160) NULL,              -- autoridad emisora (sanitaria estatal, CONOCER, SEP...)
    `folio`              VARCHAR(160) NULL,
    `valid_until`        DATE         NULL,              -- vigencia (la empresa caduca por fecha)
    `photo_path`         VARCHAR(500) NULL,              -- foto del documento/credencial

    -- Origen del requisito: DATO, no categoría en código ----------------------
    `origen`             VARCHAR(20)  NOT NULL DEFAULT 'normativo',  -- normativo | contractual | recomendado
    `exigido_por`        VARCHAR(160) NULL,              -- quién lo exige (estudio/plataforma/marca/agencia)
    `is_gate`            TINYINT(1)   NOT NULL DEFAULT 0,-- COMPUERTA (detiene) vs CONDICIONANTE (registra el hueco)

    -- "En trámite" SIN folio ni fecha compromiso = "no lo tiene" --------------
    `status`             VARCHAR(20)  NOT NULL DEFAULT 'presentado',  -- presentado | en_tramite | no_aplica
    `pending_commit_date` DATE        NULL,              -- fecha compromiso si en_tramite (sin ella, no es en trámite)

    -- CONOCER (estándar de competencia): clave+nombre IMPRESOS, no quemados ---
    `standard_code`      VARCHAR(60)  NULL,              -- clave del estándar (EC####) tal como viene
    `standard_name`      VARCHAR(255) NULL,              -- nombre oficial tal como viene (se imprime)

    -- VALIDACIÓN MANUAL (calca medic_credentials): "quién validó" -------------
    `validation_method`  VARCHAR(20)  NULL,              -- documents_reviewed (hoy) | registry_checked (futuro)
    `validated_at`       TIMESTAMP    NULL DEFAULT NULL, -- NULL = PENDIENTE
    `validated_by_id`    BIGINT UNSIGNED NULL,           -- FK-soft a users
    `validated_snapshot` JSON         NULL,              -- atestación: {attested, validated_by, attested_role, attested_ip, checked_at}

    `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by_id`      BIGINT UNSIGNED NULL,
    `created_at`         TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `external_auth_holder_idx`    (`holder_type`, `holder_id`),
    KEY `external_auth_validated_idx` (`validated_at`),
    KEY `external_auth_validator_idx` (`validated_by_id`),
    KEY `external_auth_active_idx`    (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. RECURSO DE TRASLADO DEL DÍA — 3 estados; solo el 1 lleva badge. -----------
CREATE TABLE IF NOT EXISTS `ambulance_day_resources` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_id`      BIGINT UNSIGNED NULL,           -- CurrentProduction (FK-soft)
    `shoot_day`          INT          NULL,              -- ProductionCalendar::shootDayFor
    `resource_date`      DATE         NULL,              -- fecha real (display)

    -- El estado del día (elegir el 2 NO es falla; el 3 es hueco visible) -------
    `state`              VARCHAR(30)  NOT NULL DEFAULT 'none',  -- ambulance_on_site | declared_medium | none

    -- Estado 1: referencia al proveedor y (si existe) al acta que da el badge --
    `provider_id`        BIGINT UNSIGNED NULL,           -- FK-soft a ambulance_providers
    `ambulance_inspection_id` BIGINT UNSIGNED NULL,      -- FK-soft al acta vigente (da el badge)

    -- Estado 2: medio declarado — a quién se llama y en cuánto llega ----------
    `transport_means`    VARCHAR(200) NULL,              -- p. ej. "vehículo de producción para lo no grave"
    `call_service`       VARCHAR(200) NULL,              -- servicio al que se llama para lo grave
    `call_phone`         VARCHAR(50)  NULL,
    `response_time`      VARCHAR(80)  NULL,              -- tiempo de respuesta DECLARADO (no es el tiempo al hospital)

    `notes`              TEXT         NULL,
    `declared_by_id`     BIGINT UNSIGNED NULL,           -- FK-soft a users (quién fijó el criterio ANTES)
    `declared_by_name`   VARCHAR(255) NULL,              -- snapshot
    `declared_at`        DATETIME     NULL,
    `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_amb_day_prod_day` (`production_id`, `shoot_day`),  -- un recurso por producción+día
    KEY `amb_day_state_idx`    (`state`),
    KEY `amb_day_provider_idx` (`provider_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ACTA SELLADA — verificación en sitio (espejo de tool_inspections). --------
CREATE TABLE IF NOT EXISTS `ambulance_inspections` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                   CHAR(36)     NULL,          -- verificador público (GeneratesUuidKey)
    `production_id`          BIGINT UNSIGNED NULL,       -- CurrentProduction (FK-soft)
    `shoot_day`             INT          NULL,          -- ProductionCalendar (VA EN EL HASH)

    -- Disparador que ACOTA esta acta (muestra solo la parte que toca) ----------
    `trigger_scope`          VARCHAR(20)  NOT NULL DEFAULT 'unidad',  -- identidad | persona | unidad | consumo | riesgo

    -- Tipo verificado (referencia viva + snapshot congelado) ------------------
    `ambulance_type_id`      BIGINT UNSIGNED NULL,       -- ambulance_types.id (FK-soft)
    `type_code`              VARCHAR(20)  NULL,          -- snapshot (AMB-0#)
    `type_name`              VARCHAR(255) NULL,          -- snapshot
    `rama`                   VARCHAR(20)  NULL,          -- snapshot (terrestre/aerea/maritima)
    `type_level`             INT          NULL,          -- snapshot (1..4)
    `capacity_level`         INT          NULL,          -- aérea/marítima: capacidad resolutiva declarada

    -- Unidad física (frozen) --------------------------------------------------
    `provider_id`            BIGINT UNSIGNED NULL,       -- FK-soft a ambulance_providers
    `provider_name`          VARCHAR(255) NULL,          -- snapshot
    `plates`                 VARCHAR(40)  NULL,          -- placas
    `economic_number`        VARCHAR(40)  NULL,          -- número económico
    `unit_photo_path`        VARCHAR(500) NULL,          -- foto real de la unidad

    -- Tripulación de hoy (congelada) ------------------------------------------
    `crew_snapshot`          JSON         NULL,          -- [{name, role, id_photo_path, doc_status}]

    -- Ejecución del checklist -------------------------------------------------
    `checklist_snapshot`     JSON         NULL,          -- puntos ejecutados congelados [{code,text,is_gate,outcome,norm,answer}]

    -- Veredicto (derivado del dato, no capturado; fail-safe) ------------------
    `verdict`                VARCHAR(30)  NOT NULL,      -- paro | actividad_no_ejecutable | apta
    `resolution_path`        VARCHAR(30)  NULL,          -- correccion_mismo_dia | reemplazo (solo en PARO)
    `observations`           TEXT         NULL,

    -- Correspondencia tipo ↔ riesgo (cuando trigger = riesgo) -----------------
    `day_risk_level`         INT          NULL,          -- nivel de riesgo del día declarado
    `correspondence_ok`      TINYINT(1)   NULL,          -- ¿el tipo alcanza para el riesgo? (NULL = no evaluado)

    -- Inspector (safety) con cédula congelada ---------------------------------
    `inspector_user_id`      BIGINT UNSIGNED NULL,       -- FK-soft a users
    `inspector_name`         VARCHAR(255) NULL,          -- snapshot
    `inspector_role`         VARCHAR(100) NULL,          -- snapshot
    `inspector_cedula`       VARCHAR(60)  NULL,          -- snapshot (medic_credentials si tiene, si no NULL)

    -- Desbloqueo del PARO (acto con autor) — VA EN EL HASH (estado resuelto) --
    `unblocked_by_id`        BIGINT UNSIGNED NULL,
    `unblocked_by_name`      VARCHAR(255) NULL,
    `unblocked_at`           DATETIME     NULL,

    -- ESTADO posterior al sello — HASH-EXCLUIDO (retirar no invalida) ---------
    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `retired_at`             DATETIME     NULL,
    `retired_by_id`          BIGINT UNSIGNED NULL,
    `retired_reason`         VARCHAR(255) NULL,
    `superseded_by_id`       BIGINT UNSIGNED NULL,

    `created_at`             TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ambulance_inspections_uuid_unique` (`uuid`),
    KEY `amb_insp_type_idx`       (`ambulance_type_id`),
    KEY `amb_insp_provider_idx`   (`provider_id`),
    KEY `amb_insp_production_idx` (`production_id`, `shoot_day`),
    KEY `amb_insp_verdict_idx`    (`verdict`),
    KEY `amb_insp_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
