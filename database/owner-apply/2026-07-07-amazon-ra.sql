-- ============================================================================
-- CrewCare — Scouting: Encabezado Amazon MGM Studios Risk Assessment (2026-07-07)
-- Agrega los 3 campos OPCIONALES del encabezado del formulario oficial de Amazon
-- MGM que aún no existían en scouting_reports:
--   production_type  (Production Type: TV / Film / Game Show)
--   manager_name     (Production Manager)
--   safety_rep_name  (Production Safety Rep)
--
-- El resto del encabezado Amazon ya se cubre con columnas existentes
-- (production_name, location_address, date_shoot, make_date) y con la Marca
-- (brand_name = Company). La TABLA DE PELIGROS vive en `risk_assessment`
-- (LONGTEXT/JSON) y NO requiere DDL: solo cambia la FORMA de cada fila.
--
-- Aplicar FUERA de Laravel (NO `php artisan migrate`). El CÓDIGO es defensivo
-- ($fillable / validación nullable): la pantalla funciona aunque las columnas
-- aún no existan; al aplicar este delta la captura de esos campos se activa.
--
-- Idempotente (MySQL 5.7-safe): cada ALTER va detrás de un check en
-- information_schema, así RE-EJECUTARLO es seguro (equivalente a hasColumn).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_add_amazon_ra;

DELIMITER //
CREATE PROCEDURE crewcare_add_amazon_ra()
BEGIN
    -- production_type: solo si aún no existe.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'scouting_reports'
          AND COLUMN_NAME  = 'production_type'
    ) THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `production_type` VARCHAR(40) NULL AFTER `production_name`;
    END IF;

    -- manager_name: solo si aún no existe.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'scouting_reports'
          AND COLUMN_NAME  = 'manager_name'
    ) THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `manager_name` VARCHAR(255) NULL AFTER `production_type`;
    END IF;

    -- safety_rep_name: solo si aún no existe.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'scouting_reports'
          AND COLUMN_NAME  = 'safety_rep_name'
    ) THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `safety_rep_name` VARCHAR(255) NULL AFTER `manager_name`;
    END IF;
END //
DELIMITER ;

CALL crewcare_add_amazon_ra();
DROP PROCEDURE IF EXISTS crewcare_add_amazon_ra;
