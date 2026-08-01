-- ============================================================================
-- CrewCare — PERMISO DE TRABAJO EMITIDO (ciclo completo: emisión→cierre)
-- (2026-07-30) — delta #44. Sigue del #43 (inspección preventiva + verificador).
--
-- QUÉ ES: el DOCUMENTO que produce EMITIR un permiso de actividad. El catálogo
--   (delta #41: `permits`/`permit_points`, 15 permisos / 103 puntos compuerta) UNE
--   a las normas; el permiso emitido las COPIA (snapshot congelado), porque es
--   documento con consecuencia LEGAL. El safety emite para una ACTIVIDAD en un
--   SITIO y una JORNADA (no para una herramienta: la herramienta es lo que lo
--   dispara, no su objeto), el ejecutante designado ACEPTA, y ambos quedan sellados
--   con `HasDigitalSignatures` y verificables por QR en el verificador público.
--
-- ── REQUISITO PREVIO: `permits`/`permit_points` (delta #41) y `digital_signatures`
--   FK-soft (sin FK dura) a `permits`/`tools`/`users` — el permiso debe seguir vivo
--   aunque el catálogo cambie o un usuario se dé de baja (es documento histórico).
--   Igual que `tool_inspections` (#42) y los pivotes polimórficos del repo.
--
-- ── DOCTRINA DEL CONGELAMIENTO (misma que cmedic/cédula y el acta de inspección) ─
--   El permiso guarda COPIAS, no referencias vivas:
--     · `points_snapshot` (JSON) — cada punto compuerta con su TEXTO, ejecutor,
--       sensible_al_sitio y la respuesta, tal como estaban al emitir.
--     · `standards_snapshot` (JSON) — normas citadas (regulation_code) congeladas.
--     · `permit_*` — código/clave/nombre/definición/alcance-de-sitio al emitir.
--     · `issuer_*` / `acceptor_*` — las DOS identidades (quien emite y quien acepta)
--       con su cédula o identificación congelada.
--   Si mañana cambia el catálogo, el permiso sigue diciendo lo que dijo, y el sello
--   SHA-256 lo prueba (se firma sobre estas columnas propias, no sobre relaciones).
--
-- ── AUTORIZACIÓN EXTERNA = DECLARACIÓN, NO VERIFICACIÓN (Paso 2) ──────────────
--   Cuando `ext_auth_mandatory`=1 (SEDENA pirotecnia/armas, AFAC dron) el permiso
--   NO se emite sin `ext_auth_folio`/`authority`/`valid_until`/`declared_by`. La app
--   NO comprueba nada de eso: es una DECLARACIÓN bajo responsabilidad de quien la
--   captura. El documento lo dice con esas palabras; nunca "autorización verificada".
--
-- ── VIGENCIA POR JORNADA + REVERIFICACIÓN (Paso 3) ───────────────────────────
--   `shoot_day` (ProductionCalendar) ancla la vigencia temporal — los llamados
--   cruzan la medianoche, por eso es el ENTERO, no la fecha. `permit_site_scope`
--   (copiado de `permits.site_scope`) gobierna el sitio: `indiferente` vale por la
--   jornada; `reverificacion` re-corre SOLO los puntos sensibles al mover, y eso se
--   registra en `reverifications` (JSON, HASH-EXCLUIDO: no re-sella la emisión);
--   `ligado_al_sitio` exige emisión nueva al cambiar de sitio (`superseded_by_id`).
--
-- ── CIERRE / SUSPENSIÓN (Paso 4) — patrón de retiro hash-excluido de #42 ──────
--   `closed_*` (con autor y hora; trabajo en caliente exige `fire_watch_confirmed`)
--   y `suspended_*` son ESTADO posterior al sello: NO recalculan ni re-firman el
--   hash (el documento cerrado/suspendido sigue ÍNTEGRO, solo cambió de estado). El
--   verificador público lo muestra "cerrado/suspendido con su fecha", nunca ALTERADO.
--
-- REGLA DE NOMBRES: ninguna columna colisiona con props de Eloquent
--   (`changes/original/attributes/relations`). `points_snapshot`/`close_notes` seguros.
--
-- APLICAR FUERA DE LARAVEL (NO migrate), MySQL 5.7. Idempotente:
--   CREATE TABLE IF NOT EXISTS (solo crea; no altera nada existente).
--     source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-30-issued-permits.sql
--   Permiso: php artisan db:seed --class=PermitIssuancePermissionsSeeder + cache:clear
-- MySQL 5.7: JSON prohíbe DEFAULT → `points_snapshot`/`standards_snapshot`/`reverifications` van JSON NULL.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `issued_permits` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                   CHAR(36)     NULL,          -- verificador público (GeneratesUuidKey)
    `production_id`          BIGINT UNSIGNED NULL,       -- CurrentProduction (FK-soft)
    `shoot_day`              INT          NULL,          -- ProductionCalendar::shootDayFor (VA EN EL HASH)

    -- Permiso de origen (referencia viva + snapshot congelado) ----------------
    `permit_id`              BIGINT UNSIGNED NULL,       -- permits.id del catálogo (FK-soft)
    `permit_code`            VARCHAR(20)  NULL,          -- snapshot (PER-##)
    `permit_key`             VARCHAR(60)  NULL,          -- snapshot (altura, pirotecnia…)
    `permit_family`          VARCHAR(120) NULL,          -- snapshot ("1. Calor y fuego")
    `permit_name`            VARCHAR(255) NULL,          -- snapshot
    `permit_definition`      TEXT         NULL,          -- snapshot
    `permit_site_scope`      VARCHAR(40)  NULL,          -- snapshot: indiferente | reverificacion | ligado_al_sitio
    `points_snapshot`        JSON         NULL,          -- puntos compuerta congelados [{code,text,executor,is_gate,site_sensitive,requires_contact,answer}]
    `standards_snapshot`     JSON         NULL,          -- normas de ítem congeladas (regulation_code)

    -- ACTIVIDAD + SITIO + JORNADA (el objeto del permiso, no la herramienta) ---
    `activity_description`   TEXT         NULL,          -- qué actividad autoriza
    `site_label`             VARCHAR(255) NULL,          -- dónde (locación/zona)
    `tool_id`                BIGINT UNSIGNED NULL,       -- herramienta que lo DISPARÓ (FK-soft, opcional)
    `tool_code`              VARCHAR(20)  NULL,          -- snapshot
    `tool_name`              VARCHAR(255) NULL,          -- snapshot

    -- AUTORIZACIÓN EXTERNA (declaración, no verificación) ---------------------
    `ext_auth_mandatory`     TINYINT(1)   NOT NULL DEFAULT 0,  -- snapshot: ¿era obligatoria?
    `ext_auth_authority`     VARCHAR(120) NULL,          -- SEDENA | AFAC | municipal…
    `ext_auth_folio`         VARCHAR(120) NULL,          -- folio del documento externo (declarado)
    `ext_auth_valid_until`   DATE         NULL,          -- vigencia del documento externo (declarada)
    `ext_auth_declared_by`   VARCHAR(255) NULL,          -- quién declara, bajo su responsabilidad
    `ext_auth_note`          TEXT         NULL,          -- qué autoriza / observaciones

    -- FIRMA 1 · EMITE (safety), cédula congelada ------------------------------
    `issuer_user_id`         BIGINT UNSIGNED NULL,       -- FK-soft a users
    `issuer_name`            VARCHAR(255) NULL,          -- snapshot
    `issuer_role`            VARCHAR(100) NULL,          -- snapshot (rol al firmar)
    `issuer_cedula`          VARCHAR(60)  NULL,          -- snapshot (medic_credentials si tiene, si no NULL)

    -- FIRMA 2 · ACEPTA (ejecutante designado), identificación congelada -------
    `acceptor_user_id`       BIGINT UNSIGNED NULL,       -- FK-soft a users (puede ser no-crew → NULL)
    `acceptor_name`          VARCHAR(255) NULL,          -- snapshot
    `acceptor_role`          VARCHAR(100) NULL,          -- snapshot (especialista/operador…)
    `acceptor_id_label`      VARCHAR(60)  NULL,          -- tipo de identificación (cédula/INE/pasaporte)
    `acceptor_id_value`      VARCHAR(120) NULL,          -- identificación congelada
    `accepted_at`            DATETIME     NULL,          -- acto de aceptación (VA EN EL HASH)

    -- TRABAJO EN CALIENTE: vigilancia posterior es PARTE del permiso ----------
    `requires_fire_watch`    TINYINT(1)   NOT NULL DEFAULT 0,  -- derivado al emitir (VA EN EL HASH)
    `fire_watch_confirmed`   TINYINT(1)   NOT NULL DEFAULT 0,  -- se declara al CERRAR (HASH-EXCLUIDO)

    -- REVERIFICACIÓN (Paso 3): registro en el MISMO permiso, sin re-sellar -----
    `reverifications`        JSON         NULL,          -- [{at,by_id,by_name,site_label,points:[code],result}] (HASH-EXCLUIDO)

    -- CIERRE (Paso 4): acto con autor y hora ----------------------------------
    `closed_at`              DATETIME     NULL,          -- (HASH-EXCLUIDO)
    `closed_by_id`           BIGINT UNSIGNED NULL,
    `closed_by_name`         VARCHAR(255) NULL,
    `close_notes`            TEXT         NULL,

    -- SUSPENSIÓN (Paso 4): patrón de retiro hash-excluido de #42 --------------
    `suspended_at`           DATETIME     NULL,          -- (HASH-EXCLUIDO)
    `suspended_by_id`        BIGINT UNSIGNED NULL,
    `suspended_reason`       VARCHAR(255) NULL,

    -- Emisión nueva que SUSTITUYE a ésta (cambio de sitio / re-montaje) -------
    `superseded_by_id`       BIGINT UNSIGNED NULL,       -- self-ref soft (HASH-EXCLUIDO)

    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`             TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `issued_permits_uuid_unique`   (`uuid`),
    KEY `issued_permits_permit_idx`     (`permit_id`),
    KEY `issued_permits_code_day_idx`   (`permit_code`, `shoot_day`, `is_active`),
    KEY `issued_permits_production_idx` (`production_id`),
    KEY `issued_permits_tool_idx`       (`tool_id`),
    KEY `issued_permits_closed_idx`     (`closed_at`),
    KEY `issued_permits_suspended_idx`  (`suspended_at`),
    KEY `issued_permits_superseded_idx` (`superseded_by_id`),
    KEY `issued_permits_active_idx`     (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
