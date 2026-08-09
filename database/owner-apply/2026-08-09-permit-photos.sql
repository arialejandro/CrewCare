-- ============================================================================
-- CrewCare — PERMISO DE TRABAJO: fotografías adjuntas al emitir
-- (2026-08-09) — sigue del #44 (emisión de permisos, issued_permits).
--
-- QUÉ RESUELVE (petición del owner): poder adjuntar VARIAS fotos al EMITIR un permiso
--   (condición del sitio, montaje del rig, extintores en su lugar, autorización externa
--   en papel…) y verlas dentro del documento emitido. Hoy el form no tiene campo de fotos.
--
-- ── UN CAMBIO DE ESQUEMA ─────────────────────────────────────────────────────
--   issued_permits.photos JSON NULL — lista de rutas raíz-relativas (/storage/…, disco
--   'public', misma doctrina que las fotos del DSR / evidence_photos del acta de ambulancia:
--   el PATH va en el hash, no los bytes). Es CONTENIDO del permiso (adjuntar una prueba
--   distinta a un permiso sellado = ALTERADO). MySQL 5.7 prohíbe DEFAULT en JSON → JSON NULL.
--
--   SELLO — OJO (lo resuelve el modelo, no el SQL): esta columna se agrega DESPUÉS de que ya
--   hay permisos SELLADOS. Si `photos = NULL` entrara al payload canónico, el JSON de esos
--   permisos viejos cambiaría ("photos":null nuevo) y su sello se leería como ALTERADO sin que
--   nadie los tocara. Por eso IssuedPermit::canonicalSignaturePayload() RETIRA `photos` cuando
--   está vacío (override null-only): los permisos sellados antes de esta columna conservan
--   EXACTAMENTE su hash, y las emisiones nuevas CON fotos SÍ las sellan. No hay signatureExcludes.
--
-- REGLA DE NOMBRES: `photos` no colisiona con props/atributos mágicos de Eloquent.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   PROCEDURE con guard information_schema (no hay ADD COLUMN IF NOT EXISTS en 5.7).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-09-permit-photos.sql
--
-- REVERSIÓN:
--   ALTER TABLE `issued_permits` DROP COLUMN `photos`;
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_permit_photos_2026_08_09;
DELIMITER //
CREATE PROCEDURE crewcare_permit_photos_2026_08_09()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='issued_permits' AND COLUMN_NAME='photos') THEN
        ALTER TABLE `issued_permits` ADD COLUMN `photos` JSON NULL AFTER `standards_snapshot`;
    END IF;
END //
DELIMITER ;
CALL crewcare_permit_photos_2026_08_09();
DROP PROCEDURE IF EXISTS crewcare_permit_photos_2026_08_09;
