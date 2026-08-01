-- ============================================================================
-- CrewCare — Soft delete de fichas SDS / consumibles SFX (2026-07-17)
-- Paso 1b del Pilar 3: "eliminar" un consumible pasa de BORRADO DURO a RETIRO.
-- La ficha se conserva (no se destruye la fila) y desaparece del catálogo para
-- todos los roles; solo un super-admin la ve en la papelera y puede restaurarla
-- o, si de verdad hace falta, destruirla en firme.
--
-- Contenido:
--   1) COL   consumables.deleted_at        — marca de retiro. NULL = ficha viva.
--   2) IDX   consumables_deleted_idx       — sobre (deleted_at). El global scope de
--            Eloquent SoftDeletes añade `deleted_at IS NULL` a CADA consulta del
--            catálogo; el índice evita el full-scan al crecer la tabla.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO es DEFENSIVO (Consumable::supportsSoftDelete() envuelve
-- Schema::hasColumn con memo estático): la app corre IGUAL sin este SQL. Sin la
-- columna, el trait NO registra su global scope y `delete()` vuelve a ser BORRADO
-- DURO (comportamiento previo), sin tronar. Al aplicar este delta, "eliminar"
-- empieza a retirar (soft) y aparece la papelera del super-admin.
--
-- NO HAY BACKFILL: todas las filas existentes deben quedar con deleted_at NULL
-- (= vivas), que es justo el default de la columna. Por eso, a diferencia del
-- delta de verificación, aquí NO va ningún UPDATE dentro del IF.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) los ALTER/índices se guardan con un check en
-- information_schema vía un procedimiento temporal.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_consumables_soft_delete_2026_07_17;

DELIMITER //
CREATE PROCEDURE crewcare_consumables_soft_delete_2026_07_17()
BEGIN
    -- 1) consumables.deleted_at — marca de retiro (soft delete). NULL = ficha viva.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='deleted_at') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
    END IF;

    -- 2) Índice sobre deleted_at — sirve al global scope (WHERE deleted_at IS NULL)
    --    y a la consulta de la papelera (onlyTrashed → WHERE deleted_at IS NOT NULL).
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables'
          AND INDEX_NAME='consumables_deleted_idx') THEN
        ALTER TABLE `consumables`
            ADD INDEX `consumables_deleted_idx` (`deleted_at`);
    END IF;
END //
DELIMITER ;

CALL crewcare_consumables_soft_delete_2026_07_17();
DROP PROCEDURE IF EXISTS crewcare_consumables_soft_delete_2026_07_17;
