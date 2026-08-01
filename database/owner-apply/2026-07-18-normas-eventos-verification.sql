-- ============================================================================
-- CrewCare — Estado de VERIFICACIÓN de NORMAS y EVENTOS (2026-07-18)
-- Paso 2 (espejo del Paso 1c de las fichas SDS): una norma del catálogo
-- (`safety_standards`) y un evento posible del catálogo (`hazard_events`)
-- ganan el mismo rastro de validación que ya tienen los consumibles SDS.
-- Una norma/evento capturado sin fricción nace PENDIENTE hasta que una
-- autoridad verificadora lo valida. Este delta añade el rastro de esa
-- validación (solo columnas + backfill + índice; SIN permisos ni pantallas).
--
-- Contenido:
--   1) COL   safety_standards.verified_at     — cuándo se verificó. NULL = PENDIENTE.
--   1b) SELLADO INICIAL de la base curada: las 78 normas que ya existían al
--            aplicar el delta nacen VERIFICADAS (corre SOLO en la 1a aplicación).
--   2) COL   safety_standards.verified_by_id  — quién verificó (sin FK dura).
--   3) IDX   safety_standards_verified_idx    — sobre (verified_at), para el
--            filtro "pendientes".
--   4) COL   hazard_events.verified_at         — cuándo se verificó. NULL = PENDIENTE.
--   4b) SELLADO INICIAL de la base curada: los 120 eventos que ya existían al
--            aplicar el delta nacen VERIFICADOS (corre SOLO en la 1a aplicación).
--   5) COL   hazard_events.verified_by_id     — quién verificó (sin FK dura).
--   6) IDX   hazard_events_verified_idx        — sobre (verified_at).
--
-- ⚠ NOTA DE ORDEN — MUY IMPORTANTE:
--   Este SQL debe aplicarse ANTES de sembrar los 87 eventos nuevos del Paso 5.
--   Motivo: el backfill (1b/4b) sella con NOW() SOLO las filas que ya existen al
--   momento de crear la columna (la base curada de 78 normas / 120 eventos). Si
--   se aplica primero, esos 87 eventos nuevos nacerán con verified_at NULL, es
--   decir PENDIENTES, que es exactamente lo que se quiere: la base curada queda
--   sellada y lo nuevo entra a la cola de verificación. Si se aplicara DESPUÉS de
--   sembrarlos, el backfill los sellaría a todos por error.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO es DEFENSIVO (SafetyStandard::supportsVerification() /
-- HazardEvent::supportsVerification() envuelven Schema::hasColumn con memo
-- estático): la app corre IGUAL sin este SQL. Sin estas columnas ambos módulos
-- se comportan como "SIN ESTADO DE VERIFICACIÓN": no se pinta NINGÚN badge, el
-- scope `pending()` no devuelve nada. Al aplicar el delta, la feature se activa sola.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) los ALTER/índices se guardan con un check en
-- information_schema vía un procedimiento temporal.
--
-- Convención del repo: BIGINT UNSIGNED sin FK dura (igual que otros created_by_id).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_normas_eventos_verif_2026_07_18;

DELIMITER //
CREATE PROCEDURE crewcare_normas_eventos_verif_2026_07_18()
BEGIN
    -- ── safety_standards ────────────────────────────────────────────────────

    -- 1) safety_standards.verified_at — sello de verificación. NULL = pendiente.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards' AND COLUMN_NAME='verified_at') THEN
        -- Ancla = AFTER `reference_url`, una columna BASE (existe desde la migración
        -- original en TODOS los entornos). A propósito NO se ancla AFTER `category_name_en`:
        -- esa columna la crea OTRO delta (2026-07-12-modules-6-14.sql) que puede no estar
        -- aplicado aún en prod; anclar a una columna inexistente aborta el ALTER con
        -- MySQL error 1054 y tumbaría el CALL entero. La posición física es cosmética,
        -- así que se elige la columna garantizada para no depender del orden de deltas.
        ALTER TABLE `safety_standards`
            ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `reference_url`;

        -- 1b) SELLADO INICIAL de la base curada (las 78 normas de SafetyCatalogSeeder).
        --     Nacen VERIFICADAS: son el catálogo semilla del propio sistema, no algo
        --     capturado en campo. verified_by_id SE QUEDA NULL a propósito = "verificada
        --     de origen, sin autor humano" (no le inventamos un id al rastro de auditoría).
        --
        --     ¡NO SAQUES ESTE UPDATE DE LA RAMA! Vive DENTRO del IF NOT EXISTS a propósito:
        --     aquí corre SOLO en la primera aplicación (cuando la columna se acaba de crear
        --     y TODAS las filas son NULL por definición). Suelto al final del archivo se
        --     ejecutaría en CADA re-ejecución y sellaría en silencio las normas que estén
        --     legítimamente pendientes — convertiría un script idempotente en una bomba.
        UPDATE `safety_standards` SET `verified_at` = NOW() WHERE `verified_at` IS NULL;
    END IF;

    -- 2) safety_standards.verified_by_id — autoridad que validó (trazabilidad, sin FK dura).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards' AND COLUMN_NAME='verified_by_id') THEN
        ALTER TABLE `safety_standards`
            ADD COLUMN `verified_by_id` BIGINT UNSIGNED NULL AFTER `verified_at`;
    END IF;

    -- 3) Índice sobre verified_at — sirve al filtro de "pendientes" (WHERE verified_at IS NULL).
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards'
          AND INDEX_NAME='safety_standards_verified_idx') THEN
        ALTER TABLE `safety_standards`
            ADD INDEX `safety_standards_verified_idx` (`verified_at`);
    END IF;

    -- ── hazard_events ───────────────────────────────────────────────────────

    -- 4) hazard_events.verified_at — sello de verificación. NULL = pendiente.
    --     Ancla natural = AFTER is_active (deja created_at/updated_at al final).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazard_events' AND COLUMN_NAME='verified_at') THEN
        ALTER TABLE `hazard_events`
            ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_active`;

        -- 4b) SELLADO INICIAL de la base curada (los 120 eventos ya sembrados).
        --     Mismas reglas que 1b: nacen VERIFICADOS, verified_by_id NULL, y este
        --     UPDATE vive DENTRO del IF NOT EXISTS para correr SOLO la primera vez.
        --     Ver la NOTA DE ORDEN del encabezado: esto DEBE correr antes de sembrar
        --     los 87 eventos nuevos del Paso 5 (para que ellos nazcan NULL/pendientes).
        UPDATE `hazard_events` SET `verified_at` = NOW() WHERE `verified_at` IS NULL;
    END IF;

    -- 5) hazard_events.verified_by_id — autoridad que validó (sin FK dura).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazard_events' AND COLUMN_NAME='verified_by_id') THEN
        ALTER TABLE `hazard_events`
            ADD COLUMN `verified_by_id` BIGINT UNSIGNED NULL AFTER `verified_at`;
    END IF;

    -- 6) Índice sobre verified_at — filtro de "pendientes".
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazard_events'
          AND INDEX_NAME='hazard_events_verified_idx') THEN
        ALTER TABLE `hazard_events`
            ADD INDEX `hazard_events_verified_idx` (`verified_at`);
    END IF;
END //
DELIMITER ;

CALL crewcare_normas_eventos_verif_2026_07_18();
DROP PROCEDURE IF EXISTS crewcare_normas_eventos_verif_2026_07_18;
