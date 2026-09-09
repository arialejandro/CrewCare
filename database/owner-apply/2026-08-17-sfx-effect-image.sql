SET NAMES utf8mb4;

-- ============================================================================
-- SPFX · imagen principal por TIPO de efecto (referencia visual de la card).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   PROCEDURE con guard information_schema (no hay ADD COLUMN IF NOT EXISTS en 5.7).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-17-sfx-effect-image.sql
--
-- REVERSIÓN:
--   ALTER TABLE `sfx_effect_types` DROP COLUMN `image_path`;
-- ============================================================================
DROP PROCEDURE IF EXISTS crewcare_sfx_effect_image_2026_08_17;
DELIMITER //
CREATE PROCEDURE crewcare_sfx_effect_image_2026_08_17()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sfx_effect_types' AND COLUMN_NAME='image_path') THEN
        ALTER TABLE `sfx_effect_types` ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `name`;
    END IF;
END //
DELIMITER ;
CALL crewcare_sfx_effect_image_2026_08_17();
DROP PROCEDURE IF EXISTS crewcare_sfx_effect_image_2026_08_17;
