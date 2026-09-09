-- ============================================================================
-- OWNER-APPLY · 2026-08-30 · LIMPIEZA DEL CATÁLOGO (post-fusión). Delta #118.
-- Gemelo idempotente del seeder database/seeders/CatalogCleanupSeeder.php.
--
-- ⚠ CORRER EL ARCHIVO COMPLETO EN UNA SOLA SESIÓN (usa @variables).
-- ⚠ Se resuelve por NOMBRE / catalog_key, NO por id: los id de positions NO son
--    reproducibles entre una BD incremental y una recién sembrada. Los nombres sí.
-- Aditivo y reversible: nada se borra salvo alias redundantes de puestos retirados.
-- SEGURO DE CORRER VARIAS VECES. Requiere utf8mb4 para el acento del 'Móvil'.
-- ============================================================================
SET NAMES utf8mb4;

-- ── 1) name_en (84 puestos de la propuesta), por nombre ─────────────────────
UPDATE `positions` SET `name_en` = CASE `name`
  WHEN 'Crew de Cocina' THEN 'Kitchen Crew'
  WHEN 'Director de Arte en Set' THEN 'On-Set Art Director'
  WHEN 'Diseñador de Sets' THEN 'Set Designer'
  WHEN 'Diseñador Gráfico' THEN 'Graphic Designer'
  WHEN 'Asst. de Diseñador Gráfico' THEN 'Assistant Graphic Designer'
  WHEN 'Artista Conceptual' THEN 'Concept Artist'
  WHEN 'Asistente de Coord. de Arte' THEN 'Assistant Art Coordinator'
  WHEN 'Asistente de Oficina de Arte' THEN 'Art Office Assistant'
  WHEN 'Asst. de Diseño de Producción' THEN 'Assistant Production Designer'
  WHEN 'Asistente de DoP' THEN 'DoP Assistant'
  WHEN '2ndo AC' THEN 'Second AC'
  WHEN 'Cámara PA' THEN 'Camera PA'
  WHEN 'Asistente de Casting' THEN 'Casting Assistant'
  WHEN 'Asociado de Casting' THEN 'Casting Associate'
  WHEN 'Head Welder' THEN 'Head Welder'
  WHEN 'Jefe de Carpintería' THEN 'Carpentry Foreman'
  WHEN 'Jefe de Pintura Escénica' THEN 'Scenic Paint Foreman'
  WHEN 'Apoyo Eventual de Construcción' THEN 'Additional Construction Labor'
  WHEN 'Asistente de Pintor Escénico' THEN 'Scenic Painter Assistant'
  WHEN 'Asst. Coord. Construcción' THEN 'Assistant Construction Coordinator'
  WHEN 'Compras de Construcción' THEN 'Construction Buyer'
  WHEN 'Gang Boss Welder' THEN 'Welding Gang Boss'
  WHEN 'Herrero' THEN 'Blacksmith'
  WHEN 'Welder' THEN 'Welder'
  WHEN 'Contador Fiscal' THEN 'Tax Accountant'
  WHEN 'Auxiliar de Contabilidad' THEN 'Accounting Clerk'
  WHEN 'Decorador en Set' THEN 'On-Set Set Dresser'
  WHEN 'Asistente Decorador en Set' THEN 'Assistant On-Set Dresser'
  WHEN 'Apoyo Decorador en Set' THEN 'On-Set Dressing Support'
  WHEN 'Coordinador de Decoración' THEN 'Set Dec Coordinator'
  WHEN 'Asistente de Decoración' THEN 'Set Dec Assistant'
  WHEN 'Asst. Coord. de Decoración' THEN 'Assistant Set Dec Coordinator'
  WHEN 'Compras de Decoración' THEN 'Set Dec Buyer'
  WHEN 'Asistente de Director' THEN 'Assistant Director'
  WHEN 'Asst. Continuista' THEN 'Assistant Script Supervisor'
  WHEN 'Efectos Especiales Oficina' THEN 'SFX Office Coordinator'
  WHEN 'Supervisor de VFX en Set' THEN 'On-Set VFX Supervisor'
  WHEN 'Supervisor de Eléctricos' THEN 'Electrical Supervisor'
  WHEN 'Storyboardista' THEN 'Storyboard Artist'
  WHEN 'Director de Casting de Extras' THEN 'Background Casting Director'
  WHEN 'Grip Asst.' THEN 'Grip Assistant'
  WHEN 'Gerente Asst. de Locaciones' THEN 'Assistant Location Manager'
  WHEN 'Gerente de Soporte de Locaciones' THEN 'Locations Support Manager'
  WHEN 'Asst. Coordinador de Locaciones' THEN 'Assistant Locations Coordinator'
  WHEN 'Apoyo de Limpieza' THEN 'Cleanup Support'
  WHEN 'P.A. de Locaciones' THEN 'Locations PA'
  WHEN 'Personal de Loc.' THEN 'Locations Support'
  WHEN 'Diseñador de M&P' THEN 'HMU Designer'
  WHEN 'Coordinador MU&H' THEN 'HMU Coordinator'
  WHEN 'Apoyo M&P' THEN 'HMU Support'
  WHEN 'Supervisora de Música' THEN 'Music Supervisor'
  WHEN 'Asst. de Coordinador de Viajes' THEN 'Assistant Travel Coordinator'
  WHEN 'Recepción de Oficina' THEN 'Office Receptionist'
  WHEN 'Coordinadora de Intimidad' THEN 'Intimacy Coordinator'
  WHEN 'Coach de Dialecto' THEN 'Dialect Coach'
  WHEN 'Intérprete de Señas' THEN 'Sign Language Interpreter'
  WHEN 'Asst. Gerente de Unidad' THEN 'Assistant Unit Manager'
  WHEN 'Coordinador Ejecutivo' THEN 'Executive Coordinator'
  WHEN 'Coord. Ejecutivo' THEN 'Executive Coordinator'
  WHEN 'Ejecutivo Creativo' THEN 'Creative Executive'
  WHEN 'Representante Legal' THEN 'Legal Representative'
  WHEN 'Electric (rigging)' THEN 'Rigging Electric'
  WHEN 'Grip (rigging)' THEN 'Rigging Grip'
  WHEN 'Doctor de Construcción' THEN 'Construction Set Medic'
  WHEN 'Greener Adicional en Set' THEN 'Additional On-Set Greensperson'
  WHEN 'Contador de Transporte' THEN 'Transportation Accountant'
  WHEN 'Asistente de Coord. de Transporte' THEN 'Assistant Transportation Coordinator'
  WHEN 'Asistente de Transportación' THEN 'Transportation Assistant'
  WHEN 'Trainee de Transportación' THEN 'Transportation Trainee'
  WHEN 'Jefe de Utilería' THEN 'Property Master'
  WHEN 'Coordinador de Utilería' THEN 'Props Coordinator'
  WHEN 'Apoyo Eventual de Utilería' THEN 'Additional Props Labor'
  WHEN 'Asistente de Utilería' THEN 'Props Assistant'
  WHEN 'Asistente de Utilería en Set' THEN 'On-Set Props Assistant'
  WHEN 'Bodeguero Props' THEN 'Props Warehouse Manager'
  WHEN 'Compras de Utilería en Set' THEN 'On-Set Props Buyer'
  WHEN 'Utilería en Set' THEN 'On-Set Props'
  WHEN 'Asst. Diseñador' THEN 'Assistant Costume Designer'
  WHEN 'Diseñador Gráfico de Vestuario' THEN 'Costume Graphic Designer'
  WHEN 'Jefa de Taller de Costura' THEN 'Tailor Shop Supervisor'
  WHEN 'Asst. Coordinador de Vestuario' THEN 'Assistant Costume Coordinator'
  WHEN 'Asst. de Compras de Vestuario' THEN 'Assistant Costume Buyer'
  WHEN 'Asistente de Video' THEN 'Video Assist Assistant'
  WHEN 'Operador de VTR' THEN 'Video Assist Operator'
  ELSE `name_en` END
