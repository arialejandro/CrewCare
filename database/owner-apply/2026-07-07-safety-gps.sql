-- ============================================================================
-- CrewCare — Reportes de Seguridad: Ubicación GPS (2026-07-07)
-- Agrega coordenadas + dirección detectada, TODAS OPCIONALES (NULL), a los
-- 3 reportes de seguridad:
--   * hazardnotifications (Acción Insegura / Hazard)
--   * unsafeconds         (Condición Insegura)
--   * injury_reports      (Accidente / Injury)
-- Columnas nuevas por tabla: latitude DECIMAL(10,7), longitude DECIMAL(10,7)
-- y gps_address VARCHAR(500) (dirección obtenida por reverse geocoding).
--
-- Aplicar FUERA de Laravel (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO ya es defensivo (Model $fillable / validación nullable /
-- guardado con Schema::hasColumn): las pantallas siguen funcionando aunque las
-- columnas aún no existan; al aplicar este delta, la feature se activa sola.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`. Para que RE-EJECUTARLO sea
-- SEGURO (idempotente), se guarda cada ALTER con un check en information_schema
-- mediante un procedimiento temporal (mismo objetivo que Schema::hasColumn).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_add_safety_gps;

DELIMITER //
CREATE PROCEDURE crewcare_add_safety_gps()
BEGIN
    -- ------------------------------------------------------------------
    -- hazardnotifications (Acción Insegura): después de `name_loc`.
    -- ------------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'hazardnotifications'
          AND COLUMN_NAME  = 'latitude'
    ) THEN
        ALTER TABLE `hazardnotifications`
            ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `name_loc`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'hazardnotifications'
          AND COLUMN_NAME  = 'longitude'
    ) THEN
        ALTER TABLE `hazardnotifications`
            ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'hazardnotifications'
          AND COLUMN_NAME  = 'gps_address'
    ) THEN
        ALTER TABLE `hazardnotifications`
            ADD COLUMN `gps_address` VARCHAR(500) NULL AFTER `longitude`;
    END IF;

    -- ------------------------------------------------------------------
    -- unsafeconds (Condición Insegura): después de `name_loc`.
    -- ------------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'unsafeconds'
          AND COLUMN_NAME  = 'latitude'
    ) THEN
        ALTER TABLE `unsafeconds`
            ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `name_loc`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'unsafeconds'
          AND COLUMN_NAME  = 'longitude'
    ) THEN
        ALTER TABLE `unsafeconds`
            ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'unsafeconds'
          AND COLUMN_NAME  = 'gps_address'
    ) THEN
        ALTER TABLE `unsafeconds`
            ADD COLUMN `gps_address` VARCHAR(500) NULL AFTER `longitude`;
    END IF;

    -- ------------------------------------------------------------------
    -- injury_reports (Accidente): después de `incident_location`.
    -- ------------------------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'injury_reports'
          AND COLUMN_NAME  = 'latitude'
    ) THEN
        ALTER TABLE `injury_reports`
            ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `incident_location`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'injury_reports'
          AND COLUMN_NAME  = 'longitude'
    ) THEN
        ALTER TABLE `injury_reports`
            ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'injury_reports'
          AND COLUMN_NAME  = 'gps_address'
    ) THEN
        ALTER TABLE `injury_reports`
            ADD COLUMN `gps_address` VARCHAR(500) NULL AFTER `longitude`;
    END IF;
END //
DELIMITER ;

CALL crewcare_add_safety_gps();
DROP PROCEDURE IF EXISTS crewcare_add_safety_gps;
