-- ============================================================================
-- CrewCare — FIRMAS PROD.: puesto 'Representante Legal' (firmante del contrato = quien obliga a la
-- empresa). (2026-08-13) — delta. Es un USUARIO con perfil (firma AUTENTICADO, no por enlace suelto).
--   Va bajo 'Producción Ejecutiva' para caer en los elegibles de Firmas Prod. Renómbralo/duplícalo
--   si tu productora lo llama distinto (Apoderado Legal, Asesor Jurídico, etc.).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (IF NOT EXISTS por nombre+depto):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-representante-legal-position.sql
-- Requiere el catálogo (departments/positions). SIN permiso/seeder/lang.
--
-- ⚠ CHARSET: este delta COMPARA por nombre acentuado ('Producción Ejecutiva'). El SET NAMES de
--   abajo fuerza utf8mb4 en la conexión aunque el cliente venga en latin1 (si no, el WHERE no casa
--   y NO inserta, en silencio). Para deltas con comparaciones acentuadas, SIEMPRE SET NAMES utf8mb4.
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS crewcare_legal_rep_position_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_legal_rep_position_2026_08_13()
BEGIN
    DECLARE v_dept BIGINT UNSIGNED;
    SELECT id INTO v_dept FROM departments WHERE name = 'Producción Ejecutiva' LIMIT 1;

    IF v_dept IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM positions
        WHERE name = 'Representante Legal' AND department_id = v_dept AND production_id IS NULL
    ) THEN
        INSERT INTO positions (name, department_id, production_id, is_hod, active, created_at, updated_at)
        VALUES ('Representante Legal', v_dept, NULL, 0, 1, NOW(), NOW());
    END IF;
END //
DELIMITER ;
CALL crewcare_legal_rep_position_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_legal_rep_position_2026_08_13;
