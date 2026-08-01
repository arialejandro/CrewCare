-- =============================================================================================
-- BORRAR LA PRODUCCIÓN DEMO — reversión del corpus de demostración (2026-07-24)
--
-- Qué borra: SÓLO lo que sembró DemoProductionSeeder. Todo eso lleva el marcador '[DEMO]' en un
-- campo visible, y ése es el criterio: nada se borra por fecha ni por rango de ids, porque un
-- rango se lleva por delante lo que alguien capturó en medio.
--
-- Qué NO borra (a propósito):
--   · Los 5 reportes diarios que YA tenían fotografía. El seeder no los creó: sólo les movió la
--     fecha para encajarlos en el calendario. Borrarlos perdería imágenes reales.
--     Para devolverlos a su fecha original está el bloque comentado del final.
--   · Las 3 filas de prueba vacías de daily_reports (sin foto, sin crew, sin locación).
--   · El catálogo de eventos, las normas, los usuarios y los permisos.
--
-- Alternativa recomendada: volver a correr el seeder, que purga y rehace
--     php artisan db:seed --class=DemoProductionSeeder
--
-- ⚠ Correr con una copia de la base a la mano. Este archivo BORRA.
-- =============================================================================================

-- El marcador va LITERAL en cada consulta, no en una variable de sesión (`SET @MARCA = ...`).
-- Con variable, MySQL le asigna la colación de la conexión (utf8mb4_general_ci) mientras que las
-- columnas de este esquema son utf8mb4_unicode_ci, y el LIKE truena a media ejecución con
-- "Illegal mix of collations" — dejando el borrado a medias, que es el peor de los estados.

-- ---------------------------------------------------------------------------------------------
-- 1) Hijos primero: firmas, acciones y normas de los documentos marcados.
--    Si se borrara el padre antes, estas filas quedarían huérfanas apuntando a un id inexistente.
-- ---------------------------------------------------------------------------------------------
DELETE ds FROM digital_signatures ds
  JOIN hazardnotifications h ON h.id = ds.documentable_id
 WHERE ds.documentable_type = 'App\\Models\\hazardnotification'
   AND h.description_hazard_unsafe_act LIKE '%[DEMO]%';

DELETE ds FROM digital_signatures ds
  JOIN unsafeconds u ON u.id = ds.documentable_id
 WHERE ds.documentable_type = 'App\\Models\\unsafecond'
   AND u.description_unsafe_cond LIKE '%[DEMO]%';

DELETE ds FROM digital_signatures ds
  JOIN injury_reports i ON i.id = ds.documentable_id
 WHERE ds.documentable_type = 'App\\Models\\InjuryReport'
   AND i.what_happened LIKE '%[DEMO]%';

DELETE ds FROM digital_signatures ds
  JOIN scouting_reports s ON s.id = ds.documentable_id
 WHERE ds.documentable_type = 'App\\Models\\ScoutingReport'
   AND s.location_name LIKE '%[DEMO]%';

DELETE ds FROM digital_signatures ds
  JOIN cmedic c ON c.id_cmedic = ds.documentable_id
 WHERE ds.documentable_type = 'App\\Models\\cmedic'
   AND c.observations LIKE '%[DEMO]%';

DELETE ds FROM digital_signatures ds
  JOIN daily_reports d ON d.id = ds.documentable_id
 WHERE ds.documentable_type = 'App\\Models\\DailyReport'
   AND d.location_name LIKE '%[DEMO]%';

DELETE FROM action_items WHERE description LIKE '%[DEMO]%';

DELETE st FROM standardables st
  JOIN scouting_reports s ON s.id = st.standardable_id
 WHERE st.standardable_type = 'App\\Models\\ScoutingReport'
   AND s.location_name LIKE '%[DEMO]%';

-- ---------------------------------------------------------------------------------------------
-- 2) Bitácoras: las del corpus Y los espejos que el DSR Master Hub inyecta al reportar un gemelo.
-- ---------------------------------------------------------------------------------------------
DELETE FROM daily_logs WHERE description LIKE '%[DEMO]%';

DELETE dl FROM daily_logs dl
  JOIN daily_reports d ON d.id = dl.daily_report_id
 WHERE d.location_name LIKE '%[DEMO]%';

