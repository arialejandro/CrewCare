-- ============================================================================
-- CrewCare — PÓSTER MEDEVAC: primera plantilla del MOTOR DE DOCUMENTOS
-- (2026-07-31) — delta #46. Sigue del #45 (vigilancia epidemiológica).
--
-- QUÉ ES: el póster de "Protocolo general de activación de emergencias" por
--   LOCACIÓN. NO es captura nueva: se RENDERIZA desde lo que el SCOUTING ya tiene
--   (hospital, dirección, ETA, GPS, punto de reunión, acceso de emergencia) más
--   los CONTACTOS de emergencia. Cada emisión CONGELA su payload y se sella con
--   HasDigitalSignatures; entra al verificador público como 'mdvc'.
--
-- POR QUÉ ES EL PRIMERO: estrena el motor de salida de documentos (el mismo que
--   luego servirá al PAE, boletines y scouting report visual) con el documento más
--   SIMPLE. El motor mínimo = builder de SOLO LECTURA que congela un payload
--   versionado (patrón de WrapReport) + plantilla Blade que hereda el chrome v2.
--
-- ── DOS CAMBIOS DE ESQUEMA ──────────────────────────────────────────────────
--   1) scouting_reports.hospital_distance_km  (DECIMAL, nullable)
--      La franja del documento es "LOCACIÓN · DISTANCIA · TIEMPO". Hoy el scouting
--      guarda el ETA (texto) pero NO los km. El MISMO motor geo del navegador (OSRM)
--      que ya calcula el ETA devuelve también la distancia; esta columna la recibe.
--      NO se inventa: si OSRM no respondió, queda NULL y la franja no la muestra.
--
--   2) medevac_posters  (tabla nueva)
--      El documento emitido. CONGELA (payload JSON) todo lo que citó: si mañana
--      cambia el scouting, el póster sigue diciendo lo que dijo y el sello lo prueba.
--
-- ── REVISIÓN = CONSECUTIVO POR LOCACIÓN (decisión del owner) ─────────────────
--   `revision` se calcula al emitir contando los pósters previos de ESA locación
--   (scouting_report_id) + 1. Cada emisión es INDEPENDIENTE: sin cadena de versiones,
--   sin "sustituye a". El número dice "este es el N-ésimo póster de esta locación".
--
-- ── CONTACTOS HÍBRIDOS (decisión del owner) ─────────────────────────────────
--   Los 3 contactos (set medic · risk assessment · production manager) se PRE-LLENAN
--   desde el crew por rol/puesto con su users.phone; donde no exista, el emisor los
--   captura al emitir. El resultado final se CONGELA en payload.contacts. No hay
--   columna de contactos: viven dentro del payload sellado.
--
-- ── SELLO ───────────────────────────────────────────────────────────────────
--   Se sella sobre el DATO (attributesToArray ksorteado: payload + revision + issued_*),
--   nunca sobre el render. `is_active` queda FUERA del hash (estado de listado, no
--   contenido). El póster NO tiene concepto de retiro (sin cadena/sustituye-a) →
--   el verificador lo lee en 2 estados: vigente / alterado.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). `payload`/`revision`/`location_label` seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   · scouting_reports: PROCEDURE con guard information_schema (no hay ADD COLUMN IF NOT EXISTS).
--   · medevac_posters: CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-31-medevac-poster.sql
--   Permiso Spatie: php artisan db:seed --class=MedevacPermissionsSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → `payload` va JSON NULL.
--
-- REVERSIÓN:
--   DROP TABLE IF EXISTS `medevac_posters`;
--   ALTER TABLE `scouting_reports` DROP COLUMN `hospital_distance_km`;
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1) scouting_reports.hospital_distance_km — distancia al hospital (km, del OSRM)
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_medevac_scouting_distance_2026_07_31;
DELIMITER //
CREATE PROCEDURE crewcare_medevac_scouting_distance_2026_07_31()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='scouting_reports' AND COLUMN_NAME='hospital_distance_km') THEN
        ALTER TABLE `scouting_reports`
            ADD COLUMN `hospital_distance_km` DECIMAL(6,2) NULL DEFAULT NULL AFTER `hospital_eta`;
    END IF;
END //
DELIMITER ;
CALL crewcare_medevac_scouting_distance_2026_07_31();
DROP PROCEDURE IF EXISTS crewcare_medevac_scouting_distance_2026_07_31;

-- ---------------------------------------------------------------------------
-- 2) medevac_posters — el documento emitido (payload congelado + sello)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medevac_posters` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                CHAR(36)     NULL,             -- verificador público (GeneratesUuidKey)
    `production_id`       BIGINT UNSIGNED NULL,          -- CurrentProduction (FK-soft)
    `scouting_report_id`  BIGINT UNSIGNED NULL,          -- locación de origen (FK-soft)

    `revision`            INT          NOT NULL DEFAULT 1,   -- consecutivo por locación (congelado)
    `location_label`      VARCHAR(255) NULL,                 -- snapshot del nombre de locación (listado)

    `payload`             JSON         NULL,                 -- FOTOGRAFÍA: location/gps/maps_url/distance/eta/hospital/contacts/meta

    -- Emisor (safety) con nombre CONGELADO (misma doctrina que issued_permits) ---
    `issued_by_id`        BIGINT UNSIGNED NULL,          -- FK-soft a users
    `issued_by_name`      VARCHAR(255) NULL,             -- snapshot
    `issued_at`           DATETIME     NULL,             -- momento de emisión (VA EN EL HASH)

    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,   -- listado (HASH-EXCLUIDO); nunca DELETE
    `created_at`          TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `medevac_posters_uuid_unique`      (`uuid`),
    KEY `medevac_posters_scouting_idx`   (`scouting_report_id`),
    KEY `medevac_posters_production_idx` (`production_id`),
    KEY `medevac_posters_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
