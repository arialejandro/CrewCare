-- ============================================================================
-- CrewCare — CATÁLOGO DE TIPOS DE EFECTO SFX (Capa A) + puente N:M a insumos
-- (2026-07-16) — Paso 4a del Pilar 3.
--
-- ── REQUISITO PREVIO: APLICA ANTES `2026-07-13-pillars-1-5.sql` ──────────────
-- Este delta REQUIERE que `database/owner-apply/2026-07-13-pillars-1-5.sql` ya esté
-- aplicado, porque ese archivo crea `consumables` (:35) — la tabla a la que apunta la
-- FK del puente. Es el PRIMER delta de este carril cuyo CREATE puede abortar por una
-- tabla ausente: los precedentes (`hazard_event_standard`) no llevan FK dura, así que
-- el orden de aplicación salía gratis. Aquí ya no.
--
-- SI FALTA, ESTO ES LO QUE VERÁS: `sfx_effect_types` se crea sin problema y
-- `consumable_sfx_effect_type` aborta con errno 150 ("Failed to open the referenced
-- table 'consumables'"). El delta queda A MEDIAS y el módulo NO aparece en la UI —
-- SfxEffectType::isAvailable() exige las DOS tablas. Ese error NO es un motor
-- caprichoso ni un caso de la "SALIDA DE EMERGENCIA" de abajo: es orden de
-- aplicación. No quites las FK por esto.
--
-- SE AUTOREPARA, sin limpiar nada a mano: aplica `2026-07-13-pillars-1-5.sql` y
-- RE-EJECUTA este archivo tal cual. El `IF NOT EXISTS` salta la tabla ya creada y
-- solo crea el puente que faltó.
--
-- QUÉ ES: el catálogo maestro de TIPOS de efecto especial ("qué se hace": humo
-- atmosférico, bola de fuego, salvas, pirotecnia de proximidad...), con su
-- definición, variantes, riesgo principal, control base, EPP requerido, personal
-- certificado y una FOTO de la normativa aplicable. Es doctrina reutilizable, NO
-- una ocurrencia de nada.
--
-- CÓMO SE RELACIONA CON EL RESTO (tres tablas que se confunden fácil):
--   - Capa A `sfx_effect_types` (esta)  → el TIPO de efecto. Catálogo.
--   - Capa B `consumables`              → el INSUMO/SDS ("con qué se hace"): el
--                                         bote de fluido de humo, el propano, la
--                                         carga pirotécnica. Catálogo.
--   - `sfx_events`                      → ¡OJO! NO es esto. Es la BITÁCORA de
--                                         disparos en vivo (toggle iniciar/detener
--                                         en set, con started_at/ended_at). Ahí
--                                         viven las INSTANCIAS, no los tipos.
--   Capa A ↔ Capa B es N:M (un tipo de efecto usa varios insumos; un insumo sirve
--   a varios tipos de efecto) → tabla puente `consumable_sfx_effect_type`.
--
-- Contenido:
--   1) TABLA sfx_effect_types            — el catálogo (Capa A). UNIQUE(code):
--            clave natural para que el seed del Paso 4c sea idempotente.
--   2) TABLA consumable_sfx_effect_type  — puente N:M Capa A ↔ Capa B, con FK
--            duras ON DELETE CASCADE (ver "EXCEPCIÓN DE FK" abajo).
--   Sin datos (los importa el Paso 4c), sin columnas nuevas en `consumables`
--   (Paso 4b), sin rutas ni vistas (Paso 4d).
--
-- ORDEN DEL ARCHIVO — NO REORDENAR: `sfx_effect_types` va ANTES que el puente.
-- La FK exige que la tabla referenciada ya exista; al revés el delta se aplica a
-- medias (la primera tabla creada, la segunda muerta al vuelo).
--
-- APLICAR FUERA DE LARAVEL (NO `php artisan migrate`), igual que el resto del
-- esquema. Correr en un cliente MySQL (HeidiSQL / CLI):
--   source C:/laragon/www/crewcarerr/database/owner-apply/2026-07-16-sfx-effect-types.sql
--
-- La app corre IGUAL sin este SQL: toda lectura de la Capa A va detrás de
-- SfxEffectType::isAvailable() (Schema::hasTable con memo). Sin las tablas, el
-- módulo simplemente no existe para la UI; al aplicar este delta se activa solo.
--
-- IDEMPOTENCIA: `CREATE TABLE IF NOT EXISTS` basta — este delta solo CREA tablas
-- nuevas, no altera ninguna existente. (El procedimiento temporal con
-- information_schema que usan otros deltas de este carril existe únicamente
-- porque MySQL 5.7 no soporta `ADD COLUMN IF NOT EXISTS`; aquí no hay ALTER que
-- guardar, así que no se mete ese andamiaje.)
--
-- MySQL 5.7: `JSON` es tipo nativo, pero PROHÍBE cláusula DEFAULT en columnas
-- JSON → las cuatro van `JSON NULL` a secas (NULL = "sin capturar").
--
-- ── EXCEPCIÓN DE FK DURAS ───────────────────────────────────────────────────
-- Convención del repo: BIGINT UNSIGNED. Los pivotes POLIMÓRFICOS (standardables,
-- action_items, digital_signatures) van sin FK dura porque una FK no puede
-- apuntar a "la tabla que diga esta columna de texto". Este puente NO es
-- polimórfico, así que SÍ lleva FK dura ON DELETE CASCADE — el mismo criterio y
-- precedente que `witnesses` en 2026-07-12-modules-6-14.sql:23.
--
-- Por qué aquí sí:
--   - Es un puente catálogo↔catálogo. Una fila huérfana (efecto o insumo ya
--     borrado) no es un dato degradado: es basura pura, sin lectura posible.
--   - Ambos lados son BIGINT UNSIGNED sobre InnoDB, que es lo que la FK necesita:
--     tipos compatibles e índice en la columna referenciada (aquí, su PRIMARY KEY).
--
-- SALIDA DE EMERGENCIA (igual que la de `witnesses` en :56-57): si algún entorno
-- rechazara la FK, ELIMINA las dos líneas `CONSTRAINT ... FOREIGN KEY` y CONSERVA
-- las `KEY` de cada lado. No se pierde integridad: el modelo emula el cascade a
-- mano en `SfxEffectType::booted()` / `Consumable::booted()` (purgan el puente al
-- borrar), igual que hace HazardEvent con `hazard_event_standard`.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) sfx_effect_types — el catálogo de TIPOS de efecto (Capa A, fuente única).
--
--    `code`        : clave natural estable (UNIQUE). El seeder del 4c hace
--                    updateOrCreate por aquí → re-sembrar no duplica.
--    `family`      : familia del efecto. VARCHAR LIBRE a propósito: el catálogo
--                    cerrado de familias lo fija el documento fuente del owner en
--                    el Paso 4c; hoy no lo conocemos y no se inventa.
--    `variants`    : lista de variantes del efecto.
--    `required_ppe`: EPP requerido. Nombre CANÓNICO del repo — la misma columna
--                    existe ya en `daily_reports` y `scouting_reports`.
--    `verified_at` : NULL = PENDIENTE de validar (espejo del Paso 1c en
--                    `consumables`). Sin sellado inicial: la tabla nace vacía.
--
--    ── LOS DOS JSON NORMATIVOS COMPARTEN EJE: JURISDICCIÓN ──────────────────
--    `standards_snapshot` → {csatf:[...], eeuu_ca:[...], mexico:[...], eeuu_fed:[...]}
--        Eje JURISDICCIÓN — el MISMO que `certified_personnel`, no el organismo
--        emisor. Es el vocabulario del documento fuente del owner (v1.1), medido
--        sobre sus 25 efectos: las tres primeras claves aparecen 25/25, sin una
--        sola excepción.
--          `csatf`    : boletines CSATF. Estándar de referencia CONTRACTUAL — los
--                       estudios de EEUU los exigen por contrato aunque la
--                       producción esté bajo jurisdicción mexicana.
--          `eeuu_ca`  : normativa de California (Cal-OSHA Título 8, California Fire
--                       Code, Título 19, SB 132, permisos AHJ...).
--          `mexico`   : cumplimiento legal local (NOM de la STPS, SEDENA, Ley Federal
--                       de Armas de Fuego y Explosivos, Protección Civil).
--          `eeuu_fed` : normativa federal de EEUU (OSHA 29 CFR...). ACEPTADA pero HOY
--                       SIN DATOS: en el documento solo vive en los estándares
--                       transversales (`meta`), donde se distingue A PROPÓSITO de
--                       `eeuu_ca` (29 CFR 1910.1200 federal vs. Cal-OSHA §5194
--                       estatal). Ningún efecto la usa todavía. Verla vacía NO es un
--                       bug: es vocabulario reservado para un efecto futuro.
--        Valores siempre en LISTA, nunca escalar (25/25 en el documento): una misma
--        jurisdicción cita varias normas a la vez.
--
--        NO existen las claves `osha` / `stps` / `general`: son de OTRO eje (el
--        organismo emisor de `safety_standards.regulation_badge`) y CERO efectos las
--        traen. Documentarlas garantizaba tres columnas vacías para siempre, y el
--        mapeo hacia ellas es LOSSY medido: de las 44 citas `eeuu_ca` solo 21
--        mencionan OSHA (el resto son ATF, California Fire Code, SB 132, NFPA, Título
--        19, permisos AHJ) y de las 46 `mexico` solo 36 son NOM/STPS (el resto SEDENA
--        y la Ley Federal de Armas de Fuego y Explosivos). NO reintroducirlas.
--
--        ── POR QUÉ JSON Y NO UN PIVOTE A `safety_standards` ─────────────────
--        Esto es un SNAPSHOT (foto congelada de texto normativo) por DECISIÓN DE
--        DOMINIO, NO por simplificación ni deuda técnica. Que quede escrito, porque
--        invita a "arreglarlo": SÍ se valoró el pivote real y SÍ hay mapeo — el
--        70,8% de las citas del documento cae en las 78 normas canónicas, y en CSATF
--        16 de los 17 boletines YA existen como fila. El owner lo RECHAZÓ igualmente,
--        con este criterio: "estas normas son de los SDS, no tendrían por qué estar
--        en los catálogos que ya teníamos". Es decir: la normativa que citan las
--        fichas SPFX/SDS pertenece a SU dominio y no debe colonizar
--        `safety_standards`, que sirve a los 5 reportes de seguridad. La razón es la
--        SEPARACIÓN DE DOMINIOS — no una supuesta falta de mapeo.
--    `certified_personnel` → {eeuu_ca:[...], mexico:[...]}
--        MISMO eje (jurisdicción) y encaje 25/25 en el documento. Una licencia de
--        pirotecnia en California la emite el State Fire Marshal; la de México, la
--        SEDENA. Por eso las claves son territorios. Es el subconjunto de las de
--        arriba que emite licencias: CSATF no licencia a nadie (es contractual) y el
--        documento no cita personal certificado federal de EEUU.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sfx_effect_types` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(40)  NOT NULL,
    `family`              VARCHAR(80)  NOT NULL,
    `name`                VARCHAR(255) NOT NULL,
    `definition`          TEXT         NULL,
    `variants`            JSON         NULL,
    `main_risk`           TEXT         NULL,
    `base_control`        TEXT         NULL,
    `required_ppe`        JSON         NULL,
    `certified_personnel` JSON         NULL,
    `standards_snapshot`  JSON         NULL,
    `notes`               TEXT         NULL,
    `sort_order`          INT          NOT NULL DEFAULT 0,
    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
    `verified_at`         TIMESTAMP    NULL DEFAULT NULL,
    `verified_by_id`      BIGINT UNSIGNED NULL,
    `created_at`          TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sfx_effect_types_code` (`code`),
    KEY `sfx_effect_types_family_idx`   (`family`),
    KEY `sfx_effect_types_active_idx`   (`is_active`),
    KEY `sfx_effect_types_verified_idx` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2) consumable_sfx_effect_type — puente N:M Capa A ↔ Capa B.
