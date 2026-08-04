-- ─────────────────────────────────────────────────────────────────────────────
-- Delta #51 · 2026-08-04 · Relación ICONO ↔ evento del catálogo (curable).
--
-- hazard_events.risk_icon: clave de pictograma (_rm-icon) que SOBREESCRIBE el
-- icono derivado (categoría + palabra clave). NULL = usa el derivado. Vive en el
-- CATÁLOGO, así el mapeo de riesgos y cualquier otro módulo comparten la misma
-- relación. DERIVADO/COSMÉTICO: no entra a ningún sello.
--
-- Idempotente (MySQL 5.7: no hay ADD COLUMN IF NOT EXISTS).
-- ─────────────────────────────────────────────────────────────────────────────

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'hazard_events'
    AND COLUMN_NAME  = 'risk_icon'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE hazard_events ADD COLUMN risk_icon VARCHAR(24) NULL AFTER category',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
