-- ============================================================================
-- CrewCare — EL CONTRATO · PASO C: EL SOBRE + destinatarios + consentimiento.
-- (2026-08-13) — delta. Se firma un PAQUETE (carátula+clausulado+anexos), no un
--   documento. TRES tablas:
--     contract_envelopes            (el sobre; snapshot JSON del paquete; estados
--                                    draft|sent|completed|cancelled; sella con
--                                    HasDigitalSignatures = integridad).
--     contract_envelope_recipients  (destinatarios; el certificado: nombre/correo/
--                                    cargo/empresa CONGELADOS + 4 timestamps + ip +
--                                    método; papeles preparer/contracted/binder).
--     contract_consents             (consentimiento e-firma APARTE, una vez por persona).
--   El acto de aceptación (recipients) NO se fusiona con el sello (digital_signatures).
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (CREATE TABLE IF NOT EXISTS):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-contract-envelopes.sql
-- Requiere payee_contracts. SIN permiso nuevo (creación por PayeePolicy; config por settings.manage).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `contract_envelopes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payee_contract_id` bigint(20) unsigned NOT NULL,
  `production_id` bigint(20) unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `documents` json DEFAULT NULL,
  `current_recipient_id` bigint(20) unsigned DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_envelopes_contract_idx` (`payee_contract_id`),
  KEY `contract_envelopes_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contract_envelope_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `envelope_id` bigint(20) unsigned NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `payee_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `resent_at` datetime DEFAULT NULL,
  `viewed_at` datetime DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sign_method` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cer_envelope_idx` (`envelope_id`, `sort_order`),
  KEY `cer_status_idx` (`status`),
  KEY `cer_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contract_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `consenter_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `consenter_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifier` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_consents_person_uq` (`consenter_type`, `consenter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
