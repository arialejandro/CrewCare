-- ============================================================================
-- CrewCare — PARTE B: CATÁLOGO DE DEPARTAMENTOS canónico del call sheet (2026-08-23).
-- Bloque owner "CALENDARIO DE RODAJE, MOTOR DE HORARIOS Y BACK". OWNER-APPLY, idempotente.
--
-- QUÉ HACE (solo filas + etiquetas; SIN esquema nuevo — sort_order y name_en YA existen):
--   1) RENOMBRA 7 registros (conserva id, puestos y FKs; solo la etiqueta):
--        · 'Producción Ejecutiva'      → 'Productores'
--        · 'Video/VTR'                 → 'Video Assist, DIT y Data'
--        · 'VFX'                       → 'Efectos Visuales'
--        · 'Animalero'                 → 'Animales'
--        · 'Catering'                  → 'Alimentación'
--        · 'A.N.D.A.'                  → 'ANDA'
--        · 'Asistente de Dirección'    → 'Asistentes de Dirección'
--      ⚠ 'Productores' lo referencia POR NOMBRE App\Support\SignaturePositions::SIGNER_DEPARTMENTS
--        (firmantes de contrato). Esa constante se actualizó en el MISMO commit. Si aplicas este
--        SQL sin ese código desplegado, el picker de firmantes perdería el depto hasta desplegarlo.
--        Los otros 5 no los referencia ningún código por nombre (verificado).
--   2) CREA los 6 deptos faltantes (idempotente por departments.name). Nacen SIN puestos y active=1.
--        Continuidad · Casting de Extras · Foto Fija · Craft Service · Servicios Médicos · Legal y Clearance
--        NO se mueven puestos existentes (Continuista, Foto Fija, Coordinador de Craft, Director de
--        Casting de Extras siguen bajo su depto actual) — eso es decisión aparte del owner.
--   3) POBLA sort_order (posición×10 en el orden canónico) + name_en (back bilingüe) de los 44 deptos.
--        'Crew Adicional' NO es depto: es bucket sintético del back.
--
-- REEMPLAZA los sort_order sueltos del delta 2026-07-18 (Animalero 235→270, Equipo 315→330,
-- Ambulancia 317→340) por el esquema uniforme ×10. NO renumera positions.sort_order (que codifica
-- dept*100+intra): el orden DENTRO de cada depto se conserva, pero la lista PLANA de positionscrud
-- seguirá agrupando por el orden viejo hasta un delta aparte que renumere positions.
--
-- IDEMPOTENTE y PROD-SAFE: todo por NOMBRE (nunca ids hardcodeados). El rename es no-op en 2ª
-- corrida (ya no existe el nombre viejo). Re-ejecutar deja el mismo resultado.
--
-- ⚠ ACENTOS: este delta compara y escribe nombres CON ACENTO ('Dirección', 'Cámara', …). El
--   `SET NAMES utf8mb4` de abajo es OBLIGATORIO: sin él, el cliente de MySQL en Windows los
--   interpreta como latin1 y (a) los WHERE con acento NO casan y (b) las filas nuevas quedan como
--   mojibake doble-codificado ('Servicios MÃ©dicos'). Con SET NAMES, verificado: los WHERE casan y
--   HEX('é')=C3A9. NO hace falta pasar --default-character-set en la línea de comandos.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), en un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-23-departments-canonical-order-i18n.sql
-- ============================================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS crewcare_dept_canonical_2026_08_23;

