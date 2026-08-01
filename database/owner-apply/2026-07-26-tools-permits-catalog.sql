-- ============================================================================
-- CrewCare — CATÁLOGOS DE HERRAMIENTA (Capa B) y PERMISOS DE ACTIVIDAD (Capa C)
-- (2026-07-26) — SOLO LA CAPA DE DATOS.
--
-- QUÉ ES: dos catálogos de fondo (se consultan, no se editan), importados desde
--   database/seeders/data/crewcare_herramientas_catalogo.json (73 tipos, 67 puntos
--   de comprobación, 31 variantes, 4 accesorios, 11 familias) y
--   database/seeders/data/crewcare_permisos_catalogo.json (15 permisos, 103 puntos).
--   Los DATOS los siembra `ToolPermitCatalogSeeder` (idempotente, updateOrCreate
--   por `code`); este archivo SOLO crea el esquema.
--
-- ── REQUISITO PREVIO: `safety_standards` DEBE EXISTIR ────────────────────────
--   Tres puentes (`tool_standard`, `check_point_standard`, `permit_standard`)
--   llevan FK dura a `safety_standards(id)`. Esa tabla es del baseline
--   (mysql-schema.dump). Si por lo que sea faltara, esos tres CREATE abortan con
--   errno 150 y el resto se crea igual; se AUTOREPARA re-ejecutando el archivo
--   tras restaurar `safety_standards` (el `IF NOT EXISTS` salta lo ya creado).
--
-- ── DECISIÓN DE DOMINIO (queda escrito para que nadie lo "corrija") ──────────
--   La Capa A (SPFX, 2026-07-16-sfx-effect-types.sql) guardó sus normas como
--   SNAPSHOT JSON y NO pivotó a `safety_standards`, por separación de dominios
--   ("esas normas son de los SDS"). Para ESTA capa el owner instruyó lo CONTRARIO
--   y explícito: "UNIR, NO DUPLICAR — únelas al catálogo de normas que YA EXISTE;
--   no crees una segunda tabla de normas en texto libre, eso partiría en dos el
--   marco normativo del producto." Por eso aquí SÍ hay pivotes reales a
--   `safety_standards`. La divergencia entre capas es deliberada, no un descuido.
--
--   Las normas se anclan a DOS niveles (decisión del owner): al ÍTEM
--   (`tool_standard` / `permit_standard`) y al PUNTO de comprobación
--   (`check_point_standard`). Los puntos de PERMISO no traen normas en la fuente,
--   así que no hay `permit_point_standard`.
--
--   Toda cadena de norma que NO resuelve hoy contra `safety_standards.regulation_code`
--   se PARQUEA en `catalog_pending_standards` (vínculo pendiente polimórfico), sin
--   inventar la fila de norma. Esa tabla NO es "una segunda tabla de normas": es una
--   bandeja de cadenas crudas por resolver, que se vacía cuando el owner dé de alta
--   la norma. Ver el reporte de no-resueltas del importador.
--
-- ── CORRECCIONES QUE VIVEN EN EL IMPORTADOR (no en la fuente) ────────────────
--   1. Familia por CLAVE, no por nombre visible (mapeo nombre→clave; falla ruidoso
--      si un nombre no resuelve). El único tipo sin familia es HER-026.
--   2. HER-026 "Herramienta armada en taller": familia NULL + `is_wildcard=1`. Es
--      la excepción diseñada (se elige familia al inspeccionar), no dato incompleto.
--   3. PER-05/06/07 (`por_definir`) se importan como `reverificacion`; su nota se
--      sustituye por la regla acordada (montaje temporal ⇒ reevaluación al reanudar
--      en otro sitio). La reverificación sale de sus puntos `site_sensitive` (4/3/3).
--   4. En permisos TODO punto es compuerta (`is_gate=1`, 103/103): no hay permiso a
--      medias. En herramienta SÍ hay puntos informativos (asimetría correcta).
--
-- ── ESTADO SE DECLARA (Paso 4) ──────────────────────────────────────────────
--   Los 73 tipos y 15 permisos nacen SIN verificar: `verified_at = NULL`. Es el
--   mismo patrón que `hazard_events` / `safety_standards`. NULL = redactado desde
--   conocimiento de oficio, no auditado contra el texto ni contra campo.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
--   esquema (MySQL 5.7). En un cliente MySQL:
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-26-tools-permits-catalog.sql
--   Y después, para poblar:  php artisan db:seed --class=ToolPermitCatalogSeeder
--
-- IDEMPOTENCIA: `CREATE TABLE IF NOT EXISTS` basta — solo CREA tablas nuevas, no
--   altera ninguna existente. Convención de FK dura ON DELETE CASCADE en los
--   puentes catálogo↔catálogo, igual que `effect_standard`. SALIDA DE EMERGENCIA:
--   si un entorno rechaza una FK, borra la línea `CONSTRAINT ... FOREIGN KEY` y
--   conserva la `KEY`; el modelo purga los pivotes a mano en `booted()`.
--
-- MySQL 5.7: `JSON` es nativo pero PROHÍBE DEFAULT en columnas JSON → van `JSON NULL`.
-- ORDEN DEL ARCHIVO — NO REORDENAR: cada tabla referenciada por una FK se crea antes.
--
-- COLLATION: las 12 tablas van `utf8mb4_unicode_ci`, la convención de las tablas
--   NUEVAS del repo (hazard_events, effect_standard, sfx_effect_types). EXCEPCIÓN
--   PUNTUAL: `catalog_pending_standards.raw_code` se declara `utf8mb4_general_ci`
--   para igualar a `safety_standards.regulation_code` (tabla legacy, general_ci).
--   Son los dos únicos textos que se compararán directamente (una raw_code se
--   convierte en un regulation_code al darse de alta la norma); sin igualar la
--   collation, un JOIN `regulation_code = raw_code` aborta con errno 1267 "Illegal
--   mix of collations". Los pivotes unen por BIGINT id, así que a ellos les da igual.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) tool_families — las 11 familias de herramienta. `family_key` = clave estable.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tool_families` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `family_key`     VARCHAR(40)  NOT NULL,
    `name`           VARCHAR(120) NOT NULL,
    `name_en`        VARCHAR(120) NULL,
    `shape_question` VARCHAR(255) NULL,      -- pregunta_de_forma ("¿Gira un disco?")
    `is_energized`   TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`     INT          NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tool_families_key` (`family_key`),
    KEY `tool_families_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) tools — los 73 tipos (HER-001..HER-073). `code` UNIQUE = clave natural.
--    `tool_family_id` NULL solo para el comodín (HER-026), marcado con
--    `is_wildcard=1`. Las listas ricas (alias, EPP, modos de falla, checklists de
--    texto) van en JSON, igual que `sfx_effect_types`. `budget` = presupuesto
--    {puntos, de_paro, segundos_estimados} (regla: ≤90 s de detención).
--    `triggers_permit_name` es la declaración capa-B por NOMBRE (ayuda de lectura);
--    la relación AUTORITATIVA por ID vive en `permit_tool`.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tools` (
    `id`                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                          VARCHAR(20)  NOT NULL,
    `tool_family_id`                BIGINT UNSIGNED NULL,
    `is_wildcard`                   TINYINT(1)   NOT NULL DEFAULT 0,
    `name`                          VARCHAR(255) NOT NULL,
    `name_en`                       VARCHAR(255) NULL,
    `definition`                    TEXT         NULL,
    `context`                       VARCHAR(40)  NULL,   -- contexto (rodaje/taller/prep)
    `quick_id`                      TEXT         NULL,   -- identificacion_rapida
    `main_risk`                     TEXT         NULL,   -- riesgo_principal
    `requires_designated_operator`  TINYINT(1)   NOT NULL DEFAULT 0,
    `is_accessory`                  TINYINT(1)   NOT NULL DEFAULT 0,
    `triggers_permit_name`          VARCHAR(120) NULL,   -- permiso_que_dispara (nombre)
    `aliases`                       JSON         NULL,   -- alias_set
    `departments`                   JSON         NULL,   -- departamentos
    `stages`                        JSON         NULL,   -- etapas
    `critical_parts`                JSON         NULL,   -- partes_criticas
    `failure_modes`                 JSON         NULL,   -- modos_falla_set
    `stop_checks`                   JSON         NULL,   -- paro_inmediato
    `not_executable_checks`         JSON         NULL,   -- actividad_no_ejecutable
    `observation_checks`            JSON         NULL,   -- solo_observacion
    `min_ppe`                       JSON         NULL,   -- epp_minimo
    `budget`                        JSON         NULL,   -- presupuesto
    `notes`                         TEXT         NULL,
    `sort_order`                    INT          NOT NULL DEFAULT 0,
    `is_active`                     TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`                   TIMESTAMP    NULL DEFAULT NULL,
    `verified_by_id`                BIGINT UNSIGNED NULL,
    `created_at`                    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`                    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tools_code` (`code`),
    KEY `tools_family_idx`   (`tool_family_id`),
    KEY `tools_active_idx`   (`is_active`),
    KEY `tools_verified_idx` (`verified_at`),
    KEY `tools_accessory_idx`(`is_accessory`),
    CONSTRAINT `tools_family_fk` FOREIGN KEY (`tool_family_id`)
        REFERENCES `tool_families` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) tool_variants — 31 variantes (nombres). Hijas 1:N de un tipo.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tool_variants` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tool_id`    BIGINT UNSIGNED NOT NULL,
    `name`       VARCHAR(255) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NULL DEFAULT NULL,
    `updated_at` TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tool_variants` (`tool_id`,`name`),
    KEY `tool_variants_tool_idx` (`tool_id`),
    CONSTRAINT `tool_variants_tool_fk` FOREIGN KEY (`tool_id`)
        REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4) tool_check_points — los 67 puntos de comprobación (PC-/FH-/TS-/FD-/FS-...).
