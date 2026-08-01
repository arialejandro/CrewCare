-- =====================================================================================
--  PIEZA 3 (corrida 2/2) · Expediente clínico — INMUTABILIDAD, ANEXOS y CONSENTIMIENTO
--  Fecha: 2026-07-24
--
--  ⚠ CORRER DESPUÉS de `2026-07-24-health-record-fixes.sql`. Ese estabiliza las columnas
--    (peso/talla nullable, fecha de influenza, fuera crt19); éste EMPIEZA A SELLARLAS. Al
--    revés, el primer ALTER movería el payload firmado y los expedientes ya sellados se
--    auto-acusarían de "ALTERADO".
--
--  CONTENIDO
--    1) `formularios`  + `uuid` CHAR(36) UNIQUE      → verificador público por QR
--    2) tabla nueva    `health_record_addendums`     → anexos del médico (append-only)
--    3) tabla nueva    `privacy_consents`            → aviso de privacidad aceptado
--    4) `cmedic`       + 3 columnas `intake_*`       → qué versión del expediente vio el médico
--
--  ---------------------------------------------------------------------------------
--  1) uuid en `formularios`
--     Lo pide el verificador público (SealVerifier resuelve por (tipo, uuid), nunca por id
--     autoincremental). El trait de firmas EXCLUYE `uuid` del hash, así que añadirlo no mueve
--     ningún sello. Las filas existentes reciben uuid en el mismo paso: es un IDENTIFICADOR,
--     no una afirmación — darles uuid no dice que estén selladas, y de hecho NO se sellan
--     (ver abajo).
--
--     ⚠ LOS EXPEDIENTES VIEJOS NO SE SELLAN RETROACTIVAMENTE. Sellar hoy una declaración de
--     enero de 2024 afirmaría "este contenido está congelado desde entonces", que es falso:
--     nadie puede saber si cambió entre medias. `verifyLatestSignature()` devuelve null y el
--     documento se muestra honestamente como SIN SELLO. Un sello que miente vale menos que
--     ninguno.
--
--  ---------------------------------------------------------------------------------
--  2) `health_record_addendums` — el expediente NO se edita, se ANEXA
--     Decisión del owner: nadie edita el expediente, tampoco su titular. Si fuera editable,
--     alguien podría ocultar una enfermedad crónica retroactivamente ante un seguro o una
--     reclamación; el expediente existe para demostrar QUÉ SE DECLARÓ Y CUÁNDO.
--
--     Pero un tipo de sangre equivocado congelado para siempre puede MATAR a una persona. Por
--     eso el anexo NO es una comodidad, es un requisito de seguridad — y lo crea SÓLO un
--     médico, tras valorar, como en un expediente clínico real: el paciente no edita su
--     historia, el profesional la actualiza y lo documenta.
--
--     `changes` es JSON campo→valor nuevo. NO se sobrescribe nada: el original queda intacto
--     con su hash, y la ficha muestra el estado VIGENTE (original + anexos aplicados) más la
--     traza cronológica. Efecto disuasorio deliberado: un intento de ocultar algo quedaría
--     visible y fechado JUNTO al original que dice lo contrario.
--
--     El snapshot de cédula (medic_*) se CONGELA aquí igual que en `cmedic`: quién anexó, con
--     qué cédula y si estaba verificada EN ESE MOMENTO. Si el médico pierde la verificación
--     mañana, el anexo de ayer sigue diciendo la verdad de ayer.
--
--  ---------------------------------------------------------------------------------
--  3) `privacy_consents` — no se recaba un dato de salud sin consentimiento
--     Hoy la app NO tiene aviso, ni términos, ni consentimiento, ni tabla — y captura
--     tabaquismo, alcoholismo, toxicomanías y antecedentes familiares de cáncer. En México
--     los datos de salud son datos personales SENSIBLES y requieren consentimiento EXPRESO
--     del titular ANTES de recabarlos.
--
--     `version` es lo que da valor legal: prueba QUÉ texto aceptó la persona. Por eso el
--     consentimiento no cabe en una columna booleana de `users` — al publicar un texto nuevo
--     se perdería el histórico de quién aceptó el anterior.
--
--     UNIQUE (user_id, version): se acepta una vez por versión. Publicar una versión nueva
--     vuelve a preguntar a todos, solo, sin tocar código.
--
--     ⚠ ESTA TABLA ES TAMBIÉN EL DETECTOR DE "PRIMER LOGIN". La app no tiene ninguno:
--     `users` no tiene `first_login` ni `password_changed_at`, y `email_verified_at` está
--     muerto (User importa MustVerifyEmail y no lo implementa; 0 de 92 usuarios lo tienen).
--     No hacía falta inventar una columna: NO TENER FILA VIGENTE *ES* estar pendiente. Sirve
--     igual para los 92 existentes que para quien se dé de alta mañana.
--
--  ---------------------------------------------------------------------------------
--  4) `cmedic.intake_*` — qué versión del expediente vio el médico esa noche
--     Si el 3 de agosto se atendió sin alergias registradas y el 5 se anexa "penicilina", la
--     consulta del 3 tiene que poder demostrar QUÉ VIO EL MÉDICO. Se guardan tres cosas:
--       · intake_formulario_id  el expediente consultado
--       · intake_addendum_id    el ÚLTIMO anexo vigente en ese instante (NULL = el original)
--       · intake_hash           SHA-256 del estado aplicado que se le mostró
--     Con (formulario_id, addendum_id) el estado es RECONSTRUIBLE exactamente, porque los
--     anexos nunca borran nada; el hash permite probar que la reconstrucción es la buena.
--
--     ⚠ Las 3 nacen NULL y van en `cmedic::NULLABLE_HASH_EXCLUDES` → las consultas YA
--     SELLADAS conservan su hash y NO se marcan "ALTERADO". Las nuevas sí las hashean, así
--     que el sello de la consulta cubre también qué expediente vio.
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). Idempotente.
--
--  REVERSIÓN:
--    DROP TABLE IF EXISTS `health_record_addendums`;
--    DROP TABLE IF EXISTS `privacy_consents`;
--    ALTER TABLE `cmedic` DROP COLUMN `intake_formulario_id`,
--                         DROP COLUMN `intake_addendum_id`,
--                         DROP COLUMN `intake_hash`;
--    ALTER TABLE `formularios` DROP INDEX `formularios_uuid_unique`, DROP COLUMN `uuid`;
--    Los 3 expedientes existentes quedan intactos (sólo pierden el uuid, que nadie más usa).
--    Las firmas de expedientes sellados quedarían huérfanas en `digital_signatures`; se
--    limpian con:  DELETE FROM `digital_signatures` WHERE `documentable_type` LIKE '%formulario%';
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_health_seal_consent_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_health_seal_consent_2026_07_24()
BEGIN
    -- 1) uuid del expediente ---------------------------------------------------------
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formularios' AND COLUMN_NAME='uuid') THEN
        ALTER TABLE `formularios` ADD COLUMN `uuid` CHAR(36)
            COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `id_user`;
        -- Filas existentes: identificador, no sello. UUID() de MySQL es v1 (basado en MAC y
        -- reloj) frente al v4 aleatorio que genera la app; para 3 filas históricas que nadie
        -- va a verificar por QR es irrelevante, y evita depender de PHP para el relleno.
        UPDATE `formularios` SET `uuid` = UUID() WHERE `uuid` IS NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='formularios'
          AND INDEX_NAME='formularios_uuid_unique') THEN
        ALTER TABLE `formularios` ADD UNIQUE KEY `formularios_uuid_unique` (`uuid`);
    END IF;

    -- 4) trazabilidad del intake en la consulta ---------------------------------------
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='intake_formulario_id') THEN
        ALTER TABLE `cmedic` ADD COLUMN `intake_formulario_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='intake_addendum_id') THEN
        ALTER TABLE `cmedic` ADD COLUMN `intake_addendum_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='intake_hash') THEN
        ALTER TABLE `cmedic` ADD COLUMN `intake_hash` CHAR(64)
            COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
    END IF;
