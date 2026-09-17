-- Hotel/hospedaje por PERSONA para el back (columna HOTEL de los formatos internacionales).
-- Aditivo, idempotente. Aplicar en prod (el owner corre owner-apply; en local/test lo hace la migración).
SET NAMES utf8mb4;

SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'call_person_schedules'
               AND column_name = 'hotel_code');

SET @sql := IF(@col = 0,
    'ALTER TABLE `call_person_schedules` ADD COLUMN `hotel_code` VARCHAR(24) NULL AFTER `pickup_place_text`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
