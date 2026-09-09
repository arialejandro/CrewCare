-- ============================================================================
-- CrewCare — CONTRATO · PASO C: BITÁCORA DE EVENTOS del sobre (append-only).
-- (2026-08-15) — delta. Fase 1 de la alineación a arquitectura tipo DocuSign: el estado del sobre
--   deja de vivir SOLO en columnas booleanas y pasa a DERIVARSE de una bitácora inmutable — la
--   tabla más crítica de un servicio de firma. Una fila por evento; nunca se actualiza ni se borra.
--
-- occurred_at = reloj del SERVIDOR + display_timezone (defensibilidad). prev_hash/hash = CADENA
--   (HMAC-SHA256, misma llave del sello): alterar el evento N invalida del N en adelante.
-- ADITIVO: convive con las columnas de estado actuales; no las reemplaza. Los sobres viejos siguen
--   válidos (nacen sin eventos; la bitácora arranca desde el primer evento nuevo).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-15-contract-envelope-events.sql
-- SIN permiso/seeder/lang. FK-soft.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `contract_envelope_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `envelope_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `actor_label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `display_timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `prev_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cee_envelope_idx` (`envelope_id`, `id`),
  KEY `cee_event_idx` (`event`),
  KEY `cee_recipient_idx` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
