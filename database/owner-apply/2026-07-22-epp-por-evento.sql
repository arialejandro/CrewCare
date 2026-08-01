-- ============================================================================
-- CrewCare — EPP VIVO: el evento trae su EPP y el hallazgo lo hereda (2026-07-22)
--
-- Contenido:
--   1) COL  hazard_events.required_ppe  — EPP mínimo de ese evento (JSON array).
--   2) COL  daily_logs.required_ppe     — EPP del hallazgo (JSON array), heredado
--                                          del evento al capturarlo.
--
-- ── EL PROBLEMA QUE RESUELVE ────────────────────────────────────────────────
-- El EPP se declaraba UNA VEZ al abrir el día y se congelaba. Pero el plan de rodaje
-- cambia: entra un plano nuevo, se agrega una escena, la locación resulta otra cosa —
-- y aparece EPP que nadie contempló a las 6 de la mañana. El documento seguía
-- afirmando el EPP del alta como si nada hubiera pasado.
--
-- Decisión del owner: el EPP va POR HALLAZGO, ATADO AL EVENTO. Cuando se captura un
-- hallazgo y se elige su evento del catálogo, el EPP de ese evento se copia al
-- hallazgo. Así el EPP del día deja de ser una lista fija y pasa a ser la UNIÓN de lo
-- declarado al alta más lo que fueron exigiendo los hechos del día. Se vuelve vivo
-- sin que nadie tenga que acordarse de editarlo.
--
-- ── POR QUÉ SE COPIA Y NO SE LEE POR RELACIÓN ───────────────────────────────
-- daily_logs.required_ppe es un SNAPSHOT, igual que regulation_badge/regulation_code:
-- si mañana se corrige el EPP de un evento del catálogo, los hallazgos ya capturados
-- —que forman parte de un documento sellado— NO deben cambiar retroactivamente. El
-- acta dice lo que se exigió ese día, no lo que hoy diríamos que se debió exigir.
--
-- ── NOMBRE DE LA COLUMNA ────────────────────────────────────────────────────
-- `required_ppe` es el nombre CANÓNICO del repo: ya existe con ese nombre en
-- daily_reports, scouting_reports y sfx_effect_types (este último es el precedente
-- exacto — un catálogo que carga su propio EPP, poblado por el owner en sus 25 filas).
--
-- ⚠ ANCLA DE COLUMNA: se ancla a columnas BASE de cada tabla (`sort_order` en
--   hazard_events, `photo_path` en daily_logs), nunca a columnas creadas por otro
--   delta que podría no estar aplicado en prod (anclar a una columna inexistente
--   aborta el ALTER con error 1054 y tumba el CALL entero).
--
-- ⚠ SIN BACKFILL EN daily_logs: los 35 hallazgos existentes tienen hazard_event_id
--   NULL (se capturaron antes del catálogo único), así que no hay de dónde derivar su
--   EPP. Quedan en NULL y la vista simplemente no pinta EPP para ellos. Correcto: el
--   acta no debe inventar un EPP que nadie exigió ese día.
--
-- ⚠ EL POBLADO DE hazard_events VA POR SEEDER, NO POR SQL: son 207 filas y el mapeo
--   se autoriza por CATEGORÍA (38 familias de riesgo), no evento por evento. Ver
--   database/seeders/HazardEventPpeSeeder.php. El seeder es ADITIVO e idempotente:
--   sólo escribe donde required_ppe está vacío, así que re-correrlo no pisa ajustes
--   hechos a mano por el owner.
--
-- ── SOBRE EL SELLO SHA-256 ──────────────────────────────────────────────────
-- Ninguna de las dos columnas pertenece a `daily_reports`, que es la tabla cuyo hash
-- firma el DSR. Agregarlas NO altera ninguna firma existente. (daily_logs y
-- hazard_events nunca entraron en canonicalSignaturePayload del encabezado.)
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). El código es DEFENSIVO:
-- todo va con guard Schema::hasColumn y la app corre igual sin este SQL.
--
-- ── REVERSIÓN ───────────────────────────────────────────────────────────────
--   ALTER TABLE `hazard_events` DROP COLUMN `required_ppe`;
--   ALTER TABLE `daily_logs`    DROP COLUMN `required_ppe`;
--   + revertir el commit del código.
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_epp_por_evento_2026_07_22;

DELIMITER //
CREATE PROCEDURE crewcare_epp_por_evento_2026_07_22()
BEGIN
    -- 1) hazard_events.required_ppe — EPP mínimo de la familia de riesgo del evento.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hazard_events' AND COLUMN_NAME='required_ppe') THEN
        ALTER TABLE `hazard_events`
            ADD COLUMN `required_ppe` JSON NULL DEFAULT NULL AFTER `sort_order`;
    END IF;

    -- 2) daily_logs.required_ppe — snapshot del EPP exigido por ESE hallazgo.
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_logs' AND COLUMN_NAME='required_ppe') THEN
        ALTER TABLE `daily_logs`
            ADD COLUMN `required_ppe` JSON NULL DEFAULT NULL AFTER `photo_path`;
    END IF;
END //
DELIMITER ;

CALL crewcare_epp_por_evento_2026_07_22();
DROP PROCEDURE IF EXISTS crewcare_epp_por_evento_2026_07_22;
