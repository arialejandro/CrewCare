-- PAQUETE DEL LLAMADO: ensamble front+back, firma de los 3, congelado y envío.
-- Aditivo (2 tablas NUEVAS), nada sella/altera lo existente. Idempotente (CREATE TABLE IF NOT EXISTS).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `call_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` bigint(20) unsigned NOT NULL,
  `call_date` date NOT NULL,
  `front_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `front_pages` smallint(5) unsigned DEFAULT NULL,
  `sign_field_map` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signers` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_at` timestamp NULL DEFAULT NULL,
  `frozen_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frozen_schedule` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_packages_production_id_call_date_unique` (`production_id`,`call_date`),
  KEY `call_packages_production_id_index` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `call_package_signatures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `call_package_id` bigint(20) unsigned NOT NULL,
  `signer_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `role_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rubrica_image` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_package_signatures_pkg_signer_unique` (`call_package_id`,`signer_key`),
  KEY `call_package_signatures_call_package_id_index` (`call_package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