WHERE `name` IN ('Crew de Cocina','Director de Arte en Set','Diseñador de Sets','Diseñador Gráfico','Asst. de Diseñador Gráfico','Artista Conceptual','Asistente de Coord. de Arte','Asistente de Oficina de Arte','Asst. de Diseño de Producción','Asistente de DoP','2ndo AC','Cámara PA','Asistente de Casting','Asociado de Casting','Head Welder','Jefe de Carpintería','Jefe de Pintura Escénica','Apoyo Eventual de Construcción','Asistente de Pintor Escénico','Asst. Coord. Construcción','Compras de Construcción','Gang Boss Welder','Herrero','Welder','Contador Fiscal','Auxiliar de Contabilidad','Decorador en Set','Asistente Decorador en Set','Apoyo Decorador en Set','Coordinador de Decoración','Asistente de Decoración','Asst. Coord. de Decoración','Compras de Decoración','Asistente de Director','Asst. Continuista','Efectos Especiales Oficina','Supervisor de VFX en Set','Supervisor de Eléctricos','Storyboardista','Director de Casting de Extras','Grip Asst.','Gerente Asst. de Locaciones','Gerente de Soporte de Locaciones','Asst. Coordinador de Locaciones','Apoyo de Limpieza','P.A. de Locaciones','Personal de Loc.','Diseñador de M&P','Coordinador MU&H','Apoyo M&P','Supervisora de Música','Asst. de Coordinador de Viajes','Recepción de Oficina','Coordinadora de Intimidad','Coach de Dialecto','Intérprete de Señas','Asst. Gerente de Unidad','Coordinador Ejecutivo','Coord. Ejecutivo','Ejecutivo Creativo','Representante Legal','Electric (rigging)','Grip (rigging)','Doctor de Construcción','Greener Adicional en Set','Contador de Transporte','Asistente de Coord. de Transporte','Asistente de Transportación','Trainee de Transportación','Jefe de Utilería','Coordinador de Utilería','Apoyo Eventual de Utilería','Asistente de Utilería','Asistente de Utilería en Set','Bodeguero Props','Compras de Utilería en Set','Utilería en Set','Asst. Diseñador','Diseñador Gráfico de Vestuario','Jefa de Taller de Costura','Asst. Coordinador de Vestuario','Asst. de Compras de Vestuario','Asistente de Video','Operador de VTR');

