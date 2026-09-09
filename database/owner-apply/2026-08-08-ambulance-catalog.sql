-- ============================================================================
-- CrewCare — CATÁLOGO DE VERIFICACIÓN DE AMBULANCIAS (tipos + puntos)
-- (2026-08-08) — delta #51. CIMIENTO del bloque "Verificación de ambulancias".
--
-- QUÉ ES: el catálogo "de fondo" que alimenta el acta de verificación de
--   ambulancia (documento sellado, por construir en un delta posterior). Es el
--   gemelo del catálogo de HERRAMIENTAS (delta #41: `tools`/`tool_check_points`):
--     · `ambulance_types`             — los 6 TIPOS de la NOM-034-SSA3-2013
--       (terrestre traslado/básica/avanzada/UCI + aérea + marítima).
--     · `ambulance_inspection_points` — los 92 PUNTOS binarios de verificación.
--   Fuente: database/seeders/data/crewcare_ambulancias_catalogo.json (del owner).
--   El importador es AmbulanceCatalogSeeder (idempotente, updateOrCreate por code).
--
-- ── HERENCIA POR APÉNDICE (A ⊆ B ⊆ C ⊆ D) ────────────────────────────────────
--   Un tipo terrestre HEREDA todos los puntos de su rama con min_level <= su
--   level (y max_level NULL o >= su level). Por eso `min_level`/`max_level` viven
--   en el PUNTO, no en el tipo: la pertenencia se DERIVA, no se duplica. Las ramas
--   aérea/marítima NO son peldaños de la escalera terrestre (level NULL): cumplen
--   los apéndices A-D según su capacidad resolutiva (dato del acta, no del tipo) y
--   además sus propios puntos. Por eso `rama` y `level` son campos distintos.
--   Excepción declarada en el catálogo: AMBV-104 (DEA) lleva max_level=2 (no aplica
--   a C/D, donde lo sustituye el desfibrilador-monitor de C.1.1).
--
-- ── EL ESTADO SE DECLARA (no auditado) ───────────────────────────────────────
--   Los 92 puntos se derivaron del texto publicado de la NOM y NO han sido
--   auditados contra campo ni validados por un responsable sanitario. Nacen con
--   `verified_at` NULL (mismo patrón que `tools`/`safety_standards`: redactado
--   desde oficio, sin auditar). El seeder NUNCA marca verificado; tampoco pisa un
--   `verified_at`/`is_active` que el owner ya haya puesto a mano.
--
-- ── ORIGEN = DATO, NO CATEGORÍA EN CÓDIGO ────────────────────────────────────
--   `origen` (normativo/contractual/recomendado) y `exigido_por` (quién lo exige:
--   estudio, plataforma, marca o agencia) viven como DATOS. La norma solo puebla
--   `normativo`/`exigido_por=NULL`; lo contractual se agrega como perfil de
--   proyecto más adelante (no se quema ninguna clave de contratante en el código).
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (`changes/original/attributes/relations`). `trigger_scopes`/`nota`/`text_es`
--   son seguros. (`triggers` a secas se evita: cercano a palabra reservada de SQL.)
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-08-ambulance-catalog.sql
--   Luego: php artisan db:seed --class=AmbulanceCatalogSeeder
-- MySQL 5.7: JSON prohíbe DEFAULT → `trigger_scopes` va JSON NULL.
-- ============================================================================

-- TIPOS (6) — el "de fondo"; sin CRUD de usuario, como `tools`. ---------------
CREATE TABLE IF NOT EXISTS `ambulance_types` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                  VARCHAR(20)  NOT NULL,          -- AMB-01 … AMB-06
    `name_es`               VARCHAR(255) NOT NULL,
    `name_en`               VARCHAR(255) NULL,
    `rama`                  VARCHAR(20)  NOT NULL DEFAULT 'terrestre',  -- terrestre | aerea | maritima
    `level`                 INT          NULL,              -- 1..4 (terrestre); NULL en aérea/marítima
    `apendice`              VARCHAR(4)   NULL,              -- A..F (apéndice normativo de la NOM)
    `personal_minimo`       TEXT         NULL,
    `dimensiones_minimas`   TEXT         NULL,
    `capacidad`             TEXT         NULL,
    `sort_order`            INT          NOT NULL DEFAULT 0,
    `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`           TIMESTAMP    NULL DEFAULT NULL, -- estado declarado (sin auditar); seeder no lo toca
    `verified_by_id`        BIGINT UNSIGNED NULL,
    `created_at`            TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ambulance_types_code` (`code`),
    KEY `ambulance_types_rama_level_idx` (`rama`, `level`),
    KEY `ambulance_types_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PUNTOS (92) — binarios; la pertenencia a un tipo se DERIVA por rama+level. ----
CREATE TABLE IF NOT EXISTS `ambulance_inspection_points` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                  VARCHAR(20)  NOT NULL,          -- AMBV-001 …
    `text_es`               TEXT         NOT NULL,          -- texto_punto
    `norm_ref`              VARCHAR(120) NULL,              -- referencia_norma (numeral: "A.2.11", "5.1.8")
    `rama`                  VARCHAR(20)  NOT NULL DEFAULT 'todas',  -- terrestre | aerea | maritima | todas
    `min_level`             INT          NULL,              -- nivel mínimo del apéndice (herencia A⊆B⊆C⊆D)
    `max_level`             INT          NULL,              -- tope (solo AMBV-104 = 2); NULL = sin tope
    `is_gate`               TINYINT(1)   NOT NULL DEFAULT 0,-- es_compuerta
    `outcome_if_fail`       VARCHAR(30)  NULL,              -- paro_inmediato | actividad_no_ejecutable
    `origen`                VARCHAR(20)  NOT NULL DEFAULT 'normativo',  -- normativo | contractual | recomendado
    `exigido_por`           VARCHAR(160) NULL,              -- DATO: quién lo exige (NULL en normativo)
    `trigger_scopes`        JSON         NULL,              -- disparadores: [identidad,persona,unidad,consumo,riesgo]
    `requires_document`     TINYINT(1)   NOT NULL DEFAULT 0,-- pide_documento (no se abre el botiquín: se pide el papel)
    `nota`                  TEXT         NULL,
    `norm_code`             VARCHAR(40)  NULL,              -- norma (NOM-034-SSA3-2013)
    `sort_order`            INT          NOT NULL DEFAULT 0,
    `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`           TIMESTAMP    NULL DEFAULT NULL, -- estado declarado (sin auditar); seeder no lo toca
    `verified_by_id`        BIGINT UNSIGNED NULL,
    `created_at`            TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ambulance_points_code` (`code`),
    KEY `ambulance_points_rama_level_idx` (`rama`, `min_level`),
    KEY `ambulance_points_gate_idx`       (`is_gate`),
    KEY `ambulance_points_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
