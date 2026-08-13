-- ============================================================================
-- CrewCare — QUIEN COBRA · DELTA DE EXTRANJERO.
-- (2026-08-13) — delta chico. Sigue del intake (Paso 3).
--
-- QUÉ RESUELVE: un crew/proveedor EXTRANJERO no tiene INE, CSF ni 32-D (ni RFC/CURP).
--   `nationality = extranjera` alterna el paquete: pasaporte, documento migratorio y
--   comprobante de residencia fiscal (sembrados aparte). Así un extranjero completa su
--   intake al 100% sin quedar en `missing` permanente por documentos que no le aplican.
--
--   Un solo cambio de esquema: `document_types.nationality` — a qué nacionalidad aplica
--   el tipo ('mexicana' | 'extranjera' | NULL = ambas). El paquete SIGUE siendo configurable
--   por producción (document_requirements); no se fija nada en código. RFC/CURP ya eran
--   nullable, así que quedan opcionales para el extranjero sin más cambios.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente.
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-payee-foreigner.sql
--   Luego: php artisan db:seed --class=DocumentTypeSeeder        (nationality + docs extranjero)
--          php artisan db:seed --class=DocumentRequirementSeeder (los mete al paquete)
--          php artisan cache:clear
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_doctype_nationality_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_doctype_nationality_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_types' AND COLUMN_NAME='nationality') THEN
        ALTER TABLE `document_types`
            ADD COLUMN `nationality` VARCHAR(20) NULL AFTER `legal_nature`;  -- mexicana | extranjera | NULL=ambas
    END IF;
END //
DELIMITER ;
CALL crewcare_doctype_nationality_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_doctype_nationality_2026_08_13;

-- REVERSIÓN: ALTER TABLE `document_types` DROP COLUMN `nationality`;
