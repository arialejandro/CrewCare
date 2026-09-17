-- ============================================================================
-- CrewCare — CATÁLOGO ORGANIZACIONAL · Fusión semilla+vivo, ESQUEMA (delta #114).
-- Gemelo de 2026_08_27_000001_catalog_fusion_schema.
--
-- ADITIVO. NO trunca, NO renumera: los 202 puestos vivos conservan su id (production_user,
-- contratos y rutas de firma apuntan ahí). Solo agrega columnas declaradas por la semilla y
-- dos tablas hijas (alias es/en + alias ambiguos con su regla). Nada se sella.
-- Idempotente (information_schema / IF NOT EXISTS). MySQL 5.7/8.0.
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-27-catalog-fusion-schema.sql
--
-- NOTA: la SIEMBRA de las 255 filas fusionadas va por seeder aparte (CatalogFusionSeeder),
-- no aquí. Este archivo solo abre el esquema.
-- ============================================================================
SET NAMES utf8mb4;

-- ── positions: columnas declaradas por la semilla ───────────────────────────
-- `rank` es palabra reservada en MySQL 8.0 → siempre entre backticks.
DROP PROCEDURE IF EXISTS `cc_catalog_positions`;
DELIMITER //
CREATE PROCEDURE `cc_catalog_positions`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='catalog_key') THEN
        ALTER TABLE `positions` ADD COLUMN `catalog_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL AFTER `name_en`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='rank') THEN
        ALTER TABLE `positions` ADD COLUMN `rank` SMALLINT NOT NULL DEFAULT 60 AFTER `catalog_key`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='binding') THEN
        ALTER TABLE `positions` ADD COLUMN `binding` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit' AFTER `rank`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='hod_capable') THEN
        ALTER TABLE `positions` ADD COLUMN `hod_capable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `binding`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='grade') THEN
        ALTER TABLE `positions` ADD COLUMN `grade` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL AFTER `hod_capable`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND COLUMN_NAME='existence') THEN
        ALTER TABLE `positions` ADD COLUMN `existence` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core' AFTER `grade`;
    END IF;
    -- índice de apoyo para el typeahead / agrupación por clave (no único: varias filas vivas
    -- comparten catalog_key, p.ej. direction.set_pa → Cast PA / Key Set PA / Set PA).
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='positions' AND INDEX_NAME='positions_catalog_key_idx') THEN
        ALTER TABLE `positions` ADD KEY `positions_catalog_key_idx` (`catalog_key`);
    END IF;
END //
DELIMITER ;
CALL `cc_catalog_positions`();
DROP PROCEDURE IF EXISTS `cc_catalog_positions`;

-- ── departments: clave de catálogo, existencia, hint de cuenta ───────────────
DROP PROCEDURE IF EXISTS `cc_catalog_departments`;
DELIMITER //
CREATE PROCEDURE `cc_catalog_departments`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='departments' AND COLUMN_NAME='catalog_key') THEN
        ALTER TABLE `departments` ADD COLUMN `catalog_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NULL AFTER `name_en`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='departments' AND COLUMN_NAME='existence') THEN
        ALTER TABLE `departments` ADD COLUMN `existence` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core' AFTER `catalog_key`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='departments' AND COLUMN_NAME='account_hint') THEN
        ALTER TABLE `departments` ADD COLUMN `account_hint` VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL AFTER `existence`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='departments' AND INDEX_NAME='departments_catalog_key_idx') THEN
        ALTER TABLE `departments` ADD KEY `departments_catalog_key_idx` (`catalog_key`);
    END IF;
END //
DELIMITER ;
CALL `cc_catalog_departments`();
DROP PROCEDURE IF EXISTS `cc_catalog_departments`;

-- ── Alias es/en para puestos y departamentos (tabla hija) ────────────────────
CREATE TABLE IF NOT EXISTS `catalog_aliases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,   -- 'position' | 'department'
  `entity_id` bigint(20) unsigned NOT NULL,
  `lang` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,           -- 'es' | 'en'
  `alias` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalog_aliases_unique` (`entity_type`,`entity_id`,`lang`,`alias`(100)),
  KEY `catalog_aliases_lookup_idx` (`alias`(100)),
  KEY `catalog_aliases_entity_idx` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Alias ambiguos con su regla de resolución ────────────────────────────────
-- "coordinador" nunca resuelve solo (exige depto en la misma celda); "supervisor" es prefijo de
-- grado, no de función. Si no hay contexto, NO resolver: mandar a revisión.
CREATE TABLE IF NOT EXISTS `catalog_ambiguous_aliases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,          -- 'require_department' | 'grade_prefix'
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalog_ambiguous_alias_unique` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación:
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
--     AND ((TABLE_NAME='positions'    AND COLUMN_NAME IN ('catalog_key','rank','binding','hod_capable','grade','existence'))
--       OR (TABLE_NAME='departments' AND COLUMN_NAME IN ('catalog_key','existence','account_hint')));  -- espera 9
--   SHOW TABLES LIKE 'catalog\_%';   -- espera catalog_aliases + catalog_ambiguous_aliases
