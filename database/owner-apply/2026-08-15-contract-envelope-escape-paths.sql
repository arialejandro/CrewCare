-- ============================================================================
-- CrewCare — CONTRATO · PASO C · FASE 2: CAMINOS DE ESCAPE del sobre.
-- (2026-08-15) — delta. Da al sobre las salidas que "definen si el sistema se usa": RECHAZAR (el
--   firmante se niega, con motivo), ANULAR (con motivo), REENVIAR (recordatorio), VENCER (barrido).
--   Cada transición escribe su evento en la bitácora (contract_envelope_events, Fase 1).
--
--   expires_at        — fecha límite; se fija al ENVIAR (sent). NULL en sobres viejos (el barrido cae
--                       a sent_at + ventana). No entra al hash (metadato de ruta).
--   declined_at       — cuándo un firmante rechazó (status='declined').
--   expired_at        — cuándo el barrido lo marcó vencido (status='expired').
--   resolution_reason — motivo HUMANO de anular/rechazar (estado terminal). También va en el payload
--                       del evento (registro inviolable); esta columna es la copia para mostrar.
--
-- NINGUNA entra al hash del sello (todas en $signatureExcludes del modelo): son metadato de ruta,
--   no el documento → los sobres YA sellados NO se vuelven "alterados".
-- NULLABLE/ADITIVO: convive con status/cancelled_at; los sobres viejos siguen válidos.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (centinela por columna):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-15-contract-envelope-escape-paths.sql
-- Requiere `contract_envelopes`. SIN permiso/seeder/lang.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_envelope_escape_2026_08_15;
DELIMITER //
CREATE PROCEDURE crewcare_envelope_escape_2026_08_15()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND COLUMN_NAME = 'expires_at') THEN
        ALTER TABLE `contract_envelopes`
            ADD COLUMN `expires_at` DATETIME NULL AFTER `sent_at`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND COLUMN_NAME = 'declined_at') THEN
        ALTER TABLE `contract_envelopes`
            ADD COLUMN `declined_at` DATETIME NULL AFTER `cancelled_at`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND COLUMN_NAME = 'expired_at') THEN
        ALTER TABLE `contract_envelopes`
            ADD COLUMN `expired_at` DATETIME NULL AFTER `declined_at`;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_envelopes'
          AND COLUMN_NAME = 'resolution_reason') THEN
        ALTER TABLE `contract_envelopes`
            ADD COLUMN `resolution_reason` VARCHAR(500) NULL AFTER `expired_at`;
    END IF;
END //
DELIMITER ;
CALL crewcare_envelope_escape_2026_08_15();
DROP PROCEDURE IF EXISTS crewcare_envelope_escape_2026_08_15;
