-- ============================================================================
-- CrewCare — CONTRACT BUILDER · gating del builder + figura legal. 2026-08-14.
--
-- CONTENIDO:
--   1) permiso Spatie `contracts.author` (redactar/ensamblar plantillas — Contract Builder).
--   2) rol `representante-legal` (figura legal de la productora: FIRMA documentos + CREA contratos).
--   3) grants: line-producer + super-admin → contracts.author;
--      representante-legal → contracts.author + documents.view + documents.sign + profile.update-own.
--
--   El Contract Builder es DISTINTO de `settings.manage`: el contenido LEGAL del contrato es de la
--   PRODUCTORA — CrewCare solo ensambla, numera y estampa firmas; no redacta. super-admin lo obtiene
--   igual por Gate::before; se asigna explícito por consistencia.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (INSERT ... SELECT NOT EXISTS).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-14-contracts-author-permission.sql
-- ⚠ TRAS APLICAR: limpiar la caché de permisos o la app no verá lo nuevo:
--     php artisan permission:cache-reset      (o  php artisan cache:clear)
--
-- REVERSIÓN (deja intactos los contratos ya firmados/sellados):
--   DELETE rhp FROM `role_has_permissions` rhp JOIN `permissions` p ON p.id = rhp.permission_id
--     WHERE p.`name` = 'contracts.author';
--   DELETE FROM `permissions` WHERE `name` = 'contracts.author';
--   -- el rol `representante-legal` se conserva salvo que se quiera retirar a mano.
-- ============================================================================

-- 1) El permiso (Spatie: UNIQUE(name, guard_name)).
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'contracts.author', 'web', NOW(), NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `name` = 'contracts.author' AND `guard_name` = 'web');

-- 2) El rol representante-legal (Spatie: UNIQUE(name, guard_name)).
INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'representante-legal', 'web', NOW(), NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `name` = 'representante-legal' AND `guard_name` = 'web');

-- 3a) contracts.author → line-producer + super-admin.
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id
  FROM `permissions` p
  JOIN `roles` r ON r.`guard_name` = 'web' AND r.`name` IN ('line-producer', 'super-admin')
 WHERE p.`name` = 'contracts.author' AND p.`guard_name` = 'web'
   AND NOT EXISTS (SELECT 1 FROM `role_has_permissions` rhp WHERE rhp.`permission_id` = p.id AND rhp.`role_id` = r.id);

-- 3b) set completo → representante-legal (solo permisos que existan).
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id
  FROM `permissions` p
  JOIN `roles` r ON r.`guard_name` = 'web' AND r.`name` = 'representante-legal'
 WHERE p.`guard_name` = 'web'
   AND p.`name` IN ('contracts.author', 'documents.view', 'documents.sign', 'profile.update-own')
   AND NOT EXISTS (SELECT 1 FROM `role_has_permissions` rhp WHERE rhp.`permission_id` = p.id AND rhp.`role_id` = r.id);
