-- ============================================================================
-- CrewCare — HDS de 16 secciones + pictogramas GHS + clave natural en
-- `consumables` (2026-07-17) — Paso 4b del Pilar 3.
--
-- QUÉ ES: la Capa B (`consumables`, el INSUMO/SDS: "con qué se hace" el efecto)
-- hoy guarda una SDS RESUMIDA — cuatro campos de texto libre que un Safety teclea
-- en set. Este delta le abre sitio a la ficha COMPLETA: las 16 secciones de la
-- Hoja de Datos de Seguridad (NOM-018-STPS / GHS), los pictogramas, y la clave
-- natural que hace idempotente el seed. Es SOLO ESQUEMA.
--
-- FUERA DE ESTE PASO, a propósito:
--   - Los DATOS los importa el Paso 4c desde el catálogo SPFX v1.1 del owner
--     (`crewcare_spfx_catalogo.json`, 41 insumos). Aquí NO se siembra NADA: no
--     hay UPDATE de sellado como el de `2026-07-16-sds-verification.sql`, y las
--     13 columnas nacen NULL en las 12 fichas vivas.
--   - Las rutas y vistas son del Paso 4d.
--   - `sfx_effect_types` y el puente `consumable_sfx_effect_type` son del Paso 4a
--     (`2026-07-16-sfx-effect-types.sql`) y aquí NO SE TOCAN.
--
-- Contenido — 13 columnas NUEVAS, TODAS NULLABLE, + 1 índice:
--    1) COL  code             — clave natural del catálogo SPFX (`INS-FIRE-01`).
--    2) COL  material_family  — familia del MATERIAL (`familia_insumo`).
--    3) COL  synonyms         — JSON, otros nombres comerciales (`sinonimos`).
--    4) COL  cas_number       — CAS, extraído de la prosa (nace casi vacía; ver abajo).
--    5) COL  ghs_pictograms   — JSON, códigos GHS de la prosa (ver abajo).
--    6) COL  sds_sections     — JSON, las 16 secciones NOM-018 (`hds`).
--    7) COL  sds_level        — profundidad de la ficha (`sds_nivel`): 2 o 3.
--    8) COL  sds_status       — estado declarado de la ficha (`sds_estado`).
--    9) COL  sds_source_note  — nota de la fuente (`sds_fuente_nota`).
--   10) COL  sds_source_date  — fecha de GENERACIÓN del catálogo. NO es verificación.
--   11) COL  sds_disclaimer   — descargo de responsabilidad (`sds_disclaimer`).
--   12) COL  source_verified  — confianza documental del catálogo de origen.
--   13) COL  sds_url_verified — el enlace resuelve a la HDS de ESA sustancia.
--   14) IDX  uq_consumables_code — UNIQUE sobre `code` (ver "POR QUÉ NULLABLE").
--
-- ── NOMBRES EN INGLÉS, CONTENIDO EN ESPAÑOL ─────────────────────────────────
-- El spec del owner escribe estas columnas en español (`enlace_sds`, `numero_un`,
-- `hds`...). Van en INGLÉS por la decisión que él mismo tomó en el Paso 4a
-- ("19/19 tablas del repo en inglés"). El CONTENIDO que guardan sigue en español,
-- tal cual viene del catálogo.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. Correr en un cliente MySQL (HeidiSQL / CLI):
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-17-consumables-sds16.sql
--
-- La app corre IGUAL sin este SQL: toda lectura de estas columnas va detrás de
-- Consumable::supportsExtendedSds() (Schema::hasColumn con memo estático). Sin
-- ellas el módulo se comporta como HOY — ficha resumida y punto: sdsSection()
-- devuelve null y no se pinta ninguna sección. Al aplicar el delta se activa solo.
--
-- REVERSIÓN LIMPIA, SIN PÉRDIDA: este delta NO TOCA NINGUNA COLUMNA EXISTENTE —
-- solo añade. Deshacerlo es un DROP de las 13 nuevas y del índice; nada de lo que
-- había antes cambia de tipo, de nombre ni de contenido.
--
-- MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; para que RE-EJECUTARLO sea
-- SEGURO (idempotente) cada ALTER va con un check en information_schema vía un
-- procedimiento temporal (COLUMNS para las columnas, STATISTICS para el índice).
-- MySQL 5.7 además PROHÍBE cláusula DEFAULT en columnas JSON → las tres van
-- `JSON NULL` a secas.
--
-- ── POR QUÉ `code` ES NULLABLE *Y* UNIQUE ───────────────────────────────────
-- Las 12 fichas que ya viven en la tabla NO tienen code (no salieron del catálogo
-- SPFX). NOT NULL abortaría el ALTER en el acto. NULLABLE no rompe la unicidad:
-- en un índice UNIQUE MySQL admite N filas con NULL — las 12 conviven sin
-- colisionar, y los 41 codes que siembre el 4c siguen siendo únicos entre sí. El
-- 4c usa esta clave para su updateOrCreate y para mapear el pivote del 4a; sin el
-- UNIQUE, re-sembrar duplicaría el catálogo.
--
-- ── LAS TRES COLUMNAS DE VERIFICACIÓN SON TRES COSAS DISTINTAS ──────────────
-- Se parecen en el nombre y NO significan lo mismo. NO se derivan una de otra:
--   1) `verified_at` / `verified_by_id` (Paso 1c) = ACTO DE GOBIERNO. Un
--      responsable con permiso `sds.manage` aprobó la ficha en esta instalación.
--      Solo lo escribe el servidor tras un clic humano.
--   2) `source_verified` = CONFIANZA DOCUMENTAL del catálogo de origen: el dato
--      está cotejado contra una fuente. Lo afirma el documento, no CrewCare.
--   3) `sds_url_verified` = EL ENLACE RESUELVE a la HDS de ESA sustancia (no a un
--      buscador, ni a la ficha de otro producto). Auditado por el owner el
--      2026-07-16; 27 de 41 en true.
-- Todas las combinaciones son legítimas: un Safety puede aprobar una ficha cuya
-- cita no está cotejada, y una ficha impecable de origen puede seguir sin aprobar.
-- Ambas nacen NULLABLE y NULL ≠ false: NULL = "esta ficha no viene de un catálogo
-- con cotejo" (las 12 vivas); false = "viene del catálogo y NO está cotejada".
--
-- ── `sds_source_date`: EL NOMBRE ES DELIBERADO. NO LO "CORRIJAS" ────────────
-- Recibe `sds_fecha_verificacion` del documento, pero NO se llama `sds_verified_on`
-- ni nada con "verified" — y NUNCA se mapea a `verified_at`. Motivo medido: vale
-- `2026-07-16` en los 41 insumos, INCLUIDOS los 14 cuyo enlace NO está verificado.
-- Eso no es una fecha de verificación: es la fecha en que se GENERÓ el catálogo.
-- Llamarla "verified" invitaría a volcarla en `verified_at` y sellaría el catálogo
-- ENTERO como aprobado por una autoridad que nunca lo miró — 41 fichas mintiendo
-- sobre su propia auditoría. De ahí "source_date".
--
-- ── `cas_number` Y `ghs_pictograms` NACEN CASI VACÍAS Y ESTÁ BIEN ───────────
-- El documento NO tiene campos `cas` ni `pictogramas`: ambos viven embebidos en la
-- PROSA de las secciones, y los extrae el 4c. Que se vean vacías NO es un bug:
--   - `cas_number`: solo 4 de 41 lo traen (en `3_composicion`). Los otros 37 son
--     MEZCLAS, y una mezcla legítimamente NO tiene un CAS único. Esta columna
--     está condenada a estar casi vacía POR EL DOMINIO, no por falta de datos.
--   - `ghs_pictograms`: 24 de 41 traen códigos en `2_peligros`. ¡OJO SEMÁNTICO!
--     AUSENCIA ≠ DESCONOCIDO: 17 insumos dicen "NO CLASIFICADO en GHS", que es un
--     DATO afirmado, no un hueco. El 4c decide la codificación (NULL = no sabemos
--     vs. `[]` = clasificado y no le corresponde ninguno); aquí solo se abre el
--     hueco. No rellenes esta columna "porque está vacía".
--
-- ── RECONCILIAR, NO DUPLICAR ────────────────────────────────────────────────
-- Estas columnas YA EXISTEN y NO se tocan aquí; las llena el 4c:
--   - `sds_url`     ← recibirá `sds_fuente_url`. NO se crea una segunda columna de
--                     URL. (Comprobado: la más larga del catálogo mide 123 chars,
--                     entra de sobra en el VARCHAR(500) actual.)
--   - `un_number`   ← lo extraerá el 4c de `14_transporte`.
--   - `signal_word` ← lo extraerá el 4c de `2_peligros`.
--   - `type`        → queda LEGACY: el EFECTO ahora vive en `sfx_effect_types` (4a)
--                     y la clase de MATERIAL en `material_family`. NO se borra ni se
--                     renombra; se reconcilia en el 4c.
--   - `hazards` / `precautions` → texto libre de las 12 fichas vivas. NO SE TOCAN.
--                     En 4c se decidirá si se espejan desde `2_peligros` /
--                     `8_controles_epp_vle`.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_consumables_sds16_2026_07_17;

