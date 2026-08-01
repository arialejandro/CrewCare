-- ============================================================================
-- CrewCare — VIGILANCIA EPIDEMIOLÓGICA: panel silencioso + estudio de brote
-- (2026-07-31) — delta #45. Sigue del #44 (emisión de permisos).
--
-- QUÉ ES: dos piezas para que el SAFETY y el MÉDICO lean patrones de salud del
--   rodaje y conversen una estrategia. NADA de esto notifica, alerta ni declara
--   brotes: es estadística silenciosa que dos personas leen.
--
--   1) `indicator_terms` — una tabla CORTA de ~30 términos por grupo indicador
--      (gastrointestinal, respiratorio, dérmico, alérgico, oftálmico,
--      musculoesquelético). NO es un catálogo cerrado de medicamentos: es un
--      diccionario de señales contra el que se resuelven el nombre del medicamento
--      y el texto del diagnóstico. Lo que no cae en ningún grupo se queda SIN
--      CLASIFICAR (visible). Nace `is_clinician_verified = 0`: es una PROPUESTA que
--      el owner/médico ajusta; el sistema no finge que un médico ya la validó.
--
--   2) `outbreak_studies` — el ESTUDIO DE BROTE que el MÉDICO decide emitir cuando
--      él lo juzga (el sistema jamás lo genera solo ni sugiere que hay brote).
--      Estructura NOM-017-SSA2-2012: definición operacional de casos, descripción
--      por tiempo/lugar/persona, tasa de ataque, hipótesis y medidas de control.
--      El sistema aporta los CONTEOS (`counts_snapshot`); el criterio lo pone el
--      médico. Se sella con `HasDigitalSignatures` y entra al verificador público
--      como `'brote'`. Este documento SÍ puede llevar NOMBRES (es clínico y firmado
--      por quien responde por él). La GRÁFICA nunca — es agregada.
--
-- ── SOLO LECTURA sobre lo clínico ───────────────────────────────────────────
--   Este módulo NO toca `cmedic` ni el expediente: los LEE. Las consultas selladas
--   siguen intactas (cero ALTERADO). `outbreak_studies` es una tabla NUEVA aparte.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-31-epi-surveillance.sql
--   Seeders: php artisan db:seed --class=EpiPermissionsSeeder
--            php artisan db:seed --class=IndicatorTermSeeder   (la propuesta de términos)
--            php artisan cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → `counts_snapshot` va JSON NULL.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1) TÉRMINOS INDICADORES (diccionario de señales, NO catálogo de medicamentos)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `indicator_terms` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_key`             VARCHAR(40)  NOT NULL,             -- gastrointestinal | respiratorio | dermico | alergico | oftalmico | musculoesqueletico
    `match_kind`            VARCHAR(20)  NOT NULL,             -- 'medicamento' (contra medication_items[].name) | 'diagnostico' (contra diagnosis)
    `term`                  VARCHAR(150) NOT NULL,             -- NORMALIZADO (minúsculas, sin acentos, sin espacios dobles)
    `display_term`          VARCHAR(150) NULL,                 -- cómo se muestra (opcional)
    `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
    `is_clinician_verified` TINYINT(1)   NOT NULL DEFAULT 0,   -- PROPUESTA: 0 hasta que un médico la revise
    `source_note`           VARCHAR(255) NULL,                 -- de dónde salió (botiquín de referencia / vocabulario común)
    `created_at`            TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `indicator_terms_group_idx`  (`group_key`),
    KEY `indicator_terms_kind_idx`   (`match_kind`),
    KEY `indicator_terms_term_idx`   (`term`),
    KEY `indicator_terms_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) ESTUDIO DE BROTE (documento clínico sellado, a demanda del médico)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `outbreak_studies` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                  CHAR(36)     NULL,                 -- verificador público (GeneratesUuidKey)
    `production_id`         BIGINT UNSIGNED NULL,              -- CurrentProduction (FK-soft)

    -- Autor (médico) con cédula CONGELADA (misma doctrina que la consulta) ----
    `created_by_id`         BIGINT UNSIGNED NULL,              -- FK-soft a users
    `medic_name`            VARCHAR(255) NULL,                 -- snapshot
    `medic_cedula`          VARCHAR(60)  NULL,                 -- snapshot
    `medic_cedula_verified` TINYINT(1)   NULL,                 -- snapshot (tri-estado: null = no aplica)

    -- Encuadre del estudio ----------------------------------------------------
    `title`                 VARCHAR(255) NULL,                 -- rótulo del estudio
    `group_key`             VARCHAR(40)  NULL,                 -- grupo indicador bajo estudio (o null si mixto/libre)
    `period_from`           DATE         NULL,                 -- ventana estudiada (inicio)
    `period_to`             DATE         NULL,                 -- ventana estudiada (fin)

    -- NOM-017-SSA2-2012: el criterio lo pone el MÉDICO (texto libre) ----------
    `case_definition`       TEXT         NULL,                 -- definición operacional de casos
    `time_description`      TEXT         NULL,                 -- descripción por TIEMPO
    `place_description`     TEXT         NULL,                 -- descripción por LUGAR
    `person_description`    TEXT         NULL,                 -- descripción por PERSONA (SÍ puede llevar nombres)
    `attack_rate_cases`     INT          NULL,                 -- numerador (casos)
    `attack_rate_population`INT          NULL,                 -- denominador (población en riesgo)
    `attack_rate_note`      VARCHAR(255) NULL,                 -- la tasa como la enuncia el médico
    `hypothesis`            TEXT         NULL,                 -- hipótesis
    `control_measures`      TEXT         NULL,                 -- medidas de control

    -- CONTEOS que aportó el sistema (congelados) ------------------------------
    `counts_snapshot`       JSON         NULL,                 -- [{shoot_day, date, count, denominator, ...}] tal como se leyeron

    `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`            TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `outbreak_studies_uuid_unique` (`uuid`),
    KEY `outbreak_studies_production_idx` (`production_id`),
    KEY `outbreak_studies_author_idx`     (`created_by_id`),
    KEY `outbreak_studies_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
