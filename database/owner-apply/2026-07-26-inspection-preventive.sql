-- =====================================================================================
--  INSPECCIÓN PREVENTIVA · régimen de vigencia + momento + origen + retiro del acta
--  Fecha: 2026-07-26 — delta #43. Sigue del #42 (tool_inspections).
--
--  QUÉ AÑADE:
--   A) `tools.inspection_regime` VARCHAR(20) NOT NULL DEFAULT 'por_evento'
--        Régimen de vigencia del TIPO (no de cada inspección). Tres valores:
--          · por_jornada    — caduca al cambiar shoot_day (equipo con operador designado).
--          · por_colocacion — vale mientras siga puesto; NO caduca por día (estructura temporal;
--                             reusa la mecánica de `permits.site_scope`, no un segundo mecanismo).
--          · por_evento     — no caduca sola, no aparece en la lista del día (la mayoría).
--        Default 'por_evento' (el que no exige nada). El seeder ToolInspectionRegimeSeeder fija
--        las excepciones confirmadas por el owner (5 por_jornada + 6 por_colocacion).
--
--   B) En `tool_inspections`:
--      · `inspection_moment` VARCHAR(20) NULL — llegada_equipo | previo_al_uso | en_uso | por_hallazgo.
--          Cambia cómo se REDACTA el veredicto (no cómo se calcula). VA EN EL HASH (contenido del
--          acta, fijado al crear e inmutable). Seguro: hay 0 actas selladas.
--      · `origin_type`/`origin_id` — vínculo POLIMÓRFICO al reporte de origen (condición/acto/DSR/
--          accidente) cuando la inspección nace de uno; NULL cuando nace del menú. VA EN EL HASH.
--      · `retired_at` DATETIME NULL, `retired_by_id`, `retired_reason` VARCHAR(255), `superseded_by_id`
--          (self-ref a tool_inspections) — el RETIRO del acta. HASH-EXCLUIDOS (estado post-sello,
--          igual que is_active): retirar NO recalcula ni re-firma el sello. El verificador público
--          los lee para mostrar "VÁLIDO PERO RETIRADO" sin marcar ALTERADO.
--
--  ⚠ SELLO: los 4 campos de retiro se declaran en `ToolInspection::$signatureExcludes`. `inspection_moment`,
--    `origin_type` y `origin_id` SÍ entran al hash (se fijan al crear). Como hay 0 sellos de insp,
--    nada queda "ALTERADO". Los 61 sellos de los otros 9 tipos no se tocan.
--
--  SIN FK dura (coherente con tool_inspections, que es FK-soft por ser documento histórico):
--    origin_* es polimórfico (no admite FK), y superseded_by_id / retired_by_id se dejan soft para
--    que el acta sobreviva aunque el otro documento o el usuario se borren.
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). MySQL 5.7: sin ADD COLUMN IF NOT EXISTS,
--    por eso el wrapper information_schema dentro de un PROCEDURE (idempotente).
--
--  REVERSIÓN:
--    ALTER TABLE `tools` DROP COLUMN `inspection_regime`;
--    ALTER TABLE `tool_inspections` DROP COLUMN `inspection_moment`, DROP COLUMN `origin_type`,
--      DROP COLUMN `origin_id`, DROP COLUMN `retired_at`, DROP COLUMN `retired_by_id`,
--      DROP COLUMN `retired_reason`, DROP COLUMN `superseded_by_id`;
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_inspection_preventive_2026_07_26;
DELIMITER //
CREATE PROCEDURE crewcare_inspection_preventive_2026_07_26()
BEGIN
    -- A) tools.inspection_regime
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tools' AND COLUMN_NAME='inspection_regime') THEN
        ALTER TABLE `tools` ADD COLUMN `inspection_regime` VARCHAR(20) NOT NULL DEFAULT 'por_evento';
        ALTER TABLE `tools` ADD INDEX `tools_regime_idx` (`inspection_regime`);
    END IF;

    -- B) tool_inspections — momento (hash)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='inspection_moment') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `inspection_moment` VARCHAR(20) NULL AFTER `checklist_mode`;
    END IF;

    -- origen polimórfico (hash)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='origin_type') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `origin_type` VARCHAR(191) NULL;
        ALTER TABLE `tool_inspections` ADD COLUMN `origin_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `tool_inspections` ADD INDEX `tool_inspections_origin_idx` (`origin_type`,`origin_id`);
    END IF;

    -- retiro (HASH-EXCLUIDO)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='retired_at') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `retired_at` DATETIME NULL;
        ALTER TABLE `tool_inspections` ADD COLUMN `retired_by_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `tool_inspections` ADD COLUMN `retired_reason` VARCHAR(255) NULL;
        ALTER TABLE `tool_inspections` ADD COLUMN `superseded_by_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `tool_inspections` ADD INDEX `tool_inspections_retired_idx` (`retired_at`);
        ALTER TABLE `tool_inspections` ADD INDEX `tool_inspections_superseded_idx` (`superseded_by_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_inspection_preventive_2026_07_26();
DROP PROCEDURE IF EXISTS crewcare_inspection_preventive_2026_07_26;
