-- ============================================================================
-- CrewCare — Fixes de COHERENCIA (2026-07-13)
-- Capa de ESQUEMA + LIMPIEZA DE CATÁLOGO de la ola de correcciones de coherencia.
--
-- Contenido:
--   1) COL   unsafeconds.involved_department  — depto/área implicada en la condición.
--   2) COL   daily_logs.created_by_id         — autofirma del autor del log (trazabilidad).
--   3) PURGA safety_standards id 1..22        — filas legacy específicas-de-proyecto
--            (nombres que citan producciones/locaciones y códigos secuestrados).
--   4) UNIQUE safety_standards.regulation_code — evita que se vuelva a "secuestrar"
--            un código de norma; el seeder ya es idempotente por regulation_code.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO es DEFENSIVO (Schema::hasColumn + $fillable): las pantallas
-- siguen funcionando aunque estas columnas aún no existan; al aplicar este delta,
-- las features se activan solas.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) los ALTER/índices se guardan con un check en
-- information_schema vía un procedimiento temporal.
--
-- Convención del repo: BIGINT UNSIGNED sin FK dura (igual que otros created_by_id).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 3) PURGA del catálogo safety_standards (id 1..22)  [el owner APROBÓ purgar]
--
--    POR QUÉ ES SEGURO:
--    - Las filas 1..22 son basura legacy específica-de-proyecto: nombres que
--      citan producciones/locaciones ("Cosmic Frizzer", "Condors", "Pirañas /
--      Dragón Barbudo"), un badge inventado ("AMAZON"), y CÓDIGOS de boletín
--      SECUESTRADOS (p.ej. Bulletin #1=Armas usado para "Vidrios" y para
--      "Housekeeping"; Bulletin #5=Conciencia de seguridad usado para "Escaleras";
--      Bulletin #6=Manejo de animales usado para "Alturas"; Bulletin #15=Boating
--      usado para "Estrés térmico"; el inexistente "Bulletin #23A"; NOMs sin año).
--      NO son las filas canónicas del seeder (esas viven en id 23..67 y las
--      re-siembra SafetyCatalogSeeder por regulation_code).
--    - La pivote polimórfica `standardables` está VACÍA (0 filas) → nadie apunta a
--      estos id por FK/relación N:M.
--    - Los reportes SNAPSHOTEAN regulation_code/regulation_badge (columnas COPIA en
--      dailyreports/unsafeconds/etc.), NO guardan FK a safety_standards.id → borrar
--      estas filas no rompe ningún rastro de auditoría ya emitido.
--    - Verificado: NO hay constraints FK apuntando a safety_standards.
--
--    IDEMPOTENTE: tras el primer borrado esos id ya no existen (el auto_increment
--    reasigna ids más altos), así que re-ejecutar este DELETE no borra nada.
-- ----------------------------------------------------------------------------
DELETE FROM `safety_standards` WHERE `id` BETWEEN 1 AND 22;

-- ----------------------------------------------------------------------------
-- 1/2/4) Columnas nuevas + índice UNIQUE (idempotentes vía procedimiento temporal)
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_coherencia_2026_07_13;

DELIMITER //
CREATE PROCEDURE crewcare_coherencia_2026_07_13()
BEGIN
    -- 1) unsafeconds.involved_department — depto/área implicada en la condición insegura.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='involved_department') THEN
        ALTER TABLE `unsafeconds`
            ADD COLUMN `involved_department` VARCHAR(255) NULL AFTER `location_unsafe_cond`;
    END IF;

    -- 2) daily_logs.created_by_id — autofirma del autor del log (trazabilidad, sin FK dura).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_logs' AND COLUMN_NAME='created_by_id') THEN
        ALTER TABLE `daily_logs`
            ADD COLUMN `created_by_id` BIGINT UNSIGNED NULL AFTER `regulation_code`;
    END IF;

    -- 4) safety_standards.regulation_code — índice UNIQUE (se corre DESPUÉS del DELETE de
    --    arriba, cuando ya no quedan códigos duplicados/secuestrados). Verificado: las
    --    filas canónicas (id 23..67) NO tienen regulation_code duplicado.
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards'
          AND INDEX_NAME='uq_safety_standards_reg_code') THEN
        ALTER TABLE `safety_standards`
            ADD UNIQUE INDEX `uq_safety_standards_reg_code` (`regulation_code`);
    END IF;
END //
DELIMITER ;

CALL crewcare_coherencia_2026_07_13();
DROP PROCEDURE IF EXISTS crewcare_coherencia_2026_07_13;
