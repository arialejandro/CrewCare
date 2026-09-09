-- ============================================================================
-- CrewCare — SCOUTING: bandera "¿habrá ambulancia?" (Parte D del bloque ambulancias)
-- (2026-08-08) — delta #54. Sigue del #51/#52/#53 (verificación de ambulancias).
--
-- QUÉ RESUELVE (petición del owner, R4): el scouting sólo declara SÍ/NO habrá
--   ambulancia en la locación. Es una decisión de PLANEACIÓN (pre-llamado); la
--   verificación real de la unidad vive en el módulo de ambulancias (acta sellada).
--   Esta bandera alimenta el póster MEDEVAC ("Ambulancia prevista: Sí/No").
--
-- ── UN CAMBIO DE ESQUEMA ─────────────────────────────────────────────────────
--   scouting_reports.has_ambulance TINYINT(1) NULL — tri-estado:
--     NULL = sin declarar · 1 = Sí · 0 = No.
--
--   ⚠ SELLO: scouting_reports es un documento SELLADO y HOY no tiene signatureExcludes.
--   Añadir una columna la metería en el hash de las actas YA selladas (attributesToArray
--   la incluiría como null) → todas saldrían ALTERADAS. Por eso el modelo declara
--   `$signatureExcludes = ['has_ambulance']`: la bandera NO entra al sello (es dato de
--   planeación, no un hecho sellado) y los scoutings existentes siguen VÁLIDOS.
--
-- REGLA DE NOMBRES: `has_ambulance` no colisiona con props de Eloquent.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   PROCEDURE con guard information_schema (no hay ADD COLUMN IF NOT EXISTS en 5.7).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-08-scouting-has-ambulance.sql
--
-- REVERSIÓN:
--   ALTER TABLE `scouting_reports` DROP COLUMN `has_ambulance`;
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_scouting_has_ambulance_2026_08_08;
DELIMITER //
CREATE PROCEDURE crewcare_scouting_has_ambulance_2026_08_08()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='has_ambulance') THEN
        ALTER TABLE `scouting_reports` ADD COLUMN `has_ambulance` TINYINT(1) NULL AFTER `ambulance_company`;
    END IF;
END //
DELIMITER ;
CALL crewcare_scouting_has_ambulance_2026_08_08();
DROP PROCEDURE IF EXISTS crewcare_scouting_has_ambulance_2026_08_08;
