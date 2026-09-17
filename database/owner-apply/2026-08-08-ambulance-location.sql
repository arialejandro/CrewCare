-- ============================================================================
-- CrewCare — ACTA DE AMBULANCIA: locación de la verificación (GPS-back)
-- (2026-08-08) — delta #55. Sigue del #52/#53 (verificación de ambulancias).
--
-- QUÉ RESUELVE (petición del owner): registrar DÓNDE se realizó la verificación.
--   El "GPS-back" (captura silenciosa en el navegador) sugiere el nombre del scouting
--   más cercano; el safety puede corregirlo. Se muestra en el cintillo del acta.
--
-- ── TRES COLUMNAS ────────────────────────────────────────────────────────────
--   ambulance_inspections.latitude       DECIMAL(10,7) NULL
--   ambulance_inspections.longitude      DECIMAL(10,7) NULL
--   ambulance_inspections.location_label VARCHAR(255)  NULL  (nombre de la locación)
--
--   ⚠ SELLO: son campos AÑADIDOS DESPUÉS de que ya había actas selladas (p.ej. AMBU-0005).
--   Si entraran al hash, esas actas saldrían ALTERADAS por mero drift de esquema (columnas
--   null nuevas), no por manipulación. Como es CONTEXTO/provenencia (y puede corregirse), el
--   modelo lo declara en `$signatureExcludes` → NO entra al sello y las actas viejas siguen
--   VÁLIDAS. (Distinto de placas/serie/veredicto/tripulación, que SÍ se sellan.)
--
-- REGLA DE NOMBRES: sin colisión con props de Eloquent.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guard information_schema):
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-08-ambulance-location.sql
--
-- REVERSIÓN:
--   ALTER TABLE `ambulance_inspections`
--     DROP COLUMN `latitude`, DROP COLUMN `longitude`, DROP COLUMN `location_label`;
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_amb_location_2026_08_08;
DELIMITER //
CREATE PROCEDURE crewcare_amb_location_2026_08_08()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ambulance_inspections' AND COLUMN_NAME='latitude') THEN
        ALTER TABLE `ambulance_inspections` ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `economic_number`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ambulance_inspections' AND COLUMN_NAME='longitude') THEN
        ALTER TABLE `ambulance_inspections` ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ambulance_inspections' AND COLUMN_NAME='location_label') THEN
        ALTER TABLE `ambulance_inspections` ADD COLUMN `location_label` VARCHAR(255) NULL AFTER `longitude`;
    END IF;
END //
DELIMITER ;
CALL crewcare_amb_location_2026_08_08();
DROP PROCEDURE IF EXISTS crewcare_amb_location_2026_08_08;