-- ── 2) rank mal propuesto → recalcula sort_order = dept.sort_order*100 + rank ──
UPDATE `positions` p JOIN `departments` d ON p.`department_id` = d.`id`
  SET p.`rank` = 50, p.`sort_order` = d.`sort_order` * 100 + 50
  WHERE p.`name` IN ('Asst. de Diseñador Gráfico','Gerente Asst. de Locaciones','Asst. Gerente de Unidad','Asst. Diseñador');
UPDATE `positions` p JOIN `departments` d ON p.`department_id` = d.`id`
  SET p.`rank` = 20, p.`sort_order` = d.`sort_order` * 100 + 20
  WHERE p.`name` IN ('Diseñador de Sets','Diseñador Gráfico','Diseñador Gráfico de Vestuario','Director de Arte en Set','Gerente de Soporte de Locaciones');

-- ── 3) Equipo: desactiva EQUIPO FÍSICO = todo en 'Equipo' salvo Dolly (puesto real) y
--    Asistente/Luces (198, GATEADO). Por NOMBRE (el catalog_key puede quedar revuelto en un
--    seed fresco). Limpia también el mojibake heredado del nombre del Móvil.
SET @equipo = (SELECT `id` FROM `departments` WHERE `name` = 'Equipo' LIMIT 1);
UPDATE `positions` SET `name` = 'Móvil Alpha'
  WHERE `department_id` = @equipo AND `name` LIKE 'M%' AND `name` <> 'Móvil Alpha';  -- limpia mojibake
UPDATE `positions` SET `active` = 0
  WHERE `department_id` = @equipo AND `name` NOT IN ('Dolly','Asistente/Luces');

-- ── 3b) Ajustes (2026-08-30): Asistente/Luces ES puesto (encargado de las luces) → name_en;
--    Jefa de Equipo se desactiva (no se borra). ──
UPDATE `positions` SET `name_en` = 'Lighting Assistant' WHERE `name` = 'Asistente/Luces';
UPDATE `positions` SET `active` = 0 WHERE `name` = 'Jefa de Equipo';

