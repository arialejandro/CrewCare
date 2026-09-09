-- ============================================================================
-- CrewCare — PASO 5 · migrar el PROVEEDOR de ambulancias a la base única (quien cobra).
-- (2026-08-13) — delta. Puente, NO destrucción.
--
-- QUÉ HACE: agrega `ambulance_providers.payee_id` (FK-soft NULL) para ligar el proveedor
--   a su identidad `payees` (persona MORAL). Los ids del proveedor NO cambian.
--
-- 🔴 POR QUÉ PUENTE Y NO MOVER: las actas selladas (`ambulance_inspections`) referencian
--   `provider_id` y CONGELAN `provider_name` DENTRO del hash (no están en $signatureExcludes).
--   Renumerar/borrar proveedores marcaría las actas como ALTERADO. Con el puente, los ids
--   siguen igual y el sello no se toca. `ambulance_crew` (padrón) y el catálogo NO se mueven.
--
-- DESPUÉS DEL SQL, correr el backfill (crea el payee + contrato + re-apunta docs de operar):
--     php artisan db:seed --class=MigrateAmbulanceProvidersToPayeesSeeder
--     php artisan cache:clear
--   (En un install NUEVO no hay proveedores → el backfill es no-op; los nuevos nacen ligados
--    desde el alta de proveedor.)
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente (guardado por information_schema):
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-13-ambulance-provider-payee-link.sql
-- REQUIERE: la tabla `payees` (delta 2026-08-13-payee-base) y `ambulance_providers` (delta #52).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_link_ambulance_payee_2026_08_13;
DELIMITER //
CREATE PROCEDURE crewcare_link_ambulance_payee_2026_08_13()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ambulance_providers' AND COLUMN_NAME='payee_id') THEN
        ALTER TABLE `ambulance_providers`
            ADD COLUMN `payee_id` BIGINT UNSIGNED NULL AFTER `id`,
            ADD KEY `ambulance_providers_payee_idx` (`payee_id`);
    END IF;
END //
DELIMITER ;
CALL crewcare_link_ambulance_payee_2026_08_13();
DROP PROCEDURE IF EXISTS crewcare_link_ambulance_payee_2026_08_13;
