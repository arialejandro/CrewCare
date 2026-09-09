-- ============================================================================
-- CrewCare — TRANSPORTACIÓN (Bloque 1 · ajuste §1): BORRADOR del checklist.
-- (2026-08-24) — gemelo de create_vehicle_inspection_drafts.
--
-- QUÉ ES: 1 tabla NUEVA `vehicle_inspection_drafts`. Guardado parcial EN SERVIDOR del checklist
--   (como el intake): retomable desde cualquier dispositivo, DEL AUTOR (created_by_id). NO es un
--   acta — al cerrar se sella el acta (vehicle_inspections) y el borrador se BORRA. Las fotos se
--   guardan al vuelo y sus rutas viven en `point_photos` {code: ruta}.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7/8.0. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-24-transport-drafts.sql
-- MySQL 5.7: JSON prohíbe DEFAULT → answers/point_photos van JSON NULL.
-- Referencias BLANDAS (sin FK dura) a vehicles/users/productions/vehicle_inspections.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vehicle_inspection_drafts` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vehicle_id`           BIGINT UNSIGNED NOT NULL,          -- vehicles (FK-soft)
    `production_id`        BIGINT UNSIGNED NULL,              -- CurrentProduction (FK-soft)
    `created_by_id`        BIGINT UNSIGNED NULL,              -- AUTOR: solo él ve/retoma su borrador
    `is_reevaluation`      TINYINT(1)   NOT NULL DEFAULT 0,
    `origin_inspection_id` BIGINT UNSIGNED NULL,              -- acta de origen si es reevaluación
    `answers`              JSON         NULL,                 -- {code: 'ok'|'fail'}
    `point_photos`         JSON         NULL,                 -- {code: ruta ImageCompressor}
    `unit_photo_path`      VARCHAR(500) NULL,
    `km`                   INT          NULL,
    `observations`         TEXT         NULL,
    `created_at`           TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_veh_draft_vehicle_author` (`vehicle_id`, `created_by_id`),  -- 1 borrador por vehículo+autor
    KEY `veh_draft_author_idx` (`created_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
