-- =====================================================================================
--  DSR ←→ SCOUTING · vínculo de origen del HOSPITAL designado
--  Fecha: 2026-07-25
--
--  QUÉ AÑADE (1 columna nullable):
--    daily_reports.scouting_report_id  → locación scouteada (Scouting) de la que el DSR
--                                        HEREDA su hospital designado, ambulancia y nombre
--                                        de locación (belongsTo ScoutingReport).
--
--  EL PROBLEMA que resuelve:
--    El hospital designado del DSR se prellena con ARRASTRE del DSR anterior. Eso hereda el
--    hospital de OTRA locación → un dato erróneo con apariencia de correcto, y es el campo que
--    alguien lee corriendo cuando hay un herido. La fuente natural del hospital es el SCOUTING
--    de ESA locación (que ya calcula hospital privado-primero + ETA con el buscador geo). Este
--    vínculo permite que el DSR tome el hospital de la locación real y NO de un día ajeno.
--
--  ⚠ SELLO / HASH: la columna nace NULL (sin default no-nulo). El modelo DailyReport excluye
--    scouting_report_id del payload del hash CUANDO es null (canonicalSignaturePayload override),
--    de modo que los 17 DSR ya sellados —todos con la columna en NULL— conservan su hash y NO
--    quedan marcados "ALTERADO". Un DSR nuevo que SÍ apunta a un scouting la incluye en el hash
--    → el vínculo queda atado al sello. Mismo patrón que los gemelos Acto/Condición.
--
--  SIN FK dura a propósito: es un enlace snapshot nullable (belongsTo en el modelo). No arrastra
--    ON DELETE ni falla si el scouting se retira (los scoutings se retiran con is_active/estado,
--    no con DELETE, pero aun así evitamos acoplar el borrado). Coherente con scouting_report_id
--    de hazardnotifications/unsafeconds.
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). MySQL 5.7: no hay ADD COLUMN IF NOT
--    EXISTS, por eso el wrapper information_schema.COLUMNS dentro de un PROCEDURE (idempotente).
--
--  REVERSIÓN:
--    ALTER TABLE `daily_reports` DROP COLUMN `scouting_report_id`;
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_dsr_scouting_link_2026_07_25;
DELIMITER //
CREATE PROCEDURE crewcare_dsr_scouting_link_2026_07_25()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='scouting_report_id') THEN
        ALTER TABLE `daily_reports` ADD COLUMN `scouting_report_id` BIGINT UNSIGNED NULL DEFAULT NULL;
    END IF;

    -- Índice para consultar "qué DSR nacieron de esta locación scouteada".
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND INDEX_NAME='daily_reports_scouting_report_id_index') THEN
        ALTER TABLE `daily_reports` ADD INDEX `daily_reports_scouting_report_id_index` (`scouting_report_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_dsr_scouting_link_2026_07_25();
DROP PROCEDURE IF EXISTS crewcare_dsr_scouting_link_2026_07_25;
