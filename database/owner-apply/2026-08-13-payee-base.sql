-- ============================================================================
-- CrewCare — BASE ÚNICA DE QUIEN COBRA (Paso 1: LA ENTIDAD).
-- (2026-08-13) — delta. Fundación del bloque "quien cobra": una identidad fiscal
--   con varios contratos, más el catálogo de tipos de documento (clave).
--
-- QUÉ ES (3 tablas nuevas + 1 ALTER, dos niveles):
--   NIVEL 1 · `payees`           — la IDENTIDAD (persona FÍSICA o MORAL). Datos
--                                   fiscales DE LA PERSONA (RFC, país, banco/CLABE)
--                                   y liga OPCIONAL a `users` (si quien cobra es crew).
--   NIVEL 2 · `payee_contracts`  — el CONTRATO / concepto de cobro. VARIOS por
--                                   identidad → así se logra el N:M "quién contrató"
--                                   (cada contrato lleva su `contracted_by_user_id`).
--                                   REPSE aplica AQUÍ (al contrato), no a la persona.
--   CATÁLOGO · `document_types`   — `document_type` deja de ser TEXTO LIBRE: pasa a
--                                   catálogo con CLAVE ("32D" y "Opinión SAT" = el
--                                   mismo documento). Guarda la forma de vigencia y si
--                                   exige estado positivo (32-D) — DATO, no código.
--   ALTER   · `external_authorizations` — se EXTIENDE el ledger ya existente (no se
--                                   duplica): + `document_type_id` (FK-soft al catálogo),
--                                   + `issued_at` (fecha de emisión, para calcular
--                                   caducidad) y + `result_status` (estado requerido,
--                                   p.ej. 32-D positiva/negativa).
--
-- ── POR QUÉ TABLAS PROPIAS Y NO `production_user` ────────────────────────────
--   `production_user` tiene `unique(production_id, user_id)` (una fila por persona)
--   → PROHÍBE el N:M "una identidad contratada por varios departamentos". Ese pivote
--   se queda INTACTO para la membresía del crew. La visibilidad (Paso 4) se filtra
--   por `payee_contracts.contracted_by_user_id` con un HERMANO de applyDepartmentScope.
--
-- ── FUERA DE ESTE PASO (por diseño) ──────────────────────────────────────────
--   · RÉGIMEN FISCAL: se REPORTA la cardinalidad y se ESPERA — no se crea aquí.
--   · CURP / NSS: NO son campo de identidad; solo existen atados al régimen REPSE
--     (listado de personal), que se construye en Paso 2. No van en `payees`.
--   · Paquetes por producción, toggles antes/después, fecha de corte de la 32-D:
--     Paso 2. Aquí solo va el catálogo maestro de tipos.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). `notes`/`concept`/`scope` son seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS + ALTER guardado por information_schema (no hay
--   ADD COLUMN IF NOT EXISTS en 5.7).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-payee-base.sql
--   Luego: php artisan db:seed --class=DocumentTypeSeeder   (catálogo de tipos)
--          php artisan cache:clear
-- Referencias BLANDAS (sin FK dura) a users/productions/document_types: son
--   registros que sobreviven bajas y cambios de catálogo.
-- ============================================================================

