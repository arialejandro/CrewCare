-- =====================================================================================
--  PASO 2/3 · Módulo Médico — KEY MEDIC (permiso otorgable y revocable)
--  Fecha: 2026-07-24
--
--  CONTENIDO: UNA fila nueva en `permissions` → `medical.consolidate`.
--    NO se asigna a ningún ROL a propósito (ni siquiera a `medic`): nace APAGADO. El
--    super-admin lo enciende a la PERSONA desde /rolescrud (permiso DIRECTO de Spatie en
--    model_has_permissions), igual que el acceso clínico del safety-officer del paso 1/3.
--
--  QUÉ HABILITA:
--    · Ver TODAS las consultas individuales de un paciente (un médico común sólo ve las suyas —
--      aislamiento por propiedad, cmedic::scopeVisibleTo).
--    · Emitir el PDF consolidado de la BITÁCORA semanal y del CONTEO de medicamentos
--      (MedicalReportController::canEmitAggregate). Un médico SIN este permiso no los emite.
--
--  POR QUÉ UN PERMISO Y NO `production_user.is_lead`:
--    los médicos están en el departamento "Salud y Seguridad", que comparten con los safety
--    officers → el is_lead de esa área normalmente NO es un médico. Verificado en vivo: los 3
--    médicos tienen is_lead = 0 y el único is_lead vivo es un HOD de Locaciones.
--
--  NO HAY TABLA NUEVA: la trazabilidad (quién lo otorgó y cuándo) reusa `medical_access_grants`
--  del paso 1/3 — su columna `permission` guarda 'medical.consolidate' en vez de 'medical.view'.
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`).
--  ⚠ TRAS APLICAR: limpiar la caché de permisos de Spatie o la app no verá el permiso nuevo:
--      php artisan permission:cache-reset      (o  php artisan cache:clear)
--
--  REVERSIÓN:
--    DELETE mhp FROM `model_has_permissions` mhp
--      JOIN `permissions` p ON p.id = mhp.permission_id WHERE p.`name` = 'medical.consolidate';
--    DELETE FROM `medical_access_grants` WHERE `permission` = 'medical.consolidate';
--    DELETE FROM `permissions` WHERE `name` = 'medical.consolidate';
--    (Consultas, expedientes y firmas quedan intactos: este paso no toca datos clínicos.)
-- =====================================================================================

-- Idempotente: INSERT ... SELECT con NOT EXISTS (Spatie tiene UNIQUE(name, guard_name)).
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'medical.consolidate', 'web', NOW(), NOW()
  FROM DUAL
 WHERE NOT EXISTS (
     SELECT 1 FROM `permissions`
      WHERE `name` = 'medical.consolidate' AND `guard_name` = 'web'
 );
