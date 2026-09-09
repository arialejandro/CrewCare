<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regla de negocio a nivel BD: en `cmedic`, exactamente UNO de
 * (id_user, lite_patient_id) debe estar presente (XOR). Consulta de crew
 * registrada VS paciente lite. Se aplica en INSERT y UPDATE.
 * Corre despues de crear cmedic (2026_06_25_000049) y lite_patients.
 */
class CreateCmedicXorTriggers extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `cmedic_patient_xor_ins` BEFORE INSERT ON `cmedic`
FOR EACH ROW
BEGIN
    IF (NEW.id_user IS NULL) = (NEW.lite_patient_id IS NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cmedic: exactamente uno de id_user / lite_patient_id debe estar presente (XOR).';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `cmedic_patient_xor_upd` BEFORE UPDATE ON `cmedic`
FOR EACH ROW
BEGIN
    IF (NEW.id_user IS NULL) = (NEW.lite_patient_id IS NULL) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'cmedic: exactamente uno de id_user / lite_patient_id debe estar presente (XOR).';
    END IF;
END
SQL);
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `cmedic_patient_xor_ins`');
        DB::unprepared('DROP TRIGGER IF EXISTS `cmedic_patient_xor_upd`');
    }
}
