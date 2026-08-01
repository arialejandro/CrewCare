-- =====================================================================================
--  PASO 2/2 · Gemelos Acto Inseguro + Condición Insegura — VÍNCULOS + INVOLUCRADO + CONTENIDO
--  Fecha: 2026-07-24
--
--  CONTENIDO (8 columnas nullable, 4 por tabla):
--    hazardnotifications (Acto):
--      · scouting_report_id      → locación autoritativa (Scouting) del hallazgo (belongsTo)
--      · involved_user_id        → persona involucrada (del CrewList); NO se imprime en el doc
--      · related_unsafecond_id   → enlace REAL a la Condición hermana (reemplaza el "bluff")
--      · human_factor            → JSON: factor(es) humano(s) del acto (análisis, SÍ se imprime)
--    unsafeconds (Condición):
--      · scouting_report_id      → locación autoritativa (Scouting)
--      · involved_user_id        → persona involucrada; NO se imprime
--      · related_hazard_id       → enlace REAL al Acto hermano (la fecha se DERIVA de él)
--      · is_recurrent            → ¿ya se había reportado esta condición? (SÍ se imprime)
--
--  EL PROBLEMA que resuelve:
--    - El hallazgo no quedaba ligado a su locación (el motor /geo/scoutings-nearby existía pero
--      no se persistía el match). Ahora el scouting acumula lo que pasó ahí.
--    - El involucrado se capturaba como texto libre (o no existía en el Acto); ahora es FK al crew,
--      para poder alertar al jefe inmediato (lead is_lead del depto) sin exponer el nombre en el doc.
--    - "Notificación: Sí · fecha" sugería una relación Condición↔Acto que no existía (sin FK).
--    - Los gemelos eran idénticos salvo etiquetas: ahora el Acto pide factor humano y la Condición
--      recurrencia (cada uno lo que su naturaleza requiere).
--
--  ⚠ SELLO / HASH: TODAS las columnas nacen NULL (sin default no-nulo). El modelo excluye estas
--    columnas del payload del hash CUANDO son null (ver canonicalSignaturePayload en los modelos),
--    de modo que un reporte sellado ANTES de existir estas columnas conserva su hash y NO queda
--    marcado "ALTERADO". Los reportes con valor SÍ las hashean → quedan protegidas.
--
--  SIN FK dura a propósito: son columnas snapshot/enlace nullable (belongsTo en el modelo), para no
--    arrastrar ON DELETE ni fallar si el objetivo se retira. Coherente con el resto de los reportes.
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). MySQL 5.7: no hay ADD COLUMN IF NOT EXISTS,
--    por eso el wrapper information_schema.COLUMNS dentro de un PROCEDURE (idempotente).
--
--  REVERSIÓN:
--    ALTER TABLE `hazardnotifications` DROP COLUMN `scouting_report_id`, DROP COLUMN `involved_user_id`,
--        DROP COLUMN `related_unsafecond_id`, DROP COLUMN `human_factor`;
--    ALTER TABLE `unsafeconds` DROP COLUMN `scouting_report_id`, DROP COLUMN `involved_user_id`,
--        DROP COLUMN `related_hazard_id`, DROP COLUMN `is_recurrent`;
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_gemelos_vinculos_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_gemelos_vinculos_2026_07_24()
BEGIN
    -- ---------- ACTO INSEGURO (hazardnotifications) ----------
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='scouting_report_id') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `scouting_report_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='involved_user_id') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `involved_user_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='related_unsafecond_id') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `related_unsafecond_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='human_factor') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `human_factor` JSON NULL DEFAULT NULL;
    END IF;

    -- Índice para las consultas por locación (el scouting acumula sus hallazgos).
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND INDEX_NAME='hazardnotifications_scouting_report_id_index') THEN
        ALTER TABLE `hazardnotifications` ADD INDEX `hazardnotifications_scouting_report_id_index` (`scouting_report_id`);
    END IF;

    -- ---------- CONDICIÓN INSEGURA (unsafeconds) ----------
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='scouting_report_id') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `scouting_report_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='involved_user_id') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `involved_user_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='related_hazard_id') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `related_hazard_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    -- is_recurrent: NULLABLE sin default no-nulo (NULL = no capturado/legacy → no se imprime;
    -- 0 = no recurrente; 1 = recurrente). Así los reportes viejos quedan en NULL y el override del
    -- hash los excluye (no "ALTERADO").
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='is_recurrent') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `is_recurrent` TINYINT(1) NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND INDEX_NAME='unsafeconds_scouting_report_id_index') THEN
        ALTER TABLE `unsafeconds` ADD INDEX `unsafeconds_scouting_report_id_index` (`scouting_report_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_gemelos_vinculos_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_gemelos_vinculos_2026_07_24;
