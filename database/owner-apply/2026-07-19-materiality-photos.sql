-- =============================================================================
-- 2026-07-19 · MATERIALIDAD (evidencia fiscal SAT) — tabla de fotos con fecha de servidor
-- -----------------------------------------------------------------------------
-- Repositorio de evidencia fotográfica para comprobación fiscal del consumo médico.
-- La FECHA DE CAPTURA es `created_at` (la pone el SERVIDOR vía Eloquent, NO es editable ni
-- viene del formulario) → es la fecha que aguanta revisión fiscal.
--
-- Pestaña Materialidad del módulo médico. Gate = permission:medical.materials (el mismo del
-- conteo; NO se crea un permiso nuevo). La app degrada con empty-state si esta tabla no existe.
--
-- IDEMPOTENTE: CREATE TABLE IF NOT EXISTS (MySQL 5.7.33 no soporta ADD COLUMN IF NOT EXISTS,
-- pero sí la creación condicional de tabla). Sin FKs duras (captured_by_id/consultation_id/
-- production_id son referencias blandas nullable) para no acoplar el borrado ni chocar con el
-- PK no estándar de cmedic (id_cmedic).
--
-- REVERSIÓN:  DROP TABLE IF EXISTS `materiality_photos`;  (el conteo y los datos médicos intactos)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `materiality_photos` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_path`      VARCHAR(255)    NOT NULL,                 -- ruta pública (Storage::url())
  `note`            VARCHAR(500)    NULL DEFAULT NULL,        -- concepto/descripción corta (opcional)
  `captured_by_id`  BIGINT UNSIGNED NULL DEFAULT NULL,        -- users.id que subió (auditoría)
  `consultation_id` BIGINT UNSIGNED NULL DEFAULT NULL,        -- cmedic.id_cmedic si aplica (blando)
  `production_id`   BIGINT UNSIGNED NULL DEFAULT NULL,        -- producción si aplica (blando)
  `created_at`      TIMESTAMP       NULL DEFAULT NULL,        -- FECHA DE SERVIDOR (fiscal, no editable)
  `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `materiality_photos_created_at_idx`  (`created_at`),
  KEY `materiality_photos_captured_by_idx` (`captured_by_id`)
) ENGINE=InnoDB;