DELIMITER //
CREATE PROCEDURE crewcare_consumables_sds16_2026_07_17()
BEGIN
    -- 1) code — clave natural del catálogo SPFX (`id` del documento, p.ej. `INS-FIRE-01`,
    --    máx. real 12 chars). NULLABLE + UNIQUE a propósito: ver la cabecera.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='code') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `code` VARCHAR(40) NULL AFTER `id`;
    END IF;

    -- 2) material_family — `familia_insumo` (máx. real 44).
    --    ¡NO SE LLAMA `family`! `sfx_effect_types.family` ya existe y es OTRO EJE:
    --    allí `family` = el EFECTO (qué se hace); aquí = el MATERIAL (con qué se
    --    hace). Un `where('family', ...)` cruzado compila, corre y devuelve BASURA
    --    en silencio. El nombre explícito es la única barrera contra eso.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='material_family') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `material_family` VARCHAR(80) NULL AFTER `type`;
    END IF;

    -- 3) synonyms — `sinonimos`: lista de nombres comerciales/alternos del insumo.
    --    Alimenta la búsqueda del 4d ("¿el bote dice otra cosa?"). JSON sin DEFAULT (5.7).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='synonyms') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `synonyms` JSON NULL AFTER `material_family`;
    END IF;

    -- 4) cas_number — número CAS. Lo extrae el 4c de la prosa de `3_composicion`.
    --    Nace casi vacía A PROPÓSITO (4/41: el resto son mezclas). Ver la cabecera.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='cas_number') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `cas_number` VARCHAR(40) NULL AFTER `synonyms`;
    END IF;

    -- 5) ghs_pictograms — códigos de pictograma GHS. Los extrae el 4c de la prosa de
    --    `2_peligros` (24/41). Va junto a `signal_word` porque son el mismo bloque GHS
    --    de la etiqueta. AUSENCIA ≠ DESCONOCIDO — ver la cabecera antes de tocarla.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='ghs_pictograms') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `ghs_pictograms` JSON NULL AFTER `signal_word`;
    END IF;

    -- 6) sds_sections — el corazón del delta: las 16 secciones de la HDS (`hds`), tal
    --    cual, con sus claves `1_identificacion` … `16_otra` (NOM-018-STPS). Los 41
    --    insumos las traen como TEXTO PLANO (cero objetos anidados), así que el JSON
    --    es un mapa clave→string. El nombre va en inglés por coherencia con `sds_url`
    --    (el owner la llamaba `hds`). Las etiquetas legibles NO se guardan aquí: viven
    --    en Consumable::sdsSectionLabels().
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_sections') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_sections` JSON NULL AFTER `sds_url`;
    END IF;

    -- 7) sds_level — `sds_nivel`: profundidad de la ficha. Valores REALES en el
    --    catálogo: solo 2 y 3. TINYINT sobra y deja margen si aparece un nivel 1/4.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_level') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_level` TINYINT NULL AFTER `sds_sections`;
    END IF;

    -- 8) sds_status — `sds_estado`: frase que declara de dónde sale la ficha y con qué
    --    reservas. Texto libre del documento (máx. real 114); VARCHAR(255) con holgura.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_status') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_status` VARCHAR(255) NULL AFTER `sds_level`;
    END IF;

    -- 9) sds_source_note — `sds_fuente_nota`: la letra pequeña de la cita (qué fabricante,
    --    qué edición, qué se asumió). TEXT: es prosa, no cabe darle tope arbitrario.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_source_note') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_source_note` TEXT NULL AFTER `sds_status`;
    END IF;

    -- 10) sds_source_date — `sds_fecha_verificacion`: fecha de GENERACIÓN del catálogo.
    --     NO ES UNA VERIFICACIÓN Y NO SE MAPEA A `verified_at` JAMÁS. El porqué completo
    --     (los 41 valen 2026-07-16, incluidos los 14 sin enlace verificado) está en la
    --     cabecera. Si vienes a renombrarla a algo con "verified", LEE ESO PRIMERO.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_source_date') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_source_date` DATE NULL AFTER `sds_source_note`;
    END IF;

    -- 11) sds_disclaimer — `sds_disclaimer`: descargo del catálogo ("esto no sustituye a
    --     la HDS del fabricante"). Se guarda POR FICHA porque es lo que hay que enseñar
    --     junto a ella; en una app de cumplimiento el descargo viaja con el documento.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_disclaimer') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_disclaimer` TEXT NULL AFTER `sds_source_date`;
    END IF;

    -- 12) source_verified — `verificado`: confianza documental DEL ORIGEN (nº 2 de las
    --     tres columnas de verificación de la cabecera). NULLABLE y NULL ≠ false.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='source_verified') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `source_verified` TINYINT(1) NULL AFTER `sds_disclaimer`;
    END IF;

    -- 13) sds_url_verified — `sds_url_verificada` (campo nuevo de la v1.1): el enlace de
    --     `sds_url` se abrió y resuelve a la HDS de ESA sustancia (nº 3 de las tres).
    --     27/41 en true. NO es lo mismo que `source_verified` ni que `verified_at`.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables' AND COLUMN_NAME='sds_url_verified') THEN
        ALTER TABLE `consumables`
            ADD COLUMN `sds_url_verified` TINYINT(1) NULL AFTER `source_verified`;
    END IF;

    -- 14) uq_consumables_code — UNIQUE sobre la clave natural. Es lo que hace IDEMPOTENTE
    --     al seed del 4c (updateOrCreate por `code`) y lo que impide un catálogo doble.
    --     Va SEPARADO del ADD COLUMN (rama propia) para que un delta aplicado a medias
    --     se repare al re-ejecutar. Chequeo contra STATISTICS: los índices no viven en
    --     COLUMNS.
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='consumables'
          AND INDEX_NAME='uq_consumables_code') THEN
        ALTER TABLE `consumables`
            ADD UNIQUE KEY `uq_consumables_code` (`code`);
    END IF;
END //
DELIMITER ;

CALL crewcare_consumables_sds16_2026_07_17();
DROP PROCEDURE IF EXISTS crewcare_consumables_sds16_2026_07_17;
