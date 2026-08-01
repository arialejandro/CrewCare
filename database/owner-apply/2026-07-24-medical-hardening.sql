-- =====================================================================================
--  PASO 1/3 · Módulo Médico — ENDURECIMIENTO (snapshot de cédula + sello + registro de acceso)
--  Fecha: 2026-07-24
--
--  CONTENIDO:
--    cmedic (consulta médica) — 4 columnas nullable:
--      · uuid                  → identificador público estable para la cadena CFDI del sello
--                                (lo asigna GeneratesUuidKey al crear; NUNCA entra al hash).
--      · medic_cedula          → SNAPSHOT del número de cédula de quien atendió, CONGELADO al
--                                momento de crear la consulta (item 4). Deja de resolverse en vivo.
--      · medic_name            → SNAPSHOT del nombre del médico que atendió (congelado).
--      · medic_cedula_verified → estado de la cédula en ese momento: 1=verificada, 0=pendiente,
--                                NULL=el autor no tenía cédula/credencial.
--
--    medical_access_grants (NUEVA tabla, item 7) — bitácora de OTORGAMIENTO de acceso médico
--      DIRECTO a una persona (Spatie guarda el permiso en model_has_permissions; esta tabla es
--      la TRAZABILIDAD: quién lo otorgó/revocó y cuándo). Sin FK dura (convención del repo).
--
--  POR QUÉ:
--    - El sello SHA de la consulta (HasDigitalSignatures) debe cubrir QUIÉN atendió. Si la cédula
--      se resolviera en vivo, cambiarla después alteraría "quién atendió" SIN mover el hash. El
--      snapshot (medic_cedula/medic_name/medic_cedula_verified) entra al payload firmado → queda
--      cubierto por el sello (item 4 + 5).
--    - El acceso médico del safety-officer deja de venir del ROL: se otorga a la PERSONA cuando
--      hay confianza del proyecto, solo el super-admin, con registro (item 7).
--
--  ⚠ SELLO / HASH (req del paso): las 4 columnas de cmedic nacen NULL. El modelo cmedic excluye
--    del payload del hash las columnas de snapshot CUANDO son null (canonicalSignaturePayload +
--    NULLABLE_HASH_EXCLUDES). `uuid` SIEMPRE se excluye (columna volátil del trait). Una consulta
--    sellada con snapshot (valor no-null) SÍ lo hashea → queda protegida. Hoy no hay consultas
--    selladas, así que nada queda "ALTERADO".
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). MySQL 5.7: sin ADD COLUMN IF NOT EXISTS,
--    por eso el wrapper information_schema.COLUMNS dentro de un PROCEDURE (idempotente).
--
--  REVERSIÓN:
--    ALTER TABLE `cmedic` DROP COLUMN `uuid`, DROP COLUMN `medic_cedula`,
--        DROP COLUMN `medic_name`, DROP COLUMN `medic_cedula_verified`;
--    DROP TABLE IF EXISTS `medical_access_grants`;
--    (Las firmas ya emitidas quedan en digital_signatures; sin las columnas, verifyLatestSignature
--     de esas consultas daría "ALTERADO" hasta re-sellar. En local no hay consultas selladas aún.)
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_medical_hardening_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_medical_hardening_2026_07_24()
BEGIN
    -- ---------- cmedic: uuid + snapshot de cédula ----------
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `cmedic` ADD COLUMN `uuid` VARCHAR(36) NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='medic_cedula') THEN
        ALTER TABLE `cmedic` ADD COLUMN `medic_cedula` VARCHAR(40) NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='medic_name') THEN
        ALTER TABLE `cmedic` ADD COLUMN `medic_name` VARCHAR(255) NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='medic_cedula_verified') THEN
        ALTER TABLE `cmedic` ADD COLUMN `medic_cedula_verified` TINYINT(1) NULL DEFAULT NULL;
    END IF;

    -- Índice para cotejar el sello por uuid (cadena CFDI pública).
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND INDEX_NAME='cmedic_uuid_index') THEN
        ALTER TABLE `cmedic` ADD INDEX `cmedic_uuid_index` (`uuid`);
    END IF;
END //
DELIMITER ;
CALL crewcare_medical_hardening_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_medical_hardening_2026_07_24;

-- ---------- medical_access_grants: bitácora de otorgamiento (item 7) ----------
-- IF NOT EXISTS: MySQL 5.7 sí soporta CREATE TABLE IF NOT EXISTS (a diferencia de ADD COLUMN).
CREATE TABLE IF NOT EXISTS `medical_access_grants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `permission` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medical.view',
  `granted_by_id` bigint(20) unsigned DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `revoked_by_id` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `medical_access_grants_user_idx` (`user_id`),
  KEY `medical_access_grants_active_idx` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- item 7: quitar medical.view del ROL safety-officer (Spatie role_has_permissions) ----------
-- El seeder ya nace sin él (fresh installs), pero una instancia EXISTENTE conserva el grant en el
-- pivote. Se retira aquí (idempotente: borra 0 ó 1 fila). El acceso clínico del safety-officer pasa a
-- otorgarse DIRECTO a la persona (medical_access_grants + model_has_permissions), solo por super-admin.
-- ⚠ TRAS APLICAR: limpiar la caché de permisos de Spatie para que la app olvide la matriz vieja:
--     php artisan permission:cache-reset      (o  php artisan cache:clear)
DELETE rhp FROM `role_has_permissions` rhp
  JOIN `roles` r       ON r.id = rhp.role_id
  JOIN `permissions` p ON p.id = rhp.permission_id
  WHERE r.`name` = 'safety-officer' AND p.`name` = 'medical.view';
