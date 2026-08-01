-- =====================================================================================
-- CrewCare — DELTAS DE ESQUEMA que aplica el OWNER (fuera de Laravel; NO `php artisan migrate`)
-- Fecha: 2026-06-28  |  Módulo de seguridad/compliance — Items 1-3 ACTIVOS; Item 4 (Call Sheet) EN PAUSA
-- Aplicar en este orden. Todo el CÓDIGO ya está en el repo y es DEFENSIVO: las pantallas
-- siguen funcionando aunque una columna/tabla aún no exista (Schema::hasColumn / tablas nuevas
-- solo se consultan dentro de sus propias rutas). Tras aplicar, las features se activan solas.
-- =====================================================================================

-- -------------------------------------------------------------------------------------
-- ITEM 1 — Catálogo normativo: liga "Ver boletín" (CSATF/OSHA/STPS)
-- -------------------------------------------------------------------------------------
ALTER TABLE safety_standards
    ADD COLUMN reference_url VARCHAR(500) NULL AFTER regulation_code;

-- Después del ALTER, poblar las 61 URLs oficiales (idempotente, no pisa ligas manuales):
--   php artisan db:seed --class=SafetyCatalogSeeder
-- (También corregir a mano, si aplica, la fila vieja "Vías Férreas": #28 = Railroad; #29 = Globos.)


-- -------------------------------------------------------------------------------------
-- ITEM 2 — Catálogo normativo en los 3 reportes de incidentes (snapshot badge+code)
-- -------------------------------------------------------------------------------------
ALTER TABLE hazardnotifications
    ADD COLUMN regulation_badge VARCHAR(20) NULL,
    ADD COLUMN regulation_code  VARCHAR(50) NULL;

ALTER TABLE unsafeconds
    ADD COLUMN regulation_badge VARCHAR(20) NULL,
    ADD COLUMN regulation_code  VARCHAR(50) NULL;

ALTER TABLE injury_reports
    ADD COLUMN regulation_badge VARCHAR(20) NULL,
    ADD COLUMN regulation_code  VARCHAR(50) NULL;


-- -------------------------------------------------------------------------------------
-- ITEM 3 — Scouting H&S combinado (operativo + capa H&S). Tabla NUEVA, paralela al
-- location_report legacy (que se conserva; estrangulador). Lista/crea en /scoutings.
-- -------------------------------------------------------------------------------------
CREATE TABLE scouting_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    production_id BIGINT UNSIGNED NULL,
    production_name VARCHAR(255) NULL,
    location_name VARCHAR(255) NOT NULL,
    location_address VARCHAR(500) NULL,
    scene VARCHAR(255) NULL,
    date_prep DATE NULL, date_shoot DATE NULL, date_wrap DATE NULL,
    loc_setting VARCHAR(30) NULL,
    shoot_time VARCHAR(30) NULL,
    complexity VARCHAR(20) NULL,
    nearest_hospital VARCHAR(255) NULL,
    hospital_address VARCHAR(500) NULL,
    hospital_eta VARCHAR(50) NULL,
    emergency_access VARCHAR(500) NULL,
    assembly_point VARCHAR(255) NULL,
    ambulance_company VARCHAR(255) NULL,
    emergency_phone VARCHAR(50) NULL,
    risk_assessment LONGTEXT NULL,                 -- JSON: 13 categorías {key,label,answer,risk,note,badge,code,url}
    requires_specific_ra TINYINT(1) NOT NULL DEFAULT 0,  -- gatillo SB132 (actividad especial)
    exec_summary TEXT NULL,
    viability_checklist LONGTEXT NULL,             -- JSON: [{area,status,responsible,note}]
    agreements LONGTEXT NULL,                      -- JSON: [{item,responsible,date,status}]
    operational_notes TEXT NULL,
    main_image_path VARCHAR(500) NULL,
    additional_images_paths LONGTEXT NULL,         -- JSON array
    make_by VARCHAR(255) NULL,             -- AUTOFIRMA: nombre del usuario logueado (fijado server-side)
    created_by_id BIGINT UNSIGNED NULL,    -- AUTOFIRMA: id del usuario logueado (trazabilidad inmutable)
    make_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);


-- -------------------------------------------------------------------------------------
-- ITEM 4 — Call Sheet ("el llamado"): EN PAUSA (2026-06-28). NO crear tabla por ahora.
-- El módulo inicial se retiró a _legacy_backup/decommission-2026-06-28/. Decisión del owner:
-- un callsheet real parte de guion(es) + cast list + números de personaje + necesidades por
-- departamento; se reconstruirá "cuando lleguemos con dirección". No aplicar DDL de call_sheets
-- hasta ese rediseño. (Los permisos call_sheets.* se revirtieron del seeder RBAC.)
-- -------------------------------------------------------------------------------------


-- =====================================================================================
-- FASE 2 (2026-06-28 PM) — Autofirma + chips de severidad/estado
-- =====================================================================================

-- AUTOFIRMA: id del usuario logueado (trazabilidad inmutable) en los reportes.
-- (scouting_reports ya lo trae en su CREATE TABLE de arriba.)
ALTER TABLE daily_reports       ADD COLUMN created_by_id BIGINT UNSIGNED NULL;
ALTER TABLE hazardnotifications ADD COLUMN created_by_id BIGINT UNSIGNED NULL;
ALTER TABLE unsafeconds         ADD COLUMN created_by_id BIGINT UNSIGNED NULL;
ALTER TABLE injury_reports      ADD COLUMN created_by_id BIGINT UNSIGNED NULL;

-- CHIPS de lectura rápida en Acc. Inseguras y Cond. Inseguras:
--   risk_level    = Bajo / Medio / Alto / Extremo  (matriz 5x5 simplificada)
--   action_status = Abierto / En proceso / Cerrado (cierre del lazo de acción correctiva)
ALTER TABLE hazardnotifications ADD COLUMN risk_level VARCHAR(20) NULL, ADD COLUMN action_status VARCHAR(20) NULL;
ALTER TABLE unsafeconds         ADD COLUMN risk_level VARCHAR(20) NULL, ADD COLUMN action_status VARCHAR(20) NULL;


-- =====================================================================================
-- FASE 3 (2026-06-28 PM) — Rediseño de catálogos (paso #2): orden canónico
-- Los catálogos (Departamentos/Puestos) ahora se gestionan sobre las tablas NUEVAS
-- departments/positions (CatalogController). sort_order = orden que fluye a Crew List / llamado.
-- =====================================================================================
ALTER TABLE departments ADD COLUMN sort_order INT NOT NULL DEFAULT 0;
ALTER TABLE positions   ADD COLUMN sort_order INT NOT NULL DEFAULT 0;


-- =====================================================================================
-- FASE 4 (2026-06-28 PM) — Panel de Marca (branding configurable por super-admin)
-- Tabla clave/valor para personalizar logo del cliente / nombre de marca / título PWA / color.
-- El permiso settings.manage se siembra en RolesAndPermissionsSeeder (super-admin).
-- =====================================================================================
CREATE TABLE settings (
  `key` VARCHAR(80) NOT NULL PRIMARY KEY,
  `value` TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
