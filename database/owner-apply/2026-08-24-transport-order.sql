-- ============================================================================
-- CrewCare — TRANSPORTACIÓN (Bloque 2): ORDEN DE TRANSPORTACIÓN.
-- (2026-08-24) — gemelo de create_transport_parties / _equipment / _addresses /
--                _orders / _order_runs / _run_occupants.
--
-- QUÉ ES (7 tablas, todas dentro de la instancia, referencias BLANDAS por id):
--   1. `transport_parties`         — PADRÓN LIGERO cast/agencia/cliente (se sugiere la 2a vez).
--   2. `transport_equipment`       — catálogo de equipamiento (íconos: tag, hielera, …).
--   3. `transport_addresses`       — direcciones PRIVADAS que presetea transpo (§3). El PDF
--                                     imprime `public_label` ('CASA'); la calle real la ve el
--                                     driver de la corrida y la allowlist de transpo.
--   4. `transport_address_viewers` — allowlist: qué miembros de transpo ven la dirección real.
--   5. `transport_orders`          — LA ORDEN por día. NO SE SELLA NI SE FIRMA: se CONGELA
--                                     (status=frozen + frozen_snapshot). UUID discreto en el pie.
--   6. `transport_order_runs`      — LAS CORRIDAS. Pick up con el lenguaje del motor de horarios
--                                     del llamado (offset+literal+lugar); la orden es DUEÑA de su
--                                     pick up y sólo lo COMPARA contra el back (no lo escribe).
--   7. `transport_run_occupants`   — ocupantes (crew/cast/agency/client/free) + carga + depto.
--
-- ⚠ LA ORDEN NO ENTRA AL VERIFICADOR PÚBLICO (no es un sello): no hay línea en SealVerifier.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations/casts). La "carga" del ocupante va en `load_note`.
--
-- COLACIÓN: todo utf8mb4_unicode_ci. No hay uniones por TEXTO contra tablas viejas (todo por id),
--   así que ninguna columna necesita general_ci.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7/8.0. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-24-transport-order.sql
--   Luego: php artisan db:seed --class=TransportEquipmentSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → legend/frozen_snapshot/equipment van JSON NULL.
-- ============================================================================

-- 1. PADRÓN LIGERO (cast/agencia/cliente) -------------------------------------
CREATE TABLE IF NOT EXISTS `transport_parties` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_id` BIGINT UNSIGNED NULL,                          -- CurrentProduction (FK-soft)
    `kind`          VARCHAR(20)  NOT NULL DEFAULT 'cast',          -- cast | agency | client
    `name`          VARCHAR(160) NOT NULL,
    `note`          VARCHAR(255) NULL,
    `created_by_id` BIGINT UNSIGNED NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `transport_parties_prod_kind_idx` (`production_id`,`kind`),
    KEY `transport_parties_name_idx`      (`name`),
    KEY `transport_parties_active_idx`    (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CATÁLOGO DE EQUIPAMIENTO (íconos) ----------------------------------------
CREATE TABLE IF NOT EXISTS `transport_equipment` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(40) NOT NULL,                             -- clave estable (va en runs.equipment)
    `name_es`    VARCHAR(80) NOT NULL,
    `name_en`    VARCHAR(80) NULL,
    `icon`       VARCHAR(40) NULL,                                 -- clave de ícono svg/heroicon
    `sort_order` INT         NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP   NULL DEFAULT NULL,
    `updated_at` TIMESTAMP   NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `transport_equipment_code_unique` (`code`),
    KEY `transport_equipment_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DIRECCIONES PRIVADAS (transport-owned) -----------------------------------
