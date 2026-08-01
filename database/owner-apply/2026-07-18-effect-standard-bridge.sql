-- ============================================================================
-- CrewCare — PUENTE SPFX ↔ NORMAS: pivote `effect_standard` (2026-07-18, Paso 5b)
--
-- Liga el catálogo de TIPOS de efecto especial (`sfx_effect_types`, Pieza 1 / Capa A)
-- al catálogo normativo canónico (`safety_standards`, Pieza 2+3) por ID, cerrando el
-- círculo Pieza 1 ↔ 2+3. Hasta hoy las normas de cada efecto vivían SOLO como texto
-- libre estructurado por jurisdicción en `sfx_effect_types.standards_snapshot` (JSON).
-- Este pivote las REFERENCIA por ID cuando el código empareja limpio con el catálogo;
-- el snapshot NO se toca (queda como fuente autoritativa y fallback — ver más abajo).
--
-- ── REVERSIÓN EXPLÍCITA DE UNA DECISIÓN PREVIA ──────────────────────────────
-- El comentario de `2026-07-16-sfx-effect-types.sql` (:134-144) y el doc-block de
-- App\Models\SfxEffectType documentaban que el owner RECHAZÓ este pivote ("estas
-- normas son de los SDS, no tendrían por qué estar en los catálogos"). El owner
-- SUPERÓ esa decisión el 2026-07-18, con un matiz que respeta su espíritu: este
-- puente NO inserta filas SPFX en `safety_standards` (no lo coloniza) — solo apunta
-- a filas CANÓNICAS que Pieza 2+3 ya creó. Las citas que no existen como norma
-- canónica (Fire Code, SB 132, SEDENA, NFPA, Fact Sheets CSATF...) NO se insertan:
-- se quedan solo en el snapshot y el seeder las LOGuea como no-resueltas.
--
-- ── PRECEDENCIA (aplicar ANTES): ────────────────────────────────────────────
--   1) `2026-07-16-sfx-effect-types.sql`  → crea `sfx_effect_types` (lado izq. de la FK).
--   2) el carril de Pieza 2+3 que puebla `safety_standards` (base + is-active +
--      verification + EnrichedCatalogSeeder) → lado der. de la FK, ya con datos.
-- Si falta `sfx_effect_types`, el CREATE aborta con errno 150 ("Failed to open the
-- referenced table"). NO es capricho del motor ni la SALIDA DE EMERGENCIA de abajo:
-- es orden de aplicación. Se autorepara: aplica el prerequisito y RE-EJECUTA este
-- archivo tal cual (`CREATE TABLE IF NOT EXISTS` salta lo ya creado).
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). En un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-18-effect-standard-bridge.sql
-- Después:  php artisan db:seed --class=EffectStandardBridgeSeeder   (reconciliación)
--           (dry-run:  BRIDGE_DRY_RUN=true php artisan db:seed --class=EffectStandardBridgeSeeder)
--
-- La app corre IGUAL sin este SQL: toda lectura del puente va detrás de
-- SfxEffectType::supportsStandardsBridge() (Schema::hasTable con memo). Sin la tabla,
-- la relación `standards()` se declara (perezosa, no truena) pero no se ejecuta.
--
-- IDEMPOTENCIA: `CREATE TABLE IF NOT EXISTS` basta — este delta solo CREA una tabla
-- nueva, no altera ninguna existente. Sin el andamiaje de information_schema que usan
-- otros deltas del carril (ese solo hace falta para `ADD COLUMN IF NOT EXISTS`, que
-- MySQL 5.7 no soporta; aquí no hay ALTER).
--
-- ── FK DURAS ON DELETE CASCADE ──────────────────────────────────────────────
-- Mismo criterio y precedente que `consumable_sfx_effect_type` (2026-07-16:188) y
-- `witnesses` (2026-07-12-modules-6-14.sql:23): este puente NO es polimórfico (a
-- diferencia de `standardables`), es catálogo↔catálogo, así que SÍ lleva FK dura.
-- Una fila huérfana (efecto o norma ya borrada) no es dato degradado: es basura sin
-- lectura posible. Ambos lados son BIGINT UNSIGNED sobre InnoDB con PRIMARY KEY en
-- `id` (verificado: sfx_effect_types.id y safety_standards.id son bigint(20) unsigned)
-- → tipos compatibles e índice en la columna referenciada, que es lo que la FK exige.
-- La distinta COLLATION de las dos tablas (unicode_ci vs general_ci) es irrelevante:
-- las columnas de la FK son BIGINT, sin collation.
--
-- SALIDA DE EMERGENCIA (igual que `consumable_sfx_effect_type`): si algún entorno
-- rechazara la FK, ELIMINA las dos líneas `CONSTRAINT ... FOREIGN KEY` y CONSERVA las
-- `KEY` de cada lado. No se pierde integridad: el modelo emula el cascade a mano en
-- SfxEffectType::booted() (purga el puente al borrar), como HazardEvent con
-- `hazard_event_standard`.
--
-- REVERSIÓN COMPLETA DEL PASO: `DROP TABLE IF EXISTS effect_standard;` + revertir el
-- commit. El texto libre `standards_snapshot` NUNCA se tocó → queda intacto.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- effect_standard — pivote N:M efecto ↔ norma canónica.
--    Columnas al estilo `hazard_event_standard` (id de modelo completo + `_id`),
--    tabla nombrada `effect_standard` (corto, como pidió el spec del Paso 5b).
--    UNIQUE(efecto, norma) → el sync()/reconciliación es idempotente y sin dobles.
--    Timestamps a propósito: en compliance, saber CUÁNDO se reconcilió un vínculo es
--    traza útil (mismo criterio que `consumable_sfx_effect_type`).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `effect_standard` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sfx_effect_type_id` BIGINT UNSIGNED NOT NULL,
    `safety_standard_id` BIGINT UNSIGNED NOT NULL,
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_effect_standard` (`sfx_effect_type_id`, `safety_standard_id`),
    KEY `effect_standard_effect_idx` (`sfx_effect_type_id`),
    KEY `effect_standard_std_idx`    (`safety_standard_id`),
    CONSTRAINT `effect_standard_effect_fk`
        FOREIGN KEY (`sfx_effect_type_id`) REFERENCES `sfx_effect_types` (`id`) ON DELETE CASCADE,
    CONSTRAINT `effect_standard_std_fk`
        FOREIGN KEY (`safety_standard_id`) REFERENCES `safety_standards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