--    Nombre en orden ALFABÉTICO (convención de Laravel y del repo:
--    `hazard_event_standard`, `production_user`).
--    UNIQUE(efecto, insumo) → idempotente al sembrar el 4c y sin vínculos dobles.
--    Timestamps incluidos a propósito: en una app de cumplimiento, saber CUÁNDO se
--    ligó un insumo a un efecto es traza útil.
--    FK duras ON DELETE CASCADE (ver "EXCEPCIÓN DE FK" en la cabecera; si algún
--    motor las rechaza, quita los CONSTRAINT y deja las KEY).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `consumable_sfx_effect_type` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sfx_effect_type_id` BIGINT UNSIGNED NOT NULL,
    `consumable_id`      BIGINT UNSIGNED NOT NULL,
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_consumable_sfx_effect_type` (`sfx_effect_type_id`, `consumable_id`),
    KEY `consumable_sfx_effect_type_effect_idx`     (`sfx_effect_type_id`),
    KEY `consumable_sfx_effect_type_consumable_idx` (`consumable_id`),
    CONSTRAINT `consumable_sfx_effect_type_effect_fk`
        FOREIGN KEY (`sfx_effect_type_id`) REFERENCES `sfx_effect_types` (`id`) ON DELETE CASCADE,
    CONSTRAINT `consumable_sfx_effect_type_consumable_fk`
        FOREIGN KEY (`consumable_id`) REFERENCES `consumables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
