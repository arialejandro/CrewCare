-- ============================================================================
-- CrewCare — PAE · PLAN DE ATENCIÓN A EMERGENCIAS (2026-08-06)
-- Sigue del motor de documentos que estrenó el MEDEVAC (delta #46).
--
-- QUÉ ES: el documento que pide Amazon, UNO POR LLAMADO (día de rodaje). Puede
--   cubrir DOS locaciones (company move). NO es captura nueva: se RENDERIZA desde
--   el/los SCOUTING elegidos (locación autoritativa: hospital, distancia, ETA, GPS,
--   punto de reunión, accesos, y los RIESGOS ya evaluados en risk_assessment) más
--   el ORGANIGRAMA de emergencia resuelto del crew. Cada emisión CONGELA su payload
--   y se sella con HasDigitalSignatures; entra al verificador público como 'pae'.
--
-- ── UNA SOLA TABLA (el documento) ───────────────────────────────────────────
--   emergency_action_plans. El PAE se ancla al DÍA DE RODAJE + las LOCACIONES
--   ELEGIDAS (no al módulo de llamado, que no existe). Las locaciones cubiertas,
--   el organigrama, los riesgos y —si el emisor lo pide— las vistas del mapa de
--   riesgos viven DENTRO del payload congelado: si mañana cambia el scouting o el
--   mapa, el PAE emitido sigue diciendo lo que dijo y el sello lo prueba.
--
-- ── EMISIONES INDEPENDIENTES (como el MEDEVAC y el mapa) ─────────────────────
--   Cada emisión es un documento NUEVO y SIN RELACIÓN con los anteriores: sin
--   cadena de versiones, sin "sustituye a". No hay columna `revision`. El PAE NO
--   implementa sealRetirement() → el verificador lo lee en 2 estados: vigente/alterado.
--
-- ── SELLO ───────────────────────────────────────────────────────────────────
--   Se sella sobre el DATO (attributesToArray ksorteado: payload + shoot_day +
--   plan_date + unit_name + issued_*), nunca sobre el render. `is_active` queda
--   FUERA del hash (estado de listado, no contenido); nunca se borra con ->delete().
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). shoot_day/plan_date/unit_name/plan_label/
--   payload son seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-06-pae-emergency-action-plans.sql
--   Permiso Spatie `pae.issue`: lo cablea el owner en el seeder de permisos (ZONA
--   RESERVADA) + php artisan permission:cache-reset. Ruta y menú, también owner.
-- MySQL 5.7: JSON prohíbe DEFAULT → `payload` va JSON NULL.
--
-- REVERSIÓN:
--   DROP TABLE IF EXISTS `emergency_action_plans`;
-- ============================================================================

CREATE TABLE IF NOT EXISTS `emergency_action_plans` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`           CHAR(36)     NULL,             -- verificador público (GeneratesUuidKey)
    `production_id`  BIGINT UNSIGNED NULL,          -- CurrentProduction (FK-soft)

    `shoot_day`      INT          NULL,             -- día de rodaje (congelado; VA EN EL HASH)
    `plan_date`      DATE         NULL,             -- fecha del llamado (VA EN EL HASH)
    `unit_name`      VARCHAR(255) NULL,             -- unidad (company move = misma unidad; VA EN EL HASH)
    `plan_label`     VARCHAR(255) NULL,             -- snapshot para el listado (locaciones/día)

    `payload`        JSON         NULL,             -- FOTOGRAFÍA: header/org/company_move/locations[] (riesgos, hospital, mapa)

    -- Emisor (safety) con nombre CONGELADO (misma doctrina que MEDEVAC/issued_permits) --
    `issued_by_id`   BIGINT UNSIGNED NULL,          -- FK-soft a users
    `issued_by_name` VARCHAR(255) NULL,             -- snapshot
    `issued_at`      DATETIME     NULL,             -- momento de emisión (VA EN EL HASH)

    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,   -- listado (HASH-EXCLUIDO); nunca DELETE
    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `emergency_action_plans_uuid_unique` (`uuid`),
    KEY `emergency_action_plans_production_idx` (`production_id`),
    KEY `emergency_action_plans_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
