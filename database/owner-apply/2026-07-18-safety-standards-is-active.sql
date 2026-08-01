-- ============================================================================
-- CrewCare — Estado VIGENTE / RETIRADO de NORMAS (2026-07-18)
-- Paso 4a: la pantalla de captura de normas (`safety_standards`) gana un flag
-- de vigencia para que una autoridad pueda RETIRAR una norma del catálogo de
-- captura SIN romper el histórico que ya la referencia.
--
-- Contenido:
--   1) COL   safety_standards.is_active   — 1 = vigente/capturable, 0 = retirada.
--   2) IDX   safety_standards_active_idx  — sobre (is_active), para el orden
--            "vigentes primero" y el filtro de la lista.
--
-- SIN backfill: el DEFAULT 1 ya deja TODAS las filas existentes (las 78 normas
-- curadas) como vigentes en el mismo ALTER. No hace falta un UPDATE posterior.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. El CÓDIGO es DEFENSIVO (SafetyStandard::supportsActiveFlag() envuelve
-- Schema::hasColumn con memo estático): la app corre IGUAL sin este SQL. Sin la
-- columna, isActive() devuelve true (todo vigente) y scopeActive() es un no-op,
-- así que ni la lista ni el retiro truenan. Al aplicar el delta, la feature se
-- activa sola.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) el ALTER/índice se guardan con un check en
-- information_schema vía un procedimiento temporal.
--
-- ⚠ NOTA DURA — POR QUÉ `is_active` Y NO `deleted_at` (SoftDeletes):
--   "Retirar" una norma debía ser un booleano PLANO, no un SoftDelete. Injury
--   (injuryreport.blade.php) y Scouting (scoutings/show.blade.php) pintan sus
--   normas SOLO por la relación VIVA ->standards, SIN snapshot / sin copia
--   congelada del texto de la norma al momento del reporte. Si el retiro fuera
--   un `deleted_at` con el scope global de SoftDeletes, esa relación dejaría de
--   resolver la fila retirada y la norma DESAPARECERÍA de reportes históricos ya
--   firmados — reescribiría el pasado. `is_active` (SIN scope global) deja la
--   fila resolviéndose viva para el histórico; "retirada" solo la saca de la
--   pantalla de CAPTURA (no permite elegirla en normas nuevas), nunca del
--   registro que ya la citó. Por eso NUNCA se usa ->delete() sobre una norma: su
--   hook `deleting` purgaría además standardables + hazard_event_standard y
--   destruiría el histórico de vínculos.
--
-- ⚠ ANCLA DE COLUMNA: AFTER `reference_url`, una columna BASE (existe desde la
--   migración original en TODOS los entornos). A propósito NO se ancla AFTER
--   `category_name_en`: esa columna la crea OTRO delta (2026-07-12-modules-6-14)
--   que puede no estar aplicado aún en prod; anclar a una columna inexistente
--   aborta el ALTER con MySQL error 1054 y tumbaría el CALL entero. La posición
--   física es cosmética, así que se elige la columna garantizada.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_safety_standards_is_active_2026_07_18;

DELIMITER //
CREATE PROCEDURE crewcare_safety_standards_is_active_2026_07_18()
BEGIN
    -- 1) safety_standards.is_active — vigencia de la norma. 1 = capturable, 0 = retirada.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards' AND COLUMN_NAME='is_active') THEN
        ALTER TABLE `safety_standards`
            ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `reference_url`;
        -- SIN UPDATE de backfill: el DEFAULT 1 ya deja vigentes a todas las filas
        -- existentes al crear la columna. (Nada nace pendiente aquí — la vigencia no
        -- es la verificación; una norma nueva nace VIGENTE y, por separado, pendiente
        -- de verificar según su creador.)
    END IF;

    -- 2) Índice sobre is_active — sirve al orden "vigentes primero" (ORDER BY is_active DESC)
    --    y a cualquier filtro futuro por vigencia.
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='safety_standards'
          AND INDEX_NAME='safety_standards_active_idx') THEN
        ALTER TABLE `safety_standards`
            ADD INDEX `safety_standards_active_idx` (`is_active`);
    END IF;
END //
DELIMITER ;

CALL crewcare_safety_standards_is_active_2026_07_18();
DROP PROCEDURE IF EXISTS crewcare_safety_standards_is_active_2026_07_18;
