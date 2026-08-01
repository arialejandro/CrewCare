-- ============================================================================
-- Delta #47 — MEDEVAC: persistir el MAPA de la ruta en el SCOUTING (2026-08-01).
--
-- Problema (feedback owner): el mapa se subía en la emisión y se congelaba SÓLO en
-- ese póster; al re-emitir se perdía ("no se queda nada guardado"). El mapa de la ruta
-- locación → hospital es dato de la LOCACIÓN, así que vive en el scouting: se sube una
-- vez y toda emisión posterior lo reutiliza (y lo congela sellado en su payload).
--
-- Se guarda el data-URI comprimido (ASCII base64) → columna LONGTEXT. La tabla es
-- latin1; base64 es ASCII, cabe sin problema. Idempotente (guarda por information_schema).
-- Aplicar MANUALMENTE con el cliente mysql (NUNCA artisan migrate):
--   mysql ... crewcare < database/owner-apply/2026-08-01-medevac-map-persist.sql
-- ============================================================================

DROP PROCEDURE IF EXISTS _cc_add_scouting_hospital_map;
DELIMITER //
CREATE PROCEDURE _cc_add_scouting_hospital_map()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'scouting_reports'
          AND COLUMN_NAME = 'hospital_map'
    ) THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `hospital_map` LONGTEXT NULL
            COMMENT 'data-URI del mapa de la ruta al hospital (MEDEVAC); persiste entre emisiones';
    END IF;
END //
DELIMITER ;
CALL _cc_add_scouting_hospital_map();
DROP PROCEDURE IF EXISTS _cc_add_scouting_hospital_map;
