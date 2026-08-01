-- ============================================================================
-- CrewCare — ACTA DE INSPECCIÓN DE HERRAMIENTA (vertical de enforcement)
-- (2026-07-26) — delta #42. Sigue del #41 (catálogos de herramienta/permisos).
--
-- QUÉ ES: el DOCUMENTO que produce una inspección. El catálogo (delta #41) se une
--   a las normas; el acta las COPIA (snapshot congelado), porque es documento con
--   consecuencia. Un safety encuentra la herramienta, corre su checklist y el acta
--   registra el veredicto (PARO / ACTIVIDAD NO EJECUTABLE / APTA) sellado con
--   `HasDigitalSignatures` y verificable por QR en el verificador público.
--
-- ── REQUISITO PREVIO: `tools` y `digital_signatures` deben existir ───────────
--   FK-soft (sin FK dura) a `tools`/`departments`/`users` — el acta debe seguir
--   viva aunque el catálogo cambie o un usuario se dé de baja (es un documento
--   histórico). El sello vive en `digital_signatures` (tabla genérica del baseline,
--   sin cambios). NO usa FK dura a propósito, igual que `digital_signatures` y los
--   pivotes polimórficos del repo.
--
-- ── DOCTRINA DEL CONGELAMIENTO (misma que cmedic/cédula congelada) ───────────
--   El acta guarda COPIAS, no referencias vivas:
--     · `checklist_snapshot` (JSON) — cada punto ejecutado con su TEXTO, gate,
--       salida_si_falla, normas citadas y la respuesta binaria, tal como estaban.
--     · `tool_*` — código/nombre/familia/modelo de la herramienta al inspeccionar.
--     · `inspector_*` — nombre, rol y CÉDULA del safety congelados (la cédula sale
--       de medic_credentials si la tiene, si no NULL — igual que cmedic).
--   Si mañana cambia el catálogo, el acta sigue diciendo lo que dijo, y el sello
--   SHA-256 lo prueba (se firma sobre estas columnas propias, no sobre relaciones).
--
-- ── EL PARO SE DESBLOQUEA (acto con autor) ──────────────────────────────────
--   Un PARO dura minutos: `unblocked_by_id`/`unblocked_at` registran QUIÉN lo
--   levanta y cuándo, con la vía de salida en `resolution_path` (corrección el
--   mismo día vs reemplazo). El desbloqueo RE-SELLA (nueva firma sobre el estado
--   resuelto); el sello original queda en el historial (patrón InjuryReport).
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (`changes/original/attributes/relations`). `checklist_snapshot` es seguro.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-26-tool-inspections.sql
--   Permiso: php artisan db:seed --class=ToolInspectionPermissionsSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → `checklist_snapshot`/`tool_standards_snapshot` van JSON NULL.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `tool_inspections` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                   CHAR(36)     NULL,          -- verificador público (GeneratesUuidKey)
    `production_id`          BIGINT UNSIGNED NULL,       -- CurrentProduction (FK-soft)
    `shoot_day`              INT          NULL,          -- ProductionCalendar::shootDayFor (derivado)

    -- Herramienta inspeccionada (referencia viva + snapshot congelado) --------
    `tool_id`                BIGINT UNSIGNED NULL,       -- tipo del catálogo (FK-soft; HER-026 = comodín)
    `tool_code`              VARCHAR(20)  NULL,          -- snapshot
    `tool_name`              VARCHAR(255) NULL,          -- snapshot
    `tool_family_key`        VARCHAR(40)  NULL,          -- snapshot (familia elegida si comodín)
    `tool_model`             VARCHAR(255) NULL,          -- MARCA/MODELO: dato de instancia, tecleado al inspeccionar
    `tool_standards_snapshot` JSON        NULL,          -- normas de ítem congeladas (regulation_code)

    -- Ejecución del checklist -------------------------------------------------
    `checklist_mode`         VARCHAR(20)  NOT NULL DEFAULT 'safety',  -- safety | operator (rótulo del "doble")
    `checklist_snapshot`     JSON         NULL,          -- lista ejecutada congelada [{code,text,is_gate,outcome,standards,answer}]

    -- Veredicto (derivado del dato, no capturado) -----------------------------
    `verdict`                VARCHAR(30)  NOT NULL,      -- paro | actividad_no_ejecutable | apta
    `resolution_path`        VARCHAR(30)  NULL,          -- correccion_mismo_dia | reemplazo (solo en PARO)
    `observations`           TEXT         NULL,          -- observaciones que salieron (puntos condicionados + nota)

    -- Departamento a notificar (el safety lo elige) ---------------------------
    `department_id`          BIGINT UNSIGNED NULL,       -- FK-soft a departments
    `department_name`        VARCHAR(120) NULL,          -- snapshot

    -- Inspector (cédula congelada) --------------------------------------------
    `inspector_user_id`      BIGINT UNSIGNED NULL,       -- FK-soft a users
    `inspector_name`         VARCHAR(255) NULL,          -- snapshot
    `inspector_role`         VARCHAR(100) NULL,          -- snapshot (rol al firmar)
    `inspector_cedula`       VARCHAR(60)  NULL,          -- snapshot (medic_credentials si tiene, si no NULL)

    -- Desbloqueo del PARO (acto con autor) ------------------------------------
    `unblocked_by_id`        BIGINT UNSIGNED NULL,       -- FK-soft a users
    `unblocked_by_name`      VARCHAR(255) NULL,          -- snapshot
    `unblocked_at`           DATETIME     NULL,

    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`             TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tool_inspections_uuid_unique` (`uuid`),
    KEY `tool_inspections_tool_idx`       (`tool_id`),
    KEY `tool_inspections_production_idx` (`production_id`),
    KEY `tool_inspections_verdict_idx`    (`verdict`),
    KEY `tool_inspections_department_idx` (`department_id`),
    KEY `tool_inspections_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
