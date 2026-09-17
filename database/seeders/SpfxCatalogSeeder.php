<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Consumable;
use App\Models\SfxEffectType;

/**
 * SpfxCatalogSeeder — importa el catálogo SPFX del owner (Paso 4c del Pilar 3).
 *
 * QUÉ IMPORTA: 25 tipos de efecto → `sfx_effect_types` (Capa A, el QUÉ se hace),
 * 41 insumos/SDS → `consumables` (Capa B, el CON QUÉ se hace) y los 56 vínculos
 * N:M entre ambas → `consumable_sfx_effect_type`. NO toca esquema, rutas ni vistas.
 *
 * ── DE DÓNDE (LEE ESTO ANTES DE CAMBIAR EL ARCHIVO) ─────────────────────────
 * Fuente: `database/seeders/data/crewcare-spfx-catalogo-v1.1.json`, copia BYTE A
 * BYTE (sha1 verificado) de `crewcare_spfx_catalogo.json` del owner.
 *
 * EL NOMBRE DICE LA VERSIÓN DEL CONTENIDO, Y ES A PROPÓSITO. Existe por ahí un
 * `crewcare_spfx_catalogo_v2.json` cuyo nombre suena más nuevo y NO LO ES: es
 * `meta.version: 1.0` y trae las HDS CRUZADAS (la gasolina gelificada apunta a la
 * ficha del propano). El bueno es el que aquí se llama v1.1 — `meta.version: 1.1`,
 * con la auditoría de HDS del owner ya aplicada, el bloque `meta.auditoria_hds` y
 * el campo `sds_url_verificada`. Por eso este archivo NO se llama `_v2`: sembrar el
 * equivocado es exactamente el error que ese nombre invita a cometer.
 *
 * Y NO SE DEJA EN MANOS DEL NOMBRE DEL ARCHIVO: loadCatalog() ABORTA si falta
 * `meta.auditoria_hds` (el bloque existe en la v1.1 y NO existe en la v1.0), antes de
 * escribir un solo byte. NO se gatea por `meta.version`: una v1.2 legítima debe poder
 * sembrarse, así que lo que se exige es que la AUDITORÍA esté aplicada, no un número.
 *
 * CONSECUENCIA MEDIDA Y ESPERADA: con la v1.1 los insumos salen 32 verificados / 9
 * pendientes (no 29/12, que son cifras de la v1.0: la auditoría promovió 3 insumos).
 * Los efectos salen 17/8 en ambas y los vínculos 56 en ambas. Si ves 32/9, el seeder
 * está leyendo el archivo CORRECTO. 29/12 significa que alguien metió la v1.0 (ese
 * chivato de report() es un SEGUNDO cinturón: corre después del commit, así que quien
 * de verdad para la v1.0 es el fail-fast de `meta.auditoria_hds`).
 *
 * ── CÓMO CORRERLO ──────────────────────────────────────────────────────────
 * 1) DRY-RUN (no escribe NADA; calcula y reporta). Hazlo SIEMPRE primero:
 *      PowerShell:  $env:SPFX_DRY_RUN=1; php artisan db:seed --class=SpfxCatalogSeeder
 *                   Remove-Item Env:SPFX_DRY_RUN      <- ¡acuérdate de limpiarla!
 *      Git Bash:    SPFX_DRY_RUN=1 php artisan db:seed --class=SpfxCatalogSeeder
 *
 * 2) CORRIDA REAL (transacción; cualquier error revierte todo):
 *      php artisan db:seed --class=SpfxCatalogSeeder
 *
 * Va por variable de entorno porque `db:seed` no admite flags propios. OJO en
 * PowerShell: `$env:` PERSISTE en la sesión — si no la borras, la "corrida real"
 * seguirá siendo un ensayo y jurarás que el seeder no escribe.
 *
 * ── POR QUÉ EL DRY-RUN NO ESCRIBE NI PARA REVERTIR ─────────────────────────
 * Se valoró el patrón "todo en DB::transaction + excepción de control al final
 * para hacer rollback" y se DESCARTÓ. Motivos, en orden de peso:
 *   1) Un dry-run por rollback SÍ ESCRIBE. Toma locks de escritura, quema
 *      AUTO_INCREMENT (InnoDB no lo devuelve al revertir) y dispara los eventos de
 *      modelo (incluido el hook `deleting` de Consumable::booted()). Sobre una BD
 *      viva eso no es "no tocar nada", y este seeder se va a correr en PRODUCCIÓN.
 *   2) El modo de fallo se vuelve ambiguo: si algo truena DE VERDAD a mitad, el
 *      catch que espera la excepción de control puede tragárselo y reportar un
 *      "ensayo exitoso". El ensayo y el error se hacen indistinguibles.
 *   3) Cualquier DDL hace COMMIT IMPLÍCITO en MySQL: la transacción se cierra sola
 *      y el "ensayo" queda escrito de forma permanente, sin aviso.
 *   4) Un flag que nunca llama a save() es DEMOSTRABLEMENTE incapaz de mutar la BD.
 *      "No puede escribir" > "escribe y deshace" cuando el que corre esto es el
 *      owner sobre datos de cumplimiento.
 * El precio del no-escribir es que no ensayas las restricciones del motor. Se paga
 * con checkFit(): un preflight que mide CADA valor contra el ancho REAL de su
 * columna y aborta si algo no cabe (el caso `familia_insumo` de 44 chars contra un
 * VARCHAR(40), que es justo lo que reventaría). Se recupera casi todo el valor del
 * ensayo sin tocar un byte.
 *
 * ── EL SEEDER NO PARSEA LA PROSA. LLEVA UN MAPA LITERAL CURADO ─────────────
 * `un_number`, `signal_word`, `cas_number` y `ghs_pictograms` NO se extraen con
 * regex: viven en promotedMap(), 41 entradas escritas y revisadas a mano. Esto no
 * es pereza, es lo contrario — se MIDIÓ que una regex "razonable" miente en
 * silencio sobre sustancias que van a arder:
 *   - `/\b\d{4}\b/` sobre `14_transporte` guarda **2009** como número UN en 7 filas
 *     de pirotecnia. "2009" es el AÑO de la NOM-009-SCT2/**2009**.
 *   - `/UN (\d{4})/` pierde los números tras diagonal sin prefijo: INS-FIRE-07
 *     ("UN 1256/1268") y INS-ARM-01 ("UN 0014/0326/0327/0338").
 *   - Un detector de condicionales por la palabra "según" marca como dudosas las 4
 *     filas PYR-02/04/06/07, cuyo GHS01 es FIRME: ahí el "según" gobierna la CLASE
 *     DE TRANSPORTE ("UN 0509 (1.4C) / UN 0160 (1.1C) ... según tipo"), no el
 *     pictograma.
 * Un parser repetiría esos errores en cada re-seed, callado. 41 filas se escriben
 * a mano UNA vez y se revisan. Si el catálogo sube a v1.2, se revisa el mapa.
 *
 * ── PRINCIPIO RECTOR DE LAS COLUMNAS PROMOVIDAS ────────────────────────────
 * El texto ÍNTEGRO vive igual en `sds_sections` (las 16 secciones). Las columnas
 * escalares son ATAJOS para lista/búsqueda/badges, no la fuente de verdad. Por eso:
 * SOLO se llenan cuando el dato es INEQUÍVOCO; ante ambigüedad, NULL. Un NULL manda
 * a leer la sección; un dato inventado MIENTE con cara de certeza. Esto es una app
 * de cumplimiento. Cifras resultantes (sobre 41), todas deliberadas:
 *   ghs_pictograms  19 lista firme /  6 `[]` / 16 NULL
 *   un_number        9              / 32 NULL
 *   signal_word     29 (23 PELIGRO + 6 ATENCIÓN) / 12 NULL
 *   cas_number       4              / 37 NULL   <- el resto son MEZCLAS: vacío es CORRECTO
 *
 * ── IDEMPOTENTE ────────────────────────────────────────────────────────────
 * updateOrCreate por `code` en ambas capas (NUNCA firstOrCreate: no actualizaría en
 * la 2ª corrida). El N:M se re-sincroniza con sync(). Re-correr no duplica.
 *
 * ── NO SE REGISTRA EN DatabaseSeeder ───────────────────────────────────────
 * A propósito: el repo no mete ahí los seeders de datos (HazardEventSeeder tampoco).
 * Se corre a mano, con --class.
 *
 * Requiere el esquema de los Pasos 4a y 4b aplicado (ver checkSchema()).
 */
class SpfxCatalogSeeder extends Seeder
{
    /** Archivo de datos, relativo a database_path(). Ver la cabecera sobre el nombre. */
    const DATA_FILE = 'seeders/data/crewcare-spfx-catalogo-v1.1.json';

    /**
     * Umbral del reporte de solapamiento con las 12 fichas legacy. CALIBRADO, no
     * inventado: a 55 se pierde el solapamiento real "Humo (glicol/glicerina)" ↔
     * INS-ATM-01 (puntúa 53.1). A 50 aparece, siguen saliendo 0 candidatos para
     * "Solventes/thinner" (que de verdad no tiene pareja) y el total es de 18
     * candidatos: una pantalla para que el owner los despache. Se favorece el RECALL
     * a propósito — esto es un REPORTE que lee un humano: un falso positivo cuesta
     * un vistazo, un falso negativo deja un duplicado vivo en el catálogo.
     */
    const OVERLAP_THRESHOLD = 50.0;

    /** Máximo de candidatos a reportar por ficha legacy (evita ahogar el reporte). */
    const OVERLAP_TOP_N = 3;

    /** Modo ensayo: si es true NO se escribe NADA (ver la cabecera). */
    protected $dryRun = false;

    /** Timestamp único del import; sella `verified_at` de lo "verificado de origen". */
    protected $importedAt;

    /** Contadores del reporte final. */
    protected $stats = [
        'effects_created'     => 0,
        'effects_updated'     => 0,
        'effects_verified'    => 0,
        'effects_pending'     => 0,
        'consumables_created' => 0,
        'consumables_updated' => 0,
        'consumables_verified' => 0,
        'consumables_pending' => 0,
        'refs_declared'       => 0,
        'links'               => 0,
        'refs_broken'         => 0,
        'links_detached'      => 0,
        'human_seals_kept'    => 0,
        'seals_reopened'      => 0,
    ];

