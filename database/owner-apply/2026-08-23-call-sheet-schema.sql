-- ============================================================================
-- CrewCare — PARTE D: MOTOR DE HORARIOS + config del llamado + back (2026-08-23).
-- Bloque owner "CALENDARIO DE RODAJE, MOTOR DE HORARIOS Y BACK". OWNER-APPLY, idempotente.
--
-- 5 TABLAS NUEVAS (todo aditivo; nada se sella → cero hashes tocados):
--   call_places            catálogo de lugares (clave corta + nombre, SIN vigencias).
--   call_days              config por producción+fecha (general, jornada, contingente, ubicaciones…).
--   call_day_meals         servicios de comida del día (offset del general + toggle + lugar + contingente).
--   call_dept_offsets      N2 · offset (o literal) por departamento — SINGLETON por producción.
--   call_person_schedules  N3 · offset/pick up/marca de comida por persona — SINGLETON por producción.
--
-- REGLA: todo se guarda como OFFSET (minutos) respecto al general del día, nunca hora absoluta. Los
-- valores que no son hora (O/C, D/C, texto libre; N/A, SD, W/N) van como LITERAL, no como offset.
--
-- IDEMPOTENTE (CREATE TABLE IF NOT EXISTS). APLICAR FUERA DE LARAVEL (NO `php artisan migrate`):
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-08-23-call-sheet-schema.sql
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `call_places` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(24) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_places_prod_code` (`production_id`,`code`),
  KEY `call_places_prod` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `call_days` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_id` BIGINT UNSIGNED NOT NULL,
  `call_date` DATE NOT NULL,
  `general_call` TIME NULL,
  `journey_minutes` INT NULL,
  `wrap_estimate_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `location_place_id` BIGINT UNSIGNED NULL,
  `location_text` VARCHAR(190) NULL,
  `basecamp_place_id` BIGINT UNSIGNED NULL,
  `basecamp_text` VARCHAR(190) NULL,
  `notes` TEXT NULL,
  `cast_count` INT NULL,
  `bg_count` INT NULL,
  `sign_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `footer_extra` TEXT NULL,
  `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_days_prod_date` (`production_id`,`call_date`),
  KEY `call_days_prod` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `call_day_meals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_day_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `label` VARCHAR(80) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `offset_minutes` INT NULL,
  `explicit_time` TIME NULL,
  `place_id` BIGINT UNSIGNED NULL,
  `place_text` VARCHAR(120) NULL,
  `cast_override` INT NULL,
  `bg_override` INT NULL,
  `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `call_day_meals_day` (`call_day_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `call_dept_offsets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED NOT NULL,
  `offset_minutes` INT NULL,
  `literal_value` VARCHAR(40) NULL,
  `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_dept_offsets_prod_dept` (`production_id`,`department_id`),
  KEY `call_dept_offsets_prod` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `call_person_schedules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `schedule_offset_minutes` INT NULL,
  `schedule_literal` VARCHAR(40) NULL,
  `pickup_offset_minutes` INT NULL,
  `pickup_literal` VARCHAR(40) NULL,
  `pickup_place_id` BIGINT UNSIGNED NULL,
  `pickup_place_text` VARCHAR(120) NULL,
  `meal_mark` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_person_prod_user` (`production_id`,`user_id`),
  KEY `call_person_prod` (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
