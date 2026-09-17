SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────────────
-- SPFX · efectos del catálogo de FÁBRICA nacen VERIFICADOS DE ORIGEN
-- ────────────────────────────────────────────────────────────────────────────
-- `sfx_effect_types` es catálogo de fábrica de CrewCare: existe desde el inicio en
-- producción y es autoritativo. Los efectos NO tienen ruta de verificación por humano
-- (a diferencia de los INSUMOS/consumables), así que el estado "pendiente" (verified_at
-- NULL → badge ámbar en las cards) es UI muerta para ellos.
--
-- Este delta marca como verificadas DE ORIGEN (verified_by_id = NULL, la convención de
-- "sin humano detrás") las filas que quedaron pendientes, de modo que ninguna card muestre
-- "Pendiente de verificación".
--
-- IDEMPOTENTE: solo toca filas con verified_at NULL; NO repisa ningún sello existente.
-- (El seeder SpfxCatalogSeeder ya siembra todos los efectos verificados de origen; esto
--  alinea las instalaciones que se poblaron con el SQL base 2026-07-16-sfx-effect-types.sql.)
UPDATE sfx_effect_types
   SET verified_at = COALESCE(verified_at, NOW())
 WHERE verified_at IS NULL;
