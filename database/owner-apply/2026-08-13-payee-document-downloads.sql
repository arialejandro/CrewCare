-- ============================================================================
-- CrewCare — BITÁCORA DE DESCARGAS de documentos fiscales del payee.
-- (2026-08-13) — delta. Decisión del owner: el dato es RFC/CLABE/domicilio de
--   terceros y la app ya registra quién consultó la cédula → registrar también la
--   DESCARGA del PDF fiscal.
--
-- QUÉ ES: tabla `payee_document_downloads` (append-only). Registra QUIÉN descargó
--   QUÉ documento y CUÁNDO (+ ip). Se escribe en el serve gateado (PayeeController@document).
--   NUNCA bloquea: si el insert falla, el documento igual se entrega. FK-soft.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-payee-document-downloads.sql
-- SIN permiso/seeder/lang. Sin dependencias de otros deltas (solo referencia ids sueltos).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `payee_document_downloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_id` bigint(20) unsigned NOT NULL,
  `document_id` bigint(20) unsigned NOT NULL,
  `document_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `downloaded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payee_dl_payee_idx` (`payee_id`),
  KEY `payee_dl_document_idx` (`document_id`),
  KEY `payee_dl_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
