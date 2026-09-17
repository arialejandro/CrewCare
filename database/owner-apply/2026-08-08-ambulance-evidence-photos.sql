-- ============================================================================
-- CrewCare — ACTA DE AMBULANCIA: evidencia fotográfica múltiple
-- (2026-08-08) — delta #53. Sigue del #52 (verificación de ambulancias).
--
-- QUÉ RESUELVE (petición del owner): además de la foto de la unidad, poder adjuntar
--   VARIAS fotos como PRUEBA de lo verificado — para sostener una REVOCACIÓN (paro /
--   no autorizado) o para argumentar una AUTORIZACIÓN (apta) después. La evidencia
--   debe ser inmutable, así que se CONGELA en el acta y entra al sello.
--
-- ── UN CAMBIO DE ESQUEMA ─────────────────────────────────────────────────────
--   ambulance_inspections.evidence_photos JSON NULL — lista de rutas (disco 'public',
--   misma doctrina que las fotos del DSR: el path va en el hash, no los bytes). Es
--   CONTENIDO del acta (no estado) → entra al sello automáticamente (cambiar la lista
--   de evidencia de un acta sellada = ALTERADO en el verificador). No hay signatureExcludes
--   nuevo. MySQL 5.7 prohíbe DEFAULT en JSON → va JSON NULL a secas.
--
-- REGLA DE NOMBRES: `evidence_photos` no colisiona con props de Eloquent.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   PROCEDURE con guard information_schema (no hay ADD COLUMN IF NOT EXISTS en 5.7).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-08-ambulance-evidence-photos.sql
--
-- REVERSIÓN:
--   ALTER TABLE `ambulance_inspections` DROP COLUMN `evidence_photos`;
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_amb_evidence_2026_08_08;
DELIMITER //
CREATE PROCEDURE crewcare_amb_evidence_2026_08_08()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ambulance_inspections' AND COLUMN_NAME='evidence_photos') THEN
        ALTER TABLE `ambulance_inspections` ADD COLUMN `evidence_photos` JSON NULL AFTER `unit_photo_path`;
    END IF;
END //
DELIMITER ;
CALL crewcare_amb_evidence_2026_08_08();
DROP PROCEDURE IF EXISTS crewcare_amb_evidence_2026_08_08;
