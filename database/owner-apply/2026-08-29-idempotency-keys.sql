-- ============================================================================
-- OWNER-APPLY · 2026-08-29 · idempotency_keys (envío diferido offline · Camino A)
-- Delta #117. Gemelo idempotente de la migración
--   2026_08_29_000001_create_idempotency_keys_table.php
--
-- QUÉ: memoria "a lo hecho, hecho" del middleware IdempotentReplay. Un borrador
-- capturado sin red se reproduce por la RUTA NORMAL al reconectar; el cliente manda
-- X-Idempotency-Key (= id del borrador) para que un reintento del mismo envío NO
-- cree un segundo documento sellado.
--
-- SEGURO DE CORRER VARIAS VECES: CREATE TABLE IF NOT EXISTS. No toca datos.
-- `idem_key` (no `key`) para no chocar con la palabra reservada de MySQL 8.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `idempotency_keys` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idem_key`        VARCHAR(191) NOT NULL,
  `user_id`         BIGINT UNSIGNED NULL,
  `method`          VARCHAR(8) NULL,
  `path`            VARCHAR(255) NULL,
  `response_status` SMALLINT UNSIGNED NULL,
  `created_at`      TIMESTAMP NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_keys_idem_key_unique` (`idem_key`),
  KEY `idempotency_keys_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
