-- ============================================================================
-- CrewCare — QUIEN COBRA · PASO 3 (INTAKE) · parentesco del contacto de emergencia.
-- (2026-08-16) — delta. Sigue de 2026-08-13-payee-intake.sql.
--
-- QUÉ ES:
--   · `payees.emergency_contact_relationship` — parentesco del CONTACTO DE EMERGENCIA.
--     Va SEPARADO del parentesco del beneficiario: no siempre es la misma persona y se
--     consulta en lugares distintos (p.ej. el parentesco del contacto en un reporte
--     médico). Antes el intake pedía nombre y teléfono del contacto pero NO su parentesco.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+. Idempotente (columna centinela).
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-16-payee-emergency-relationship.sql
--   Luego: php artisan cache:clear
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_payee_emergency_rel_2026_08_16;
DELIMITER //
CREATE PROCEDURE crewcare_payee_emergency_rel_2026_08_16()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payees' AND COLUMN_NAME='emergency_contact_relationship') THEN
        ALTER TABLE `payees`
            ADD COLUMN `emergency_contact_relationship` VARCHAR(60) NULL AFTER `emergency_contact_phone`;
    END IF;
END //
DELIMITER ;
CALL crewcare_payee_emergency_rel_2026_08_16();
DROP PROCEDURE IF EXISTS crewcare_payee_emergency_rel_2026_08_16;

-- REVERSIÓN (manual): ALTER TABLE payees DROP COLUMN emergency_contact_relationship;
