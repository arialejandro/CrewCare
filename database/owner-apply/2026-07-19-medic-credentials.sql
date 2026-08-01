-- =============================================================================
-- CrewCare — CÉDULA PROFESIONAL DEL MÉDICO: tabla `medic_credentials` (2026-07-19)
-- MySQL 5.7.33 / InnoDB / utf8mb4_unicode_ci
-- -----------------------------------------------------------------------------
-- QUÉ: el registro de la licencia profesional de cada médico y el rastro de su
-- validación contra el registro oficial (SEP).
--
-- POR QUÉ: es un módulo MÉDICO-LEGAL, no un dato de perfil. Ata cada firma médica
-- del sistema (consultas, addendum médico, expediente diario) a una licencia
-- verificable, de modo que la responsabilidad quede DESLINDADA: quién firmó, con
-- qué cédula, validada por quién y cuándo. Sin esta pieza, una firma médica es
-- una afirmación sin respaldo.
--
-- ── ALCANCE: SOLO MÉDICOS, Y ESO NO LO SABE LA BD ────────────────────────────
-- La tabla cuelga de `users`, pero solo aplica a usuarios con rol Spatie `medic`
-- (fuente única: User::isMedic(), ver `2026-07-19-daytest-neutralizar-valor-2.sql`).
-- Esa restricción se valida en el CONTROLADOR, NO en el esquema: los roles viven
-- en las tablas de Spatie y el motor no tiene forma de expresar "solo si este
-- usuario tiene tal rol" en un CHECK (y MySQL 5.7 ni siquiera los aplica). Que la
-- BD acepte una fila para un no-médico no es un hueco del esquema: es el límite
-- de la capa. La puerta está arriba.
--
-- ── SIN FK DURAS HACIA `users` ───────────────────────────────────────────────
-- `user_id` y `verified_by_id` son BIGINT UNSIGNED con su `KEY`, como referencia
-- BLANDA a `users.id`. NO llevan FK. Es la convención del repo: no existe UNA SOLA
-- FK dura hacia `users` en todo `database/owner-apply/` (mismo criterio que
-- `materiality_photos.captured_by_id` y que los `created_by_id` del resto). Dos
-- motivos: (1) no acoplar el borrado de usuarios a este módulo — dar de baja a una
-- persona no debe tropezar con, ni arrastrar en cascada, su rastro de cédula; y
-- (2) el modelo degrada solo — un `verified_by_id` cuyo usuario ya no existe se
-- pinta sin nombre, exactamente como el NULL de "validada sin autor humano" que se
-- describe abajo. Una credencial huérfana sigue siendo evidencia legible.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). En un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-19-medic-credentials.sql
--
-- La app corre IGUAL sin este SQL: toda lectura del módulo va detrás de
-- MedicCredential::supportsCredentials() (Schema::hasTable con memo estático),
-- mismo patrón que SfxEffectType::supportsStandardsBridge() y
-- Consumable::supportsVerification(). Sin la tabla, el módulo se comporta como
-- "SIN CÉDULAS": no se pinta ningún badge, la captura avisa que no está
-- disponible y NADA truena. Al aplicar este delta, la feature se activa sola.
--
-- IDEMPOTENCIA: `CREATE TABLE IF NOT EXISTS` basta — este delta solo CREA una
-- tabla nueva, no altera ninguna existente. Sin el andamiaje de information_schema
-- + PROCEDURE que usan otros deltas del carril: ese solo hace falta para
-- `ADD COLUMN IF NOT EXISTS`, que MySQL 5.7 no soporta. Aquí no hay ningún ALTER.
--
-- ── REVERSIÓN ────────────────────────────────────────────────────────────────
--   DROP TABLE IF EXISTS `medic_credentials`;
--   + revertir el commit del código.
-- No hay nada más que deshacer: este delta NO toca `users` ni ninguna otra tabla
-- existente, y no siembra ni sella ningún dato (ver la nota del sellado abajo).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- SEMÁNTICA DE LA VERIFICACIÓN — LÉELA ANTES DE TOCAR LA UI.
--
-- El par `verified_at` / `verified_by_id` está calcado de `consumables` y
-- `safety_standards`, PERO AQUÍ LOS DOS NULL SIGNIFICAN COSAS DISTINTAS que en el
-- resto del repo. Son TRES estados, no dos:
--
--   1) verified_at NULL                       → PENDIENTE. La cédula está
--      capturada, pero NADIE la ha validado todavía. El badge NO se enciende.
--
--   2) verified_at con fecha + verified_by_id NULL → validada por el ENGANCHE
--      AUTOMÁTICO a la SEP, sin autor humano. HOY NO OCURRE (el enganche es un
--      sub-paso diferido); el estado queda listo para cuando exista. La UI debe
--      tolerar esta combinación y NO nombrar a nadie.
--
--   3) verified_at con fecha + verified_by_id con valor → validada por ESA
--      persona. Es el único caso en que la UI atribuye la validación a un humano.
--
-- A DIFERENCIA DE `consumables`, AQUÍ NO HAY SELLADO INICIAL DE ORIGEN.
-- En el SDS las fichas preexistentes nacían VERIFICADAS porque venían del catálogo
-- semilla del propio sistema (ver `2026-07-16-sds-verification.sql`:42-61). Aquí
-- ese razonamiento NO aplica y sería peligroso: la tabla nace VACÍA, no hay
-- catálogo semilla, y NINGUNA cédula puede darse por buena sin que alguien (o el
-- registro oficial) la valide. NO AÑADAS NINGÚN `UPDATE` DE SELLADO a este archivo
-- ni a sus reejecuciones: encendería badges de licencias que nadie coteja.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medic_credentials` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- El médico dueño de la cédula. Referencia BLANDA a `users.id` (sin FK, ver
  -- cabecera). UNIQUE: UNA CÉDULA POR PERSONA — un médico no acumula licencias
  -- en el sistema; si la suya cambia, se corrige la fila, no se agrega otra.
  `user_id`             BIGINT UNSIGNED NOT NULL,

  -- El número de cédula profesional. UNIQUE: DOS PERSONAS NO PUEDEN COMPARTIR
  -- CÉDULA. Un duplicado ES UNA SEÑAL DE SUPLANTACIÓN, no un accidente de
  -- captura, y el motor debe rechazarlo antes de que llegue a una firma. La
  -- tabla nace vacía, así que este UNIQUE no puede chocar con datos previos.
  `cedula`              VARCHAR(40)  NOT NULL,

  -- Descriptivos (Médico Cirujano, Urgenciólogo…). Nullable a propósito: se
  -- capturan si se conocen; no bloquean el registro de la cédula.
  `profession`          VARCHAR(150) NULL DEFAULT NULL,
  `specialty`           VARCHAR(150) NULL DEFAULT NULL,

  -- El nombre TAL COMO lo devuelve/confirma el registro oficial. Es LA PIEZA
  -- ANTISUPLANTACIÓN: se compara contra el nombre del usuario en la app y, si no
  -- coinciden, el badge NO se enciende. Guardar el nombre del registro (y no solo
  -- el número) es lo que impide colgarse de la cédula de otro.
  `registered_name`     VARCHAR(255) NULL DEFAULT NULL,

  -- Enlace de cotejo a la fuente oficial SEP, para que un humano compruebe. La
  -- vista aplica DOBLE LISTA BLANCA antes de pintarlo: esquema (http/https) Y
  -- dominio (SEP). NUNCA se pinta como `<a>` sin pasar LAS DOS — es una URL que
  -- viene de captura, no de confianza.
  `verification_url`    VARCHAR(500) NULL DEFAULT NULL,

  -- Cómo se validó: `manual` (una persona la validó) o `sep_auto` (reservado
  -- para el enganche automático a la SEP, sub-paso posterior; HOY NO SE EMITE).
  -- VARCHAR y NO ENUM, siguiendo la tendencia reciente del repo: el enum cerrado
  -- vive en la capa de validación, donde se puede ampliar sin un ALTER de tabla.
  `verification_source` VARCHAR(20)  NULL DEFAULT NULL,

  -- Lo que devolvió/confirmó el registro, con su fecha. EVIDENCIA AUDITABLE: sin
  -- esto el badge sería una afirmación sin respaldo — quedaría el "sí, está
  -- validada" pero no CONTRA QUÉ. Tipo JSON nativo a secas: MySQL 5.7 PROHÍBE la
  -- cláusula DEFAULT en columnas JSON (error 1101), ni siquiera DEFAULT NULL.
  `verified_snapshot`   JSON         NULL,

  -- Sello de validación. Los TRES estados están explicados arriba; NULL aquí es
  -- PENDIENTE, y NULL en el autor con fecha puesta es "validada por la SEP".
  `verified_at`         TIMESTAMP    NULL DEFAULT NULL,
  `verified_by_id`      BIGINT UNSIGNED NULL DEFAULT NULL,

  -- Los escribe Eloquent. Sin CURRENT_TIMESTAMP ni ON UPDATE, convención del repo.
  `created_at`          TIMESTAMP    NULL DEFAULT NULL,
  `updated_at`          TIMESTAMP    NULL DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medic_credentials_user`   (`user_id`),
  UNIQUE KEY `uq_medic_credentials_cedula` (`cedula`),
  KEY `medic_credentials_verified_idx`   (`verified_at`),   -- filtro "pendientes"
  KEY `medic_credentials_verified_by_idx`(`verified_by_id`) -- referencia blanda
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- VERIFICACIÓN (para el owner, después de aplicar)
--   ESPERADO: la tabla EXISTE y está VACÍA.
--     · tabla_existe = 1   (0 = el CREATE no corrió; revisa errores arriba)
--     · filas        = 0   (cualquier otro número significa que alguien sembró
--                           datos: NO debe haber ninguna cédula pre-cargada, ver
--                           la nota de "sin sellado inicial" más arriba)
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS tabla_existe
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'medic_credentials';

SELECT COUNT(*) AS filas FROM `medic_credentials`;
