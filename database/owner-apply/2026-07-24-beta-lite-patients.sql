-- =====================================================================================
--  MÓDULO BETA · REGISTRO LITE DE PACIENTE + SELLADO VERSIONADO DE LA CONSULTA
--  Fecha: 2026-07-24
--
--  ⚠ ORDEN: CORRER DESPUÉS de `2026-07-24-health-record-seal-consent.sql` (el que añade
--    los `intake_*` a `cmedic`). Este toca `cmedic` para admitir pacientes que NO son
--    usuarios del crew, y EMPIEZA A VERSIONAR el sello — sin la base anterior, los checks
--    de columna fallarían.
--
--  QUÉ Y POR QUÉ
--  -------------
--  El módulo médico solo sabía atender a `users` del crew. Los extras, day players,
--  visitantes y proveedores —la población que MÁS atención médica necesita— no existen en
--  `users` y quedaban fuera. Aquí entra el REGISTRO LITE: una persona registrable en el
--  momento de la consulta, REUTILIZABLE (vuelve otros días y se reencuentra, no se re-teclea),
--  sin cuenta, sin contraseña, sin rol, sin login.
--
--  1) `lite_patients`  — la persona no-crew. Sin uuid ni sello: NO es un documento que se
--     verifique por QR, es un directorio. Lo sellado sigue siendo la CONSULTA.
--     `merged_into_id` (auto-referencia) resuelve la FUSIÓN de duplicados SIN reescribir
--     consultas: la fila duplicada se marca "fundida en X" y su identidad se resuelve al
--     grupo, pero cada consulta CONSERVA su lite_patient_id original → su sello no se mueve.
--
--  2) `cmedic` — tres cambios:
--     a) `id_user` pasa a NULLABLE **conservando su FK** `cmedic_ibfk_1`→`users`. Una consulta
--        de crew sigue exigiendo integridad referencial; una consulta lite lleva id_user NULL.
--     b) `lite_patient_id` BIGINT NULL + FK a `lite_patients`. La app garantiza el XOR (una y
--        solo una de las dos referencias presente); MySQL 5.7 IGNORA los CHECK, así que la
--        invariante vive en el modelo/FormRequest, no en el motor.
--     c) `seal_version` TINYINT DEFAULT 1. VERSIONA EL ALGORITMO DE SELLADO.
--        · Las filas EXISTENTES quedan en v1 (default) — su algoritmo NO conoce lite_patient_id
--          ni seal_version, así que su hash NO se mueve y NO salen "ALTERADO".
--        · Las filas NUEVAS se sellan en v2, que SÍ incluye lite_patient_id (la identidad del
--          paciente entra al sello: no se puede intercambiar sin romper el hash).
--        Cada fila DECLARA su versión y el verificador usa esa versión para comprobarla
--        (cmedic::canonicalSignaturePayload). Un sello que no ata al paciente sería un sello que
--        miente; por eso se VERSIONA en vez de EXCLUIR la columna del hash.
--        seal_version NUNCA entra en el hash (es metadato del hash); flipearla cambia el
--        algoritmo de recomputo → el hash deja de casar → la fila se marca ALTERADA (tamper-evidente).
--
--  3) `medications.beta_flagged` — cada variante que teclea un clínico beta se marca, para
--     revisarla/fusionarla después. NO se bloquea la creación (interrumpir al médico es peor).
--
--  4) `clinic_attestations` — ATESTACIÓN ÚNICA del aviso. El clínico declara UNA VEZ que el
--     aviso está disponible en el puesto médico; se guarda su cédula CONGELADA, fecha/hora y la
--     VERSIÓN vigente del aviso. NO bloquea la consulta: si falta, la consulta se guarda igual y
--     queda pendiente para administración (derivado: clínico con consultas y sin atestación).
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). Idempotente.
--
--  REVERSIÓN:
--    DROP TABLE IF EXISTS `clinic_attestations`;
--    ALTER TABLE `medications` DROP COLUMN `beta_flagged`;
--    ALTER TABLE `cmedic` DROP FOREIGN KEY `cmedic_lite_patient_fk`;
--    ALTER TABLE `cmedic` DROP COLUMN `lite_patient_id`, DROP COLUMN `seal_version`;
--    ALTER TABLE `cmedic` MODIFY `id_user` BIGINT(20) UNSIGNED NOT NULL;  -- (solo si NO hay filas lite)
--    DROP TABLE IF EXISTS `lite_patients`;
--    Firmas de consultas lite quedarían huérfanas; se limpian por documentable_type/id si hiciera falta.
-- =====================================================================================

