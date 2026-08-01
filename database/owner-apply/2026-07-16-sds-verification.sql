-- ============================================================================
-- CrewCare — Estado de VERIFICACIÓN de fichas SDS (2026-07-16)
-- Paso 1c del Pilar 3: una ficha SDS capturada en campo (por un Safety, sin
-- fricción) nace PENDIENTE hasta que una autoridad verificadora (`sds.manage`)
-- la valida. Este delta añade el rastro de esa validación.
--
-- Contenido:
--   1) COL   consumables.verified_at     — cuándo se verificó. NULL = PENDIENTE.
--   1b) SELLADO INICIAL del catálogo preexistente: las fichas que ya existían al
--            aplicar el delta nacen VERIFICADAS (ver el comentario in-situ; corre
--            SOLO en la primera aplicación).
--   2) COL   consumables.verified_by_id  — quién verificó (sin FK dura).
--   3) IDX   consumables_verified_idx    — sobre (verified_at), para el filtro
--            "pendientes" de la lista del catálogo.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO es DEFENSIVO (Consumable::supportsVerification() envuelve
-- Schema::hasColumn con memo estático): la app corre IGUAL sin este SQL. Sin
-- estas columnas el módulo se comporta como "SIN ESTADO DE VERIFICACIÓN": no se
-- pinta NINGÚN badge (ni verificada ni pendiente), el scope `pending()` no
-- devuelve nada y la acción de verificar avisa que no está disponible. Al
-- aplicar este delta, la feature se activa sola.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) los ALTER/índices se guardan con un check en
-- information_schema vía un procedimiento temporal.
--
-- Convención del repo: BIGINT UNSIGNED sin FK dura (igual que otros created_by_id).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_sds_verification_2026_07_16;

DELIMITER //
CREATE PROCEDURE crewcare_sds_verification_2026_07_16()
BEGIN
    -- 1) consumables.verified_at — sello de verificación. NULL = pendiente de validar.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='verified_at') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `sort_order`;

        -- 1b) SELLADO INICIAL del catálogo preexistente.
        --
        --     QUÉ: las fichas que ya vivían en la tabla antes de este delta (las 12 que
        --     siembra ConsumableSeeder) nacen VERIFICADAS. Sin esto saldrían con el badge
        --     "Capturado en set · pendiente de verificación", que sería FALSO: nadie las
        --     capturó en set, vienen del catálogo semilla del propio sistema.
        --
        --     POR QUÉ verified_by_id SE QUEDA NULL: es deliberado. NULL en el autor =
        --     "verificada de origen, sin autor humano". No hay una persona real a quien
        --     atribuirle esta validación, y NO queremos inventarle un id (p.ej. el del
        --     super-admin) a un rastro de auditoría. La UI debe tolerar verified_at con
        --     verified_by_id NULL y no nombrar a nadie.
        --
        --     ¡NO SAQUES ESTE UPDATE DE LA RAMA! Vive DENTRO del IF NOT EXISTS a propósito,
        --     no por comodidad: aquí corre SOLO en la primera aplicación (justo cuando la
        --     columna se acaba de crear y TODAS las filas son NULL por definición). Suelto
        --     al final del archivo se ejecutaría en CADA re-ejecución y sellaría en silencio
        --     las fichas que un Safety capturó en campo y que están legítimamente pendientes
        --     — convertiría un script idempotente en una bomba silenciosa.
        UPDATE `consumables` SET `verified_at` = NOW() WHERE `verified_at` IS NULL;
    END IF;

    -- 2) consumables.verified_by_id — autoridad que validó la ficha (trazabilidad, sin FK dura).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='verified_by_id') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `verified_by_id` BIGINT UNSIGNED NULL AFTER `verified_at`;
    END IF;

    -- 3) Índice sobre verified_at — sirve al filtro de "pendientes" (WHERE verified_at IS NULL).
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables'
          AND INDEX_NAME='consumables_verified_idx') THEN
        ALTER TABLE `consumables`
            ADD INDEX `consumables_verified_idx` (`verified_at`);
    END IF;
END //
DELIMITER ;

CALL crewcare_sds_verification_2026_07_16();
DROP PROCEDURE IF EXISTS crewcare_sds_verification_2026_07_16;