CREATE TABLE IF NOT EXISTS `transport_addresses` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_id` BIGINT UNSIGNED NULL,
    `label`         VARCHAR(120) NOT NULL,                         -- rótulo interno ("Casa de …")
    `address`       VARCHAR(255) NULL,                             -- calle real PRIVADA (el PDF NO la imprime)
    `public_label`  VARCHAR(60)  NULL,                             -- lo que ve todo el mundo; NULL → 'CASA' al render
    `is_private`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by_id` BIGINT UNSIGNED NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `transport_addresses_prod_idx`   (`production_id`),
    KEY `transport_addresses_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ALLOWLIST de visibilidad de la dirección real (señalamiento simple) -------
CREATE TABLE IF NOT EXISTS `transport_address_viewers` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transport_address_id` BIGINT UNSIGNED NOT NULL,
    `user_id`              BIGINT UNSIGNED NOT NULL,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `transport_addr_viewer_unique`   (`transport_address_id`,`user_id`),
    KEY          `transport_addr_viewer_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. LA ORDEN (por día, versionada, CONGELADA — NO sellada) --------------------
CREATE TABLE IF NOT EXISTS `transport_orders` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)     NULL,                            -- UUID discreto en el pie (no sello)
    `production_id`   BIGINT UNSIGNED NULL,
    `order_date`      DATE         NOT NULL,
    `version`         INT          NOT NULL DEFAULT 1,             -- por producción+fecha
    `status`          VARCHAR(20)  NOT NULL DEFAULT 'draft',       -- draft | frozen
    `notes_general`   TEXT         NULL,                           -- notas generales (hitos sin vehículo)
    `legend`          JSON         NULL,                           -- leyenda de claves usadas
    `prev_version_id` BIGINT UNSIGNED NULL,                        -- versión inmediata anterior (para §4 diff)
    `frozen_at`       DATETIME     NULL,
    `frozen_by_id`    BIGINT UNSIGNED NULL,
    `frozen_snapshot` JSON         NULL,                           -- documento congelado al emitir
    `created_by_id`   BIGINT UNSIGNED NULL,
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `transport_orders_uuid_unique`        (`uuid`),
    KEY          `transport_orders_prod_date_ver_idx` (`production_id`,`order_date`,`version`),
    KEY          `transport_orders_status_idx`        (`status`),
    KEY          `transport_orders_active_idx`        (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. LAS CORRIDAS -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transport_order_runs` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transport_order_id`    BIGINT UNSIGNED NOT NULL,
    `sort_order`            INT          NOT NULL DEFAULT 0,
    `run_type`              VARCHAR(20)  NOT NULL DEFAULT 'normal', -- normal | aeropuerto | aplicacion
    `vehicle_id`            BIGINT UNSIGNED NULL,                   -- vehicles (FK-soft); NULL sólo si aplicacion
    `driver_user_id`        BIGINT UNSIGNED NULL,                   -- users (FK-soft)
    `pickup_offset_minutes` INT          NULL,                      -- OFFSET desde general_call (motor de horarios)
    `pickup_literal`        VARCHAR(16)  NULL,
    `pickup_place_kind`     VARCHAR(12)  NULL,                      -- call | private | text
    `pickup_place_id`       BIGINT UNSIGNED NULL,                   -- call_places (call) | transport_addresses (private)
    `pickup_place_text`     VARCHAR(255) NULL,
    `dest_place_kind`       VARCHAR(12)  NULL,
    `dest_place_id`         BIGINT UNSIGNED NULL,
    `dest_text`             VARCHAR(255) NULL,
    `equipment`             JSON         NULL,                      -- ["tag","hielera",…] códigos de transport_equipment
    `notes`                 TEXT         NULL,
    `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`            TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `transport_runs_order_idx`   (`transport_order_id`),
    KEY `transport_runs_vehicle_idx` (`vehicle_id`),
    KEY `transport_runs_driver_idx`  (`driver_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. OCUPANTES ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transport_run_occupants` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transport_order_run_id` BIGINT UNSIGNED NOT NULL,
    `sort_order`             INT          NOT NULL DEFAULT 0,
    `source`                 VARCHAR(12)  NOT NULL DEFAULT 'crew',  -- crew | cast | agency | client | free
    `user_id`                BIGINT UNSIGNED NULL,                  -- users (source=crew)
    `party_id`               BIGINT UNSIGNED NULL,                  -- transport_parties (cast/agency/client)
    `name_snapshot`          VARCHAR(160) NULL,                     -- nombre resuelto (obligatorio para free)
    `department_id`          BIGINT UNSIGNED NULL,                  -- puntero a departamento
    `load_note`              VARCHAR(120) NULL,                     -- "carga" (descriptor libre; NO 'load')
    `created_at`             TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `transport_occupants_run_idx`   (`transport_order_run_id`),
    KEY `transport_occupants_user_idx`  (`user_id`),
    KEY `transport_occupants_party_idx` (`party_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
