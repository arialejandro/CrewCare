-- ============================================================================
-- PAE · VERSIONADO (2026-08-06, decisión del owner que REEMPLAZA la de "emisiones
-- independientes"). El PAE ahora se puede EDITAR: cada edición emite una REVISIÓN
-- nueva (documento sellado independiente) que SUPERSEDE a la anterior. La versión
-- visible del documento deriva de `revision` (v1.0, v2.0, …).
--
--   · revision       INT, arranca en 1; +1 por cada edición.
--   · supersedes_id  apunta a la versión anterior (FK-soft, sin ON DELETE duro).
--   · root_id        apunta a la PRIMERA versión de la cadena → el FOLIO es estable
--                    (mismo PAE-#### para v1/v2/v3). NULL en la v1 = ella misma.
--
-- Idempotente-ish: si ya existen las columnas, MySQL 5.7 dará error 1060 (duplicate
-- column) — en ese caso ya está aplicado y se ignora. La tabla la crea el delta
-- 2026-08-06-pae-emergency-action-plans.sql (aplicar ESE primero).
-- ============================================================================

ALTER TABLE `emergency_action_plans`
  ADD COLUMN `revision` INT NOT NULL DEFAULT 1 AFTER `shoot_day`,
  ADD COLUMN `supersedes_id` BIGINT UNSIGNED NULL AFTER `revision`,
  ADD COLUMN `root_id` BIGINT UNSIGNED NULL AFTER `supersedes_id`;

ALTER TABLE `emergency_action_plans`
  ADD INDEX `eap_supersedes_idx` (`supersedes_id`),
  ADD INDEX `eap_root_idx` (`root_id`);
