-- ============================================================================
-- Delta #49 — CAPTURA FLUIDA · medida de control PRE-PROPUESTA (2026-08-01).
--
-- Paso 3 del bloque de captura fluida: al agregar un peligro, su MEDIDA DE CONTROL
-- debe llegar pre-propuesta DESDE el evento del catálogo (editable, citando la norma).
-- Hoy `hazard_events` NO tiene dónde guardar ese texto (solo description_es/en, que
-- describe el PELIGRO, no el control). Se agregan dos columnas bilingües, en paralelo
-- a description_es/en:
--   * control_measure_es / control_measure_en — TEXT NULL.
--
-- ⚠ REGLA: si el evento no tiene medida redactada, la columna se queda NULL y el
-- campo del formulario llega VACÍO. NO se rellena con plantilla genérica. Llenar estas
-- columnas es TRABAJO DE CONTENIDO DEL OWNER (0/207 hoy), no de código.
--
-- Idempotente (guarda por information_schema). Aplicar MANUAL con el cliente mysql
-- (NUNCA artisan migrate):
--   mysql -u root crewcare < database/owner-apply/2026-08-01-hazard-control-measure.sql
-- ============================================================================

DROP PROCEDURE IF EXISTS _cc_add_hazard_control_measure;
DELIMITER //
CREATE PROCEDURE _cc_add_hazard_control_measure()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'hazard_events'
          AND COLUMN_NAME = 'control_measure_es'
    ) THEN
        ALTER TABLE `hazard_events`
            ADD COLUMN `control_measure_es` TEXT NULL
            COMMENT 'Medida de control pre-propuesta (ES). NULL = sin medida; el campo llega vacio.'
            AFTER `description_en`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'hazard_events'
          AND COLUMN_NAME = 'control_measure_en'
    ) THEN
        ALTER TABLE `hazard_events`
            ADD COLUMN `control_measure_en` TEXT NULL
            COMMENT 'Medida de control pre-propuesta (EN). NULL = sin medida.'
            AFTER `control_measure_es`;
    END IF;
END //
DELIMITER ;
CALL _cc_add_hazard_control_measure();
DROP PROCEDURE IF EXISTS _cc_add_hazard_control_measure;
