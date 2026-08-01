-- ============================================================================
-- CrewCare — CATÁLOGO ÚNICO DE "EVENTOS POSIBLES" + homologación (2026-07-13)
--
-- Un solo catálogo de eventos realistas de seguridad, etiquetado por CONTEXTO
-- (locación / set de filmación / construcción / adaptación de foros y sets /
-- transversal) y por CATEGORÍA (misma taxonomía que el Scouting: eléctrico,
-- alturas, fuego, etc.), y LIGADO N:N a sus normas equivalentes del catálogo
-- normativo `safety_standards` (CSATF + STPS + OSHA a la vez). Los 5 reportes
-- (Daily, Hazard, Cond. Insegura, Lesión, Scouting) comparten este catálogo:
-- al elegir un evento se auto-etiqueta su norma (snapshot badge/code) y se
-- adjuntan sus normas al pivote polimórfico `standardables` ya existente.
--
-- Solo ESQUEMA (tablas + columnas). Modelos/seeders son DEFENSIVOS
-- (Schema::hasTable / hasColumn): las pantallas siguen funcionando aunque estas
-- estructuras aún no existan; al aplicar este delta las features se activan solas.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). Correr en un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-13-hazard-events-catalog.sql
-- Después:  php artisan db:seed --class=SafetyCatalogSeeder   (normas nuevas)
--           php artisan db:seed --class=HazardEventSeeder     (eventos + N:N)
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) los ALTER se guardan con un check en information_schema vía
-- un procedimiento temporal. Las tablas usan `CREATE TABLE IF NOT EXISTS`.
--
-- Convención del repo: BIGINT UNSIGNED, sin FK dura en los pivotes polimórficos.
-- `hazard_event_standard` es un pivote N:N clásico (no polimórfico) → los índices
-- UNIQUE evitan duplicados; la integridad de borrado la cuida la app (modelos).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) hazard_events — el catálogo de eventos posibles (fuente única de verdad).
--    `context`  : location | film_set | construction | set_build | transversal
--    `category` : taxonomía Scouting (access, electrical, fire, heights, water,
--                 traffic, weather, hazmat, structural, confined, biological,
--                 crowd, special) — NULL permitido para eventos sin categoría.
--    `default_likelihood` (A–E) / `default_consequence` (1–5): severidad TÍPICA
--                 del evento (opcional; puede pre-sugerir el nivel de riesgo).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hazard_events` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(20)  NOT NULL,
    `context`             VARCHAR(30)  NOT NULL,
    `category`            VARCHAR(30)  NULL,
    `name_es`             VARCHAR(255) NOT NULL,
    `name_en`             VARCHAR(255) NULL,
    `description_es`      VARCHAR(600) NULL,
    `description_en`      VARCHAR(600) NULL,
    `default_likelihood`  CHAR(1)      NULL,
    `default_consequence` TINYINT      NULL,
    `sort_order`          INT          NOT NULL DEFAULT 0,
    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`          DATETIME     NULL DEFAULT NULL,
    `updated_at`          DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `hazard_events_code_unique` (`code`),
    KEY `hazard_events_context_idx`  (`context`),
    KEY `hazard_events_category_idx` (`category`),
    KEY `hazard_events_active_idx`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) hazard_event_standard — pivote N:N evento ↔ norma equivalente.
--    UNIQUE(evento, norma) → idempotente al sembrar. BIGINT sin FK dura (misma
--    convención que standardables); el modelo purga vínculos al borrar.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hazard_event_standard` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hazard_event_id`    BIGINT UNSIGNED NOT NULL,
    `safety_standard_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `hazard_event_standard_unique` (`hazard_event_id`, `safety_standard_id`),
    KEY `hazard_event_standard_event_idx` (`hazard_event_id`),
    KEY `hazard_event_standard_std_idx`   (`safety_standard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) Columna snapshot `hazard_event_id` en los reportes que guardan UN evento
--    a nivel fila (Hazard, Cond. Insegura, Lesión) y en daily_logs (bitácora).
--    El Scouting guarda el evento POR FILA dentro de su JSON `risk_assessment`
--    (campo event_id/event_name) → no necesita columna. Idempotente vía proc.
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crewcare_hazard_events_2026_07_13;

DELIMITER //
CREATE PROCEDURE crewcare_hazard_events_2026_07_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazardnotifications' AND COLUMN_NAME='hazard_event_id') THEN
        ALTER TABLE `hazardnotifications` ADD COLUMN `hazard_event_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `hazardnotifications` ADD KEY `hazardnotifications_event_idx` (`hazard_event_id`);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unsafeconds' AND COLUMN_NAME='hazard_event_id') THEN
        ALTER TABLE `unsafeconds` ADD COLUMN `hazard_event_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `unsafeconds` ADD KEY `unsafeconds_event_idx` (`hazard_event_id`);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='injury_reports' AND COLUMN_NAME='hazard_event_id') THEN
        ALTER TABLE `injury_reports` ADD COLUMN `hazard_event_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `injury_reports` ADD KEY `injury_reports_event_idx` (`hazard_event_id`);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_logs' AND COLUMN_NAME='hazard_event_id') THEN
        ALTER TABLE `daily_logs` ADD COLUMN `hazard_event_id` BIGINT UNSIGNED NULL;
        ALTER TABLE `daily_logs` ADD KEY `daily_logs_event_idx` (`hazard_event_id`);
    END IF;
END //
DELIMITER ;

CALL crewcare_hazard_events_2026_07_13();
DROP PROCEDURE IF EXISTS crewcare_hazard_events_2026_07_13;