--    Compartidos por muchos tipos (N:M). `scope` = ambito; `supersedes` =
--    sustituye (un punto de familia/tipo reemplaza a un universal). `is_gate` =
--    es_compuerta (63 sí, 4 informativos). `outcome_if_fail`, `severity`,
--    `photo_evidence`, `estimated_seconds` alimentan la futura calculadora.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tool_check_points` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                 VARCHAR(20)  NOT NULL,
    `scope`                VARCHAR(40)  NOT NULL,   -- ambito
    `scope_key`            VARCHAR(60)  NULL,       -- clave_ambito
    `text_es`              TEXT         NOT NULL,   -- texto
    `text_en`              TEXT         NULL,       -- (fuente sin EN; listo para i18n)
    `is_gate`              TINYINT(1)   NOT NULL DEFAULT 1,   -- es_compuerta
    `severity`             TINYINT      NULL,       -- severidad (1-5)
    `outcome_if_fail`      VARCHAR(60)  NULL,       -- salida_si_falla
    `supersedes`           VARCHAR(20)  NULL,       -- sustituye (code de otro punto)
    `photo_evidence`       VARCHAR(40)  NULL,       -- evidencia_foto
    `estimated_seconds`    INT          NULL,       -- segundos_estimados
    `visible_to_naked_eye` TINYINT(1)   NOT NULL DEFAULT 1,   -- observable_a_simple_vista
    `sort_order`           INT          NOT NULL DEFAULT 0,
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`          TIMESTAMP    NULL DEFAULT NULL,
    `verified_by_id`       BIGINT UNSIGNED NULL,
    `created_at`           TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tool_check_points_code` (`code`),
    KEY `tool_check_points_scope_idx` (`scope`),
    KEY `tool_check_points_gate_idx`  (`is_gate`),
    KEY `tool_check_points_active_idx`(`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5) check_point_tool — pivote N:M tipo ↔ punto (de puntos_ref/herramientas_ref,
--    íntegros en ambos sentidos). Nombre alfabético (convención Laravel/repo).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `check_point_tool` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tool_check_point_id` BIGINT UNSIGNED NOT NULL,
    `tool_id`             BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_check_point_tool` (`tool_check_point_id`,`tool_id`),
    KEY `check_point_tool_cp_idx`   (`tool_check_point_id`),
    KEY `check_point_tool_tool_idx` (`tool_id`),
    CONSTRAINT `check_point_tool_cp_fk` FOREIGN KEY (`tool_check_point_id`)
        REFERENCES `tool_check_points` (`id`) ON DELETE CASCADE,
    CONSTRAINT `check_point_tool_tool_fk` FOREIGN KEY (`tool_id`)
        REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6) tool_standard — puente N:M ÍTEM tipo ↔ norma. FK dura a safety_standards.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tool_standard` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tool_id`            BIGINT UNSIGNED NOT NULL,
    `safety_standard_id` BIGINT UNSIGNED NOT NULL,
    `created_at`         TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tool_standard` (`tool_id`,`safety_standard_id`),
    KEY `tool_standard_tool_idx` (`tool_id`),
    KEY `tool_standard_std_idx`  (`safety_standard_id`),
    CONSTRAINT `tool_standard_tool_fk` FOREIGN KEY (`tool_id`)
        REFERENCES `tools` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tool_standard_std_fk` FOREIGN KEY (`safety_standard_id`)
        REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7) check_point_standard — puente N:M PUNTO ↔ norma. FK dura a safety_standards.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `check_point_standard` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tool_check_point_id` BIGINT UNSIGNED NOT NULL,
    `safety_standard_id`  BIGINT UNSIGNED NOT NULL,
    `created_at`          TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_check_point_standard` (`tool_check_point_id`,`safety_standard_id`),
    KEY `check_point_standard_cp_idx`  (`tool_check_point_id`),
    KEY `check_point_standard_std_idx` (`safety_standard_id`),
    CONSTRAINT `check_point_standard_cp_fk` FOREIGN KEY (`tool_check_point_id`)
        REFERENCES `tool_check_points` (`id`) ON DELETE CASCADE,
    CONSTRAINT `check_point_standard_std_fk` FOREIGN KEY (`safety_standard_id`)
        REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8) permits — los 15 permisos (PER-01..PER-15). `code` UNIQUE. `site_scope` ya
