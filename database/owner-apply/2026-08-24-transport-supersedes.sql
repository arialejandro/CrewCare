-- ============================================================================
-- CrewCare — TRANSPORTACIÓN (Bloque 2 §0): SUSTITUCIÓN de puntos de vehículo.
-- (2026-08-24) — gemelo de add_supersedes_to_vehicle_check_points.
--
-- QUÉ ES:
--   Columna `vehicle_check_points.supersedes` (ALTER idempotente) — MISMO mecanismo que el
--   catálogo de herramientas (`tool_check_points.supersedes`, JSON `sustituye`). Un punto
--   aplicable que sustituye a otro DESPLAZA al sustituido cuando ambos aplican.
--
--   Efecto: REM-005 (calzas del remolcado, critical) SUSTITUYE a CAR-004 (cuñas de la caja,
--   minor) en un vehículo `has_cargo_box` E `is_towed` (camper de vestuario). Pickup con caja
--   sin remolque → sigue CAR-004; remolcado sin caja → sigue REM-005.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7/8.0. Idempotente (guardado por
--   information_schema):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-24-transport-supersedes.sql
--   Luego: php artisan db:seed --class=VehicleCatalogSeeder --force  (fija REM-005.supersedes=CAR-004)
--          php artisan cache:clear
-- Las actas ya selladas NO cambian (usan su `checklist_snapshot` congelado). Cero re-sello.
-- ============================================================================

DROP PROCEDURE IF EXISTS `cc_add_vehicle_check_points_supersedes`;
DELIMITER //
CREATE PROCEDURE `cc_add_vehicle_check_points_supersedes`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vehicle_check_points'
          AND COLUMN_NAME = 'supersedes'
    ) THEN
        ALTER TABLE `vehicle_check_points`
            ADD COLUMN `supersedes` VARCHAR(30) COLLATE utf8mb4_unicode_ci NULL AFTER `applies_when`;
    END IF;
END //
DELIMITER ;
CALL `cc_add_vehicle_check_points_supersedes`();
DROP PROCEDURE IF EXISTS `cc_add_vehicle_check_points_supersedes`;