END //
DELIMITER ;
CALL crewcare_health_seal_consent_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_health_seal_consent_2026_07_24;

-- 2) Anexos del médico (append-only) --------------------------------------------------
CREATE TABLE IF NOT EXISTS `health_record_addendums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formulario_id` bigint(20) unsigned NOT NULL,
  -- Qué clase de anexo es. Lista cerrada en el modelo (MOTIVOS): corrección de dato,
  -- vacuna nueva, condición detectada, otro.
  `reason` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'correccion',
  -- JSON campo→valor nuevo. Sólo campos de la lista blanca del modelo.
  --
  -- ⚠ SE LLAMA `changed_fields` Y NO `changes` POR UNA RAZÓN DURA, no por estilo.
  -- Eloquent (HasAttributes) declara `protected $changes = []` para rastrear qué cambió tras un
  -- save. Una columna llamada `changes` convive con esa propiedad sin chocar… hasta que se lee
  -- desde OTRO modelo: como ambos heredan de Model, PHP considera legítimo el acceso al miembro
  -- PROTEGIDO y devuelve el arreglo interno de Eloquent (vacío) EN LUGAR de pasar por __get() y
  -- aplicar el cast. Resultado: `$anexo->changes` daba el JSON correcto desde una vista o un
  -- script, y un array VACÍO desde dentro de formulario::aplicados(). Los anexos "se guardaban
  -- bien" y simplemente no se aplicaban. Falla en silencio y sólo en un contexto.
  `changed_fields` text COLLATE utf8mb4_unicode_ci,
  -- El POR QUÉ, en palabras del médico. Obligatorio: un cambio sin motivo no es un anexo
  -- clínico, es una edición disfrazada.
  `notes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  -- Snapshot CONGELADO de quién anexó (mismo patrón que cmedic).
  `medic_cedula` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medic_cedula_verified` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `health_record_addendums_uuid_unique` (`uuid`),
  KEY `health_record_addendums_formulario_idx` (`formulario_id`),
  KEY `health_record_addendums_medic_idx` (`created_by_id`),
  CONSTRAINT `health_record_addendums_formulario_fk` FOREIGN KEY (`formulario_id`)
      REFERENCES `formularios` (`id_formulario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Consentimiento del aviso de privacidad -------------------------------------------
CREATE TABLE IF NOT EXISTS `privacy_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  -- QUÉ texto aceptó. Es el campo que da valor legal al registro.
  `version` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `privacy_consents_user_version_unique` (`user_id`,`version`),
  KEY `privacy_consents_user_idx` (`user_id`),
  CONSTRAINT `privacy_consents_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
