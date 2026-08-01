-- ============================================================================
-- CrewCare — Limpieza de schema `users`: DROP de columnas COVID muertas (2026-07-07)
--
-- Elimina columnas que ya NO se leen en ningún lado vivo y que solo se
-- inicializaban en el alta (desacople COVID). El CÓDIGO que las escribía ya se
-- retiró en el MISMO deploy (CrewController::newuser, User::$fillable,
-- TestAccountsSeeder). Ver [[users-schema-cleanup-approach]].
--
--   tested, resultpcr, lastpcr, enfermo, inline, ultimatemperatura, inlined
--
-- NOTA `inlined`: existe en la base offline; se dropea igual. Todas van con
-- guarda IF EXISTS → re-ejecutar o una columna ausente es un no-op inofensivo.
--
-- SE CONSERVAN (NO tocar aquí): lastwr / encuestadiaria (cuestionario clínico),
-- y los DIFERIDOS que aún tienen lecturas vivas → daytest (rol legacy, refactor
-- UI de grupos pendiente), labn ("Jerarquía"), age (→ has_badge_photo), zone
-- (→ department_name). Esos van en un delta posterior tras su refactor de código.
--
-- Aplicar FUERA de Laravel (NO `php artisan migrate`), JUNTO con el deploy del
-- código de arriba (así no queda ventana con columnas NOT NULL sin escritura).
-- Idempotente (MySQL 5.7-safe): cada DROP va detrás de un check en
-- information_schema → re-ejecutarlo es seguro (equivalente a hasColumn).
-- ============================================================================

DROP PROCEDURE IF EXISTS crewcare_drop_users_covid;

DELIMITER //
CREATE PROCEDURE crewcare_drop_users_covid()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'tested') THEN
        ALTER TABLE `users` DROP COLUMN `tested`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'resultpcr') THEN
        ALTER TABLE `users` DROP COLUMN `resultpcr`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'lastpcr') THEN
        ALTER TABLE `users` DROP COLUMN `lastpcr`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'enfermo') THEN
        ALTER TABLE `users` DROP COLUMN `enfermo`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'inline') THEN
        ALTER TABLE `users` DROP COLUMN `inline`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'ultimatemperatura') THEN
        ALTER TABLE `users` DROP COLUMN `ultimatemperatura`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'inlined') THEN
        ALTER TABLE `users` DROP COLUMN `inlined`;
    END IF;
END //
DELIMITER ;

CALL crewcare_drop_users_covid();
DROP PROCEDURE IF EXISTS crewcare_drop_users_covid;
