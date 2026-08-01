-- ============================================================================
-- CrewCare — Paso 1b: crear departamentos ANIMALERO y EQUIPO (EFD) + separar la
-- AMBULANCIA médica del EFD-equipo (2026-07-18). OWNER-APPLY, idempotente.
--
-- QUÉ HACE (solo filas + reasignación + sort_order; SIN esquema nuevo):
--   1) Crea el depto ANIMALERO. Reasigna el puesto 'Animalero' (hoy bajo Utilería)
--      al nuevo depto. sort_order 235 = ENTRE Utilería (230) y Picture Cars (240).
--   2) Crea el depto EQUIPO (la casa de renta de equipo/luces; "EFD" en el call sheet).
--      NOMBRE fijo 'Equipo' (durable, agnóstico de proveedor — decisión del owner).
--      Crea sus 5 puestos de equipo/luces. sort_order 315 = TRAS Sustentabilidad (310),
--      antes de Ambulancia (317), como en el call sheet.
--   3) Crea el depto AMBULANCIA — es su PROPIO departamento (decisión del owner): NO tiene
--      relación con EFD/Equipo NI con Salud y Seguridad. Renombra el puesto 'Ambulancia (EFD)'
--      → 'Ambulancia' y lo MUEVE a este depto (antes colgaba, mal, de S&S). sort_order 317 =
--      TRAS Equipo (315), antes de Seguridad (320), como en el call sheet.
--
-- ESQUEMA DE NUMERACIÓN (consistente con 2026-07-18-catalog-sort-order.sql):
--   departments.sort_order  = posición en el orden del call sheet (aquí por BRECHA: 235, 315,
--                             sin renumerar ningún depto existente → orden canónico intacto).
--   positions.sort_order    = dept.sort_order*100 + intra*10  (agrupa por depto en la lista
--                             PLANA de positionscrud). Animalero: 23510 (cae entre Utilería
--                             23080 y Picture Cars 24010). Equipo: 31510..31550. Ambulancia:
--                             31710 (entre Equipo 31550 y Seguridad 32010).
--
-- IDEMPOTENTE y PROD-SAFE: todo se referencia por NOMBRE (los ids de las filas nuevas los
--   asigna el AUTO_INCREMENT y difieren entre entornos → NUNCA se hardcodean). Re-ejecutar
--   deja el mismo resultado (IF NOT EXISTS + UPDATE determinista; el rename es idempotente
--   porque tras renombrar ya no existe 'Ambulancia (EFD)').
--
-- NO TOCA: is_hod de puestos existentes, radio_channel, ni nada fuera de lo listado. Los 5
--   puestos de Equipo nacen is_hod=0 (ninguno es jefe claro; el owner puede marcar uno luego).
--
-- NO SE CONSTRUYE AQUÍ: el concepto "proveedor / casa de renta" (vendor-naming por producción)
--   queda ANOTADO para Transportación; aquí solo se crean los 2 deptos con nombre fijo.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), en un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-18-departments-animalero-equipo.sql
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_dept_1b_2026_07_18;

