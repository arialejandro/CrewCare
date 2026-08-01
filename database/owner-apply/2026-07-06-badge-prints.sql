-- ============================================================================
-- CrewCare — Gafetes: tabla de IMPRESIÓN de credenciales (2026-07-06)
--
-- Reemplaza el uso semántico de la columna `users.age` (nombre sin sentido que el
-- código legacy sobrecargaba como "credencial impresa"). Ahora la impresión vive en
-- su propia tabla: la PRESENCIA de una fila = ese gafete ya se imprimió.
--   printed_at    = cuándo se marcó impreso
--   printed_by_id = quién lo marcó (auditable, sistema cerrado)
--
-- `users.age` se DEJA INTACTA (columna muerta) para NO generar conflicto con nada
-- que aún la referencie (import legacy, seeders). Aplicar FUERA de Laravel (NO migrate).
-- Idempotente: CREATE TABLE IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `badge_prints` (
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `printed_at`    TIMESTAMP NULL DEFAULT NULL,
    `printed_by_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
