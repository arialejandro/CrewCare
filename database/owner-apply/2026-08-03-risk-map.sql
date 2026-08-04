-- ============================================================================
-- CrewCare — MAPEO DE RIESGOS Y RECURSOS (2026-08-03) — delta #50.
--
-- QUÉ ES: un EDITOR APARTE (no vive dentro del scouting) que produce un DOCUMENTO
--   SELLADO, una página por VISTA, que el crew lee para conocer las condiciones de
--   seguridad de la locación. Referencia un scouting en SOLO LECTURA: de él toma sus
--   IMÁGENES y sus EVENTOS YA EVALUADOS en el risk assessment. No hay captura libre
--   de peligros: un marcador de peligro solo puede citar un `event_id` ya evaluado
--   en ese scouting (de ahí sale la norma y su URL). El scouting NO se modifica aquí.
--
--   Reemplaza al andamiaje anterior (imágenes por tipo sobre `scouting_canvases`,
--   retirado). El check "Mapeo de riesgos" en Imágenes del scouting SE QUEDA como
--   puente (marca qué fotos del scouting alimentan el editor).
--
-- ── DOCTRINA DEL SELLO ──────────────────────────────────────────────────────
--   El SHA-256 se calcula sobre el DATO (vistas + marcadores + coordenadas +
--   event_ids + narrativas), NO sobre el render. Mejorar una imagen (realce) NO
--   invalida el sello: `image_enhanced_path` vive aparte y queda FUERA del hash.
--   `status`/`folio`/`uuid`/`sealed_*` son estado de emisión (fuera del contenido).
--   La imagen ORIGINAL nunca se sobrescribe (`image_original_path` es inmutable).
--
-- ── TRES TABLAS, aditivas ────────────────────────────────────────────────────
--   * risk_maps          — el documento. Un risk_map pertenece a UN scouting.
--   * risk_map_views     — una fila por PÁGINA (imagen anotada + 3 narrativas cortas).
--   * risk_map_markers   — gota + icono sobre la vista, por x_pct/y_pct (0..100).
--
--   FK DURA solo ENTRE las tablas nuevas (todas InnoDB utf8mb4): borrar un risk_map
--   cae en cascada a sus vistas y marcadores. A `scouting_reports`/`hazard_events`/
--   `productions` se referencia FK-SOFT (pueden no ser InnoDB/mismo charset; además
--   el documento es histórico y debe sobrevivir cambios del catálogo).
--
-- ── FUERA DE ALCANCE (estructura lista, sin implementar) ─────────────────────
--   Realce por IA (`image_enhanced_path`) y trazo de croquis de área (`polygon`).
--   Las columnas ya existen para recibirlos después; hoy quedan NULL.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (changes/original/attributes/relations). `label`/`reference_text` seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF
--   NOT EXISTS). MySQL 5.7: JSON prohíbe DEFAULT → `polygon` va JSON NULL.
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-03-risk-map.sql
--   Permiso Spatie: php artisan db:seed --class=RiskMapPermissionsSeeder + cache:clear
--
-- REVERSIÓN:
--   DROP TABLE IF EXISTS `risk_map_markers`;
--   DROP TABLE IF EXISTS `risk_map_views`;
--   DROP TABLE IF EXISTS `risk_maps`;
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1) risk_maps — el documento
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `risk_maps` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scouting_id`    BIGINT UNSIGNED NOT NULL,                 -- scouting_reports.id (FK-soft, solo lectura)
    `project_id`     BIGINT UNSIGNED NULL DEFAULT NULL,        -- productions.id (CurrentProduction, FK-soft)
    `location_id`    BIGINT UNSIGNED NULL DEFAULT NULL,        -- reservado (hoy no hay catálogo de locaciones)

    `title`          VARCHAR(160) NOT NULL,                    -- título interno editable (la banda dice "MAPEO DE RIESGOS Y RECURSOS")
    `status`         ENUM('draft','sealed') NOT NULL DEFAULT 'draft',
    `version`        INT UNSIGNED NOT NULL DEFAULT 1,
    `pin_scale`      VARCHAR(8) NOT NULL DEFAULT 'md',         -- tamaño del pin (sm|md|lg); DISPLAY, fuera del hash

    -- Emisión / sello (se asignan al sellar; NULL mientras es borrador) --------
    `folio`          VARCHAR(40)  NULL DEFAULT NULL,           -- consecutivo legible (RMAP-####)
    `uuid`           CHAR(36)     NULL DEFAULT NULL,           -- verificador público
    `sealed_at`      DATETIME     NULL DEFAULT NULL,           -- momento del sello
    `seal_hash`      CHAR(64)     NULL DEFAULT NULL,           -- SHA-256 sobre el DATO
    `sealed_by`      BIGINT UNSIGNED NULL DEFAULT NULL,        -- users.id (FK-soft)

    `created_by_id`  BIGINT UNSIGNED NULL DEFAULT NULL,        -- autor del borrador (auditoría)
    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `risk_maps_uuid_unique`   (`uuid`),
    KEY `risk_maps_scouting_idx`   (`scouting_id`),
    KEY `risk_maps_project_idx`    (`project_id`),
    KEY `risk_maps_status_idx`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2) risk_map_views — una fila por PÁGINA del documento
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `risk_map_views` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `risk_map_id`          BIGINT UNSIGNED NOT NULL,
    `sort_order`           INT UNSIGNED NOT NULL DEFAULT 0,

    `view_type`            ENUM('satelital','aerea','fachada_calle','acceso_circulacion','set','basecamp','detalle','otro')
                           NOT NULL DEFAULT 'otro',
    `label`                VARCHAR(160) NULL DEFAULT NULL,     -- propuesta desde view_type, editable

    `image_original_path`  VARCHAR(500) NOT NULL,             -- INMUTABLE, nunca se sobrescribe (/storage/...)
    `image_enhanced_path`  VARCHAR(500) NULL DEFAULT NULL,    -- realce opcional (FUERA DE ALCANCE hoy; fuera del hash)
    `image_source`         ENUM('scouting_photo','upload','satelital','dron') NOT NULL DEFAULT 'upload',

    -- Narrativa: TRES campos cortos, no un textarea libre --------------------
    `narrative_what`       VARCHAR(280) NULL DEFAULT NULL,    -- "Qué hay aquí"
    `narrative_decision`   VARCHAR(280) NULL DEFAULT NULL,    -- "Qué se decidió"
    `narrative_action`     VARCHAR(280) NULL DEFAULT NULL,    -- "Qué debe hacer el crew"

    `created_at`           TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `risk_map_views_map_order_idx` (`risk_map_id`, `sort_order`),
    CONSTRAINT `risk_map_views_map_fk`
        FOREIGN KEY (`risk_map_id`) REFERENCES `risk_maps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3) risk_map_markers — gota + icono sobre la vista (x_pct/y_pct)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `risk_map_markers` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `view_id`        BIGINT UNSIGNED NOT NULL,
    `sort_order`     INT UNSIGNED NOT NULL DEFAULT 0,

    `kind`           ENUM('resource','hazard','area') NOT NULL,
    `resource_type`  ENUM('extintor','salida_emergencia','botiquin','punto_alarma',
                          'manguera_hidrante','tablero_electrico','punto_reunion','acceso_ambulancia')
                     NULL DEFAULT NULL,                        -- solo si kind=resource
    `event_id`       BIGINT UNSIGNED NULL DEFAULT NULL,        -- hazard_events.id (obligatorio si kind=hazard; FK-soft)

    `x_pct`          DECIMAL(6,3) NOT NULL,                    -- 0..100, relativo a la imagen
    `y_pct`          DECIMAL(6,3) NOT NULL,
    `label_side`     ENUM('left','right') NOT NULL DEFAULT 'right',
    `reference_text` VARCHAR(200) NULL DEFAULT NULL,           -- frase de referencia visible (opcional)
    `polygon`        JSON         NULL DEFAULT NULL,           -- solo kind=area (FUERA DE ALCANCE hoy)

    `created_at`     TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `risk_map_markers_view_order_idx` (`view_id`, `sort_order`),
    KEY `risk_map_markers_event_idx`      (`event_id`),
    CONSTRAINT `risk_map_markers_view_fk`
        FOREIGN KEY (`view_id`) REFERENCES `risk_map_views` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