-- ── 4) Unifica 3 duplicados: alias del retirado → sobreviviente, luego desactiva el retirado ──
-- A) Coordinador Ejecutivo ← Coord. Ejecutivo (por nombre)
SET @svA = (SELECT `id` FROM `positions` WHERE `name` = 'Coordinador Ejecutivo' LIMIT 1);
SET @rtA = (SELECT `id` FROM `positions` WHERE `name` = 'Coord. Ejecutivo' LIMIT 1);
UPDATE `production_user` SET `position_id` = @svA WHERE `position_id` = @rtA;
INSERT IGNORE INTO `catalog_aliases` (`entity_type`,`entity_id`,`lang`,`alias`,`created_at`,`updated_at`)
  SELECT 'position', @svA, `lang`, `alias`, NOW(), NOW() FROM `catalog_aliases` WHERE `entity_type`='position' AND `entity_id`=@rtA;
INSERT IGNORE INTO `catalog_aliases` (`entity_type`,`entity_id`,`lang`,`alias`,`created_at`,`updated_at`) VALUES
  ('position',@svA,'es','coord. ejecutivo',NOW(),NOW()),
  ('position',@svA,'es','coordinador ejecutivo',NOW(),NOW()),
  ('position',@svA,'en','executive coordinator',NOW(),NOW());
DELETE FROM `catalog_aliases` WHERE `entity_type`='position' AND `entity_id`=@rtA;
UPDATE `positions` SET `active` = 0 WHERE `id` = @rtA;

-- B) Jefe de Utilería ← Jefe de Props / Property Master (retirado por NOMBRE)
SET @svB = (SELECT `id` FROM `positions` WHERE `name` = 'Jefe de Utilería' LIMIT 1);
SET @rtB = (SELECT `id` FROM `positions` WHERE `name` = 'Jefe de Props' LIMIT 1);
UPDATE `production_user` SET `position_id` = @svB WHERE `position_id` = @rtB;
INSERT IGNORE INTO `catalog_aliases` (`entity_type`,`entity_id`,`lang`,`alias`,`created_at`,`updated_at`)
  SELECT 'position', @svB, `lang`, `alias`, NOW(), NOW() FROM `catalog_aliases` WHERE `entity_type`='position' AND `entity_id`=@rtB;
INSERT IGNORE INTO `catalog_aliases` (`entity_type`,`entity_id`,`lang`,`alias`,`created_at`,`updated_at`) VALUES
  ('position',@svB,'es','jefe de utileria',NOW(),NOW()),
  ('position',@svB,'es','jefe de utilería',NOW(),NOW()),
  ('position',@svB,'en','property master',NOW(),NOW());
DELETE FROM `catalog_aliases` WHERE `entity_type`='position' AND `entity_id`=@rtB;
UPDATE `positions` SET `active` = 0 WHERE `id` = @rtB;

-- C) Coordinador MU&H ← Coordinador de M&P (retirado por NOMBRE)
SET @svC = (SELECT `id` FROM `positions` WHERE `name` = 'Coordinador MU&H' LIMIT 1);
SET @rtC = (SELECT `id` FROM `positions` WHERE `name` = 'Coordinador de M&P' LIMIT 1);
UPDATE `production_user` SET `position_id` = @svC WHERE `position_id` = @rtC;
INSERT IGNORE INTO `catalog_aliases` (`entity_type`,`entity_id`,`lang`,`alias`,`created_at`,`updated_at`)
  SELECT 'position', @svC, `lang`, `alias`, NOW(), NOW() FROM `catalog_aliases` WHERE `entity_type`='position' AND `entity_id`=@rtC;
INSERT IGNORE INTO `catalog_aliases` (`entity_type`,`entity_id`,`lang`,`alias`,`created_at`,`updated_at`) VALUES
  ('position',@svC,'es','coordinador mu&h',NOW(),NOW()),
  ('position',@svC,'es','coordinador muh',NOW(),NOW()),
  ('position',@svC,'en','hmu coordinator',NOW(),NOW());
DELETE FROM `catalog_aliases` WHERE `entity_type`='position' AND `entity_id`=@rtC;
UPDATE `positions` SET `active` = 0 WHERE `id` = @rtC;