--    trae la corrección (por_definir→reverificacion en 05/06/07). Autorización
--    externa 0-o-1 por permiso, inline: `ext_auth_mandatory` es el campo que luego
--    impedirá emitir. `budget` = presupuesto {puntos, del_safety, del_especialista}.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permits` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                 VARCHAR(20)  NOT NULL,
    `permit_key`           VARCHAR(60)  NULL,       -- clave (altura, pirotecnia...)
    `family`               VARCHAR(120) NULL,       -- familia ("1. Calor y fuego")
    `name`                 VARCHAR(255) NOT NULL,
    `definition`           TEXT         NULL,
    `covers`               JSON         NULL,       -- cubre
    `issued_when`          TEXT         NULL,       -- cuando_se_emite
    `issued_by`            VARCHAR(255) NULL,       -- emite
    `accepted_by`          VARCHAR(255) NULL,       -- acepta
    `signatures`           JSON         NULL,       -- firmas
    `validity`             TEXT         NULL,       -- vigencia
    `scope`                TEXT         NULL,       -- alcance
    `site_scope`           VARCHAR(40)  NULL,       -- alcance_sitio (corregido)
    `site_scope_note`      TEXT         NULL,       -- alcance_sitio_nota (corregido 05/06/07)
    `reverify_on_move`     JSON         NULL,       -- reverificacion_al_mover
    `ext_auth_authority`   VARCHAR(120) NULL,       -- autorizacion_externa.autoridad
    `ext_auth_what`        TEXT         NULL,       -- autorizacion_externa.que
    `ext_auth_mandatory`   TINYINT(1)   NULL,       -- autorizacion_externa.obligatorio (NULL = sin autorización externa)
    `budget`               JSON         NULL,       -- presupuesto
    `notes`                TEXT         NULL,
    `sort_order`           INT          NOT NULL DEFAULT 0,
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`          TIMESTAMP    NULL DEFAULT NULL,
    `verified_by_id`       BIGINT UNSIGNED NULL,
    `created_at`           TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permits_code` (`code`),
    KEY `permits_site_scope_idx` (`site_scope`),
    KEY `permits_active_idx`     (`is_active`),
    KEY `permits_verified_idx`   (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9) permit_points — los 103 puntos de permiso. OWNED 1:N por un permiso (cada
--    punto trae `permiso_ref` único). `is_gate` SIEMPRE 1. `executor` (safety/
--    especialista); `requires_contact` marca los que el Safety NO ejecuta.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permit_points` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`              VARCHAR(20)  NOT NULL,
    `permit_id`         BIGINT UNSIGNED NOT NULL,
    `text_es`           TEXT         NOT NULL,   -- texto
    `text_en`           TEXT         NULL,       -- (fuente sin EN; listo para i18n)
    `executor`          VARCHAR(40)  NULL,       -- ejecutor (safety/especialista)
    `is_gate`           TINYINT(1)   NOT NULL DEFAULT 1,   -- es_compuerta (103/103)
    `site_sensitive`    TINYINT(1)   NOT NULL DEFAULT 0,   -- sensible_al_sitio
    `requires_contact`  TINYINT(1)   NOT NULL DEFAULT 0,   -- requiere_contacto
    `sort_order`        INT          NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`       TIMESTAMP    NULL DEFAULT NULL,
    `verified_by_id`    BIGINT UNSIGNED NULL,
    `created_at`        TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permit_points_code` (`code`),
    KEY `permit_points_permit_idx` (`permit_id`),
    KEY `permit_points_site_idx`   (`site_sensitive`),
    KEY `permit_points_active_idx` (`is_active`),
    CONSTRAINT `permit_points_permit_fk` FOREIGN KEY (`permit_id`)
        REFERENCES `permits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10) permit_standard — puente N:M ÍTEM permiso ↔ norma. (Los puntos de permiso
--     NO traen normas en la fuente → no hay permit_point_standard.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permit_standard` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id`          BIGINT UNSIGNED NOT NULL,
    `safety_standard_id` BIGINT UNSIGNED NOT NULL,
    `created_at`         TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permit_standard` (`permit_id`,`safety_standard_id`),
    KEY `permit_standard_permit_idx` (`permit_id`),
    KEY `permit_standard_std_idx`    (`safety_standard_id`),
    CONSTRAINT `permit_standard_permit_fk` FOREIGN KEY (`permit_id`)
        REFERENCES `permits` (`id`) ON DELETE CASCADE,
    CONSTRAINT `permit_standard_std_fk` FOREIGN KEY (`safety_standard_id`)
        REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11) permit_tool — puente N:M permiso ↔ herramienta (16 pares de
--     herramientas_que_lo_disparan, POR ID). Relación autoritativa.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permit_tool` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` BIGINT UNSIGNED NOT NULL,
    `tool_id`   BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permit_tool` (`permit_id`,`tool_id`),
    KEY `permit_tool_permit_idx` (`permit_id`),
    KEY `permit_tool_tool_idx`   (`tool_id`),
    CONSTRAINT `permit_tool_permit_fk` FOREIGN KEY (`permit_id`)
        REFERENCES `permits` (`id`) ON DELETE CASCADE,
    CONSTRAINT `permit_tool_tool_fk` FOREIGN KEY (`tool_id`)
        REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12) catalog_pending_standards — bandeja POLIMÓRFICA de vínculos norma↔ítem que
--     NO resolvieron contra safety_standards. Sin FK a safety_standards (por
--     definición no existe la fila). `linkable_type/id` = Tool / ToolCheckPoint /
--     Permit. `jurisdiction` = bucket de la fuente (csatf/eeuu_ca/mexico). Se
--     vacía cuando el owner da de alta la norma y se re-siembra el catálogo.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `catalog_pending_standards` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `raw_code`       VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,   -- MISMA collation que safety_standards.regulation_code: permite unir en SQL al dar de alta la norma

    `jurisdiction`   VARCHAR(20)  NULL,       -- csatf | eeuu_ca | mexico
    `linkable_type`  VARCHAR(191) NOT NULL,   -- App\Models\Tool | ToolCheckPoint | Permit
    `linkable_id`    BIGINT UNSIGNED NOT NULL,
    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_catalog_pending_standards` (`raw_code`,`linkable_type`,`linkable_id`),
    KEY `catalog_pending_standards_linkable_idx` (`linkable_type`,`linkable_id`),
    KEY `catalog_pending_standards_raw_idx` (`raw_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
