-- ============================================================================
-- CrewCare — SAFETY MEETING declarado con honestidad + foto (2026-07-21)
-- Paso DSR: la junta de seguridad del día deja de ser "una hora suelta" y pasa a
-- declararse REALIZADA o NO REALIZADA, con evidencia fotográfica cuando sí ocurrió.
--
-- Contenido:
--   1) COL  daily_reports.safety_meeting_held        — 1 realizada / 0 no realizada / NULL sin declarar.
--   2) COL  daily_reports.safety_meeting_photo_path  — foto en gran angular del crew reunido.
--   3) UPDATE de UNA SOLA VEZ (dentro del IF NOT EXISTS) que transcribe a la
--      columna nueva lo que 3 reportes históricos YA declaraban en texto libre.
--
-- ── POR QUÉ ─────────────────────────────────────────────────────────────────
-- El documento mostraba contradicciones como "No hubo safety meeting · 14:00 HRS".
-- Eso NO era un bug de la vista: era DATO. Cuando `safety_meeting_topics` era texto
-- libre, alguien escribió ahí "No hubo safety meeting" y aparte llenó la hora; la
-- vista pintaba las dos cosas sin saber que se contradicen. Al migrar los temas a
-- checkboxes (2026-07-13) esa válvula de escape DESAPARECIÓ: hoy no existe forma
-- de declarar que la junta no ocurrió. Esta columna la devuelve, ya estructurada.
--
-- Valor de negocio: los estudios internacionales EXIGEN el safety meeting y su
-- evidencia; los locales hoy muchas veces no lo hacen. Con el booleano el mismo
-- documento sirve a los dos mundos SIN mentir en ninguno — declara lo que pasó.
--
-- ── POR QUÉ `NULL` Y NO `NOT NULL DEFAULT 1` ────────────────────────────────
-- Un DEFAULT 1 marcaría como "junta realizada" a los 8 reportes históricos, tres de
-- los cuales dicen por escrito lo contrario. Sería inventar un hecho en un registro
-- que es EXPEDIENTE. NULL significa "no declarado" y deja tres estados distinguibles:
--   NULL → histórico anterior a esta columna: la vista se comporta como siempre.
--   0    → declarado NO realizado: la vista lo dice y APAGA la hora (fin de la
--          contradicción, sin borrar el texto original que el humano escribió).
--   1    → declarado realizado: hora + temas + foto.
--
-- ── SOBRE EL SELLO SHA-256 ──────────────────────────────────────────────────
-- Agregar columnas a `daily_reports` SÍ cambia canonicalSignaturePayload() (el hash
-- se calcula sobre attributesToArray()). Se verificó ANTES de escribir este archivo:
-- `digital_signatures` tiene CERO filas para App\Models\DailyReport (los 8 reportes
-- existentes son anteriores al código de firma del 2026-07-12 y no se ha creado
-- ninguno desde entonces). No hay ninguna firma que invalidar. Si en el futuro se
-- agregan columnas a esta tabla CON firmas ya emitidas, habrá que decidir entre
-- re-firmar o declarar $signatureExcludes — y ambas opciones tienen costo.
-- Por la misma razón el UPDATE del punto 3 es seguro HOY y no lo sería mañana.
--
-- ⚠ NO se toca `daily_reports.status`: es una columna huérfana (nadie la lee ni la
--   escribe) pero VIVE en el payload del hash. Escribirla invalidaría firmas futuras.
--
-- ⚠ ANCLA DE COLUMNA: AFTER `safety_meeting_time` y AFTER `safety_meeting_topics`,
--   ambas columnas BASE de la tabla (existen en todos los entornos). A propósito NO
--   se ancla a `uuid` ni a `day_risk_factors`: las crea OTRO delta
--   (2026-07-12-modules-6-14) que puede no estar aplicado en prod, y anclar a una
--   columna inexistente aborta el ALTER con error 1054 y tumba el CALL entero.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). El CÓDIGO ES DEFENSIVO: la
-- persistencia va con guard Schema::hasColumn y la vista degrada al comportamiento
-- actual si la columna no existe, así que la app corre IGUAL sin este SQL.
--
-- ── REVERSIÓN ───────────────────────────────────────────────────────────────
--   ALTER TABLE `daily_reports` DROP COLUMN `safety_meeting_held`;
--   ALTER TABLE `daily_reports` DROP COLUMN `safety_meeting_photo_path`;
--   + revertir el commit del código.
--   (El UPDATE del punto 3 no necesita reversión: solo escribió en una columna que
--    el DROP elimina. El texto original de safety_meeting_topics nunca se tocó.)
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_dsr_safety_meeting_2026_07_21;

DELIMITER //
CREATE PROCEDURE crewcare_dsr_safety_meeting_2026_07_21()
BEGIN
    -- 1) safety_meeting_held — 1 realizada / 0 no realizada / NULL sin declarar.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='safety_meeting_held') THEN
        ALTER TABLE `daily_reports`
            ADD COLUMN `safety_meeting_held` TINYINT(1) NULL DEFAULT NULL AFTER `safety_meeting_time`;

        -- UPDATE de UNA SOLA VEZ, dentro del IF: solo corre en la ejecución que CREA
        -- la columna, así que re-ejecutar el archivo no vuelve a pisar datos que el
        -- usuario haya corregido a mano después.
        --
        -- NO inventa nada: transcribe a la columna estructurada lo que el humano YA
        -- había escrito con sus palabras en el campo de texto libre. El texto original
        -- se conserva intacto en safety_meeting_topics (es el registro de lo que se
        -- capturó); la columna nueva solo lo vuelve legible para la vista.
        --
        -- ⚠ EL PATRÓN VA ANCLADO AL INICIO, y esto NO es un detalle de estilo. Un
        --   '%o hubo safety meeting%' sin anclar también casa con "Sólo hubo safety
        --   meeting al arranque" o "Como hubo safety meeting temprano…", que describen
        --   juntas que SÍ ocurrieron: la columna es utf8mb4_general_ci (insensible a
        --   mayúsculas Y a acentos), así que la 'o' final de "Sólo"/"Como" entra en el
        --   comodín. El expediente acabaría afirmando un hecho falso Y ocultando la hora
        --   real. Y como este UPDATE vive dentro del IF NOT EXISTS, re-ejecutar el
        --   archivo NUNCA lo corregiría: hay una sola oportunidad de hacerlo bien.
        --
        -- ANTES DE APLICAR EN PRODUCCIÓN, el owner puede ver exactamente qué filas se
        -- van a tocar con:
        --   SELECT id, report_date, safety_meeting_time, safety_meeting_topics
        --     FROM daily_reports
        --    WHERE safety_meeting_topics LIKE 'No hubo safety meeting%';
        UPDATE `daily_reports`
            SET `safety_meeting_held` = 0
            WHERE `safety_meeting_topics` LIKE 'No hubo safety meeting%';
    END IF;

    -- 2) safety_meeting_photo_path — foto en gran angular del crew reunido.
    --    varchar(255) para igualar a `hero_image_path`, que guarda el mismo tipo de
    --    valor (la URL '/storage/...' que devuelve Storage::url).
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_reports' AND COLUMN_NAME='safety_meeting_photo_path') THEN
        ALTER TABLE `daily_reports`
            ADD COLUMN `safety_meeting_photo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `safety_meeting_topics`;
    END IF;
END //
DELIMITER ;

CALL crewcare_dsr_safety_meeting_2026_07_21();
DROP PROCEDURE IF EXISTS crewcare_dsr_safety_meeting_2026_07_21;
