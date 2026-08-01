-- =====================================================================================
--  PASO 3/3 · Módulo Médico — DESBLOQUEO DEL CUESTIONARIO + MANEJO CLÍNICO
--  Fecha: 2026-07-24
--
--  CONTENIDO: 2 columnas nullable en `cmedic`.
--    · management      → MANEJO / CONDUCTA clínica de la consulta (JSON con las claves
--                        elegidas: valoración, reposo, retiro de actividad, curación,
--                        referencia a hospital, observación). Puede convivir con
--                        medicamentos o ir SOLO. MySQL 5.7 → TEXT + cast array (misma
--                        convención que cmedic.medication_items).
--    · without_record  → 1 si la consulta se registró SIN expediente/intake del paciente.
--                        0 si sí lo había. NULL = consulta anterior a esta columna.
--
--  POR QUÉ:
--    · DESBLOQUEO (item 1): antes, sin fila en `formularios` el controlador REDIRIGÍA — se
--      negaba la atención por un trámite. Ahora la consulta se abre y se guarda igual, el
--      médico ve un aviso de que atiende sin alergias ni antecedentes, y la consulta DEJA
--      CONSTANCIA de ello (without_record). Eso protege al médico y es el dato que importa
--      si algo sale mal después. Mismo principio que el GPS y el robot de cédula: lo
--      automático nunca bloquea.
--    · MANEJO (item 2): no toda consulta lleva medicamento (valoración, reposo, referencia)
--      y sin este campo el renglón queda mudo. Además, para la vigilancia epidemiológica
--      futura, 5 personas valoradas por calor y mandadas a la sombra SIN medicar hoy serían
--      invisibles: el manejo es tanta señal como el medicamento.
--
--  ⚠ SELLO / HASH: las 2 columnas nacen NULL y están en cmedic::NULLABLE_HASH_EXCLUDES →
--    se excluyen del payload firmado CUANDO son null, así que las consultas YA SELLADAS
--    conservan su hash y NO se marcan "ALTERADO". Las consultas nuevas sí las hashean
--    (without_record llega como 0/1, nunca null) → el sello cubre "se atendió sin expediente".
--
--  APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). MySQL 5.7: sin ADD COLUMN IF NOT
--  EXISTS, por eso el wrapper information_schema.COLUMNS dentro de un PROCEDURE (idempotente).
--
--  REVERSIÓN:
--    ALTER TABLE `cmedic` DROP COLUMN `management`, DROP COLUMN `without_record`;
--    (Consultas, expedientes y firmas quedan intactos. Las consultas selladas DESPUÉS de
--     aplicar esto y con valor no-null quedarían "ALTERADO" al revertir: es el mismo trato
--     que cualquier columna del payload.)
-- =====================================================================================

DROP PROCEDURE IF EXISTS crewcare_medical_triage_2026_07_24;
DELIMITER //
CREATE PROCEDURE crewcare_medical_triage_2026_07_24()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='management') THEN
        ALTER TABLE `cmedic` ADD COLUMN `management` TEXT NULL DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cmedic' AND COLUMN_NAME='without_record') THEN
        ALTER TABLE `cmedic` ADD COLUMN `without_record` TINYINT(1) NULL DEFAULT NULL;
    END IF;
END //
DELIMITER ;
CALL crewcare_medical_triage_2026_07_24();
DROP PROCEDURE IF EXISTS crewcare_medical_triage_2026_07_24;
