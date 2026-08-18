<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * SfxEffectType — Catálogo de TIPOS de efecto especial (Capa A, Pilar 3, Paso 4a).
 *
 * Cada fila es doctrina reutilizable sobre un tipo de efecto ("humo atmosférico",
 * "bola de fuego", "salvas"): su definición, variantes, riesgo principal, control
 * base, EPP, personal certificado y una foto de la normativa aplicable.
 *
 * ── NO CONFUNDIR CON SfxEvent ────────────────────────────────────────────────
 * `SfxEvent` es la BITÁCORA de disparos EN VIVO: una instancia real en set, con
 * toggle iniciar/detener (started_at / ended_at) e inyección al DSR del día. Esto
 * de aquí es el CATÁLOGO de tipos: no ocurre, no se dispara, no tiene fecha. Los
 * nombres se parecen a una letra de distancia; el eje es "tipo" vs "instancia".
 *
 * ── EL EJE DE CLASIFICACIÓN ──────────────────────────────────────────────────
 * `family` clasifica el EFECTO ........... QUÉ se hace.  (Capa A, este modelo)
 * `Consumable::typeLabels()` clasifica el MATERIAL ... CON QUÉ se hace. (Capa B)
 * Comparten léxico (Humo / Fuego / Pirotecnia) pero responden preguntas distintas:
 * un mismo insumo sirve a varios efectos y un efecto usa varios insumos — de ahí
 * que la relación sea N:M (`consumable_sfx_effect_type`) y no una columna.
 *
 * ── LOS DOS JSON NORMATIVOS COMPARTEN EJE: JURISDICCIÓN ──────────────────────
 * `standards_snapshot` → {csatf, eeuu_ca, mexico, eeuu_fed}: eje JURISDICCIÓN, el
 *     MISMO que `certified_personnel` (no el organismo emisor). Es el vocabulario
 *     del documento fuente del owner: sus 25 efectos traen las tres primeras claves
 *     25/25, sin excepción. `csatf` = boletines CSATF, referencia CONTRACTUAL (los
 *     estudios de EEUU los exigen por contrato aunque la producción esté bajo
 *     jurisdicción mexicana); `eeuu_ca` = California (Cal-OSHA Título 8, Fire Code,
 *     SB 132, permisos AHJ); `mexico` = ley local (NOM de la STPS, SEDENA, Ley
 *     Federal de Armas de Fuego y Explosivos, Protección Civil); `eeuu_fed` =
 *     federal de EEUU (OSHA 29 CFR...), ACEPTADA pero HOY SIN DATOS — solo aparece
 *     en los estándares transversales del documento y ningún efecto la usa todavía;
 *     verla vacía NO es un bug. Valores siempre en LISTA, nunca escalar.
 *     NO hay claves `osha`/`stps`/`general`: son del otro eje (el badge de
 *     `safety_standards`) y cero efectos las traen.
 * `certified_personnel` → {eeuu_ca, mexico}: MISMO eje, encaje 25/25. Una licencia
 *     de pirotecnia en California la emite el State Fire Marshal; la de México, la
 *     SEDENA. Por eso las claves son territorios, no organismos.
 *
 * EL SNAPSHOT SIGUE SIENDO LA FUENTE AUTORITATIVA de la normativa del efecto: es más
 * rico que el catálogo (estructura por jurisdicción, cita Fire Code / SB 132 / SEDENA
 * / NFPA / Fact Sheets CSATF que NO existen como norma canónica). NUNCA se borra.
 *
 * ── PUENTE AL CATÁLOGO CANÓNICO (2026-07-18, Paso 5b) ────────────────────────
 * ANTES esta ficha documentaba que el owner RECHAZÓ pivotar a `safety_standards`
 * ("estas normas son de los SDS, no tendrían por qué estar en los catálogos"). El
 * owner SUPERÓ esa decisión el 2026-07-18, con un matiz que respeta su espíritu:
 * `standards()` (belongsToMany vía `effect_standard`) REFERENCIA por ID las normas
 * canónicas que Pieza 2+3 YA creó, cuando el código del snapshot empareja limpio
 * (CSATF #N → Bulletin #N, NOM-…-STPS-…, 29 CFR …). NO inserta filas SPFX en
 * `safety_standards` (no lo coloniza): lo que no empareja se queda SOLO en el
 * snapshot y el EffectStandardBridgeSeeder lo LOGuea como no-resuelto. El puente es
 * ADITIVO — el snapshot manda; el pivote es el índice cruzado por ID.
 * DEFENSIVO: toda ejecución del puente va detrás de self::supportsStandardsBridge().
 *
 * DEFENSIVO: la Capa A puede no existir en una instancia sin el SQL aplicado →
 * toda lectura va detrás de self::isAvailable(). Ver
 * database/owner-apply/2026-07-16-sfx-effect-types.sql.
 */
class SfxEffectType extends Model
{
    protected $table = 'sfx_effect_types';

    protected $fillable = [
        'code',
        'family',
        'name',
        'image_path',
        'definition',
        'variants',
        'main_risk',
        'base_control',
        'required_ppe',
        'certified_personnel',
        'standards_snapshot',
        'notes',
        'sort_order',
        'is_active',
        'verified_at',
        'verified_by_id',
    ];

    /**
     * Los 4 JSON se castean a 'array' (convención del repo; cero 'json'/'collection').
     * Se asignan como ARRAY de PHP: el cast serializa UNA sola vez — NO usar
     * json_encode() al guardar (mismo criterio que ScoutingReport).
     */
    protected $casts = [
        'variants'            => 'array',
        'required_ppe'        => 'array',
        'certified_personnel' => 'array',
        'standards_snapshot'  => 'array',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
        'verified_at'         => 'datetime',
    ];

    // NOTA: aquí NO hay un catálogo cerrado de familias. Las familias las trae el
    // documento fuente del owner en el Paso 4c; hoy no se conocen y no se inventan.
    // `family` es VARCHAR libre hasta entonces.

    /**
     * Insumos/SDS que usa este tipo de efecto (N:M con la Capa B). Los 4 argumentos
     * van EXPLÍCITOS (patrón del repo). El 4d debe comprobar isAvailable() antes de
     * ejecutarla (load/with/sync).
     */
    public function consumables()
    {
        return $this->belongsToMany(
            Consumable::class,
            'consumable_sfx_effect_type',
            'sfx_effect_type_id',
            'consumable_id'
        )->withTimestamps();
    }

    /**
     * Normas canónicas del catálogo `safety_standards` ligadas por ID a este efecto
     * (N:M vía `effect_standard`, Paso 5b). Índice cruzado del `standards_snapshot`:
     * solo contiene las citas que emparejaron limpio con el catálogo; las demás viven
     * únicamente en el snapshot. Los 4 argumentos van EXPLÍCITOS (patrón del repo).
     *
     * DEFENSIVO: declararla es perezoso y no truena, pero EJECUTARLA (load/with/sync)
     * contra una BD sin el delta 2026-07-18-effect-standard-bridge.sql sí → todo uso
     * debe ir detrás de self::supportsStandardsBridge().
     */
    public function standards()
    {
        return $this->belongsToMany(
            SafetyStandard::class,
            'effect_standard',
            'sfx_effect_type_id',
            'safety_standard_id'
        )->withTimestamps();
    }

    /** Autoridad que validó la ficha (sin FK dura; null si nunca se verificó). */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /**
     * Ficha ya validada por una autoridad. Sin memo de columnas (a diferencia de
     * Consumable): esta tabla NACE con verified_at/verified_by_id, así que si la
     * tabla existe, sus columnas existen. La semántica es la del Paso 1c;
     * el andamio de aquel no hace falta.
     */
    public function isVerified()
    {
        return $this->verified_at !== null;
    }

    /**
     * URL de la imagen principal del tipo de efecto (referencia visual), o null si aún no se
     * sube (la UI pinta un mono-icono). La ruta se guarda como la devuelve ImageCompressor::store()
     * sobre el disco 'public'. Degrade-safe: sin la columna `image_path`, Eloquent lee null.
     */
    public function imageUrl(): ?string
    {
        $p = trim((string) $this->image_path);
        return $p !== '' ? \Illuminate\Support\Facades\Storage::url($p) : null;
    }

    /**
     * Solo tipos de efecto activos.
     * NOTA: el spec original lo llamaba `activos()`, pero su modelo hermano
     * `Consumable` ya expone `scopeActive()` — se usa el mismo nombre por coherencia.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Solo fichas pendientes de verificar (verified_at NULL = pendiente). */
    public function scopePending($query)
    {
        return $query->whereNull('verified_at');
    }

    /** Memo de disponibilidad de la Capa A en BD (ver isAvailable()). */
    protected static $tableAvailable = null;

    /**
     * ¿Existe la Capa A en esta instancia? (prod sin el SQL aplicado ⇒ no-op seguro).
     *
     * Memo estático porque una lista iteraría filas y cada Schema::hasTable lanza una
     * consulta al information_schema; sin el memo pagaríamos una por fila.
     *
     * El Paso 4d DEBE llamarlo antes de cualquier load() / with() / sync() sobre la
     * Capa A o su puente: declarar el belongsToMany es perezoso y no truena, pero
     * EJECUTARLO contra tablas inexistentes sí.
     */
    public static function isAvailable()
    {
        if (self::$tableAvailable === null) {
            self::$tableAvailable = Schema::hasTable('sfx_effect_types')
                && Schema::hasTable('consumable_sfx_effect_type');
        }
        return self::$tableAvailable;
    }

    /** Memo de disponibilidad del puente a normas (ver supportsStandardsBridge()). */
    protected static $standardsBridgeAvailable = null;

    /**
     * ¿Existe el puente `effect_standard` (Paso 5b) en esta instancia? Guard aparte de
     * isAvailable(): la Capa A puede estar aplicada sin el puente (delta posterior e
     * independiente). Todo load/with/sync de standards() debe pasar por aquí. Memo
     * estático por la misma razón que isAvailable(): evitar una consulta a
     * information_schema por fila al iterar una lista.
     */
    public static function supportsStandardsBridge()
    {
        if (self::$standardsBridgeAvailable === null) {
            self::$standardsBridgeAvailable = Schema::hasTable('effect_standard');
        }
        return self::$standardsBridgeAvailable;
    }

    /**
     * Cinturón y tirantes: al borrar un tipo de efecto se purgan sus vínculos con
     * insumos. Con la FK dura del SQL puesta esto es un no-op (el motor ya hizo el
     * CASCADE), pero cubre a quien haya usado la SALIDA DE EMERGENCIA del delta
     * (quitar los CONSTRAINT y dejar las KEY) y perdería el cascade sin enterarse.
     * Mismo patrón que HazardEvent con `hazard_event_standard`.
     */
    protected static function booted()
    {
        static::deleting(function ($effectType) {
            if (Schema::hasTable('consumable_sfx_effect_type')) {
                DB::table('consumable_sfx_effect_type')
                    ->where('sfx_effect_type_id', $effectType->id)
                    ->delete();
            }
            // Puente a normas (Paso 5b): purga los vínculos al borrar el efecto. Con
            // la FK dura del delta puesta esto es no-op (el motor ya hizo el CASCADE);
            // cubre a quien haya usado la SALIDA DE EMERGENCIA del SQL (quitar los
            // CONSTRAINT y dejar las KEY) y perdería el cascade sin enterarse.
            if (Schema::hasTable('effect_standard')) {
                DB::table('effect_standard')
                    ->where('sfx_effect_type_id', $effectType->id)
                    ->delete();
            }
        });
    }
}
