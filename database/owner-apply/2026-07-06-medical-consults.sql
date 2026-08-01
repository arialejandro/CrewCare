-- ============================================================================
-- CrewCare — Módulo de Consultas Médicas (2026-07-06)
-- Catálogo de medicamentos + estructura de la consulta (autor, fecha de
-- atención, medicamentos ESTRUCTURADOS para poder contabilizarlos).
--
-- Aplicar FUERA de Laravel (NO migrate), igual que el resto del esquema.
-- MySQL 5.7: no soporta ADD COLUMN IF NOT EXISTS → correr una sola vez.
-- El JSON se guarda como LONGTEXT (mismo patrón que scouting_reports) + cast
-- 'array' en el modelo.
-- ============================================================================

-- 1) Catálogo de medicamentos. Una fila por VARIANTE = nombre × dosis × presentación
--    (p.ej. Paracetamol 250mg Tableta, Paracetamol 500mg Efervescente son 2 filas).
--    Es el que crece en modo "híbrido": si el médico escribe una variante nueva,
--    se registra aquí para reutilizarla.
CREATE TABLE IF NOT EXISTS `medications` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150) NOT NULL,
  `dosage`       VARCHAR(60)  NOT NULL DEFAULT '',
  `presentation` VARCHAR(50)  NOT NULL DEFAULT '',
  `active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NULL DEFAULT NULL,
  `updated_at`   TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medications_variant` (`name`,`dosage`,`presentation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) cmedic: autor de la nota (auditoría — cierra el hueco detectado), fecha real
--    de atención, y el snapshot estructurado de medicamentos dispensados.
--    `medication_items` (LONGTEXT/JSON) = [{medication_id,name,dosage,presentation,quantity}]
--    Se CONSERVA `medication` (texto libre) para las consultas históricas.
ALTER TABLE `cmedic`
  ADD COLUMN `created_by_id`     BIGINT UNSIGNED NULL AFTER `id_user`,
  ADD COLUMN `consultation_date` DATE            NULL AFTER `created_by_id`,
  ADD COLUMN `medication_items`  LONGTEXT        NULL AFTER `medication`;