DELIMITER //
CREATE PROCEDURE crewcare_dept_1b_2026_07_18()
BEGIN
    DECLARE v_anim BIGINT UNSIGNED;
    DECLARE v_equi BIGINT UNSIGNED;
    DECLARE v_ambu BIGINT UNSIGNED;

    -- 1) Depto ANIMALERO (idempotente por departments.name UNIQUE) -----------------
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Animalero') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Animalero', NULL, 1, 235, NOW(), NOW());
    ELSE
        UPDATE departments SET active = 1, sort_order = 235 WHERE name = 'Animalero';
    END IF;

    -- 2) Depto EQUIPO (EFD: casa de renta de equipo/luces) -------------------------
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Equipo') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Equipo', NULL, 1, 315, NOW(), NOW());
    ELSE
        UPDATE departments SET active = 1, sort_order = 315 WHERE name = 'Equipo';
    END IF;

    -- 3) Depto AMBULANCIA (su PROPIO depto: NO S&S, NO EFD) ------------------------
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Ambulancia') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Ambulancia', NULL, 1, 317, NOW(), NOW());
    ELSE
        UPDATE departments SET active = 1, sort_order = 317 WHERE name = 'Ambulancia';
    END IF;

    -- Resolver ids reales (name-based → prod-safe) ---------------------------------
    SELECT id INTO v_anim FROM departments WHERE name = 'Animalero'  LIMIT 1;
    SELECT id INTO v_equi FROM departments WHERE name = 'Equipo'     LIMIT 1;
    SELECT id INTO v_ambu FROM departments WHERE name = 'Ambulancia' LIMIT 1;

    -- 3) Reasignar el puesto 'Animalero' (global) → depto Animalero + sort_order ----
    UPDATE positions
       SET department_id = v_anim, sort_order = 23510
     WHERE name = 'Animalero' AND production_id IS NULL;

    -- 4) Crear los 5 puestos de equipo/luces bajo Equipo ---------------------------
    --    (idempotentes por name+department_id+global; is_hod=0)
    IF NOT EXISTS (SELECT 1 FROM positions WHERE name='Móvil Alpha' AND department_id=v_equi AND production_id IS NULL) THEN
        INSERT INTO positions (name, department_id, production_id, is_hod, active, sort_order, created_at, updated_at)
        VALUES ('Móvil Alpha', v_equi, NULL, 0, 1, 31510, NOW(), NOW());
    ELSE
        UPDATE positions SET department_id=v_equi, sort_order=31510 WHERE name='Móvil Alpha' AND production_id IS NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM positions WHERE name='Asistente/Luces' AND department_id=v_equi AND production_id IS NULL) THEN
        INSERT INTO positions (name, department_id, production_id, is_hod, active, sort_order, created_at, updated_at)
        VALUES ('Asistente/Luces', v_equi, NULL, 0, 1, 31520, NOW(), NOW());
    ELSE
        UPDATE positions SET department_id=v_equi, sort_order=31520 WHERE name='Asistente/Luces' AND production_id IS NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM positions WHERE name='Planta Set' AND department_id=v_equi AND production_id IS NULL) THEN
        INSERT INTO positions (name, department_id, production_id, is_hod, active, sort_order, created_at, updated_at)
        VALUES ('Planta Set', v_equi, NULL, 0, 1, 31530, NOW(), NOW());
    ELSE
        UPDATE positions SET department_id=v_equi, sort_order=31530 WHERE name='Planta Set' AND production_id IS NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM positions WHERE name='Dolly' AND department_id=v_equi AND production_id IS NULL) THEN
        INSERT INTO positions (name, department_id, production_id, is_hod, active, sort_order, created_at, updated_at)
        VALUES ('Dolly', v_equi, NULL, 0, 1, 31540, NOW(), NOW());
    ELSE
        UPDATE positions SET department_id=v_equi, sort_order=31540 WHERE name='Dolly' AND production_id IS NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM positions WHERE name='Cabeza Remota' AND department_id=v_equi AND production_id IS NULL) THEN
        INSERT INTO positions (name, department_id, production_id, is_hod, active, sort_order, created_at, updated_at)
        VALUES ('Cabeza Remota', v_equi, NULL, 0, 1, 31550, NOW(), NOW());
    ELSE
        UPDATE positions SET department_id=v_equi, sort_order=31550 WHERE name='Cabeza Remota' AND production_id IS NULL;
    END IF;

    -- 6) AMBULANCIA a su PROPIO depto: renombrar (quita '(EFD)') y MOVER desde donde
    --    estuviera (S&S) al depto Ambulancia + sort_order. Idempotente: el rename es no-op
    --    en 2ª corrida (ya no existe '(EFD)'); el move deja dept/sort deterministas.
    UPDATE positions SET name = 'Ambulancia'
     WHERE name = 'Ambulancia (EFD)' AND production_id IS NULL;
    UPDATE positions SET department_id = v_ambu, sort_order = 31710
     WHERE name = 'Ambulancia' AND production_id IS NULL;
END //
DELIMITER ;

CALL crewcare_dept_1b_2026_07_18();
DROP PROCEDURE IF EXISTS crewcare_dept_1b_2026_07_18;
