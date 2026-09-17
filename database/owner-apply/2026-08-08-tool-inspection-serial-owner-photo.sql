-- ============================================================================
-- CrewCare — INSPECCIÓN DE HERRAMIENTA: unidad física (serie + dueño) + foto real
-- (2026-08-08) — delta #47. Sigue del #46 (póster MEDEVAC).
--
-- QUÉ RESUELVE (petición del owner): el catálogo HER-001… es un TIPO de referencia
--   (una "Sierra circular"), pero en el set hay VARIAS sierras del mismo tipo. Hoy el
--   acta guarda solo `tool_model` (texto libre) y el departamento; no distingue una
--   unidad física de otra, ni registra a su dueño, ni lleva foto. Y no había forma de
--   CONSULTAR inspecciones pasadas (solo se veía el acta recién creada o por su QR).
--
-- ── TRES NIVELES DE IDENTIDAD (el que faltaba es el de en medio) ─────────────
--   · TIPO   (tools)            → imagen GENÉRICA de referencia (se puebla con el tiempo,
--                                 fuera del código: el owner traza SVG / recorta fotos).
--   · UNIDAD (num. de serie)    → NO es tabla nueva: la unidad EMERGE del `tool_serial`
--                                 del acta. La pantalla de consulta agrupa por serie →
--                                 el historial de ESA sierra concreta.
--   · ACTA   (tool_inspections) → congela marca/modelo/serie + dueño + foto de la unidad.
--
-- ── DOS CAMBIOS DE ESQUEMA ──────────────────────────────────────────────────
--   1) tools.image_path  (VARCHAR, nullable)
--      Imagen genérica del TIPO (referencia). NULL = sin imagen todavía → la UI pinta un
--      placeholder. Se sube por el admin de imágenes (permiso tools.inspect), sin bloquear.
--
--   2) tool_inspections: 5 columnas de contenido (todas VAN EN EL SELLO, son dato fijo del
--      acta — no hay signatureExcludes nuevo):
--        · tool_brand      VARCHAR — marca de la unidad física
--        · tool_serial     VARCHAR — N.º de serie: la LLAVE de la unidad física (agrupación)
--        · owner_user_id   BIGINT  — dueño si es crew (FK-soft a users)
--        · owner_name      VARCHAR — nombre CONGELADO del dueño (crew) o texto libre (renta/externo)
--        · tool_photo_path VARCHAR — foto REAL de la unidad (disco 'public', patrón DSR)
--
-- ── SELLO ───────────────────────────────────────────────────────────────────
--   HasDigitalSignatures sella sobre attributesToArray ksorteado. Al ser columnas de
--   CONTENIDO (no estado), entran al hash automáticamente: cambiar la serie/dueño/foto
--   de un acta sellada → ALTERADO en el verificador. La foto se referencia por RUTA
--   (misma doctrina que las fotos del DSR): el path va en el hash, no los bytes.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). Todas seguras.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   PROCEDURE con guard information_schema (no hay ADD COLUMN IF NOT EXISTS en 5.7).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-08-tool-inspection-serial-owner-photo.sql
--
-- REVERSIÓN:
--   ALTER TABLE `tools` DROP COLUMN `image_path`;
--   ALTER TABLE `tool_inspections`
--     DROP COLUMN `tool_brand`, DROP COLUMN `tool_serial`,
--     DROP COLUMN `owner_user_id`, DROP COLUMN `owner_name`, DROP COLUMN `tool_photo_path`;
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1) tools.image_path — imagen genérica del TIPO (referencia)
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_tool_image_2026_08_08;
DELIMITER //
CREATE PROCEDURE crewcare_tool_image_2026_08_08()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tools' AND COLUMN_NAME='image_path') THEN
        ALTER TABLE `tools` ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `name`;
    END IF;
END //
DELIMITER ;
CALL crewcare_tool_image_2026_08_08();
DROP PROCEDURE IF EXISTS crewcare_tool_image_2026_08_08;

-- ---------------------------------------------------------------------------
-- 2) tool_inspections — unidad física (marca/serie/dueño) + foto real
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_tool_insp_unit_2026_08_08;
DELIMITER //
CREATE PROCEDURE crewcare_tool_insp_unit_2026_08_08()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='tool_brand') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `tool_brand` VARCHAR(120) NULL DEFAULT NULL AFTER `tool_model`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='tool_serial') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `tool_serial` VARCHAR(120) NULL DEFAULT NULL AFTER `tool_brand`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='tool_photo_path') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `tool_photo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `tool_serial`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='owner_user_id') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `owner_user_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `department_name`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND COLUMN_NAME='owner_name') THEN
        ALTER TABLE `tool_inspections` ADD COLUMN `owner_name` VARCHAR(160) NULL DEFAULT NULL AFTER `owner_user_id`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tool_inspections' AND INDEX_NAME='tool_inspections_serial_idx') THEN
        ALTER TABLE `tool_inspections` ADD INDEX `tool_inspections_serial_idx` (`tool_serial`);
    END IF;
END //
DELIMITER ;
CALL crewcare_tool_insp_unit_2026_08_08();
DROP PROCEDURE IF EXISTS crewcare_tool_insp_unit_2026_08_08;