-- NIVEL 1 · IDENTIDAD — una sola por persona o empresa. ------------------------
CREATE TABLE IF NOT EXISTS `payees` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `legal_nature`           VARCHAR(20)  NOT NULL,          -- fisica | moral (primera bifurcación)
    `name`                   VARCHAR(255) NOT NULL,          -- nombre completo / razón social
    `user_id`                BIGINT UNSIGNED NULL,           -- FK-soft a users: si quien cobra es crew, es la MISMA persona
    -- Datos fiscales DE LA PERSONA (no del contrato) --------------------------
    `rfc`                    VARCHAR(20)  NULL,
    `tax_residence_country`  VARCHAR(80)  NULL,              -- país de residencia fiscal
    `bank_name`              VARCHAR(120) NULL,              -- banco
    `bank_branch`            VARCHAR(120) NULL,              -- sucursal de apertura
    `bank_account`           VARCHAR(40)  NULL,              -- cuenta
    `bank_clabe`             VARCHAR(24)  NULL,              -- CLABE
    -- (régimen fiscal: OMITIDO a propósito — se reporta cardinalidad y se espera)
    `notes`                  TEXT         NULL,
    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`             INT          NOT NULL DEFAULT 0,-- listados ordenan por aquí, no por id
    `created_by_id`          BIGINT UNSIGNED NULL,
    `created_at`             TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payees_nature_idx`  (`legal_nature`),
    KEY `payees_user_idx`    (`user_id`),
    KEY `payees_active_idx`  (`is_active`),
    KEY `payees_sort_idx`    (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NIVEL 2 · CONTRATO / CONCEPTO DE COBRO — varios por identidad. ---------------
CREATE TABLE IF NOT EXISTS `payee_contracts` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payee_id`               BIGINT UNSIGNED NOT NULL,       -- FK-soft a payees (la identidad)
    `production_id`          BIGINT UNSIGNED NULL,           -- FK-soft (CurrentProduction; instancia mono-producción)
    `concept`                VARCHAR(30)  NOT NULL DEFAULT 'service',  -- crew_work | equipment_rental | service
    `title`                  VARCHAR(255) NULL,              -- descripción ("Renta Starlink")
    -- QUIÉN CONTRATA: el scope (Paso 4) resuelve su departamento vía production_user.
    `contracted_by_user_id`  BIGINT UNSIGNED NULL,           -- FK-soft a users
    `payment_frequency`      VARCHAR(30)  NULL,              -- heredada por defecto; SE GUARDA, no se consume aún
    `is_repse`               TINYINT(1)   NOT NULL DEFAULT 0,-- el régimen REPSE aplica al CONTRATO
    `notes`                  TEXT         NULL,
    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`             INT          NOT NULL DEFAULT 0,
    `created_by_id`          BIGINT UNSIGNED NULL,
    `created_at`             TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payee_contracts_payee_idx`      (`payee_id`),
    KEY `payee_contracts_contractor_idx` (`contracted_by_user_id`),
    KEY `payee_contracts_prod_idx`       (`production_id`),
    KEY `payee_contracts_active_idx`     (`is_active`),
    KEY `payee_contracts_sort_idx`       (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CATÁLOGO · TIPOS DE DOCUMENTO — clave, no texto libre. ----------------------
CREATE TABLE IF NOT EXISTS `document_types` (
    `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                      VARCHAR(60)  NOT NULL,       -- CLAVE única ('32D','CSF','INE'...)
    `name`                      VARCHAR(255) NOT NULL,
    `family`                    VARCHAR(20)  NOT NULL DEFAULT 'billing',  -- billing(cobro) | operate(operar)
    `scope`                     VARCHAR(20)  NOT NULL DEFAULT 'identity',  -- identity | contract | person
    `legal_nature`              VARCHAR(20)  NULL,           -- fisica | moral | ambas (a quién aplica)
    `validity_shape`            VARCHAR(30)  NULL,           -- days_from_emission | current_month | quarterly | permanent
    `validity_days`             INT          NULL,           -- p.ej. 90
    `requires_positive_status`  TINYINT(1)   NOT NULL DEFAULT 0,  -- 32-D exige POSITIVA
    `is_repse`                  TINYINT(1)   NOT NULL DEFAULT 0,
    `repse_phase`               VARCHAR(10)  NULL,           -- before | after (solo REPSE)
    `is_active`                 TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`                INT          NOT NULL DEFAULT 0,
    `created_at`                TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`                TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `document_types_code_unique` (`code`),
    KEY `document_types_family_scope_idx` (`family`, `scope`),
    KEY `document_types_active_idx`       (`is_active`),
    KEY `document_types_sort_idx`         (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ALTER · EXTENDER el ledger `external_authorizations` (NO duplicar). ----------
--   + document_type_id (clave del catálogo) · + issued_at (fecha de emisión)
--   · + result_status (estado requerido: 32-D positiva/negativa). Idempotente.
DROP PROCEDURE IF EXISTS crewcare_extend_external_auth_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_extend_external_auth_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='external_authorizations' AND COLUMN_NAME='document_type_id') THEN
        ALTER TABLE `external_authorizations`
            ADD COLUMN `document_type_id` BIGINT UNSIGNED NULL AFTER `document_type`,
            ADD KEY `external_auth_doctype_idx` (`document_type_id`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='external_authorizations' AND COLUMN_NAME='issued_at') THEN
        ALTER TABLE `external_authorizations`
            ADD COLUMN `issued_at` DATE NULL AFTER `valid_until`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='external_authorizations' AND COLUMN_NAME='result_status') THEN
        ALTER TABLE `external_authorizations`
            ADD COLUMN `result_status` VARCHAR(20) NULL AFTER `status`;
    END IF;
END //
DELIMITER ;
CALL crewcare_extend_external_auth_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_extend_external_auth_2026_08_13;

-- REVERSIÓN (manual):
--   DROP TABLE `payee_contracts`; DROP TABLE `payees`; DROP TABLE `document_types`;
--   ALTER TABLE `external_authorizations`
--     DROP COLUMN `document_type_id`, DROP COLUMN `issued_at`, DROP COLUMN `result_status`;
