-- ============================================================================
-- CrewCare — COTIZACIÓN (el paso PREVIO real: decide si se contrata o no).
-- (2026-08-17) — módulo nuevo. TODO ADITIVO. No toca ningún flujo en uso.
--
-- QUÉ ES:
--   La cotización es lo PRIMERO que existe, ANTES que la persona → NO cuelga de
--   `payees` (a esa altura puede no haber payee). Vive con CORREO + NOMBRE del emisor
--   hasta que se acepta; solo al ACEPTAR se liga a un payee (existente o externo-lite).
--   ⚠ NO crea usuario al cotizar (no llenar la base de gente no contratada).
--
--   · `quotations`         — la ENTIDAD. Producción (+ depto para visibilidad, + locación
--     y fechas opcionales). Estados recibida|en_negociacion|aceptada|rechazada. La
--     ACEPTACIÓN se sella con HasDigitalSignatures (columnas accepted_* + la autógrafa
--     en `acceptance_signature_image` entran al hash). El PDF subido NUNCA se modifica.
--   · `quotation_versions` — NEGOCIAR ES VERSIONAR (append-only): cada ajuste es una
--     versión nueva que referencia la anterior (`supersedes_id`); la anterior no se
--     altera ni borra. Dos formas, mismo objeto: `source_kind` = pdf (byte-intact, se
--     hashea en `pdf_sha256` pero NO se toca) | items. IVA incluido/no por cotización.
--   · `quotation_items`    — PARTIDAS congeladas por versión. Multiplicador OPCIONAL:
--     `days` NULL = cantidad × precio; con valor = cantidad × días × precio.
--
-- Nota: NO se confunde con `document_types.COTIZACION` (el tipo del paquete documental).
-- Al ACEPTAR, la cotización SATISFACE ese requisito del paquete del payee (no se re-pide).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7+. Idempotente (CREATE TABLE IF NOT EXISTS).
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-17-quotations.sql
--   Luego: php artisan cache:clear
-- ============================================================================

-- LA ENTIDAD ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotations` (
    `id`                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_id`              BIGINT UNSIGNED NOT NULL,           -- FK-soft a productions
    `department_id`              BIGINT UNSIGNED NULL,               -- FK-soft (visibilidad tipo payee)
    `location_name`              VARCHAR(191)  NULL,                 -- locación opcional (no hay tabla locations)
    `emitter_name`               VARCHAR(191)  NOT NULL,             -- nombre del emisor
    `emitter_email`              VARCHAR(191)  NULL,                 -- normalizado (lower/trim) al guardar
    `status`                     VARCHAR(20)   NOT NULL DEFAULT 'recibida', -- recibida|en_negociacion|aceptada|rechazada
    `current_version_id`         BIGINT UNSIGNED NULL,               -- versión activa
    `payee_id`                   BIGINT UNSIGNED NULL,               -- NULL hasta ACEPTAR
    -- Aceptación (sellada) ---------------------------------------------------
    `accepted_by_user_id`        BIGINT UNSIGNED NULL,               -- el Line Producer que aceptó
    `accepted_at`                DATETIME      NULL,
    `accepted_version_id`        BIGINT UNSIGNED NULL,               -- qué versión quedó aceptada
    `accepted_doc_hash`          VARCHAR(191)  NULL,                 -- sha256 del documento aceptado (PDF o items)
    `acceptance_signature_image` MEDIUMTEXT    NULL,                 -- autógrafa PNG base64 (entra al hash)
    `acceptance_sheet_path`      VARCHAR(255)  NULL,                 -- hoja de aceptación dompdf (derivada; NO al hash)
    `uuid`                       CHAR(36)      NULL,
    `created_by_id`              BIGINT UNSIGNED NULL,
    `created_at`                 TIMESTAMP     NULL DEFAULT NULL,
    `updated_at`                 TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `quotations_production_idx` (`production_id`),
    KEY `quotations_department_idx` (`department_id`),
    KEY `quotations_payee_idx`      (`payee_id`),
    KEY `quotations_status_idx`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NEGOCIAR ES VERSIONAR (append-only) ---------------------------------------
CREATE TABLE IF NOT EXISTS `quotation_versions` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_id`       BIGINT UNSIGNED NOT NULL,           -- FK-soft a quotations
    `version_no`         INT           NOT NULL DEFAULT 1,
    `supersedes_id`      BIGINT UNSIGNED NULL,               -- versión anterior (no se altera ni borra)
    `source_kind`        VARCHAR(8)    NOT NULL DEFAULT 'items', -- pdf | items
    -- PDF subido, byte-intact ------------------------------------------------
    `pdf_path`           VARCHAR(255)  NULL,
    `pdf_original_name`  VARCHAR(191)  NULL,
    `pdf_sha256`         CHAR(64)      NULL,                 -- prueba de qué se recibió (NO se modifica el PDF)
    -- Datos de la cotización -------------------------------------------------
    `quotation_number`   VARCHAR(60)   NULL,                 -- TEXTO LIBRE (no se genera): "2.1", "DB-2002"...
    `issued_at`          DATE          NULL,
    `valid_until`        DATE          NULL,                 -- vigencia (15/30 d en las reales)
    `payment_terms`      VARCHAR(500)  NULL,                 -- condiciones de pago (texto libre)
    `bank_details`       VARCHAR(500)  NULL,                 -- datos bancarios del emisor (si vienen)
    -- IVA declarado POR cotización ------------------------------------------
    `iva_included`       TINYINT(1)    NOT NULL DEFAULT 0,   -- 1 = los precios YA traen IVA
    `iva_rate`           DECIMAL(5,2)  NOT NULL DEFAULT 16.00,
    `subtotal`           DECIMAL(14,2) NOT NULL DEFAULT 0,   -- sin IVA
    `iva_amount`         DECIMAL(14,2) NOT NULL DEFAULT 0,
    `total`              DECIMAL(14,2) NOT NULL DEFAULT 0,   -- con IVA
    `change_note`        VARCHAR(500)  NULL,                 -- qué cambió respecto a la anterior
    `created_by_id`      BIGINT UNSIGNED NULL,
    `created_at`         TIMESTAMP     NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `quotation_versions_quotation_idx`  (`quotation_id`),
    KEY `quotation_versions_supersedes_idx` (`supersedes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PARTIDAS (congeladas por versión) -----------------------------------------
CREATE TABLE IF NOT EXISTS `quotation_items` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_version_id` BIGINT UNSIGNED NOT NULL,         -- FK-soft a quotation_versions
    `sort_order`           INT           NOT NULL DEFAULT 0,
    `description`          VARCHAR(255)  NOT NULL,           -- descripción corta
    `detail`               TEXT          NULL,               -- párrafo opcional bajo la partida
    `quantity`             DECIMAL(12,2) NOT NULL DEFAULT 1,
    `days`                 DECIMAL(12,2) NULL,               -- MULTIPLICADOR OPCIONAL (NULL = qty × precio)
    `unit_price`           DECIMAL(14,2) NOT NULL DEFAULT 0,
    `line_total`           DECIMAL(14,2) NOT NULL DEFAULT 0, -- qty × (days|1) × unit_price
    `created_at`           TIMESTAMP     NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `quotation_items_version_idx` (`quotation_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REVERSIÓN (manual): DROP TABLE quotation_items, quotation_versions, quotations;
