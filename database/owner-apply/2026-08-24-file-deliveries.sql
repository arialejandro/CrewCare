-- ENVÍO DE ARCHIVOS CON MARCA DE AGUA — outbox reusable (llamado + documentos sueltos).
-- El envío masivo se ENCOLA aquí y un comando agendado (cron `schedule:run`) lo drena en tandas,
-- marcando cada copia con el nombre en créditos de quien la recibe. Aditivo (2 tablas NUEVAS +
-- 1 columna nueva), nada sella/altera lo existente. Idempotente.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `file_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `source_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `base_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `watermark` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_deliveries_production_id_index` (`production_id`),
  KEY `file_deliveries_source_type_source_id_index` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file_delivery_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_delivery_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `watermark_text` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `error` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `file_delivery_recipients_file_delivery_id_index` (`file_delivery_id`),
  KEY `file_delivery_recipients_status_attempts_index` (`status`,`attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PDF(s) adicional(es) tras el back (detrás del flag `callsheet_extra_docs`).
-- Idempotente: agrega la columna solo si no existe.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'call_packages' AND COLUMN_NAME = 'extra_docs');
SET @sql := IF(@col = 0,
  'ALTER TABLE `call_packages` ADD COLUMN `extra_docs` longtext COLLATE utf8mb4_unicode_ci NULL AFTER `frozen_schedule`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
