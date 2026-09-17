-- ============================================================================
-- CrewCare — QUIEN COBRA · CIERRE PASO 1 (régimen fiscal) + PASO 2 (paquetes).
-- (2026-08-13) — delta. Sigue de 2026-08-13-payee-base.sql.
--
-- CIERRE PASO 1 · RÉGIMEN FISCAL = VARIOS:
--   `payee_fiscal_regimes`  — tabla HIJA de la identidad. El régimen lo decide el SAT
--                             y en la CSF puede venir MÁS DE UNO (p.ej. sueldos y salarios
--                             + arrendamiento para el crew que además renta su equipo).
--                             Guardar uno solo sería recortar un documento que ya tenemos.
--   `payee_contracts.fiscal_regime_id` — el CONTRATO apunta a CUÁL régimen le aplica
--                             (la identidad tiene N; el contrato, uno). Es la primera
--                             pregunta de contabilidad: bajo cuál régimen se factura esto.
--
-- PASO 2 · PAQUETES CONFIGURABLES POR PRODUCCIÓN (nada en código):
--   `document_requirements`       — QUIÉN PIDE QUÉ: por producción, qué document_type se
--                                   exige y a qué naturaleza (física/moral/ambas). Editable
--                                   (toggle is_required). El eje antes/después SE DERIVA del
--                                   tipo (repse_phase), no se duplica.
--   `production_document_settings`— escalares por producción: FECHA DE CORTE de la 32-D
--                                   (csf_cut_day). Unas la toman el 1, otras el 17 — es
--                                   CONFIGURACIÓN, no se decide aquí.
--   `document_types.aliases`      — sinónimos para buscar ("Opinión SAT" = 32-D).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente.
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-payee-packages.sql
--   Luego: php artisan db:seed --class=DocumentTypeSeeder        (aliases)
--          php artisan db:seed --class=DocumentRequirementSeeder (paquete + settings del demo)
--          php artisan cache:clear
-- ============================================================================

-- CIERRE PASO 1 · REGÍMENES FISCALES (N por identidad). -----------------------
CREATE TABLE IF NOT EXISTS `payee_fiscal_regimes` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payee_id`    BIGINT UNSIGNED NOT NULL,          -- FK-soft a payees
    `code`        VARCHAR(10)  NULL,                 -- clave SAT (601/605/606/612...) tal como viene
    `name`        VARCHAR(160) NOT NULL,             -- nombre del régimen (como en la CSF)
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payee_fiscal_regimes_payee_idx` (`payee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El CONTRATO apunta a UNO de los regímenes de su identidad. -------------------
DROP PROCEDURE IF EXISTS crewcare_contract_fiscal_regime_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_contract_fiscal_regime_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payee_contracts' AND COLUMN_NAME='fiscal_regime_id') THEN
        ALTER TABLE `payee_contracts`
            ADD COLUMN `fiscal_regime_id` BIGINT UNSIGNED NULL AFTER `payee_id`,
            ADD KEY `payee_contracts_regime_idx` (`fiscal_regime_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_contract_fiscal_regime_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_contract_fiscal_regime_2026_08_13;

-- PASO 2 · REQUISITOS por producción (el paquete editable). --------------------
CREATE TABLE IF NOT EXISTS `document_requirements` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_id`    BIGINT UNSIGNED NOT NULL,     -- FK-soft a productions
    `document_type_id` BIGINT UNSIGNED NOT NULL,     -- FK-soft a document_types
    `applies_to`       VARCHAR(20)  NOT NULL DEFAULT 'ambas',  -- fisica | moral | ambas
    `is_required`      TINYINT(1)   NOT NULL DEFAULT 1,        -- toggle por producción
    `sort_order`       INT          NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `document_requirements_prod_type_uq` (`production_id`, `document_type_id`),
    KEY `document_requirements_prod_idx` (`production_id`),
    KEY `document_requirements_type_idx` (`document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PASO 2 · SETTINGS por producción (fecha de corte de la 32-D, etc.). ----------
CREATE TABLE IF NOT EXISTS `production_document_settings` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_id` BIGINT UNSIGNED NOT NULL,        -- FK-soft a productions
    `csf_cut_day`   TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- día de corte de la 32-D/CSF (1..28); NO se decide aquí
    `created_at`    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `production_document_settings_prod_uq` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PASO 2 · sinónimos de búsqueda en el catálogo. ------------------------------
DROP PROCEDURE IF EXISTS crewcare_doctype_aliases_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_doctype_aliases_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_types' AND COLUMN_NAME='aliases') THEN
        ALTER TABLE `document_types`
            ADD COLUMN `aliases` VARCHAR(255) NULL AFTER `name`;  -- sinónimos, coma-separados ("Opinión SAT")
    END IF;
END //
DELIMITER ;
CALL crewcare_doctype_aliases_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_doctype_aliases_2026_08_13;

-- REVERSIÓN (manual):
--   DROP TABLE `document_requirements`; DROP TABLE `production_document_settings`;
--   DROP TABLE `payee_fiscal_regimes`;
--   ALTER TABLE `payee_contracts` DROP COLUMN `fiscal_regime_id`;
--   ALTER TABLE `document_types` DROP COLUMN `aliases`;
