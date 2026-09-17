-- ============================================================================
-- OWNER-APPLY · 2026-08-30 · clinical_read_logs (bitácora de LECTURA clínica). Delta #120.
-- Gemelo de la migración 2026_08_30_000002_create_clinical_read_logs_table.php.
--
-- QUÉ: registra quién abrió qué expediente clínico y cuándo. Producción puede LEER expedientes
-- (HOD/line-producer/coordinador) → esto deja el rastro. Invisible para el usuario, append-only.
-- SEGURO DE CORRER VARIAS VECES: CREATE TABLE IF NOT EXISTS. No toca datos.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `clinical_read_logs` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reader_id`          BIGINT UNSIGNED NULL,
  `reader_is_clinical` TINYINT(1) NOT NULL DEFAULT 0,
  `record_type`        VARCHAR(24) NOT NULL,
  `record_id`          BIGINT UNSIGNED NULL,
  `patient_ref`        BIGINT UNSIGNED NULL,
  `route_name`         VARCHAR(64) NULL,
  `ip_address`         VARCHAR(45) NULL,
  `opened_at`          DATETIME NOT NULL,
  `created_at`         TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinical_read_logs_record_type_record_id_index` (`record_type`, `record_id`),
  KEY `clinical_read_logs_reader_id_index` (`reader_id`),
  KEY `clinical_read_logs_opened_at_index` (`opened_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
