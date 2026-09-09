-- ============================================================================
-- CrewCare — CONTRATO · PASO C · FASE 3: UUID público del sobre (verificador).
-- (2026-08-15) — delta. Para que un sobre sea VERIFICABLE en la superficie pública
--   (/verificar/cenv/{uuid}, misma que los docs de seguridad) necesita un identificador
--   estable y no adivinable, independiente del id autoincremental. Antes no lo tenía.
--
-- `uuid` NO entra al hash del sello (HasDigitalSignatures lo trata como columna VOLÁTIL,
--   junto a created_at/updated_at) → agregarlo NO invalida ningún sello de sobre existente.
-- ADITIVO/NULLABLE + backfill: los sobres viejos reciben su uuid aquí; los nuevos lo toman
--   del trait GeneratesUuidKey al crearse. UNIQUE tolera múltiples NULL, pero el backfill deja
--   cero NULL.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela por columna/índice):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-15-contract-envelope-uuid.sql
-- Requiere `contract_envelopes`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_envelope_uuid_2026_08_15;
DELIMITER //
CREATE PROCEDURE crewcare_envelope_uuid_2026_08_15()
BEGIN
    -- 1) columna
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND COLUMN_NAME = 'uuid') THEN
        ALTER TABLE `contract_envelopes`
            ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`;
    END IF;

    -- 2) backfill de los que no tienen (idempotente: solo toca los NULL)
    UPDATE `contract_envelopes` SET `uuid` = (UUID()) WHERE `uuid` IS NULL;

    -- 3) índice único
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND INDEX_NAME = 'contract_envelopes_uuid_unique') THEN
        ALTER TABLE `contract_envelopes`
            ADD UNIQUE KEY `contract_envelopes_uuid_unique` (`uuid`);
    END IF;
END //
DELIMITER ;
CALL crewcare_envelope_uuid_2026_08_15();
DROP PROCEDURE IF EXISTS crewcare_envelope_uuid_2026_08_15;
