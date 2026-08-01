-- =====================================================================================
--  MÓDULO BETA · REFINAMIENTOS (XOR en la base + atestación por producción)
--  Fecha: 2026-07-24
--
--  ⚠ ORDEN: CORRER DESPUÉS de `2026-07-24-beta-lite-patients.sql` (crea la columna
--    `lite_patient_id` y la tabla `clinic_attestations` que este archivo endurece).
--
--  1) XOR EN LA BASE — exactamente una de `id_user` / `lite_patient_id` presente
--     El FormRequest cubre UNA puerta (el POST del formulario). La tabla cubre TODAS
--     (tinker, un import, un fix a mano, otro módulo). Por eso el candado va también aquí.
--
--     ⚠ MYSQL 5.7 IGNORA LOS `CHECK`. Hasta 8.0.16 el motor PARSEA y DESCARTA la cláusula
--       CHECK: NO enforza nada. Un CHECK a secas en 5.7 daría una falsa sensación de candado.
--       Por eso la enforcement REAL es un TRIGGER (funciona en 5.7 y en 8.0). El CHECK se
--       añade además, para que cuando la instancia corra en 8.0.16+ el motor lo enforce solo;
--       en 5.7 queda declarado y sin efecto (documentado, no oculto).
--
--  2) ATESTACIÓN por PRODUCCIÓN y por MÉDICO
--     El aviso "disponible en el puesto médico" es por PRODUCCIÓN: un médico que trabaja en
--     otro proyecto vuelve a atestiguar. `production_id` (0 = sin producción vigente, p. ej.
--     el sandbox beta) + UNIQUE(user_id, production_id) → una atestación por médico y proyecto.
--     Su AUSENCIA no bloquea ninguna consulta (se comprueba en el controlador, no aquí).
--
--  APLICAR FUERA DE LARAVEL. Idempotente.
--
--  REVERSIÓN:
--    DROP TRIGGER IF EXISTS `cmedic_patient_xor_ins`;
--    DROP TRIGGER IF EXISTS `cmedic_patient_xor_upd`;
--    ALTER TABLE `cmedic` DROP CHECK `chk_cmedic_patient_xor`;   -- (solo si el motor lo almacenó, 8.0+)
--    ALTER TABLE `clinic_attestations` DROP INDEX `clinic_attestations_user_prod_unique`,
--        ADD UNIQUE KEY `clinic_attestations_user_unique` (`user_id`), DROP COLUMN `production_id`;
-- =====================================================================================

-- 1a) TRIGGER — enforcement real del XOR (5.7 y 8.0) -----------------------------------
DROP TRIGGER IF EXISTS `cmedic_patient_xor_ins`;
DROP TRIGGER IF EXISTS `cmedic_patient_xor_upd`;
DELIMITER //
CREATE TRIGGER `cmedic_patient_xor_ins` BEFORE INSERT ON `cmedic`
FOR EACH ROW
BEGIN
    -- (X IS NULL) = (Y IS NULL) es TRUE cuando AMBOS son null o AMBOS tienen valor → violación.
    IF (NEW.id_user IS NULL) = (NEW.lite_patient_id IS NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cmedic: exactamente uno de id_user / lite_patient_id debe estar presente (XOR).';
    END IF;
END //
CREATE TRIGGER `cmedic_patient_xor_upd` BEFORE UPDATE ON `cmedic`
FOR EACH ROW
BEGIN
    IF (NEW.id_user IS NULL) = (NEW.lite_patient_id IS NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cmedic: exactamente uno de id_user / lite_patient_id debe estar presente (XOR).';
    END IF;
END //
DELIMITER ;

-- 1b) CHECK (forward-compat 8.0.16+) + 2) atestación por producción --------------------
DROP PROCEDURE IF EXISTS crewcare_beta_refin_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_beta_refin_2026_07_24()
BEGIN
    -- CHECK: en 5.7 se descarta (no queda en TABLE_CONSTRAINTS, no enforza); en 8.0.16+ se
    -- almacena y enforza. La guarda por TABLE_CONSTRAINTS evita el "duplicate check" en 8.0 al
    -- re-correr; en 5.7 nunca aparece, pero re-añadirlo es inocuo (se vuelve a descartar).
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic'
          AND CONSTRAINT_NAME='chk_cmedic_patient_xor' AND CONSTRAINT_TYPE='CHECK') THEN
        ALTER TABLE `cmedic` ADD CONSTRAINT `chk_cmedic_patient_xor`
            CHECK ((`id_user` IS NULL) <> (`lite_patient_id` IS NULL));
    END IF;

    -- production_id en la atestación
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinic_attestations' AND COLUMN_NAME='production_id') THEN
        ALTER TABLE `clinic_attestations` ADD COLUMN `production_id` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `user_id`;
    END IF;

    -- Cambiar la unicidad de (user_id) a (user_id, production_id).
    -- ORDEN DURO: primero AÑADIR la compuesta (su columna líder es user_id, así que puede
    -- sostener la FK clinic_attestations_user_fk), y SÓLO ENTONCES soltar la simple. Al revés,
    -- MySQL rechaza el DROP con errno 1553 ("needed in a foreign key constraint").
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinic_attestations'
          AND INDEX_NAME='clinic_attestations_user_prod_unique') THEN
        ALTER TABLE `clinic_attestations`
            ADD UNIQUE KEY `clinic_attestations_user_prod_unique` (`user_id`,`production_id`);
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinic_attestations'
          AND INDEX_NAME='clinic_attestations_user_unique') THEN
        ALTER TABLE `clinic_attestations` DROP INDEX `clinic_attestations_user_unique`;
    END IF;
END //
DELIMITER ;
CALL crewcare_beta_refin_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_beta_refin_2026_07_24;