    /** Refs `INS-*` que no resolvieron a un consumible (se reportan, no abortan). */
    protected $unresolved = [];

    /** Valores que NO caben en su columna (preflight; abortan la corrida). */
    protected $fitProblems = [];

    /**
     * Mapa de acentos para normalizar. NO uses iconv('ASCII//TRANSLIT'): en el PHP de
     * Windows de este equipo "ó" se convierte en "'o" (apóstrofo + o), y al limpiar la
     * puntuación "nitrógeno líquido" acaba partido en los tokens "nitr", "ogeno" e
     * "iquido". Medido. El mapa explícito da "nitrogeno liquido".
     */
    const ACCENT_MAP = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        '₂' => '2', '₃' => '3', 'º' => '', '°' => '',
    ];

    /** Palabras vacías del dominio: aparecen en casi todas las fichas y no discriminan. */
    const OVERLAP_STOPWORDS = [
        'de', 'la', 'el', 'los', 'las', 'base', 'fluido', 'con', 'y', 'o',
        'para', 'del', 'tipo', 'un', 'una', 'por', 'en',
    ];

    public function run()
    {
        $this->dryRun = $this->resolveDryRunFlag();
        $this->importedAt = now();

        if ($this->dryRun) {
            $this->command->warn('╔══════════════════════════════════════════════════════════════╗');
            $this->command->warn('║  SPFX_DRY_RUN=1 → ENSAYO. NO se escribe NADA en la BD.       ║');
            $this->command->warn('╚══════════════════════════════════════════════════════════════╝');
        }

        // (a) FAIL-FAST. Si falta esquema o el JSON, se aborta ANTES de escribir nada.
        if (!$this->checkSchema()) {
            return;
        }

        $data = $this->loadCatalog();
        if ($data === null) {
            return;
        }

        // Preflight de cabida: mide cada valor contra el ancho REAL de su columna.
        // Es lo que compra el dry-run que no escribe (ver la cabecera).
        if (!$this->checkFit($data)) {
            return;
        }

        if ($this->dryRun) {
            // Sin transacción: no hay nada que revertir porque no se escribe.
            $this->importEffects($data['efectos']);
            $this->importConsumables($data['insumos']);
            $this->linkGraph($data['efectos'], $data['insumos']);
        } else {
            // (c) La corrida real, ENTERA en una transacción: cualquier error revierte todo.
            $seeder = $this;
            DB::transaction(function () use ($seeder, $data) {
                $seeder->importEffects($data['efectos']);
                $seeder->importConsumables($data['insumos']);
                $seeder->linkGraph($data['efectos'], $data['insumos']);
            });
        }

        $this->report($data);
    }

    /**
     * Lee el flag de ensayo. Se consultan getenv() Y env(): getenv() ve la variable
     * puesta en la línea de comandos aunque la config esté cacheada (config:cache no
     * carga el .env, pero la variable REAL del SO sigue ahí); env() cubre el .env.
     */
    protected function resolveDryRunFlag()
    {
        $flag = getenv('SPFX_DRY_RUN');
        if ($flag === false || $flag === '') {
            $flag = env('SPFX_DRY_RUN', '');
        }
        return in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * (a) FAIL-FAST de esquema. Dice EXACTAMENTE qué SQL falta aplicar y no escribe nada.
     * La app corre igual sin estas tablas (todo va detrás de isAvailable() /
     * supportsExtendedSds()); lo que no puede es sembrarse.
     */
    protected function checkSchema()
    {
        $missing = [];

        if (!Schema::hasTable('sfx_effect_types')) {
            $missing[] = 'tabla `sfx_effect_types`      → falta database/owner-apply/2026-07-16-sfx-effect-types.sql';
        }
        if (!Schema::hasTable('consumable_sfx_effect_type')) {
            $missing[] = 'tabla `consumable_sfx_effect_type` → falta database/owner-apply/2026-07-16-sfx-effect-types.sql';
        }
        if (!Schema::hasTable('consumables')) {
            $missing[] = 'tabla `consumables`           → falta el esquema base de consumibles';
        } else {
            // `code` es la clave natural del upsert: sin ella el seeder DUPLICARÍA el
            // catálogo en cada corrida. `sds_sections` es el corazón del 4b.
            foreach (['code', 'sds_sections'] as $col) {
                if (!Schema::hasColumn('consumables', $col)) {
                    $missing[] = "columna `consumables.{$col}`   → falta database/owner-apply/2026-07-17-consumables-sds16.sql";
                }
            }
            if (!Schema::hasColumn('consumables', 'verified_at')) {
                $missing[] = 'columna `consumables.verified_at` → falta database/owner-apply/2026-07-16-sds-verification.sql';
            }
        }

        if (!empty($missing)) {
            $this->command->error('SpfxCatalogSeeder: ABORTADO — el esquema no está listo. NO se escribió nada.');
            foreach ($missing as $m) {
                $this->command->error('  · '.$m);
            }
            $this->command->warn('  Aplica el/los SQL FUERA de Laravel (NUNCA `php artisan migrate`) y vuelve a correr.');
            return false;
        }

        return true;
    }

    /**
     * (a) FAIL-FAST del archivo: si no existe, no parsea, no trae las 2 capas o le falta
     * `meta.auditoria_hds` (o sea, parece la v1.0 sin auditoría de HDS) → aborta sin escribir.
     */
    protected function loadCatalog()
    {
        $path = database_path(self::DATA_FILE);

        if (!is_file($path)) {
            $this->command->error('SpfxCatalogSeeder: ABORTADO — no existe el archivo de datos:');
            $this->command->error('  '.$path);
            $this->command->warn('  Debe ser copia de `crewcare_spfx_catalogo.json` (meta.version 1.1), NO del `_v2` (que es 1.0).');
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            $this->command->error('SpfxCatalogSeeder: ABORTADO — el JSON no parsea: '.json_last_error_msg());
            return null;
        }

        if (!isset($data['efectos']) || !is_array($data['efectos']) || empty($data['efectos'])
            || !isset($data['insumos']) || !is_array($data['insumos']) || empty($data['insumos'])) {
            $this->command->error('SpfxCatalogSeeder: ABORTADO — el JSON no trae `efectos` e `insumos` como listas no vacías.');
            return null;
        }

        // ABORT — el marcador INEQUÍVOCO de que la auditoría de HDS del owner está aplicada:
        // `meta.auditoria_hds` existe en la v1.1 y NO existe en la v1.0. Va AQUÍ, en el
        // fail-fast, porque el otro detector (el de 29/12) vive en report(), que corre
        // DESPUÉS de que la transacción commitee: avisaría del incendio con las 41 fichas ya
        // escritas y las HDS cruzadas dentro. Un aviso posterior al hecho consumado no es una
        // salvaguarda; es una necrológica.
        if (!isset($data['meta']['auditoria_hds'])) {
            $this->command->error('SpfxCatalogSeeder: ABORTADO — falta el bloque `meta.auditoria_hds`. NO se escribió nada.');
            $this->command->error('  Sin él, el archivo parece la v1.0: la versión SIN la auditoría de HDS del owner.');
            $this->command->error('  Sembrarla metería ENLACES DE SEGURIDAD CRUZADOS — la gasolina gelificada apuntando a');
            $this->command->error('  la ficha del propano — en un catálogo que la gente consulta antes de encender algo.');
            $this->command->warn('  Usa la copia con la auditoría aplicada, NUNCA el `crewcare_spfx_catalogo_v2.json` (que es la 1.0).');
            return null;
        }

        // Aviso INFORMATIVO, NO abort: el contenido ya lo gatea `meta.auditoria_hds` arriba, y
        // una v1.2 legítima con la auditoría aplicada DEBE poder sembrarse. Esto solo deja en
        // el log con qué versión se corrió y recuerda qué hay que revisar si el catálogo subió.
        $version = isset($data['meta']['version']) ? (string) $data['meta']['version'] : '(sin meta.version)';
        if ($version !== '1.1') {
            $this->command->warn("SpfxCatalogSeeder: OJO — meta.version = {$version}, se esperaba 1.1.");
            $this->command->warn('  No aborta (el archivo SÍ trae `meta.auditoria_hds`), pero si el catálogo cambió revisa');
            $this->command->warn('  promotedMap() y resolveType(): son mapas curados a mano contra la v1.1.');
        }

        return $data;
    }

    /**
     * Preflight de CABIDA. Mide cada valor contra el ancho REAL de su columna y aborta
     * si algo no entra. Sustituye al ensayo contra el motor que el dry-run no hace.
     *
     * El caso que motiva esto es real y medido: 3 de las 27 `familia_insumo` pasan de
     * 40 chars (la mayor, "Combustibles - improvisados / banderas rojas", mide 44), así
     * que volcarlas crudas en `type` VARCHAR(40) reventaría. Por eso `type` recibe el
     * mapeo de 9 slugs y la familia íntegra va a `material_family` VARCHAR(80).
     */
    protected function checkFit(array $data)
    {
        $check = function ($label, $value, $max) {
            if ($value === null) {
                return;
            }
            $len = mb_strlen((string) $value);
            if ($len > $max) {
                $this->fitProblems[] = "{$label}: {$len} chars > {$max} → [".mb_substr((string) $value, 0, 60).'…]';
            }
        };

        foreach ($data['efectos'] as $e) {
            $split = $this->splitFamily(isset($e['familia']) ? $e['familia'] : '');
            $check('efecto '.$e['id'].' code', isset($e['id']) ? $e['id'] : null, 40);
            $check('efecto '.$e['id'].' family', $split['family'], 80);
            $check('efecto '.$e['id'].' name', isset($e['nombre']) ? $e['nombre'] : null, 255);
        }

        foreach ($data['insumos'] as $i) {
            $code = isset($i['id']) ? $i['id'] : null;
            $promoted = $this->promotedFor($code);
            $check('insumo '.$code.' code', $code, 40);
            $check('insumo '.$code.' name', isset($i['nombre']) ? $i['nombre'] : null, 255);
            $check('insumo '.$code.' material_family', isset($i['familia_insumo']) ? $i['familia_insumo'] : null, 80);
            $check('insumo '.$code.' sds_status', isset($i['sds_estado']) ? $i['sds_estado'] : null, 255);
            $check('insumo '.$code.' sds_url', isset($i['sds_fuente_url']) ? $i['sds_fuente_url'] : null, 500);
            $check('insumo '.$code.' type', $this->resolveType($i), 40);
            $check('insumo '.$code.' un_number', $promoted['un'], 20);
            $check('insumo '.$code.' signal_word', $promoted['signal'], 20);
            $check('insumo '.$code.' cas_number', $promoted['cas'], 40);
        }

        if (!empty($this->fitProblems)) {
            $this->command->error('SpfxCatalogSeeder: ABORTADO — hay valores que NO CABEN en su columna. NO se escribió nada.');
            foreach ($this->fitProblems as $p) {
                $this->command->error('  · '.$p);
            }
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // (d) EFECTOS → sfx_effect_types
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Importa los 25 tipos de efecto. Upsert por `code` (updateOrCreate).
     *
     * NOTA sobre `public`: los tres import* son públicos porque el closure de
     * DB::transaction los invoca sobre `$seeder`; en PHP 7.4 un closure no hereda el
     * scope privado de la clase que lo crea sin bindTo(). Se mantienen fuera del
     * contrato útil de la clase (solo run() se llama desde fuera).
     */
    public function importEffects(array $efectos)
    {
        $codes = array_map(function ($e) {
            return $e['id'];
        }, $efectos);

        // Estado previo en UNA query: para saber qué se crea vs. qué se actualiza y para
        // NO pisar el sello humano (ver resolveVerification()).
        $existing = SfxEffectType::whereIn('code', $codes)
            ->get(['code', 'verified_at', 'verified_by_id'])
            ->keyBy('code');

        foreach ($efectos as $e) {
            $split = $this->splitFamily($e['familia']);
            $prev = isset($existing[$e['id']]) ? $existing[$e['id']] : null;
            // Los efectos son catálogo de FÁBRICA autoritativo → nacen VERIFICADOS DE ORIGEN
            // (verified_by_id NULL), nunca pendientes. No hay ruta de verificación por humano para
            // efectos, así que el badge ámbar aquí sería UI muerta. (Los INSUMOS sí conservan su
            // flujo pendiente en importConsumables()/resolveVerification().)
            $verification = $this->resolveVerification($prev, true);
            $this->stats['effects_verified']++;

            if ($prev === null) {
                $this->stats['effects_created']++;
            } else {
                $this->stats['effects_updated']++;
            }

            if ($this->dryRun) {
                continue;
            }

            $payload = [
                'family'              => $split['family'],
                'sort_order'          => $split['sort_order'],
                'name'                => $e['nombre'],
                'definition'          => isset($e['definicion']) ? $e['definicion'] : null,
                'variants'            => $this->resolveVariants($e),
                'main_risk'           => isset($e['riesgo_principal']) ? $e['riesgo_principal'] : null,
                'base_control'        => isset($e['control_base']) ? $e['control_base'] : null,
                'required_ppe'        => isset($e['epp_minimo']) ? $e['epp_minimo'] : null,
                'certified_personnel' => isset($e['personal_certificado']) ? $e['personal_certificado'] : null,
                'standards_snapshot'  => isset($e['normas']) ? $e['normas'] : null,
                'notes'               => $this->resolveNotes($e),
            ];

            // `is_active` SOLO EN EL ALTA (mismo criterio en insumos, ver importConsumables()).
            if ($prev === null) {
                $payload['is_active'] = true;
            }

            $payload = array_merge($payload, $verification);

            // Los 4 JSON tienen cast 'array' → se asignan como ARRAY de PHP. NO json_encode:
            // el cast serializa UNA vez (mismo criterio que ScoutingReport).
            SfxEffectType::updateOrCreate(['code' => $e['id']], $payload);
        }
    }

    /**
     * Parte "1. Fuego y llama" en family="Fuego y llama" + sort_order=1.
     *
     * POR QUÉ SE SEPARA: la `familia` del documento trae el orden EMBEBIDO en el texto.
     * Si el orden vive dos veces (dentro del nombre y en `sort_order`) pueden divergir;
     * y un `ORDER BY family` alfabético pondría "10. Espuma" ANTES que "2. Atmósfera",
     * que es exactamente el bug de ordenamiento que ya se pagó con `labn`/"Jerarquía".
     * El número es el orden; el texto, el nombre. Cada cosa en su columna.
     *
     * Las 11 familias del documento parsean (verificado). Si alguna no trae prefijo se
     * conserva íntegra y cae al final (sort_order 0) en vez de perder el nombre.
     */
    protected function splitFamily($familia)
    {
        $familia = trim((string) $familia);
        if (preg_match('/^\s*(\d+)\s*\.\s*(.+)$/u', $familia, $m)) {
            return ['family' => trim($m[2]), 'sort_order' => (int) $m[1]];
        }
        return ['family' => $familia, 'sort_order' => 0];
    }

    /**
     * `variants` de un efecto. FX-16 es el ÚNICO de los 25 sin `variantes`: en su lugar
     * trae `subtipos`, que es una lista de OBJETOS {nombre, materiales, fogonazo, riesgo}.
     *
     * DECISIÓN — se APLANA cada subtipo a UNA cadena con sus 4 campos ETIQUETADOS, en vez
     * de meter los objetos crudos. La columna tiene cast 'array' y hoy guarda listas de
     * strings en 24/25 filas; meter objetos en la 25ª la vuelve HETEROGÉNEA, y el
     * consumidor natural (la vista del 4d) es un `@foreach($effect->variants as $v)
     * {{ $v }}` a ciegas: con un objeto eso truena con "Array to string conversion" —
     * en un documento de cumplimiento, justo en la ficha de las armas. Aplanando, la
     * columna queda homogénea 25/25, la vista itera sin ramificar y NO SE PIERDE NADA:
     * los 4 campos sobreviven como texto con su etiqueta delante.
     */
    protected function resolveVariants(array $e)
    {
        if (isset($e['variantes']) && is_array($e['variantes'])) {
            return $e['variantes'];
        }

        if (isset($e['subtipos']) && is_array($e['subtipos'])) {
            $out = [];
            foreach ($e['subtipos'] as $s) {
                if (!is_array($s)) {
                    $out[] = (string) $s;
                    continue;
                }
                $parts = [];
                if (isset($s['nombre'])) {
                    $parts[] = $s['nombre'];
                }
                if (isset($s['materiales'])) {
                    $parts[] = 'Materiales: '.$s['materiales'];
                }
                if (isset($s['fogonazo'])) {
                    $parts[] = 'Fogonazo: '.$s['fogonazo'];
                }
                if (isset($s['riesgo'])) {
                    $parts[] = 'Riesgo: '.$s['riesgo'];
                }
                $out[] = implode(' — ', $parts);
            }
            return $out;
        }

        return null;
    }

    /**
     * `notes` de un efecto. FX-16 trae además `distincion_critica` (271 chars), que NO
     * es una nota de procedencia como las demás `notas`: es una advertencia SUSTANTIVA
     * — el "non-gun" que define el propio Boletín CSATF #1 SÍ usa cargas explosivas por
     * impulso eléctrico y NO pertenece a esa categoría. Perderla sería perder justo lo
     * que evita que alguien trate un non-gun con pólvora como si fuera una réplica inerte.
     *
     * Va al principio y con ETIQUETA EXPLÍCITA para que no se lea como la nota de
     * procedencia que la sigue (que habla de índices y fact sheets, otro registro).
     */
    protected function resolveNotes(array $e)
    {
        $notas = isset($e['notas']) ? trim((string) $e['notas']) : '';

        if (!empty($e['distincion_critica'])) {
            $out = 'DISTINCIÓN CRÍTICA (del catálogo): '.trim($e['distincion_critica']);
            if ($notas !== '') {
                $out .= "\n\nNOTA DE ORIGEN: ".$notas;
            }
            return $out;
        }

        return $notas !== '' ? $notas : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // (e) INSUMOS → consumables
    // ─────────────────────────────────────────────────────────────────────────

    /** Importa los 41 insumos/SDS. Upsert por `code`. */
    public function importConsumables(array $insumos)
    {
        $codes = array_map(function ($i) {
            return $i['id'];
        }, $insumos);

        // Se traen TAMBIÉN los campos materiales: sin ellos no hay con qué comparar lo
        // entrante, y sin esa comparación no se puede saber si el upsert invalida un sello
        // humano (ver resolveVerification()).
        $existing = Consumable::whereIn('code', $codes)
            ->get(array_merge(['code', 'verified_at', 'verified_by_id'], $this->materialFields()))
            ->keyBy('code');

        foreach ($insumos as $i) {
            $prev = isset($existing[$i['id']]) ? $existing[$i['id']] : null;

            $payload = [
                'name'            => $i['nombre'],
                'material_family' => isset($i['familia_insumo']) ? $i['familia_insumo'] : null,
                'synonyms'        => isset($i['sinonimos']) ? $i['sinonimos'] : null,
                'sds_sections'    => isset($i['hds']) ? $i['hds'] : null,
                'sds_level'       => isset($i['sds_nivel']) ? $i['sds_nivel'] : null,
                'sds_status'      => isset($i['sds_estado']) ? $i['sds_estado'] : null,
                'sds_source_note' => isset($i['sds_fuente_nota']) ? $i['sds_fuente_nota'] : null,
                // `sds_fecha_verificacion` → `sds_source_date`, NUNCA a `verified_at`: es la
                // fecha de GENERACIÓN del catálogo (vale 2026-07-16 en los 41, incluidos los
                // NO verificados). Volcarla en verified_at sellaría el catálogo entero como
                // aprobado por una autoridad que jamás lo miró.
                'sds_source_date' => isset($i['sds_fecha_verificacion']) ? $i['sds_fecha_verificacion'] : null,
                'sds_disclaimer'  => isset($i['sds_disclaimer']) ? $i['sds_disclaimer'] : null,
                'sds_url'         => isset($i['sds_fuente_url']) ? $i['sds_fuente_url'] : null,

                // Las otras dos verificaciones del 4b. NO están en el spec del 4c porque se
                // crearon después, pero el owner las pidió expresamente: si no se pueblan, su
                // auditoría de HDS del 2026-07-16 SE PIERDE al importar. Son 3 cosas distintas:
                //   verified_at     = acto de gobierno (un `sds.manage` aprobó aquí)
                //   source_verified = el catálogo de origen dice que el dato está cotejado
                //   sds_url_verified= el enlace resuelve a la HDS de ESA sustancia (27/41)
                'source_verified'  => isset($i['verificado']) ? (bool) $i['verificado'] : null,
                'sds_url_verified' => isset($i['sds_url_verificada']) ? (bool) $i['sds_url_verificada'] : null,

                // Columnas promovidas: mapa literal curado, cero parsing. Ver promotedMap().
                'ghs_pictograms' => $this->extractGhsCodes($i),
                'un_number'      => $this->extractUnNumber($i),
                'signal_word'    => $this->extractSignalWord($i),
                'cas_number'     => $this->extractCasNumber($i),

                // Puente legacy: `type` recibe el MAPEO de 9 slugs (jamás la familia cruda:
                // 44 chars contra VARCHAR(40) → reventaría), y hazards/precautions espejan
                // las secciones que ya leían las 12 fichas vivas.
                'type'        => $this->resolveType($i),
                'hazards'     => isset($i['hds']['2_peligros']) ? $i['hds']['2_peligros'] : null,
                'precautions' => isset($i['hds']['8_controles_epp_vle']) ? $i['hds']['8_controles_epp_vle'] : null,
            ];

            // El payload se arma ANTES de decidir la verificación —y también en dry-run, aunque
            // no se vaya a escribir— porque la suerte del sello depende de si los campos
            // MATERIALES entrantes difieren de los guardados. Si esto se calculara solo en la
            // corrida real, el ensayo no podría avisar de una re-apertura y el owner se
            // enteraría del cambio de estado cuando ya hubiera pasado.
            $verification = $this->resolveVerification(
                $prev,
                !empty($i['verificado']),
                $this->touchesMaterialFields($prev, $payload)
            );

            if (!empty($i['verificado'])) {
                $this->stats['consumables_verified']++;
            } else {
                $this->stats['consumables_pending']++;
            }

            if ($prev === null) {
                $this->stats['consumables_created']++;
            } else {
                $this->stats['consumables_updated']++;
            }

            if ($this->dryRun) {
                continue;
            }

            // `is_active` SOLO EN EL ALTA: la ficha nace activa, que es lo correcto. En un
            // UPDATE no se manda A PROPÓSITO. Dar de baja una ficha es una decisión de
            // VIGENCIA de un humano: ConsumableController la valida (~193/197) y su docblock
            // (~206) la deja FUERA de los campos materiales justamente porque no afirma nada
            // sobre el material — pero es una decisión igual. Si el owner desactiva
            // INS-FIRE-11 (la mezcla improvisada de la bandera roja) porque su producción la
            // prohíbe, un `is_active => true` incondicional la RESUCITA en los selectores del
            // panel SFX en el siguiente re-seed, sin una línea en el reporte. El catálogo dice
            // QUÉ existe, no QUÉ se permite en esta producción. Mismo criterio que
            // `sort_order`, que por eso tampoco viaja en el payload de update.
            if ($prev === null) {
                $payload['is_active'] = true;
            }

            $payload = array_merge($payload, $verification);

            Consumable::updateOrCreate(['code' => $i['id']], $payload);
        }
    }

    /**
     * Regla de verificación del spec, MÁS una protección que el spec no pide y hace falta.
     *
     * Spec: `verificado==true` → verified_at = timestamp del import, verified_by_id = NULL
     * ("verificada de origen", la convención de las 12 legacy del delta 1c: autor NULL =
     * sin humano detrás). `verificado==false` → verified_at = NULL (pendiente, badge ámbar).
     *
     * LA PROTECCIÓN: si la fila YA EXISTE y tiene `verified_by_id` NO NULO, la aprobó una
     * PERSONA con permiso `sds.manage` en esta instalación. Un updateOrCreate a ciegas
     * pisaría ese sello en el siguiente re-seed y BORRARÍA un acto de gobierno sin avisar.
     * Aquí se respeta: si hay autor humano, no se toca ninguna de las dos columnas. La
     * verificación de ORIGEN no se pierde por eso — vive aparte, en `source_verified`.
     *
     * PERO CONSERVAR EL SELLO NO BASTA, Y ESE ERA EL AGUJERO: el sello sobrevivía intacto
     * mientras el payload SÍ repisaba los siete campos materiales debajo de él. O sea que
     * Juan (un `sds.manage`) firmaba unos `hazards` corregidos, el siguiente re-seed los
     * devolvía al texto del catálogo, y la firma de Juan seguía ahí AVALANDO UN TEXTO QUE
     * JUAN NUNCA VIO. Un sello que sobrevive a lo que sellaba es peor que no tener sello:
     * miente con cara de autoridad. Es exactamente el agujero que cerró el Paso 1c, y la
     * doctrina es de ConsumableController::update() (~92-99): "si cambia un campo material y
     * quien edita no es autoridad, el sello se limpia". El seeder NO es autoridad —es un
     * proceso, no una persona— así que aquí SIEMPRE toca limpiar, nunca re-sellar:
     *   material cambia  → sello FUERA. La ficha vuelve a pendiente, reaparece el badge
     *                      ámbar y un `sds.manage` la vuelve a mirar. Se REPORTA (abajo):
     *                      un cambio de estado en silencio sería tan malo como el bug.
     *   material igual   → sello INTACTO. Re-correr el seeder no molesta a nadie.
     *
     * Solo aplica a INSUMOS: los efectos pasan $materialChanged=false (ver importEffects()).
     *
     * LÍMITE CONOCIDO, DICHO AQUÍ PARA QUE NADIE SE LLEVE UNA SORPRESA: la re-apertura no es
     * memoria persistente — no hay columna donde recordar "esta ficha se re-abrió". La ficha
     * queda pendiente (badge ámbar) hasta que alguien la mire, PERO si se vuelve a correr el
     * seeder antes de que la miren y el catálogo la marca `verificado`, saldrá otra vez como
     * "verificada DE ORIGEN" (verified_at con verified_by_id NULL) y el ámbar se apaga. Eso
     * NO reabre el agujero: para entonces el contenido de la ficha YA es el del catálogo, así
     * que la etiqueta "de origen" es verdad y NINGÚN humano queda avalando lo que no vio —
     * que es lo único que este código promete arreglar. Quien avisa de verdad es el REPORTE:
     * por eso la re-apertura se CUENTA y se IMPRIME, en vez de confiársela al badge.
     *
     * Idempotencia: si ya hay `verified_at` de origen se CONSERVA en vez de refrescarlo a
     * now(), para que re-correr el seeder no mueva timestamps ni ensucie `updated_at`.
     */
    protected function resolveVerification($prev, $verificado, $materialChanged = false)
    {
        if ($prev !== null && $prev->verified_by_id !== null) {
            if ($materialChanged) {
                $this->stats['seals_reopened']++;
                return ['verified_at' => null, 'verified_by_id' => null];
            }

            $this->stats['human_seals_kept']++;
            return [];
        }

        if (!$verificado) {
            return ['verified_at' => null, 'verified_by_id' => null];
        }

        $keep = ($prev !== null && $prev->verified_at !== null) ? $prev->verified_at : $this->importedAt;

        return ['verified_at' => $keep, 'verified_by_id' => null];
    }

    /**
     * Campos MATERIALES de la ficha: los que cambian lo que la SDS AFIRMA sobre el material,
     * o sea lo que el verificador avaló al sellarla. Tocar uno invalida el sello.
     *
     * RÉPLICA LITERAL de ConsumableController::materialFields() (~209-212), que es `protected`
     * y no se puede leer desde un seeder sin reflexión. LAS DOS LISTAS DEBEN MANTENERSE
     * SINCRONIZADAS: si allá se añade o se quita un campo, aquí también — si divergen, el
     * panel y el importador dejarían de coincidir sobre qué invalida una firma, y el que
     * quedara corto borraría sellos de menos (que es el modo de fallo peligroso).
     *
     * Fuera de la lista a propósito y por el mismo motivo que allá: `sort_order` e
     * `is_active` (presentación y vigencia; no afirman nada sobre el material) y
     * `description` (texto de apoyo). Los siete son escalares string en `consumables` —
     * ninguno tiene cast 'array'—, así que la comparación como string de abajo es válida.
     */
    protected function materialFields()
    {
        return ['name', 'type', 'hazards', 'precautions', 'signal_word', 'un_number', 'sds_url'];
    }

    /**
     * ¿El payload entrante cambia algún campo material respecto a lo guardado en BD?
     *
     * Mismo criterio que ConsumableController::touchesMaterialFields() (~221-234): la
     * comparación NORMALIZA A STRING a propósito, porque un campo vacío llega
     * indistintamente como `null` desde el JSON o como `''` desde la columna, y compararlos
     * en crudo (null !== '') daría un falso positivo. Aquí un falso positivo no es cosmético:
     * BORRA el sello de una ficha que nadie tocó y manda a una autoridad a re-firmar humo.
     *
     * `$prev === null` (alta) devuelve false: no hay sello previo que invalidar. Por eso esto
     * NO puede dispararse en la primera corrida — es una salvaguarda de RE-corrida.
     */
    protected function touchesMaterialFields($prev, array $payload)
    {
        if ($prev === null) {
            return false;
        }

        foreach ($this->materialFields() as $f) {
            // Campo que este payload no manda: no lo puede haber cambiado.
            if (!array_key_exists($f, $payload)) {
                continue;
            }
            if ((string) ($prev->getAttribute($f) ?? '') !== (string) ($payload[$f] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * `familia_insumo` (27 valores) → `type` (los 9 slugs de Consumable::typeLabels()).
     *
     * NO ES PÉRDIDA DE INFORMACIÓN: es una PROYECCIÓN para el chip y el filtro. La familia
     * cruda vive íntegra al lado, en `material_family` VARCHAR(80) (la mayor mide 44). Por
     * eso equivocarse en un caso dudoso es barato: se corrige con un UPDATE de `type`, sin
     * re-importar. El eje del mapeo es el MATERIAL ("con qué se hace"), no el efecto.
     *
     * Manda la FAMILIA, nunca el prefijo del ID: INS-ARM-03 (fulminante) dice "ARM" pero su
     * familia es pirotecnia → `pyro`. Al mapear por familia, sale bien solo.
     *
     * Los dudosos, con su porqué (los 5 marcados):
     *  · "Armas - inerte" → other, NO blank. La etiqueta de blank es "Salvas/municiones
     *    FOGUEO" y un cartucho dummy es precisamente lo que NO es fogueo; el chip afirmaría
     *    la falsedad exacta que el armero vigila.
     *  · "Atmósfera - gases comprimidos" → cryo. La etiqueta dice "Criogénico/CO₂" y nombra
     *    el CO₂ explícitamente.
     *  · "Chispa fría - gránulo metálico" → other, NO pyro. El titanio NO es material
     *    energético: lo calienta la máquina a 500-600 °C. El propio catálogo lo separó de la
     *    familia pirotécnica; marcarlo `pyro` afirmaría un estatus legal falso (SEDENA).
     *  · "Réplicas - energía" → other. Una batería de litio es un ARTÍCULO (UN 3480), no un
     *    producto químico.
     *  · "Soldadura - humos y gases" → chemical. No es un insumo que se compre: es una
     *    EXPOSICIÓN que genera un proceso (Cr VI, manganeso). `chemical` es el menos malo.
     *
     * `smoke` y `fire` quedan en 0 y NO es un error: son palabras de EFECTO, no de MATERIAL.
     * Ningún material ES fuego (los 11 combustibles son `fuel`; el fuego vive ahora en
     * `sfx_effect_types`). Y los 2 candidatos a `smoke` se caen solos: INS-PYR-06 es material
     * energético bajo permiso SEDENA con GHS01 → `pyro` (un chip "Humo" escondería que es un
     * explosivo), e INS-WELD-01 son humos metálicos de un proceso → `chemical` (en `smoke`
     * quedaría junto al fluido de niebla en el filtro).
     */
    protected function resolveType(array $insumo)
    {
        $map = [
            'Armas - inerte'                               => 'other',
            'Armas - munición de efecto controlada'        => 'blank',
            'Atmósfera - criogénicos'                      => 'cryo',
            'Atmósfera - fluidos base aceite'              => 'haze',
            'Atmósfera - fluidos base agua'                => 'haze',
            'Atmósfera - gases comprimidos'                => 'cryo',
            'Breakaway'                                    => 'other',
            'Chispa fría - gránulo metálico'               => 'other',
            'Combustibles - alcoholes'                     => 'fuel',
            'Combustibles - diésel/parafínicos pesados'    => 'fuel',
            'Combustibles - gas licuado'                   => 'fuel',
            'Combustibles - gelificados / espesados'       => 'fuel',
            'Combustibles - improvisados / banderas rojas' => 'fuel',
            'Combustibles - líquidos volátiles'            => 'fuel',
            'Combustibles - parafínicos líquidos'          => 'fuel',
            'Combustibles - parafínicos sólidos'           => 'fuel',
            'Espuma/burbujas - tensoactivos'               => 'chemical',
            'Moldeo/colado - epóxicos'                     => 'chemical',
            'Moldeo/colado - poliuretano'                  => 'chemical',
            'Moldeo/colado - siliconas'                    => 'chemical',
            'Nieve - fluidos/partículas'                   => 'chemical',
            'Pirotecnia - material energético controlado'  => 'pyro',
            'Polvo/partículas - tierras y polvos'          => 'chemical',
            'Réplicas - energía'                           => 'other',
            'Sangre y maquillaje'                          => 'chemical',
            'Soldadura - humos y gases'                    => 'chemical',
            'Tratamientos - retardante de fuego'           => 'chemical',
        ];

        $familia = isset($insumo['familia_insumo']) ? $insumo['familia_insumo'] : '';

        // Cae a 'other' (el DEFAULT de la columna) si el catálogo estrena familia en una
        // v1.2: mejor un chip "Otro" honesto que abortar el import o inventar un tipo.
        if (!isset($map[$familia])) {
            $this->command->warn("SpfxCatalogSeeder: familia sin mapeo → [{$familia}] ({$insumo['id']}) → type='other'. Revisa resolveType().");
            return 'other';
        }

        return $map[$familia];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COLUMNAS PROMOVIDAS — lectura del mapa curado, NO parsing (ver la cabecera)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Pictogramas GHS. TRES ESTADOS, y la diferencia es el dato:
     *   lista  = clasificado, estos códigos (19 insumos)
     *   []     = clasificado y NO le corresponde NINGUNO (6). El documento lo AFIRMA
     *            ("Normalmente NO CLASIFICADO en GHS"). No es un hueco: es información.
     *   null   = NO SABEMOS (16).
     *
     * POR QUÉ NULL EN LOS DUDOSOS: 5 traen códigos pero CONDICIONADOS y guardarlos
     * sub-avisaría — INS-FIRE-05 dice "GHS02 y, según tipo, GHS06/GHS08/GHS07": quedarse
     * con GHS02 le quita la CALAVERA (GHS06) del quimiotipo metanol, y el error iría en la
     * dirección peligrosa. INS-BLOOD-02 cubre DOS productos (spirit gum y silicona) y los
     * códigos son de uno solo. Otros 11 no traen pictograma: 6 de ellos sí traen frases H
     * (H319, H226, H350…), y derivar el pictograma del código H es lógica GHS estándar
     * pero sería CLASIFICAR nosotros, no extraer — si algún día se quiere, es un paso
     * aparte y con firma de un `sds.manage`, no un seeder.
     *
     * CONFIRMACIÓN CRUZADA que autoriza el `[]`: los 6 que dicen "no clasificado" son
     * EXACTAMENTE los mismos 6 que no traen palabra de advertencia. Sin clasificación →
     * sin pictograma → sin palabra. El dato es internamente coherente.
     */
    protected function extractGhsCodes(array $insumo)
    {
        return $this->promotedFor($insumo['id'])['ghs'];
    }

    /**
     * Número UN (VARCHAR(20), escalar). Solo 9 de 41 son inequívocos.
     *
     * POR QUÉ NULL EN LOS DEMÁS:
     *  · 12 traen VARIOS y ninguno "es" el número: INS-ARM-03 lista cinco (0044/0377/0378/
     *    0319/0320) — en texto son 27 chars, no caben en VARCHAR(20), y aunque cupieran
     *    dejarían de ser un código enlazable a una tabla de transporte.
     *  · 3 traen uno solo pero CONDICIONADO. El peor es INS-FIRE-11: dice "Base UN 1203
     *    Gasolina… La mezcla improvisada NO TIENE clasificación válida de transporte" —
     *    guardar UN 1203 sería una MENTIRA literal, negada en la misma frase.
     *  · 17 no traen. 10 dicen "no regulado" (dato conocido) y 7 son condicionales o peor:
     *    INS-PYR-07 (squib) SÍ es explosivo regulado (clase 1.3G/1.4G/1.4S) pero no da UN, e
     *    INS-WELD-01 dice "Clase 2 (ver UN por gas)".
     * NO se mete prosa ("No regulado") en la columna: es un campo de CÓDIGO, y
     * `14_transporte` íntegro ya vive en `sds_sections`.
     */
    protected function extractUnNumber(array $insumo)
    {
        return $this->promotedFor($insumo['id'])['un'];
    }

    /**
     * Palabra de advertencia GHS (VARCHAR(20)). 29 inequívocas: 23 PELIGRO + 6 ATENCIÓN.
     *
     * POR QUÉ NULL EN 12: 6 son CONDICIONALES y una regex ingenua daría un dato falso con
     * cara de certeza — "ATENCIÓN/PELIGRO según base" (INS-BLOOD-03), "PELIGRO variable
     * según sistema" (INS-BRK-02), "ATENCIÓN o sin clasificar GHS" (INS-FIRE-06). Las otras
     * 6 (ATM-01/02/03/04, BLOOD-01, BRK-01) no traen palabra porque no están clasificadas.
     *
     * NO se usa '' como tercer estado ("conocido: ninguna"): en un VARCHAR es una distinción
     * INVISIBLE que ninguna vista ni query va a respetar. El portador de "clasificado como
     * ninguno" es `ghs_pictograms = []`, y son las mismas 6 filas.
     *
     * TRES SALVEDADES, decididas y anotadas para que el owner las pueda voltear con un
     * UPDATE si no está de acuerdo:
     *  · INS-BLOOD-02 "PELIGRO (spirit gum)" → SE GUARDA PELIGRO aunque su `ghs_pictograms`
     *    quede NULL. No es incoherencia: la palabra es un nivel de SEVERIDAD y mantenerla
     *    alta yerra hacia sobre-avisar (el error seguro); un pictograma es una afirmación
     *    ESPECÍFICA de peligro, y GHS02 "inflamable" sería falso para la mitad silicona de
     *    la fila. La fila merece partirse en dos productos: queda anotado.
     *  · INS-ARM-02 "ATENCIÓN. Riesgo principal: CONFUSIÓN con munición real" → SE GUARDA.
     *    Se valoró dejarlo NULL (ese ATENCIÓN es un aviso operativo, no una palabra GHS, y
     *    el cartucho dummy no tiene clasificación). Se mantiene por COHERENCIA con
     *    INS-MOLD-03, que también dice "no clasificada" y sí conserva su ATENCIÓN: decidir
     *    que una palabra escrita "no es realmente GHS" es CLASIFICAR, que es justo lo que
     *    este seeder no hace. Además yerra hacia avisar, no hacia callar.
     *  · INS-MOLD-03 "ATENCIÓN (baja). Generalmente no clasificada o irritante leve" → se
     *    guarda ATENCIÓN y su pictograma va NULL. La incoherencia es del ORIGEN, no del
     *    seeder: trae palabra y a la vez dice "no clasificada".
     */
    protected function extractSignalWord(array $insumo)
    {
        return $this->promotedFor($insumo['id'])['signal'];
    }

    /**
     * Número CAS. Solo 4 de 41, y NO hay más: se barrieron las 16 secciones de los 41 con
     * /\b\d{2,7}-\d{2}-\d\b/ y con el token literal "CAS". Los demás aciertos de "CAS" son
     * falsos positivos de subcadena ("CASi", "CASos", "CASquillos", "CASera").
     *
     * POR QUÉ VACÍO ES CORRECTO: los otros 37 son MEZCLAS, y una mezcla legítimamente NO
     * tiene un CAS único. Esta columna está condenada a estar casi vacía POR EL DOMINIO.
     * No la "rellenes porque está vacía".
     *
     * DOS MATICES MEDIDOS: el CAS de la glicerina (INS-BLOOD-01) NO está en `1_identificacion`
     * sino en `3_composicion`; los otros 3 están en `1_identificacion`. Un extractor que solo
     * mirara `3_composicion` perdería 3 de 4, y uno que solo mirara `1_identificacion`
     * perdería el de la sangre. Otra razón para el mapa curado.
     */
    protected function extractCasNumber(array $insumo)
    {
        return $this->promotedFor($insumo['id'])['cas'];
    }

    /** Acceso seguro al mapa curado: un insumo desconocido (v1.2) no revienta, va todo NULL. */
    protected function promotedFor($code)
    {
        $map = $this->promotedMap();
        if (!isset($map[$code])) {
            return ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null];
        }
        return array_merge(['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null], $map[$code]);
    }

    /**
     * EL MAPA CURADO — 41 entradas, escritas y revisadas a mano contra la prosa.
     *
     * NO SE GENERA CON REGEX A PROPÓSITO. Ver la cabecera de la clase para las tres
     * pruebas medidas de que un parser miente en silencio aquí ("2009" como número UN, los
     * UN tras diagonal, el "según" que gobierna la clase y no el pictograma).
     *
     * Convención de esta tabla:
     *   'ghs'    => ['GHS02', …] lista firme | [] clasificado-y-ninguno | null desconocido
     *   'un'     => 'UN 1170' | null   (solo si es UNO y no está condicionado)
     *   'signal' => 'PELIGRO' | 'ATENCIÓN' | null
     *   'cas'    => '74-98-6' | null
     * El comentario de cada NULL dice POR QUÉ. Si actualizas el catálogo, revisa esto.
     */
    protected function promotedMap()
    {
        return [
            // ── Combustibles ────────────────────────────────────────────────
            // un: NULL → trae DOS ("UN 1978 Propano… GLP: UN 1075").
            'INS-FIRE-01' => ['ghs' => ['GHS02', 'GHS04'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => '74-98-6'],
            'INS-FIRE-02' => ['ghs' => ['GHS02', 'GHS07'], 'un' => 'UN 1170', 'signal' => 'PELIGRO', 'cas' => '64-17-5'],
            'INS-FIRE-03' => ['ghs' => ['GHS02', 'GHS07'], 'un' => 'UN 1219', 'signal' => 'PELIGRO', 'cas' => '67-63-0'],
            // ghs: NULL → "GHS08 y, SEGÚN GRADO, GHS02". un: NULL → "muchos grados no
            // regulados; otros UN 1223. Verificar" (el caso típico es "no regulado").
            'INS-FIRE-04' => ['ghs' => null, 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // 3 QUIMIOTIPOS en una fila. ghs: NULL → "GHS02 y, según tipo, GHS06/GHS08/GHS07"
            // (quedarse con GHS02 le quita la calavera al metanol). un: NULL → metanol 1230 /
            // etanol 1170. signal: NULL → "PELIGRO según quimiotipo".
            'INS-FIRE-05' => ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null],
            // ghs/signal: NULL → "ATENCIÓN o sin clasificar GHS" se contradice sola.
            'INS-FIRE-06' => ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null],
            // un: NULL → "UN 1256/1268" son DOS (la regex /UN (\d{4})/ solo vería el primero).
            'INS-FIRE-07' => ['ghs' => ['GHS02', 'GHS08'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            'INS-FIRE-08' => ['ghs' => ['GHS02', 'GHS04'], 'un' => 'UN 1011', 'signal' => 'PELIGRO', 'cas' => null],
            'INS-FIRE-09' => ['ghs' => ['GHS02', 'GHS07', 'GHS08', 'GHS09'], 'un' => 'UN 1203', 'signal' => 'PELIGRO', 'cas' => null],
            'INS-FIRE-10' => ['ghs' => ['GHS02', 'GHS07', 'GHS08', 'GHS09'], 'un' => 'UN 1202', 'signal' => 'PELIGRO', 'cas' => null],
            // BANDERA ROJA. ghs: NULL → el texto declara "Sin caracterizar… DESCONOCIDOS".
            // un: NULL → dice "Base UN 1203" y en la MISMA FRASE "la mezcla improvisada NO
            // TIENE clasificación válida de transporte": guardarlo sería una mentira literal.
            'INS-FIRE-11' => ['ghs' => null, 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],

            // ── Atmósfera ───────────────────────────────────────────────────
            // Los 4 de abajo + BLOOD-01 + BRK-01 son los 6 que AFIRMAN "no clasificado":
            // ghs = [] (clasificado y ninguno) y signal = null. Coherentes entre sí.
            'INS-ATM-01' => ['ghs' => [], 'un' => null, 'signal' => null, 'cas' => null],
            'INS-ATM-02' => ['ghs' => [], 'un' => null, 'signal' => null, 'cas' => null],
            // "No clasificado como sustancia en GHS, PERO: ASFIXIANTE SIMPLE" → el UN sí es firme.
            'INS-ATM-03' => ['ghs' => [], 'un' => 'UN 1845', 'signal' => null, 'cas' => null],
            'INS-ATM-04' => ['ghs' => [], 'un' => 'UN 1977', 'signal' => null, 'cas' => null],
            // un: NULL → "UN 1013 (gas) / UN 2187 (líquido refrigerado)" son DOS.
            'INS-ATM-05' => ['ghs' => ['GHS04'], 'un' => null, 'signal' => 'ATENCIÓN', 'cas' => null],

            // ── Sangre y maquillaje ─────────────────────────────────────────
            // cas: el ÚNICO que vive en `3_composicion`, no en `1_identificacion`.
            'INS-BLOOD-01' => ['ghs' => [], 'un' => null, 'signal' => null, 'cas' => '56-81-5'],
            // DOS PRODUCTOS en una fila (spirit gum / silicona). ghs: NULL → los códigos son
            // del primero. signal: PELIGRO → se conserva; ver la salvedad en extractSignalWord().
            'INS-BLOOD-02' => ['ghs' => null, 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // "ATENCIÓN/PELIGRO según base" → condicional en ambos.
            'INS-BLOOD-03' => ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null],

            // ── Breakaway ───────────────────────────────────────────────────
            // "Producto terminado NO clasificado en GHS. Riesgo REAL: QUEMADURA por azúcar
            // fundida" → el peligro es real pero no es GHS.
            'INS-BRK-01' => ['ghs' => [], 'un' => null, 'signal' => null, 'cas' => null],
            // "PELIGRO variable según sistema": poliéster/estireno, uretano, acrílico → 3 sistemas.
            'INS-BRK-02' => ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null],

            // ── Pirotecnia ──────────────────────────────────────────────────
            // Los 7 dicen literal "PELIGRO. Pictograma GHS01 (bomba explotando)": FIRME. El
            // "según tipo/artículo/dispositivo" de varios gobierna la CLASE DE TRANSPORTE,
            // NO el pictograma — de ahí que ghs sea firme y `un` no.
            'INS-PYR-01' => ['ghs' => ['GHS01'], 'un' => 'UN 0027', 'signal' => 'PELIGRO', 'cas' => null],
            // un: NULL → 0509 / 0160 / 0161 según tipo.
            'INS-PYR-02' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // un: FIRME (UN 0305); lo que varía es la CLASE ("algunas mezclas son 1.1").
            'INS-PYR-03' => ['ghs' => ['GHS01'], 'un' => 'UN 0305', 'signal' => 'PELIGRO', 'cas' => null],
            // un: NULL → 0454 / 0314 / 0315 / 0325.
            'INS-PYR-04' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // un: NULL → mecha 0105 / quickmatch 0101.
            'INS-PYR-05' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // un: NULL → 0507 / 0313 según artículo. (type=pyro, NO smoke: es explosivo.)
            'INS-PYR-06' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // un: NULL → SÍ es explosivo regulado (1.3G/1.4G/1.4S) pero el texto NO da UN.
            'INS-PYR-07' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],

            // ── Armas ───────────────────────────────────────────────────────
            // un: NULL → "UN 0014/0326/0327/0338" son CUATRO tras diagonal sin prefijo.
            'INS-ARM-01' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // Cartucho DUMMY. ghs: NULL → su peligro ("CONFUSIÓN con munición real") NO es GHS.
            // signal: ATENCIÓN → se conserva; ver la salvedad en extractSignalWord().
            'INS-ARM-02' => ['ghs' => null, 'un' => null, 'signal' => 'ATENCIÓN', 'cas' => null],
            // El ID dice ARM pero la familia dice pirotecnia → type=pyro. Manda la familia.
            // un: NULL → CINCO (0044/0377/0378/0319/0320); en texto son 27 chars > VARCHAR(20).
            'INS-ARM-03' => ['ghs' => ['GHS01'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],

            // ── Moldeo/colado ───────────────────────────────────────────────
            // un: NULL → "MDI polimérico usualmente no regulado; TDI PUEDE ir como UN 2078".
            'INS-MOLD-01' => ['ghs' => ['GHS07', 'GHS08'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // ghs: NULL → "GHS07, GHS09 (y GHS05 EN ENDURECEDORES AMÍNICOS)". signal: NULL →
            // "ATENCIÓN/PELIGRO" sin decidirse.
            'INS-MOLD-02' => ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null],
            // Trae palabra Y dice "no clasificada": incoherencia del ORIGEN. Ver la salvedad.
            'INS-MOLD-03' => ['ghs' => null, 'un' => null, 'signal' => 'ATENCIÓN', 'cas' => null],

            // ── Resto ───────────────────────────────────────────────────────
            // ghs: NULL → trae H319 pero NINGÚN pictograma; derivarlo sería clasificar.
            'INS-FOAM-01' => ['ghs' => null, 'un' => null, 'signal' => 'ATENCIÓN', 'cas' => null],
            // ghs: NULL → sin frase H ni pictograma.
            'INS-SNOW-01' => ['ghs' => null, 'un' => null, 'signal' => 'ATENCIÓN', 'cas' => null],
            // "ATENCIÓN/PELIGRO SEGÚN SÍLICE… SI CONTIENE SÍLICE… H350" → condicional en ambos.
            'INS-DUST-01' => ['ghs' => null, 'un' => null, 'signal' => null, 'cas' => null],
            // ghs: NULL → "H319/H315…; algunos boratos… VERIFICAR".
            'INS-RET-01' => ['ghs' => null, 'un' => null, 'signal' => 'ATENCIÓN', 'cas' => null],
            // ghs: NULL → los códigos H que cita son de OTRAS sustancias (acetileno, argón),
            // no del humo. un: NULL → "Gases: Clase 2 (ver UN por gas)" — regulado, sin UN.
            'INS-WELD-01' => ['ghs' => null, 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // un: NULL → "UN 1352 / UN 2546, Clase 4.1/4.2 según forma". (type=other, NO pyro.)
            'INS-SPARK-01' => ['ghs' => ['GHS02'], 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
            // ghs: NULL → "GHS02/GHS08 SEGÚN CELDA": ningún subconjunto es firme.
            // un: NULL → 3480 (ion) / 3481 (contenida en equipo).
            'INS-BATT-01' => ['ghs' => null, 'un' => null, 'signal' => 'PELIGRO', 'cas' => null],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // (f) N:M → consumable_sfx_effect_type
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vincula los 56 pares efecto↔insumo del documento.
     *
     * DECISIÓN — sync(), NO syncWithoutDetaching(). El JSON es la ÚNICA fuente del grafo:
     * se comprobó que NADA fuera de los modelos escribe el pivote (cero controladores,
     * rutas o vistas lo tocan), así que sync() no puede pisar trabajo de un humano —
     * hoy no existe forma de crearlo. Y sí puede hacer algo que syncWithoutDetaching()
     * nunca haría: RETRACTAR. Si una v1.2 quita un vínculo porque era erróneo o inseguro,
     * sync() lo borra; syncWithoutDetaching() lo dejaría vivo para siempre, y un vínculo
     * "este efecto usa este insumo" que sobrevive a su propia corrección es una afirmación
     * falsa en una app de cumplimiento. Es lo que debe pasar en un IMPORTADOR de catálogo.
     * sync() además está ACOTADO: por cada efecto solo toca SUS vínculos; los de efectos
     * ajenos al JSON no se rozan.
     * REVISAR ESTO si el 4d llega a permitir editar el pivote a mano: entonces el seeder
     * pasaría a competir con un humano y la respuesta cambiaría.
     *
     * 5 efectos (FX-09/10/14/17/19) no tienen insumo POR DISEÑO: son mecánicos. Reciben
     * sync([]) → lista vacía, no un vínculo huérfano.
     *
     * Recibe `$insumos` PORQUE EL ENSAYO TIENE QUE PODER FALLAR: sin la lista del JSON no hay
     * nada contra qué comprobar un ref en dry-run, y el chequeo se vuelve una ceremonia que
     * siempre dice que sí (ver el bloque del dry-run más abajo).
     */
    public function linkGraph(array $efectos, array $insumos)
    {
        // Resolución en UNA query, no una por ref.
        $refs = [];
        foreach ($efectos as $e) {
            foreach ((isset($e['insumos_ref']) ? $e['insumos_ref'] : []) as $r) {
                $refs[$r] = true;
            }
        }

        $codeToId = Consumable::whereIn('code', array_keys($refs))->pluck('id', 'code')->toArray();

        // En dry-run los insumos NO se escribieron, así que la BD todavía no los tiene y TODOS
        // los refs parecerían rotos. Se simula lo que la corrida real habría dejado: un ref
        // resuelve si su código VIENE EN EL JSON (donde se acaba de "importar") — o si ya
        // estaba en la BD, que es lo que ya trae $codeToId.
        //
        // EL `isset($jsonCodes[$code])` ES EL ENSAYO ENTERO, no una guarda de más. Marcar -1 a
        // ciegas —sin mirar el JSON— declara resoluble CUALQUIER ref, incluida una que no
        // existe en ningún lado; y como en BD limpia (el caso de PRODUCCIÓN) no hay ni una,
        // el reporte juraría "grafo íntegro" siempre. Un ensayo incapaz de fallar no es un
        // ensayo: es una firma en blanco. Un ref que no está ni en la BD ni en el JSON cae
        // abajo en $unresolved y SALE en el reporte, que es justo para lo que se corre esto.
        if ($this->dryRun) {
            $jsonCodes = [];
            foreach ($insumos as $i) {
                if (isset($i['id'])) {
                    $jsonCodes[$i['id']] = true;
                }
            }

            foreach ($refs as $code => $_) {
                if (!isset($codeToId[$code]) && isset($jsonCodes[$code])) {
                    $codeToId[$code] = -1; // marcador: resolvería tras el import, pero aún no hay id real
                }
            }
        }

        $effectIds = SfxEffectType::whereIn('code', array_map(function ($e) {
            return $e['id'];
        }, $efectos))->pluck('id', 'code')->toArray();

        foreach ($efectos as $e) {
            $ids = [];
            foreach ((isset($e['insumos_ref']) ? $e['insumos_ref'] : []) as $ref) {
                // "Declarado" = lo que el JSON PIDE vincular. Se cuenta antes de resolver para
                // que el reporte pueda contrastar lo pedido contra lo logrado sin literales.
                $this->stats['refs_declared']++;

                if (!isset($codeToId[$ref])) {
                    // (f) Ref que no resuelve: SE REPORTA Y SE SIGUE. No aborta: un vínculo
                    // perdido es un dato menos, pero abortar dejaría el catálogo entero fuera.
                    $this->unresolved[] = $e['id'].' → '.$ref;
                    $this->stats['refs_broken']++;
                    continue;
                }
                $ids[] = $codeToId[$ref];
                $this->stats['links']++;
            }

            if ($this->dryRun) {
                continue;
            }

            if (!isset($effectIds[$e['id']])) {
                $this->unresolved[] = 'efecto no encontrado tras el import: '.$e['id'];
                continue;
            }

            $effect = SfxEffectType::find($effectIds[$e['id']]);
            $result = $effect->consumables()->sync($ids);
            $this->stats['links_detached'] += count($result['detached']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // (g) SOLAPAMIENTO CON LAS 12 FICHAS LEGACY — se REPORTA, no se decide
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Busca posibles duplicados entre las fichas legacy (`code` NULL) y los 41 del catálogo.
     *
     * EL SEEDER NO TOCA NI UNA: no las borra, no las fusiona, no las marca. Solo REPORTA
     * candidatos con su score para que el OWNER decida. Fusionar fichas de seguridad es una
     * decisión de dominio, y un seed jamás la toma solo.
     *
     * POR QUÉ HEURÍSTICO Y NO EXACTO: hoy hay CERO coincidencias exactas de nombre. El
     * solapamiento es SEMÁNTICO — la BD dice "CO₂ / hielo seco" y el JSON "Hielo seco (CO2
     * sólido)". Un match ingenuo reportaría 0 y sería inútil.
     *
     * EL SCORE ES HÍBRIDO Y ESO IMPORTA (medido): similar_text() a solas RANQUEA MAL en 2 de
     * los 12 casos reales — pone "Nieve carbónica" (53.7%) por ENCIMA del verdadero
     * INS-SNOW-01 (47.8%) para "Nieve artificial (celulosa)", y "Sangre de UTILERÍA" (62.5%)
     * por encima de los combustibles para "Gasolina/diésel de UTILERÍA": se deja engañar por
     * subcadenas compartidas. Mezclando 50% similar_text + 50% solape de TOKENS
     * significativos, el candidato correcto sube al #1 en los 10 casos que tienen pareja real,
     * y los 2 que no la tienen ("Solventes/thinner") siguen sin candidatos.
     *
     * Se compara contra el `nombre` Y contra los `sinonimos` (el bote suele decir otra cosa).
     * SIGUE SIENDO UNA HEURÍSTICA: el reporte lo dice y el número está a la vista.
     */
    protected function reportLegacyOverlap(array $insumos)
    {
        $legacy = Consumable::whereNull('code')->get(['id', 'name']);
        if ($legacy->isEmpty()) {
            return;
        }

        $this->command->info('');
        $this->command->info('── POSIBLES SOLAPAMIENTOS CON LAS FICHAS LEGACY (code NULL) ──');
        $this->command->warn('   HEURÍSTICO: son CANDIDATOS, no un veredicto. El seeder NO tocó ninguna ficha legacy.');
        $this->command->warn('   Decide tú si alguna se fusiona; score = 50% similitud de texto + 50% solape de tokens.');

        $totalCandidates = 0;

        foreach ($legacy as $row) {
            $normLegacy = $this->normalize($row->name);
            $scored = [];

            foreach ($insumos as $i) {
                $candidates = [$i['nombre']];
                if (isset($i['sinonimos']) && is_array($i['sinonimos'])) {
                    $candidates = array_merge($candidates, $i['sinonimos']);
                }

                $best = 0.0;
                $via = '';
                foreach ($candidates as $c) {
                    $s = $this->similarity($normLegacy, $this->normalize($c));
                    if ($s > $best) {
                        $best = $s;
                        $via = $c;
                    }
                }
                $scored[] = ['score' => $best, 'code' => $i['id'], 'via' => $via];
            }

            usort($scored, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            $hits = [];
            foreach (array_slice($scored, 0, self::OVERLAP_TOP_N) as $s) {
                if ($s['score'] >= self::OVERLAP_THRESHOLD) {
                    $hits[] = $s;
                }
            }

            if (empty($hits)) {
                $this->command->line(sprintf('   #%-3d %-38s → sin candidatos ≥%d', $row->id, $this->truncate($row->name, 38), self::OVERLAP_THRESHOLD));
                continue;
            }

            $this->command->line(sprintf('   #%-3d %s', $row->id, $row->name));
            foreach ($hits as $h) {
                $totalCandidates++;
                $this->command->line(sprintf('        %5.1f  %-13s  ← "%s"', $h['score'], $h['code'], $this->truncate($h['via'], 46)));
            }
        }

        $this->command->info(sprintf('   Total de candidatos ≥%d: %d (de %d fichas legacy).', self::OVERLAP_THRESHOLD, $totalCandidates, $legacy->count()));
    }

    /** Normaliza para comparar: minúsculas, sin acentos, sin puntuación, espacios colapsados. */
    protected function normalize($s)
    {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $s = strtr($s, self::ACCENT_MAP);
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Tokens significativos: ≥4 chars y fuera de la lista de palabras vacías del dominio. */
    protected function tokens($normalized)
    {
        $out = array_filter(explode(' ', $normalized), function ($w) {
            return strlen($w) >= 4 && !in_array($w, self::OVERLAP_STOPWORDS, true);
        });
        return array_values(array_unique($out));
    }

    /** Score híbrido 0-100 (ver reportLegacyOverlap() para por qué no vale similar_text solo). */
    protected function similarity($a, $b)
    {
        similar_text($a, $b, $percent);

        $ta = $this->tokens($a);
        $tb = $this->tokens($b);
        $shared = array_intersect($ta, $tb);
        $overlap = (count($ta) > 0 && count($tb) > 0)
            ? count($shared) / min(count($ta), count($tb))
            : 0;

        return 0.5 * $percent + 0.5 * ($overlap * 100);
    }

    /** Recorta para que el reporte no se desmadre en ancho. */
    protected function truncate($s, $max)
    {
        $s = (string) $s;
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // (h) REPORTE
    // ─────────────────────────────────────────────────────────────────────────

    /** Reporte final. Se imprime en AMBOS modos (ensayo y real). */
    protected function report(array $data)
    {
        $verb = $this->dryRun ? 'se crearían' : 'creados';
        $verb2 = $this->dryRun ? 'se actualizarían' : 'actualizados';

        $this->command->info('');
        $this->command->info('══════════ SpfxCatalogSeeder — '.($this->dryRun ? 'ENSAYO (no se escribió nada)' : 'CORRIDA REAL').' ══════════');
        $this->command->info('  Fuente: '.self::DATA_FILE.'  (meta.version '.(isset($data['meta']['version']) ? $data['meta']['version'] : '?').')');
        $this->command->info('');

        $this->command->info(sprintf('  EFECTOS (sfx_effect_types): %d %s, %d %s',
            $this->stats['effects_created'], $verb, $this->stats['effects_updated'], $verb2));
        $this->command->info(sprintf('     verificados de origen: %d  ·  pendientes (verified_at NULL, badge ámbar): %d',
            $this->stats['effects_verified'], $this->stats['effects_pending']));

        $this->command->info(sprintf('  INSUMOS (consumables):      %d %s, %d %s',
            $this->stats['consumables_created'], $verb, $this->stats['consumables_updated'], $verb2));
        $this->command->info(sprintf('     verificados de origen: %d  ·  pendientes: %d',
            $this->stats['consumables_verified'], $this->stats['consumables_pending']));

        // Chivato SECUNDARIO de que se leyó el archivo correcto (ver la cabecera). Ya no es
        // quien para la v1.0 —eso lo hace el fail-fast de `meta.auditoria_hds` en
        // loadCatalog(), antes de escribir nada—: esto corre después del commit y solo queda
        // como red para el caso raro de un archivo con el bloque pegado y contenido viejo.
        if ($this->stats['consumables_verified'] === 32 && $this->stats['consumables_pending'] === 9) {
            $this->command->info('     ✓ 32/9 = la v1.1 correcta (la v1.0 daría 29/12).');
        } elseif ($this->stats['consumables_verified'] === 29 && $this->stats['consumables_pending'] === 12) {
            $this->command->error('     ✗ 29/12 = ¡estás sembrando la v1.0! Trae las HDS CRUZADAS. Revisa el archivo de datos.');
        }

        $this->command->info(sprintf('  VÍNCULOS N:M:               %d', $this->stats['links']));
        if (!$this->dryRun && $this->stats['links_detached'] > 0) {
            $this->command->warn(sprintf('     %d vínculo(s) RETIRADO(S) por sync(): estaban en la BD y ya no en el catálogo.',
                $this->stats['links_detached']));
        }
        $this->command->info('     (5 efectos mecánicos —FX-09/10/14/17/19— no llevan insumo POR DISEÑO.)');

        if ($this->stats['human_seals_kept'] > 0) {
            $this->command->warn(sprintf('  SELLOS HUMANOS RESPETADOS: %d ficha(s) con verified_by_id → no se tocó su verificación.',
                $this->stats['human_seals_kept']));
        }

        // Re-abrir una ficha es un CAMBIO DE ESTADO: sale SIEMPRE que ocurra, con su cuenta.
        // Que pasara en silencio sería tan malo como el sello mentiroso que esto corrige.
        if ($this->stats['seals_reopened'] > 0) {
            $this->command->warn(sprintf('  SELLOS HUMANOS INVALIDADOS: %d ficha(s) %s a PENDIENTE DE VERIFICACIÓN.',
                $this->stats['seals_reopened'], $this->dryRun ? 'volverían' : 'volvieron'));
            $this->command->warn('     Motivo: el catálogo cambia un campo MATERIAL que su verificador había avalado, así que');
            $this->command->warn('     el sello dejaría de corresponder al contenido y se retira (verified_by_id → NULL).');
            $this->command->warn('     Quedan con el badge ámbar: que un `sds.manage` las revise. (Ver resolveVerification().)');
        }

        // (h) refs no resueltas. NINGÚN número de aquí es un literal: todos salen de los
        // contadores de linkGraph(). Antes esta línea juraba "56 ida / 56 vuelta" hardcodeado
        // y se contradecía con el conteo de vínculos impreso tres líneas más arriba.
        $this->command->info('');
        if (empty($this->unresolved)) {
            $this->command->info(sprintf('  Refs INS-* no resueltas: %d (grafo íntegro: %d declaradas en el JSON / %d resueltas / %d rotas).',
                $this->stats['refs_broken'], $this->stats['refs_declared'], $this->stats['links'], $this->stats['refs_broken']));
        } else {
            $this->command->warn(sprintf('  INCIDENCIAS DEL GRAFO: %d (se reportan y se sigue; NO abortan)', count($this->unresolved)));
            $this->command->warn(sprintf('     refs: %d declaradas en el JSON / %d resueltas / %d ROTAS',
                $this->stats['refs_declared'], $this->stats['links'], $this->stats['refs_broken']));
            foreach ($this->unresolved as $u) {
                $this->command->warn('     · '.$u);
            }
        }

        // (h) columnas promovidas: pobladas vs NULL, CON EL PORQUÉ
        $this->reportPromoted($data['insumos']);

        // (g) solapamiento legacy
        $this->reportLegacyOverlap($data['insumos']);

        $this->command->info('');
        if ($this->dryRun) {
            $this->command->warn('  ENSAYO TERMINADO. No se escribió NADA.');
            $this->command->warn('  Para la corrida real: limpia SPFX_DRY_RUN y corre `php artisan db:seed --class=SpfxCatalogSeeder`.');
            $this->command->warn('  (PowerShell: Remove-Item Env:SPFX_DRY_RUN — si no, seguirá siendo un ensayo.)');
        } else {
            $this->command->info('  Import terminado.');
        }
    }

    /** (h) Cuántas columnas promovidas quedaron pobladas y por qué las otras van NULL. */
    protected function reportPromoted(array $insumos)
    {
        $ghsList = 0;
        $ghsEmpty = 0;
        $ghsNull = 0;
        $un = 0;
        $signal = 0;
        $cas = 0;

        foreach ($insumos as $i) {
            $p = $this->promotedFor($i['id']);

            if (is_array($p['ghs']) && count($p['ghs']) > 0) {
                $ghsList++;
            } elseif (is_array($p['ghs'])) {
                $ghsEmpty++;
            } else {
                $ghsNull++;
            }

            if ($p['un'] !== null) {
                $un++;
            }
            if ($p['signal'] !== null) {
                $signal++;
            }
            if ($p['cas'] !== null) {
                $cas++;
            }
        }

        $total = count($insumos);

        $this->command->info('');
        $this->command->info('── COLUMNAS PROMOVIDAS (atajos de lista/búsqueda; el texto íntegro vive en sds_sections) ──');
        $this->command->info(sprintf('  ghs_pictograms : %2d lista firme · %2d `[]` · %2d NULL   (de %d)', $ghsList, $ghsEmpty, $ghsNull, $total));
        $this->command->line('       `[]` = el documento AFIRMA "no clasificado en GHS" → clasificado y ninguno. Es un DATO.');
        $this->command->line('       NULL = desconocido: 5 traen códigos CONDICIONADOS (guardarlos sub-avisaría) y 11 no traen.');
        $this->command->info(sprintf('  un_number      : %2d poblado  · %2d NULL   (de %d)', $un, $total - $un, $total));
        $this->command->line('       NULL = 12 traen VARIOS UN (no cabe ni "es" uno), 3 condicionados, 17 sin UN.');
        $this->command->info(sprintf('  signal_word    : %2d poblado  · %2d NULL   (de %d)', $signal, $total - $signal, $total));
        $this->command->line('       NULL = 6 condicionales ("ATENCIÓN/PELIGRO según base") + 6 sin clasificar.');
        $this->command->info(sprintf('  cas_number     : %2d poblado  · %2d NULL   (de %d)', $cas, $total - $cas, $total));
        $this->command->line('       NULL = el resto son MEZCLAS y una mezcla NO tiene CAS único. Vacío es CORRECTO.');
        $this->command->line('       Ante ambigüedad → NULL: un NULL manda a leer la sección; un dato inventado miente.');
    }
}
