-- =============================================================================================
-- ¿ESTA INSTANCIA TIENE DATOS DE DEMOSTRACIÓN? — chequeo de 1 segundo (2026-07-24)
--
-- PASO OBLIGATORIO antes de entregar cualquier instancia a un cliente.
--
-- Todo lo que siembra DemoProductionSeeder lleva el marcador '[DEMO]' en un campo visible.
-- Esta consulta lo cuenta en todas las tablas afectadas. **La columna `total` debe dar 0.**
-- Si no da 0, correr `2026-07-24-borrar-produccion-demo.sql`.
--
-- Incluye además las 4 CUENTAS DE PRUEBA de TestAccountsSeeder (contraseña conocida): también
-- deben dar 0 en una instancia de cliente.
-- =============================================================================================

SELECT 'daily_reports'       AS tabla, COUNT(*) AS filas_demo FROM daily_reports       WHERE location_name                 LIKE '%[DEMO]%'
UNION ALL SELECT 'daily_logs',         COUNT(*) FROM daily_logs                        WHERE description                   LIKE '%[DEMO]%'
UNION ALL SELECT 'scouting_reports',   COUNT(*) FROM scouting_reports                  WHERE location_name                 LIKE '%[DEMO]%'
UNION ALL SELECT 'hazardnotifications',COUNT(*) FROM hazardnotifications               WHERE description_hazard_unsafe_act LIKE '%[DEMO]%'
UNION ALL SELECT 'unsafeconds',        COUNT(*) FROM unsafeconds                       WHERE description_unsafe_cond       LIKE '%[DEMO]%'
UNION ALL SELECT 'injury_reports',     COUNT(*) FROM injury_reports                    WHERE what_happened                 LIKE '%[DEMO]%'
UNION ALL SELECT 'cmedic',             COUNT(*) FROM cmedic                            WHERE observations                  LIKE '%[DEMO]%'
UNION ALL SELECT 'sfx_events',         COUNT(*) FROM sfx_events                        WHERE effect_label                  LIKE '%[DEMO]%'
UNION ALL SELECT 'action_items',       COUNT(*) FROM action_items                      WHERE description                   LIKE '%[DEMO]%'
UNION ALL SELECT 'cuentas de prueba',  COUNT(*) FROM users                             WHERE email                         LIKE '%@crewcare.test'
UNION ALL SELECT '>>> TOTAL <<<',
      (SELECT COUNT(*) FROM daily_reports        WHERE location_name                 LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM daily_logs           WHERE description                   LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM scouting_reports     WHERE location_name                 LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM hazardnotifications  WHERE description_hazard_unsafe_act LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM unsafeconds          WHERE description_unsafe_cond       LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM injury_reports       WHERE what_happened                 LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM cmedic               WHERE observations                  LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM sfx_events           WHERE effect_label                  LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM action_items         WHERE description                   LIKE '%[DEMO]%')
    + (SELECT COUNT(*) FROM users                WHERE email                         LIKE '%@crewcare.test');
