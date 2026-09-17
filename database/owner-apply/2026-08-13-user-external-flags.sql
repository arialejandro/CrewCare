-- ============================================================================
-- CrewCare — EL INFOSHEET · FASE 1: la PERSONA no-crew como usuario ÚNICO sin credenciales.
-- (2026-08-13) — delta. UNA sola tabla users (no una segunda que divergiría). El no-crew NO
--   recibe contraseña ni inicia sesión: entra por enlace firmado con external_access_token (hash
--   de UN SOLO USO; no caduca si no se usa, external_access_used_at lo marca al consumirse).
--   is_external = bandera de la PERSONA; crewlist_visible gatea el listado (opcional), no el
--   acceso. ADITIVO con DEFAULT seguro: las filas existentes quedan is_external=0 /
--   crewlist_visible=1 → ningún flujo en uso (alta, listados, login) cambia.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela `is_external`):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-user-external-flags.sql
-- Requiere `users`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_user_external_flags_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_user_external_flags_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'is_external') THEN
        ALTER TABLE `users`
            ADD COLUMN `is_external` TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN `crewlist_visible` TINYINT(1) NOT NULL DEFAULT 1,
            ADD COLUMN `external_access_token` VARCHAR(64) NULL,
            ADD COLUMN `external_access_used_at` DATETIME NULL,
            ADD UNIQUE KEY `users_external_token_uk` (`external_access_token`);
    END IF;
END //
DELIMITER ;
CALL crewcare_user_external_flags_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_user_external_flags_2026_08_13;