DELIMITER //
CREATE PROCEDURE crewcare_dept_canonical_2026_08_23()
BEGIN
    -- 1) RENOMBRES (idempotentes) -------------------------------------------------
    UPDATE departments SET name = 'Productores'
      WHERE name = 'Producción Ejecutiva';
    UPDATE departments SET name = 'Video Assist, DIT y Data'
      WHERE name = 'Video/VTR';
    -- (2026-08-23, 2ª pasada) alinear los nombres en ESPAÑOL a la lista canónica del owner. Sólo
    -- etiqueta: department_id (el FK real) no cambia. Verificado que ningún código los referencia
    -- por nombre (a diferencia de 'Productores' ↔ SignaturePositions).
    UPDATE departments SET name = 'Efectos Visuales'         WHERE name = 'VFX';
    UPDATE departments SET name = 'Animales'                 WHERE name = 'Animalero';
    UPDATE departments SET name = 'Alimentación'             WHERE name = 'Catering';
    UPDATE departments SET name = 'ANDA'                     WHERE name = 'A.N.D.A.';
    UPDATE departments SET name = 'Asistentes de Dirección'  WHERE name = 'Asistente de Dirección';

    -- 2) LOS 6 DEPTOS FALTANTES (idempotentes por name) ---------------------------
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Continuidad') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Continuidad', NULL, 1, 0, NOW(), NOW());
    END IF;
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Casting de Extras') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Casting de Extras', NULL, 1, 0, NOW(), NOW());
    END IF;
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Foto Fija') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Foto Fija', NULL, 1, 0, NOW(), NOW());
    END IF;
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Craft Service') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Craft Service', NULL, 1, 0, NOW(), NOW());
    END IF;
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Servicios Médicos') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Servicios Médicos', NULL, 1, 0, NOW(), NOW());
    END IF;
    IF NOT EXISTS (SELECT 1 FROM departments WHERE name = 'Legal y Clearance') THEN
        INSERT INTO departments (name, radio_channel, active, sort_order, created_at, updated_at)
        VALUES ('Legal y Clearance', NULL, 1, 0, NOW(), NOW());
    END IF;

    -- 3) ORDEN CANÓNICO (posición×10) + name_en (back bilingüe) --------------------
    UPDATE departments SET sort_order = 10,  name_en = 'Producers'                WHERE name = 'Productores';
    UPDATE departments SET sort_order = 20,  name_en = 'Direction'                WHERE name = 'Dirección';
    UPDATE departments SET sort_order = 30,  name_en = 'Writers'                  WHERE name = 'Escritores';
    UPDATE departments SET sort_order = 40,  name_en = 'Production'               WHERE name = 'Producción';
    UPDATE departments SET sort_order = 50,  name_en = 'Production Office'        WHERE name = 'Oficina de Producción';
    UPDATE departments SET sort_order = 60,  name_en = 'Assistant Directors'     WHERE name = 'Asistentes de Dirección';
    UPDATE departments SET sort_order = 70,  name_en = 'Continuity'              WHERE name = 'Continuidad';
    UPDATE departments SET sort_order = 80,  name_en = 'Casting'                 WHERE name = 'Casting';
    UPDATE departments SET sort_order = 90,  name_en = 'Extras Casting'          WHERE name = 'Casting de Extras';
    UPDATE departments SET sort_order = 100, name_en = 'Camera'                  WHERE name = 'Cámara';
    UPDATE departments SET sort_order = 110, name_en = 'Video Assist, DIT & Data' WHERE name = 'Video Assist, DIT y Data';
    UPDATE departments SET sort_order = 120, name_en = 'Sound'                   WHERE name = 'Sonido';
    UPDATE departments SET sort_order = 130, name_en = 'Electric'               WHERE name = 'Eléctricos';
    UPDATE departments SET sort_order = 140, name_en = 'Grip'                    WHERE name = 'Grips';
    UPDATE departments SET sort_order = 150, name_en = 'Rigging'                 WHERE name = 'Rigging';
    UPDATE departments SET sort_order = 160, name_en = 'Still Photography'       WHERE name = 'Foto Fija';
    UPDATE departments SET sort_order = 170, name_en = 'Art Department'          WHERE name = 'Arte';
    UPDATE departments SET sort_order = 180, name_en = 'Set Decoration'          WHERE name = 'Decoración';
    UPDATE departments SET sort_order = 190, name_en = 'Property'                WHERE name = 'Utilería';
    UPDATE departments SET sort_order = 200, name_en = 'Construction'            WHERE name = 'Construcción';
    UPDATE departments SET sort_order = 210, name_en = 'Wardrobe'               WHERE name = 'Vestuario';
    UPDATE departments SET sort_order = 220, name_en = 'Make-Up & Hair'          WHERE name = 'Maquillaje y Peinados';
    UPDATE departments SET sort_order = 230, name_en = 'Special Effects'         WHERE name = 'Efectos Especiales';
    UPDATE departments SET sort_order = 240, name_en = 'Visual Effects'          WHERE name = 'Efectos Visuales';
    UPDATE departments SET sort_order = 250, name_en = 'Stunts'                  WHERE name = 'Stunts';
    UPDATE departments SET sort_order = 260, name_en = 'Picture Cars'            WHERE name = 'Picture Cars';
    UPDATE departments SET sort_order = 270, name_en = 'Animals'                 WHERE name = 'Animales';
    UPDATE departments SET sort_order = 280, name_en = 'Background'              WHERE name = 'Extras';
    UPDATE departments SET sort_order = 290, name_en = 'Locations'               WHERE name = 'Locaciones';
    UPDATE departments SET sort_order = 300, name_en = 'Transportation'          WHERE name = 'Transportación';
    UPDATE departments SET sort_order = 310, name_en = 'Catering'                WHERE name = 'Alimentación';
    UPDATE departments SET sort_order = 320, name_en = 'Craft Service'           WHERE name = 'Craft Service';
    UPDATE departments SET sort_order = 330, name_en = 'Equipment'               WHERE name = 'Equipo';
    UPDATE departments SET sort_order = 340, name_en = 'Ambulance'               WHERE name = 'Ambulancia';
    UPDATE departments SET sort_order = 350, name_en = 'Security'                WHERE name = 'Seguridad';
    UPDATE departments SET sort_order = 360, name_en = 'Health & Safety'         WHERE name = 'Salud y Seguridad';
    UPDATE departments SET sort_order = 370, name_en = 'Medic'                   WHERE name = 'Servicios Médicos';
    UPDATE departments SET sort_order = 380, name_en = 'Sustainability'          WHERE name = 'Sustentabilidad';
    UPDATE departments SET sort_order = 390, name_en = 'Accounting'              WHERE name = 'Contabilidad';
    UPDATE departments SET sort_order = 400, name_en = 'Legal & Clearance'       WHERE name = 'Legal y Clearance';
    UPDATE departments SET sort_order = 410, name_en = 'ANDA'                    WHERE name = 'ANDA';
    UPDATE departments SET sort_order = 420, name_en = 'Post Production'         WHERE name = 'Post Producción';
    UPDATE departments SET sort_order = 430, name_en = 'Music'                   WHERE name = 'Música';
    UPDATE departments SET sort_order = 440, name_en = 'Other'                   WHERE name = 'Otros';
END //
DELIMITER ;

CALL crewcare_dept_canonical_2026_08_23();
DROP PROCEDURE IF EXISTS crewcare_dept_canonical_2026_08_23;