-- 1) Persona no-crew (directorio reutilizable) ----------------------------------------
CREATE TABLE IF NOT EXISTS `lite_patients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  -- Puesto o área: TEXTO LIBRE (un extra no cabe en el catálogo de puestos del crew).
  `area` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  -- PROCEDENCIA: un solo campo abierto, sin catálogo ni validación. Alimenta la futura
  -- investigación de industria; hoy es texto y nada más.
  `origin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  -- FUSIÓN: si esta fila es un duplicado, apunta a la SUPERVIVIENTE. La identidad se resuelve
  -- al grupo (superviviente + fundidas), pero las consultas NO se reescriben (sello intacto).
  `merged_into_id` bigint(20) unsigned DEFAULT NULL,
  -- Quién la registró (el clínico). Referencia blanda, sin FK (convención de la casa).
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lite_patients_name_idx` (`full_name`),
  KEY `lite_patients_phone_idx` (`phone`),
  KEY `lite_patients_merged_idx` (`merged_into_id`),
  KEY `lite_patients_creator_idx` (`created_by_id`),
  CONSTRAINT `lite_patients_merged_fk` FOREIGN KEY (`merged_into_id`)
      REFERENCES `lite_patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) cmedic: id_user nullable (CONSERVA FK) + lite_patient_id (+FK) + seal_version ----
DROP PROCEDURE IF EXISTS crewcare_beta_lite_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_beta_lite_2026_07_24()
BEGIN
    -- a) id_user → NULLABLE, sin tocar la FK cmedic_ibfk_1 (MODIFY no la elimina).
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic'
          AND COLUMN_NAME='id_user' AND IS_NULLABLE='NO') THEN
        ALTER TABLE `cmedic` MODIFY `id_user` bigint(20) unsigned NULL DEFAULT NULL;
    END IF;

    -- b) lite_patient_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='lite_patient_id') THEN
        ALTER TABLE `cmedic` ADD COLUMN `lite_patient_id` bigint(20) unsigned NULL DEFAULT NULL AFTER `id_user`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND INDEX_NAME='cmedic_lite_patient_idx') THEN
        ALTER TABLE `cmedic` ADD KEY `cmedic_lite_patient_idx` (`lite_patient_id`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic'
          AND CONSTRAINT_NAME='cmedic_lite_patient_fk' AND CONSTRAINT_TYPE='FOREIGN KEY') THEN
        ALTER TABLE `cmedic` ADD CONSTRAINT `cmedic_lite_patient_fk`
            FOREIGN KEY (`lite_patient_id`) REFERENCES `lite_patients` (`id`);
    END IF;

    -- c) seal_version (default 1 = filas existentes; las nuevas se sellan en 2)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='seal_version') THEN
        ALTER TABLE `cmedic` ADD COLUMN `seal_version` tinyint(3) unsigned NOT NULL DEFAULT 1;
    END IF;

    -- 3) medications.beta_flagged
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medications' AND COLUMN_NAME='beta_flagged') THEN
        ALTER TABLE `medications` ADD COLUMN `beta_flagged` tinyint(1) NOT NULL DEFAULT 0;
    END IF;
END //
DELIMITER ;
CALL crewcare_beta_lite_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_beta_lite_2026_07_24;

-- 4) Atestación única del aviso (declarada por el clínico) -----------------------------
CREATE TABLE IF NOT EXISTS `clinic_attestations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  -- Snapshot CONGELADO de la cédula del clínico que atestigua (mismo patrón que cmedic).
  `medic_cedula` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  -- VERSIÓN vigente del aviso en el momento de atestiguar (PrivacyNotice::VERSION).
  `privacy_version` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attested_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Una atestación por clínico (una sola vez, nunca más). La versión queda registrada.
  UNIQUE KEY `clinic_attestations_user_unique` (`user_id`),
  CONSTRAINT `clinic_attestations_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
