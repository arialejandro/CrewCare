-- =============================================================================
-- PASO A · Neutralizar users.daytest = 2 (falso grupo "Médico")
-- Fecha: 2026-07-19 · MySQL 5.7.33 / InnoDB / utf8mb4_unicode_ci
-- =============================================================================
--
-- POR QUÉ:
--   El botón "Convertir a Médico" (POST /putmed → CrewStatusController@putgb)
--   escribía daytest = 2 como marcador de "es médico". Nunca funcionó como tal:
--   en local, los 13 usuarios marcados con 2 son de producción/coordinación
--   (Gerente de Producción, Vestuario, Transporte, Contabilidad…), TODOS con rol
--   Spatie 'crew', y los 2 médicos reales (puestos 176/177 del catálogo) tienen
--   daytest = NULL. El valor no correlaciona con ser médico en NINGUNA dirección.
--
--   A partir de este paso la ÚNICA fuente autoritativa de "es médico" es el rol
--   Spatie `medic` (ver User::isMedic() y MedicRolePermissionsSeeder).
--
-- ALCANCE — SOLO el valor 2. NO se toca la columna ni los demás valores:
--     0    = grupo "Admin"       (lo escribe putga)      → se conserva
--     1    = grupo "Supervisor"  (lo escribe putgg)      → se conserva
--     3    = residuo COVID, sin escritor vivo            → se conserva
--     NULL = sin grupo, estado con el que nacen todos    → destino del 2
--
--   POR QUÉ NULL Y NO 0: el 0 significa "grupo Admin" — promovería a 13 personas
--   a un grupo que nunca tuvieron. NULL es el estado neutro con el que nacen los
--   usuarios nuevos (CrewController@newuser no escribe daytest) y cae en la MISMA
--   rama del parcial _group-toggles que 0 y 3, así que la UI no cambia de forma.
--
--   NO DROPEAR la columna: 40 usuarios vivos siguen en 0 (6) y 1 (34), leídos por
--   _group-toggles.blade.php y proyectados por SearchController.php:62. Un DROP
--   provocaría error 1054 en /searchusers.
--
-- IDEMPOTENTE: re-ejecutable; la segunda corrida afecta 0 filas.
--
-- ORDEN DE DESPLIEGUE (importante): este SQL va ANTES o en el MISMO despliegue
--   que el código. Si se quita la rama `@elseif($user->daytest === 2)` del parcial
--   sin haber corrido esto, los usuarios en 2 quedan sin ninguna rama → pierden
--   todos los botones de grupo y quedan atrapados en el 2 sin salida por UI.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- PASO 0 (MANUAL, ANTES DE APLICAR EN PROD): capturar la lista para poder revertir.
--   Los IDs de prod NO tienen por qué coincidir con los de local. Guarda el
--   resultado de esta consulta antes de ejecutar el UPDATE de abajo.
-- -----------------------------------------------------------------------------
-- SELECT id, name, lname, puestodepartamento FROM `users` WHERE `daytest` = 2;

-- -----------------------------------------------------------------------------
-- PASO 1 · Neutralización
-- -----------------------------------------------------------------------------
UPDATE `users` SET `daytest` = NULL WHERE `daytest` = 2;

-- -----------------------------------------------------------------------------
-- PASO 2 · Verificación
--   Esperado en LOCAL: la fila '2' desaparece; NULL pasa de 38 a 51;
--   0 sigue en 6, 1 en 34, 3 en 1. Total 92 usuarios.
-- -----------------------------------------------------------------------------
SELECT IFNULL(CAST(`daytest` AS CHAR), 'NULL') AS valor, COUNT(*) AS n
FROM `users`
GROUP BY `daytest`
ORDER BY `daytest`;

-- =============================================================================
-- REVERSIÓN (si hiciera falta)
--   No se creó tabla de respaldo a propósito (el Paso A no introduce tablas).
--   Los 13 IDs afectados en LOCAL el 2026-07-19 fueron:
--     146, 147, 148, 149, 162, 163, 164, 167, 175, 182, 184, 219, 220
--   Para revertir en local:
--     UPDATE `users` SET `daytest` = 2
--      WHERE `id` IN (146,147,148,149,162,163,164,167,175,182,184,219,220);
--   En PROD: usar la lista capturada en el PASO 0.
--   (Revertir el dato NO restaura el botón: eso requiere revertir el commit.)
-- =============================================================================
