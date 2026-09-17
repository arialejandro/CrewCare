-- ============================================================================
-- OWNER-APPLY · 2026-08-30 · sessions (sesión en base → listar/revocar sesiones). Delta #121.
-- Gemelo de la migración 2026_08_30_000003_create_sessions_table.php.
--
-- QUÉ: habilita SESSION_DRIVER=database para poder VER y CERRAR sesiones activas desde el perfil
-- (caso del teléfono perdido, sin cambiar contraseña). Hoy el driver es `file` (no enumerable).
--
-- ⚠ ACTIVACIÓN: además de esta tabla, poner SESSION_DRIVER=database en .env. Al activarlo TODOS
-- re-inician sesión UNA vez (las sesiones `file` dejan de valer). Aditivo: sin el flip, tabla vacía.
-- SEGURO DE CORRER VARIAS VECES: CREATE TABLE IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `sessions` (
  `id`            VARCHAR(255) NOT NULL,
  `user_id`       BIGINT UNSIGNED NULL,
  `ip_address`    VARCHAR(45) NULL,
  `user_agent`    TEXT NULL,
  `payload`       LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
