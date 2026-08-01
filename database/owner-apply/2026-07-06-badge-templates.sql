-- ============================================================================
-- CrewCare — Sistema de Gafetes (ID-Badge) CONFIGURABLE (2026-07-06)
-- Una plantilla de diseño (config JSON) editable por el super-admin. La tarjeta
-- queda fija 108mm×172mm; lo configurable son fuentes, colores, foto, posiciones.
--
-- Aplicar FUERA de Laravel (NO migrate), igual que el resto del esquema.
-- MySQL 5.7: no soporta ADD COLUMN IF NOT EXISTS → correr una sola vez.
-- El JSON se guarda como LONGTEXT (mismo patrón que scouting_reports / cmedic) +
-- cast 'array' en el modelo.
-- ============================================================================

-- 1) badge_templates: catálogo de plantillas de gafete. En v1 se usa UNA sola
--    fila activa (active=1). `config` (LONGTEXT/JSON) = mapa de opciones de diseño
--    fusionado sobre BadgeTemplate::DEFAULTS en la app.
CREATE TABLE IF NOT EXISTS `badge_templates` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL DEFAULT 'default',
  `config`     LONGTEXT     NULL,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
