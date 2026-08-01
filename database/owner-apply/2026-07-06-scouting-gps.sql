-- ============================================================================
-- CrewCare — Scouting: Ubicación GPS (2026-07-06)
-- Agrega coordenadas OPCIONALES (latitud/longitud) al reporte de scouting.
-- Se colocan justo después de `location_address`.
--
-- Aplicar FUERA de Laravel (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO ya es defensivo (Model $fillable / validación nullable):
-- la pantalla sigue funcionando aunque las columnas aún no existan; al aplicar
-- este delta, la feature se activa sola.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`. Para que RE-EJECUTARLO sea
-- SEGURO (idempotente), se guarda cada ALTER con un check en information_schema
-- mediante un procedimiento temporal (mismo objetivo que Schema::hasColumn).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_add_scouting_gps;

DELIMITER //
CREATE PROCEDURE crewcare_add_scouting_gps()
BEGIN
    -- latitude: solo si aún no existe.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'scouting_reports'
          AND COLUMN_NAME  = 'latitude'
    ) THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `location_address`;
    END IF;

    -- longitude: solo si aún no existe.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'scouting_reports'
          AND COLUMN_NAME  = 'longitude'
    ) THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;
    END IF;
END //
DELIMITER ;

CALL crewcare_add_scouting_gps();
DROP PROCEDURE IF EXISTS crewcare_add_scouting_gps;