-- ---------------------------------------------------------------------------------------------
-- 3) Efectos especiales.
-- ---------------------------------------------------------------------------------------------
DELETE FROM sfx_events WHERE effect_label LIKE '%[DEMO]%';

DELETE se FROM sfx_events se
  JOIN daily_reports d ON d.id = se.daily_report_id
 WHERE d.location_name LIKE '%[DEMO]%';

-- ---------------------------------------------------------------------------------------------
-- 4) Los documentos.
-- ---------------------------------------------------------------------------------------------
DELETE FROM hazardnotifications WHERE description_hazard_unsafe_act LIKE '%[DEMO]%';
DELETE FROM unsafeconds         WHERE description_unsafe_cond       LIKE '%[DEMO]%';
DELETE FROM injury_reports      WHERE what_happened                 LIKE '%[DEMO]%';
DELETE FROM scouting_reports    WHERE location_name                 LIKE '%[DEMO]%';
DELETE FROM cmedic              WHERE observations                  LIKE '%[DEMO]%';
DELETE FROM daily_reports       WHERE location_name                 LIKE '%[DEMO]%';

-- ---------------------------------------------------------------------------------------------
-- 5) Las fechas de la producción. Se vacían: sin ancla no hay contador de días, que es el estado
--    en el que estaba antes del corpus.
-- ---------------------------------------------------------------------------------------------
UPDATE productions SET start_date = NULL, end_date = NULL WHERE name = 'Producción Demo';

-- ---------------------------------------------------------------------------------------------
-- 6) OPCIONAL — devolver los 5 reportes CON FOTO a su fecha original.
--    Sólo tiene sentido si se quiere el estado exacto anterior al corpus. Descomentar para usar.
--    ⚠ Mover la fecha CAMBIA EL HASH: hay que volver a sellar desde la app, o esos 5 documentos
--      empezarán a mostrarse como "ALTERADO" (que es justo lo que el re-sellado del seeder evita).
-- ---------------------------------------------------------------------------------------------
-- UPDATE daily_reports SET report_date = '2026-02-16', shoot_day = 0,  production_id = NULL WHERE id = 1;
-- UPDATE daily_reports SET report_date = '2026-07-09', shoot_day = 8,  production_id = NULL WHERE id = 2;
-- UPDATE daily_reports SET report_date = '2026-07-01', shoot_day = 9,  production_id = NULL WHERE id = 3;
-- UPDATE daily_reports SET report_date = '2026-07-02', shoot_day = 10, production_id = NULL WHERE id = 4;
-- UPDATE daily_reports SET report_date = '2026-07-03', shoot_day = 11, production_id = NULL WHERE id = 5;

-- ---------------------------------------------------------------------------------------------
-- 7) Comprobación: todo debe dar 0.
-- ---------------------------------------------------------------------------------------------
SELECT 'daily_reports'  AS tabla, COUNT(*) AS quedan FROM daily_reports       WHERE location_name                 LIKE '%[DEMO]%'
UNION ALL SELECT 'scoutings',     COUNT(*) FROM scouting_reports              WHERE location_name                 LIKE '%[DEMO]%'
UNION ALL SELECT 'actos',         COUNT(*) FROM hazardnotifications           WHERE description_hazard_unsafe_act LIKE '%[DEMO]%'
UNION ALL SELECT 'condiciones',   COUNT(*) FROM unsafeconds                   WHERE description_unsafe_cond       LIKE '%[DEMO]%'
UNION ALL SELECT 'accidentes',    COUNT(*) FROM injury_reports                WHERE what_happened                 LIKE '%[DEMO]%'
UNION ALL SELECT 'consultas',     COUNT(*) FROM cmedic                        WHERE observations                  LIKE '%[DEMO]%'
UNION ALL SELECT 'bitacoras',     COUNT(*) FROM daily_logs                    WHERE description                   LIKE '%[DEMO]%'
UNION ALL SELECT 'spfx',          COUNT(*) FROM sfx_events                    WHERE effect_label                  LIKE '%[DEMO]%'
UNION ALL SELECT 'acciones',      COUNT(*) FROM action_items                  WHERE description                   LIKE '%[DEMO]%';
