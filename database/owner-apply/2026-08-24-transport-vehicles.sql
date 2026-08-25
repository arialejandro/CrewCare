-- ============================================================================
-- CrewCare — TRANSPORTACIÓN (Bloque 1): ENTIDAD Vehículo + ACTA sellada + PUENTE.
-- (2026-08-24) — gemelo de create_vehicles / create_vehicle_inspections /
--                add_vehicle_id_to_payee_contracts.
--
-- QUÉ ES:
--   1. `vehicles`             — la ENTIDAD reutilizable dentro de la instancia. Promueve
--                                `payee_contracts.asset_ref`. Driver = CREW (users); propietario
--                                proveedor = payees; particular = users/nombre suelto. SIN
--                                entidades paralelas.
--   2. `vehicle_inspections` — el ACTA sellada: veredicto GRADUADO (verdict apto/no_apto cara
--                                pública; level alto_riesgo..excelente INTERNO), fail-safe, sello
--                                HMAC-SHA256, verificador público 'veh'. Reevaluación encadenada.
--   3. `payee_contracts.vehicle_id` — columna PUENTE (ALTER idempotente) del contrato al vehículo.
--
-- ── EL ACTA CONGELA Y SE SELLA ───────────────────────────────────────────────
--   Snapshots congelados + veredicto DERIVADO (fail-safe: punto aplicable sin contestar
--   nunca da favorable). Columnas de ESTADO (is_active + retiro) HASH-EXCLUIDAS: retirar
--   no invalida el sello. TODO lo demás es contenido y entra al hash.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). Por eso los atributos resueltos van en
--   `attr_values` (NO `attributes`, que ES la prop interna de Eloquent);
--   `checklist_snapshot`/`attributes_snapshot` son seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7/8.0. Idempotente:
--   CREATE TABLE IF NOT EXISTS + ALTER guardado por information_schema.
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-24-transport-vehicles.sql
--   Luego: php artisan db:seed --class=TransportPermissionsSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → attributes/*_snapshot van JSON NULL.
-- Referencias BLANDAS (sin FK dura) a users/payees/vehicle_types/vehicles.
-- ============================================================================

-- 1. ENTIDAD VEHÍCULO ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vehicle_type_id` BIGINT UNSIGNED NULL,                 -- vehicle_types (FK-soft)
    `type_code`       VARCHAR(30)  NULL,                    -- snapshot
    `make`            VARCHAR(120) NULL,                    -- marca
    `model`           VARCHAR(120) NULL,                    -- modelo
    `year`            INT          NULL,
    `color`           VARCHAR(60)  NULL,
    `plate`           VARCHAR(40)  NULL,                    -- placa
    `vin`             VARCHAR(60)  NULL,
    `attr_values`     JSON         NULL,                    -- perfil del tipo YA RESUELTO y ajustado por unidad (NO 'attributes': prop de Eloquent)
    `owner_kind`      VARCHAR(20)  NULL,                    -- provider | person | other
    `owner_payee_id`  BIGINT UNSIGNED NULL,                 -- proveedor propietario (payees, FK-soft)
    `owner_user_id`   BIGINT UNSIGNED NULL,                 -- propietario particular en el sistema (users, FK-soft)
    `owner_name`      VARCHAR(255) NULL,                    -- propietario particular fuera del sistema (nombre suelto)
    `driver_user_id`  BIGINT UNSIGNED NULL,                 -- DRIVER = CREW (users, FK-soft), bidireccional
    `initial_km`      INT          NULL,                    -- km a la primera inspección
    `notes`           TEXT         NULL,
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by_id`   BIGINT UNSIGNED NULL,
    `created_at`      TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `vehicles_type_idx`        (`vehicle_type_id`),
    KEY `vehicles_owner_payee_idx` (`owner_payee_id`),
    KEY `vehicles_driver_idx`      (`driver_user_id`),
    KEY `vehicles_plate_idx`       (`plate`),
    KEY `vehicles_active_idx`      (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ACTA SELLADA — veredicto graduado, fail-safe, verificador 'veh'. ----------
CREATE TABLE IF NOT EXISTS `vehicle_inspections` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                 CHAR(36)     NULL,               -- verificador público (GeneratesUuidKey)
    `production_id`        BIGINT UNSIGNED NULL,            -- CurrentProduction (FK-soft)
    `shoot_day`            INT          NULL,               -- ProductionCalendar (VA EN EL HASH)
    `vehicle_id`           BIGINT UNSIGNED NULL,            -- vehicles (FK-soft)

    -- Tipo + unidad física (snapshot congelado) -------------------------------
    `vehicle_type_id`      BIGINT UNSIGNED NULL,
    `type_code`            VARCHAR(30)  NULL,
    `type_name`            VARCHAR(255) NULL,
    `make`                 VARCHAR(120) NULL,
    `model`                VARCHAR(120) NULL,
    `year`                 INT          NULL,
    `color`                VARCHAR(60)  NULL,
    `plate`                VARCHAR(40)  NULL,
    `vin`                  VARCHAR(60)  NULL,
    `attributes_snapshot`  JSON         NULL,               -- atributos resueltos usados para derivar los puntos

    -- Propietario + driver (snapshot) -----------------------------------------
    `owner_kind`           VARCHAR(20)  NULL,
    `owner_name`           VARCHAR(255) NULL,               -- proveedor o dueño
    `driver_user_id`       BIGINT UNSIGNED NULL,
    `driver_name`          VARCHAR(255) NULL,
    `km`                   INT          NULL,               -- se captura en la 1a inspección

    `unit_photo_path`      VARCHAR(500) NULL,

    -- Ejecución del checklist -------------------------------------------------
    `checklist_snapshot`   JSON         NULL,               -- [{code,grupo,module,text,class,answer,reparado,photo_path}]

    -- Veredicto (derivado del dato, no capturado; fail-safe) ------------------
    `verdict`              VARCHAR(20)  NOT NULL,           -- apto | no_apto (CARA PÚBLICA)
    `level`                VARCHAR(20)  NULL,               -- alto_riesgo|pobre|normal|bien|excelente (INTERNO)
    `n_critical`           INT          NOT NULL DEFAULT 0,
    `n_major`              INT          NOT NULL DEFAULT 0,
    `n_minor`              INT          NOT NULL DEFAULT 0,
    `observations`         TEXT         NULL,               -- resumen y recomendaciones (opcional)

    -- Reevaluación encadenada -------------------------------------------------
    `is_reevaluation`      TINYINT(1)   NOT NULL DEFAULT 0,
    `origin_inspection_id` BIGINT UNSIGNED NULL,            -- acta que originó la reevaluación

    -- Quien levanta (frozen) --------------------------------------------------
    `inspector_user_id`    BIGINT UNSIGNED NULL,
    `inspector_name`       VARCHAR(255) NULL,
    `inspector_role`       VARCHAR(100) NULL,
    `inspector_cedula`     VARCHAR(60)  NULL,
    `created_by_id`        BIGINT UNSIGNED NULL,            -- autor (= inspector); eje de "solo quien la levantó"

    -- ESTADO posterior al sello — HASH-EXCLUIDO (retirar no invalida) ---------
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `retired_at`           DATETIME     NULL,
    `retired_by_id`        BIGINT UNSIGNED NULL,
    `retired_reason`       VARCHAR(255) NULL,
    `superseded_by_id`     BIGINT UNSIGNED NULL,

    `created_at`           TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vehicle_inspections_uuid_unique` (`uuid`),
    KEY `veh_insp_vehicle_idx`    (`vehicle_id`),
    KEY `veh_insp_production_idx` (`production_id`, `shoot_day`),
    KEY `veh_insp_verdict_idx`    (`verdict`),
    KEY `veh_insp_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PUENTE del contrato al vehículo — ALTER idempotente (no destructivo). -----
DROP PROCEDURE IF EXISTS `cc_add_payee_contract_vehicle_id`;
DELIMITER //
CREATE PROCEDURE `cc_add_payee_contract_vehicle_id`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payee_contracts'
          AND COLUMN_NAME = 'vehicle_id'
    ) THEN
        ALTER TABLE `payee_contracts`
            ADD COLUMN `vehicle_id` BIGINT UNSIGNED NULL AFTER `asset_ref`,
            ADD KEY `payee_contracts_vehicle_idx` (`vehicle_id`);
    END IF;
END //
DELIMITER ;
CALL `cc_add_payee_contract_vehicle_id`();
DROP PROCEDURE IF EXISTS `cc_add_payee_contract_vehicle_id`;
