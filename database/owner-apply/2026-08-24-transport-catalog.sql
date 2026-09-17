-- ============================================================================
-- CrewCare — TRANSPORTACIÓN (Bloque 1): CATÁLOGO de tipos + puntos de verificación.
-- (2026-08-24) — gemelo de las migraciones create_vehicle_types / create_vehicle_check_points.
--
-- QUÉ ES: las 2 tablas de FONDO del módulo de verificación de vehículos.
--   1. `vehicle_types`        — el TIPO propone el perfil de atributos (attr_profile JSON);
--                                los atributos se confirman/ajustan por unidad. EDITABLE.
--   2. `vehicle_check_points` — el CATÁLOGO de puntos, con severidad GRADUADA (class:
--                                critical|major|minor — vocabulario deliberado, NO el binario
--                                is_gate de herramienta/ambulancia) y `applies_when` (expresión
--                                sobre atributos: "powertrain != electric", "seats > 8", ...).
--
-- ── SALEN DE OFICIO ──────────────────────────────────────────────────────────
--   `norm_id` NULO y `verified_at` NULL en todos los puntos: no salen de una norma
--   auditada, salen de oficio (el vehículo detenido en prep, sin nadie esperando).
--   No se inventan normas ni URLs. `requires_photo` obliga foto por punto.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7/8.0. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-24-transport-catalog.sql
--   Luego: php artisan db:seed --class=VehicleCatalogSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → `attr_profile` va JSON NULL.
-- Referencias BLANDAS (sin FK dura) a users (verified_by_id): registro histórico.
-- ============================================================================

-- 1. TIPOS de vehículo — el tipo PROPONE el perfil; los atributos CONFIRMAN. --------
CREATE TABLE IF NOT EXISTS `vehicle_types` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`           VARCHAR(30)  NOT NULL,                 -- auto | van | camion | camper_maquillaje | especial ...
    `name_es`        VARCHAR(255) NOT NULL,
    `name_en`        VARCHAR(255) NULL,
    `is_special`     TINYINT(1)   NOT NULL DEFAULT 0,       -- 'especial': sin perfil, todo a mano
    `attr_profile`   JSON         NULL,                     -- {powertrain,has_cargo_box,seats,has_lpg_or_sanitary,has_genset_or_heat_appliances,water_tank_liters,tows}
    `notes`          TEXT         NULL,
    `sort_order`     INT          NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`    TIMESTAMP    NULL DEFAULT NULL,        -- NULL = de oficio, sin auditar
    `verified_by_id` BIGINT UNSIGNED NULL,                 -- FK-soft a users
    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vehicle_types_code` (`code`),
    KEY `vehicle_types_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PUNTOS de verificación — severidad GRADUADA + applies_when sobre atributos. -----
CREATE TABLE IF NOT EXISTS `vehicle_check_points` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`           VARCHAR(20)  NOT NULL,                 -- VEH-001 | CAR-001 | ENE-001 | DOC-001 ...
    `grupo`          VARCHAR(40)  NULL,                     -- frenos | llantas | luces | motor ...
    `module`         VARCHAR(20)  NOT NULL DEFAULT 'nucleo',-- nucleo|carga|ocupacion|habitables|energia|agua|remolque|electrica
    `text_es`        TEXT         NOT NULL,
    `text_en`        TEXT         NULL,
    `class`          VARCHAR(10)  NOT NULL DEFAULT 'minor', -- critical | major | minor (VOCABULARIO DELIBERADO)
    `requires_photo` TINYINT(1)   NOT NULL DEFAULT 0,       -- foto obligatoria del punto
    `applies_when`   VARCHAR(255) NULL,                     -- expresión sobre atributos; NULL/'' = aplica siempre
    `norm_id`        BIGINT UNSIGNED NULL,                  -- NULO: de oficio, no de una norma
    `sort_order`     INT          NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`    TIMESTAMP    NULL DEFAULT NULL,        -- NULL en todos
    `verified_by_id` BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vehicle_points_code` (`code`),
    KEY `vehicle_points_module_idx` (`module`),
    KEY `vehicle_points_class_idx`  (`class`),
    KEY `vehicle_points_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
