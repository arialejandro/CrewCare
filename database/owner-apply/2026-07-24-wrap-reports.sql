-- =============================================================================
-- CrewCare — REPORTE FINAL DE WRAP: tabla `wrap_reports` (2026-07-24)
-- MySQL 5.7.33 / InnoDB / utf8mb4_unicode_ci
-- -----------------------------------------------------------------------------
-- QUÉ: el documento de cierre de una producción. Es el único reporte que no
-- describe un momento sino TODA la producción, y su espina (SB 132) es contrastar
-- la experiencia REAL de riesgo contra las evaluaciones que se hicieron antes.
--
-- ── POR QUÉ HACE FALTA UNA TABLA (y no basta calcular al vuelo) ──────────────
-- El wrap se SELLA. Un sello SHA-256 dice "este documento no ha cambiado desde
-- este instante", y para eso el documento tiene que SER algo fijo. Si el hash se
-- calculara sobre agregados vivos, cualquier corrección posterior en un DSR
-- —añadir una foto, cerrar un hallazgo— movería los totales y el wrap se
-- auto-acusaría de ALTERADO sin que nadie lo tocara. Eso no es detección de
-- manipulación: es una falsa alarma garantizada.
--
-- Por eso `payload` guarda el CÁLCULO CONGELADO: el reporte es una declaración
-- fechada sobre lo que la base decía ese día. Misma doctrina que el snapshot de
-- cédula en las consultas médicas (`cmedic.medic_cedula`) — congelar el dato que
-- se firma, no re-leerlo después.
--
-- ── EL ADDENDUM NO SOBRESCRIBE ──────────────────────────────────────────────
-- SB 132 pide el reporte dentro de los 60 días del wrap y un ADDENDUM si hay
-- regrabaciones posteriores. `parent_id` + `kind='addendum'` implementan eso como
-- ANEXO REFERENCIADO CON SELLO PROPIO, nunca como reemplazo: el original queda
-- intacto, con su hash y su folio, y el anexo cubre sólo su propio rango de
-- fechas. Mismo patrón del addendum médico del Injury. Reescribir el original
-- rompería su sello y, con él, la única prueba de que existió antes.
--
-- ── SIN FK DURAS HACIA `users` ───────────────────────────────────────────────
-- `issued_by_id` es referencia BLANDA, como el resto del repo: dar de baja a una
-- persona no debe arrastrar ni tropezar con un documento entregado a un tercero.
-- `production_id` tampoco lleva FK dura, por la misma convención local.
-- `parent_id` SÍ podría llevarla, pero se deja blanda por coherencia: el anexo
-- huérfano se sigue leyendo, y el modelo ya guarda `parent_folio` dentro del
-- payload para que el papel impreso no dependa de la base.
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`). En un cliente MySQL:
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-24-wrap-reports.sql
--
-- La app corre IGUAL sin este SQL: WrapReport::supported() (Schema::hasTable con
-- memo estático) apaga el módulo entero — el menú no lo ofrece y las rutas
-- responden 404. Mismo patrón que MedicCredential::supportsCredentials().
--
-- REVERSIÓN: DROP TABLE IF EXISTS `wrap_reports`;  (más el borrado de las firmas
-- del tipo, si se quiere dejar limpio:
--   DELETE FROM digital_signatures WHERE documentable_type = 'App\\Models\\WrapReport';)
-- Ningún otro objeto de la base depende de esta tabla.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `wrap_reports` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Producción a la que cierra. Blanda a propósito (ver cabecera).
  `production_id`  BIGINT UNSIGNED NULL DEFAULT NULL,

  -- Identificador público del documento: es lo que viaja en el QR del sello
  -- (/verificar/wrap/{uuid}). CHAR(36) como en los otros seis sellables.
  `uuid`           CHAR(36) NULL DEFAULT NULL,

  -- 'final' = el reporte de cierre. 'addendum' = anexo por regrabaciones
  -- posteriores; en ese caso `parent_id` apunta al final que complementa.
  `kind`           VARCHAR(16) NOT NULL DEFAULT 'final',
  `parent_id`      BIGINT UNSIGNED NULL DEFAULT NULL,

  -- Rango CUBIERTO por este documento. En el 'final' es el de la producción; en
  -- un addendum, sólo los días de la regrabación. Se guarda en la fila —y no
  -- sólo dentro del payload— para poder listar y detectar traslapes con SQL.
  `period_start`   DATE NULL DEFAULT NULL,
  `period_end`     DATE NULL DEFAULT NULL,

  -- Motivo del anexo (regrabación, corrección). NULL en el 'final'.
  `reason`         VARCHAR(255) NULL DEFAULT NULL,

  -- EL CÁLCULO CONGELADO de las 8 secciones. LONGTEXT y no JSON: el repo ya usa
  -- LONGTEXT para `scouting_reports.risk_assessment` por la misma razón —
  -- MySQL 5.7 no indexa este contenido y el cast del modelo ya lo maneja.
  -- ⚠ ESTE CAMPO ES EL DOCUMENTO. Editarlo a mano rompe el sello, que es
  --   exactamente lo que el sello existe para delatar.
  `payload`        LONGTEXT NULL DEFAULT NULL,

  `issued_at`      DATETIME NULL DEFAULT NULL,
  `issued_by_id`   BIGINT UNSIGNED NULL DEFAULT NULL,

  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `wrap_reports_uuid_unique` (`uuid`),
  KEY `wrap_reports_production_id_index` (`production_id`),
  KEY `wrap_reports_parent_id_index` (`parent_id`),
  KEY `wrap_reports_period_index` (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
