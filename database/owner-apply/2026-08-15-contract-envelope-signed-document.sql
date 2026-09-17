-- ============================================================================
-- CrewCare — CONTRATO · PASO C · FASE 3: documento FIRMADO real del sobre.
-- (2026-08-15) — delta. Hasta ahora el contrato con las autógrafas estampadas vivía SOLO como HTML
--   efímero (nunca se guardaba ni se entregaba); lo que se sellaba/entregaba era el paquete byte-intact
--   (carátula + clausulado + anexos), SIN firmas. Esta columna guarda el render firmado ya congelado.
--
--   signed_document JSON = { path, hash (sha256 del PDF), rendered_at, engine }. El PDF vive en el disco
--   privado (contracts/signed/...). Su INTEGRIDAD se ancla en la BITÁCORA (evento 'sealed' con el hash,
--   cadena inmutable de Fase 1), NO en el sello del sobre.
--
-- NO entra al hash del sello del sobre ($signatureExcludes): es un artefacto DERIVADO de datos ya
--   sellados (las autógrafas por destinatario + el paquete) → agregarlo NO invalida sellos existentes.
-- ADITIVO/NULLABLE: los sobres sin plantilla activa o anteriores a esto quedan con signed_document NULL
--   y siguen entregando el paquete byte-intact.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela por columna):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-15-contract-envelope-signed-document.sql
-- Requiere `contract_envelopes`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_envelope_signed_2026_08_15;
DELIMITER //
CREATE PROCEDURE crewcare_envelope_signed_2026_08_15()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND COLUMN_NAME = 'signed_document') THEN
        ALTER TABLE `contract_envelopes`
            ADD COLUMN `signed_document` JSON NULL AFTER `documents`;
    END IF;
END //
DELIMITER ;
CALL crewcare_envelope_signed_2026_08_15();
DROP PROCEDURE IF EXISTS crewcare_envelope_signed_2026_08_15;
