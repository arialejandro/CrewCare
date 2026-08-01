-- ============================================================================
-- Delta #48 — MAPEO DE LA LOCACIÓN: lienzos + pines sobre el scouting (2026-08-01).
--
-- Insumo del PAE. El safety LOCALIZA sobre una imagen dónde están los peligros y
-- los recursos de emergencia de la locación. NADA se despliega: LOCAL ES EL PRODUCTO.
--
-- Dos tablas nuevas, aditivas, sin tocar `scouting_reports` ni ningún sello:
--   * scouting_canvases — un scouting tiene VARIOS lienzos (satelital|foto|plano|aereo).
--     La imagen se guarda en disco (disco 'public', comprimida por ImageCompressor),
--     por eso `image_path` es una ruta, NO un data-URI (una locación lleva muchas).
--   * canvas_pins — LOS PINES PERTENECEN AL LIENZO (no al scouting): subir un plano o
--     una aérea después NO migra ni invalida los pines de las fotos. Coordenadas
--     RELATIVAS en porcentaje (x_pct/y_pct 0–100) para que el pin no se mueva al
--     cambiar de tamaño en pantalla o al imprimir.
--
-- Peligros: un pin de peligro NO duplica el catálogo — referencia por `hazard_event_id`
-- un evento YA evaluado en el `risk_assessment` del scouting (el controlador valida que
-- el id pertenezca a ese scouting). Puede quedar NULL sólo en un pin de recurso.
--
-- geo_lat/geo_lng: opcionales, sólo se llenan si el safety usa "mi ubicación actual"
-- (GPS del navegador) en un lienzo satelital/aéreo. LO VACÍO SE QUEDA VACÍO.
--
-- Idempotente (CREATE TABLE IF NOT EXISTS). SIN FK dura a propósito: `scouting_reports`
-- puede no ser InnoDB/mismo charset y una FK fallaría al aplicar; la integridad
-- (borrado en cascada de pines al borrar el lienzo) la maneja el controlador.
-- Aplicar MANUALMENTE con el cliente mysql (NUNCA artisan migrate):
--   mysql -u root crewcare < database/owner-apply/2026-08-01-location-mapping.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `scouting_canvases` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scouting_report_id` BIGINT UNSIGNED NOT NULL,
    `type`               VARCHAR(20)  NOT NULL COMMENT 'satelital|foto|plano|aereo',
    `name`               VARCHAR(120) NOT NULL COMMENT 'nombre corto del lienzo (Planta baja, Acceso norte)',
    `image_path`         VARCHAR(500) NOT NULL COMMENT 'ruta relativa en disco public (Storage::url)',
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `scouting_canvases_scouting_idx` (`scouting_report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `canvas_pins` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scouting_canvas_id` BIGINT UNSIGNED NOT NULL,
    `family`             VARCHAR(12)  NOT NULL COMMENT 'recurso|peligro',
    `resource_type`      VARCHAR(40)  NULL DEFAULT NULL COMMENT 'sólo family=recurso: extintor, botiquin, etc.',
    `hazard_event_id`    BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'sólo family=peligro: hazard_events.id ya evaluado en el scouting',
    `x_pct`              DECIMAL(6,3) NOT NULL COMMENT 'coordenada X relativa a la imagen, 0–100',
    `y_pct`              DECIMAL(6,3) NOT NULL COMMENT 'coordenada Y relativa a la imagen, 0–100',
    `note`               VARCHAR(300) NULL DEFAULT NULL COMMENT 'nota corta opcional',
    `photo_path`         VARCHAR(500) NULL DEFAULT NULL COMMENT 'foto de cerca opcional (ruta disco public)',
    `geo_lat`            DECIMAL(10,7) NULL DEFAULT NULL COMMENT 'GPS real opcional (mi ubicación actual)',
    `geo_lng`            DECIMAL(10,7) NULL DEFAULT NULL,
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `canvas_pins_canvas_idx` (`scouting_canvas_id`),
    KEY `canvas_pins_hazard_event_idx` (`hazard_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
