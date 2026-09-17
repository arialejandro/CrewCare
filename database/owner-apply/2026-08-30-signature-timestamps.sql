-- ============================================================================
-- OWNER-APPLY · 2026-08-30 · signature_timestamps (sello de tiempo TSA · RFC 3161)
-- Delta #119. Gemelo de la migración 2026_08_30_000001_create_signature_timestamps_table.php.
--
-- QUÉ: timbre externo (freeTSA) sobre cada sello digital. Es lo único que sobrevive si se
-- filtra CREWCARE_SEAL_KEY. Tabla SEPARADA keyed por digital_signatures.id → no toca el sello.
-- Se llena best-effort/async por el cron `tsa:stamp`; cubre sellos nuevos Y viejos (retroactivo).
--
-- SEGURO DE CORRER VARIAS VECES: CREATE TABLE IF NOT EXISTS. No toca datos.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `signature_timestamps` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `signature_id`     BIGINT UNSIGNED NOT NULL,
  `status`           VARCHAR(12) NOT NULL DEFAULT 'pending',
  `authority`        VARCHAR(60) NULL,
  `imprint`          CHAR(64) NULL,
  `tsr`              LONGTEXT NULL,
  `gen_time`         DATETIME NULL,
  `attempts`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `error`            TEXT NULL,
  `last_attempt_at`  DATETIME NULL,
  `stamped_at`       DATETIME NULL,
  `created_at`       TIMESTAMP NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `signature_timestamps_signature_id_unique` (`signature_id`),
  KEY `signature_timestamps_status_attempts_index` (`status`, `attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programa el cron (VPS):  * * * * *  php artisan schedule:run   (ya activo; agenda 'tsa:stamp' cada 5 min)
-- Opcional en .env:  CREWCARE_TSA_ENABLED=true  CREWCARE_TSA_URL=https://freetsa.org/tsr  CREWCARE_TSA_TIMEOUT=8
