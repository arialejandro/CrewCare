-- ============================================================================
-- CrewCare — Marca configurable: tabla `settings` (clave/valor) (2026-07-06)
--
-- Almacén simple key/value que respalda el sistema de Marca (Branding): nombre de
-- marca, título de la app/PWA, color primario y logo del cliente. Editable en vivo
-- por el super-admin en /settings/branding.
--
-- El código (App\Support\Branding) es A PRUEBA DE TABLA-AUSENTE: si `settings` no
-- existe, usa solo los defaults y NADA se rompe. Pero SIN esta tabla el branding no
-- persiste — por eso hay que crearla en producción.
--
-- Aplicar FUERA de Laravel (NO migrate). Idempotente: CREATE TABLE IF NOT EXISTS.
--
-- Seed opcional recomendado (ajustar valores al cliente real):
--   INSERT IGNORE INTO `settings` (`key`,`value`,`created_at`,`updated_at`) VALUES
--     ('brand_name','CrewCare',NOW(),NOW()),
--     ('app_title','CrewCare',NOW(),NOW()),
--     ('primary_color','#ff9900',NOW(),NOW()),
--     ('secondary_color','#1f2937',NOW(),NOW()),
--     ('accent_color','#0ea5e9',NOW(),NOW()),
--     ('client_logo','',NOW(),NOW());
-- Si `client_logo` queda vacío, los documentos usan el logo por defecto (cc_pimienta).
-- El código es a-prueba-de-clave-ausente: cualquier clave que falte usa su default (Branding::DEFAULTS).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(191) NOT NULL,
    `value`      LONGTEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
