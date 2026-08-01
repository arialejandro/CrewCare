<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Consumable — Catálogo de SDS/consumibles de efectos especiales (Pilar 3, flag 'sds_sfx').
 *
 * Cada fila es una "hoja de datos de seguridad" (SDS) resumida de un material que se
 * consume en set: humo, haze, fuego, salvas, combustibles, pirotecnia, criogénicos, etc.
 * El objetivo es que el Safety tenga a la mano peligros, precauciones y palabra de
 * advertencia antes de disparar un efecto (SfxEvent). DEFENSIVO: la tabla va detrás de
 * Schema::hasTable() en los controladores; el modelo solo declara el contrato de columnas.
 */
class Consumable extends Model
{
    /**
     * Soft delete DEFENSIVO (Paso 1b, 2026-07-17). El trait aporta el global scope
     * (oculta las fichas retiradas de TODA consulta y de todos los roles) y las macros
     * withTrashed()/onlyTrashed()/restore()/forceDelete(). Pero DOS de sus piezas tocan
     * la columna `deleted_at` y tronarían en una BD que aún no aplicó el delta
     * 2026-07-17-consumables-soft-delete.sql (p.ej. producción antes de que el owner lo
     * corra), rompiendo el módulo entero:
     *   · bootSoftDeletes()      → registra el global scope (añade WHERE deleted_at IS NULL).
     *   · performDeleteOnModel() → convierte delete() en un UPDATE `deleted_at`=NOW().
     * Las dos se gatean con supportsSoftDelete(): SIN la columna, el módulo se comporta
     * como SIEMPRE (borrado DURO), sin tronar; CON la columna, delete() retira (soft) y
     * aparece la papelera del super-admin. Es el mismo patrón defensivo que
     * supportsVerification()/supportsExtendedSds() usan para sus deltas.
     *
     * El truco: se ALIASAN los dos métodos del trait a un nombre privado para poder
     * llamarlos condicionalmente, y la clase RE-DECLARA los originales (PHP deja que el
     * método de la clase gane al del trait sin conflicto).
     */
    use SoftDeletes {
        bootSoftDeletes      as private bootSoftDeletesTrait;
        performDeleteOnModel as private performSoftDeleteOnModel;
    }

    /** Memo del soporte de soft delete en BD (ver supportsSoftDelete()). */
    protected static $softDeleteSupported = null;

    /**
     * ¿La BD tiene ya la columna `deleted_at` (delta 2026-07-17-consumables-soft-delete.sql)?
     * Memo estático por el mismo motivo que las otras supportsX(): la lista itera muchas
     * filas y cada Schema::hasColumn consulta el information_schema.
     */
    public static function supportsSoftDelete()
    {
        if (self::$softDeleteSupported === null) {
            self::$softDeleteSupported = Schema::hasColumn('consumables', 'deleted_at');
        }
        return self::$softDeleteSupported;
    }

    /** Solo registra el global scope de SoftDeletes si la columna existe (ver arriba). */
    public static function bootSoftDeletes()
    {
        if (self::supportsSoftDelete()) {
            static::bootSoftDeletesTrait();
        }
    }

    /**
     * delete() en modo defensivo: retiro (soft) si la BD lo soporta; si no, borrado DURO
     * idéntico al del Model base (para no depender de una columna que no existe).
     *
     * @return void
     */
    protected function performDeleteOnModel()
    {
        if (self::supportsSoftDelete()) {
            $this->performSoftDeleteOnModel();
            return;
        }
        // Fallback DURO: copia literal de Illuminate\Database\Eloquent\Model::performDeleteOnModel().
        $this->setKeysForSaveQuery($this->newModelQuery())->delete();
        $this->exists = false;
    }

    protected $table = 'consumables';

    protected $fillable = [
        'name',
        'type',
        'description',
        'hazards',
        'precautions',
        'signal_word',
        'un_number',
        'sds_url',
        'is_active',
        'sort_order',
        // Verificación (2026-07-16): NUNCA llegan por POST — validatedData() del
        // controlador no las incluye en sus reglas; solo las escribe el servidor.
        'verified_at',
        'verified_by_id',
        // HDS de 16 secciones + GHS + clave natural (Paso 4b, 2026-07-17). TAMPOCO
        // llegan por POST hoy: las siembra el importador del catálogo SPFX (Paso 4c)
        // y las pinta el 4d; validatedData() no las valida y no debe hacerlo aquí.
        'code',
        'material_family',
        'synonyms',
        'cas_number',
        'ghs_pictograms',
        'sds_sections',
        'sds_level',
        'sds_status',
        'sds_source_note',
        'sds_source_date',
        'sds_disclaimer',
        'source_verified',
        'sds_url_verified',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
        'verified_at'      => 'datetime',
        // Paso 4b. Los tres JSON llegan como mapa/lista → 'array'.
        'synonyms'         => 'array',
        'ghs_pictograms'   => 'array',
        'sds_sections'     => 'array',
        'sds_level'        => 'integer',
        // OJO: `sds_source_date` es 'date' (fecha de GENERACIÓN del catálogo), NO
        // un espejo de `verified_at`. Ver supportsExtendedSds() y el SQL del 4b.
        'sds_source_date'  => 'date',
        'source_verified'  => 'boolean',
        'sds_url_verified' => 'boolean',
    ];

    /** Solo consumibles activos (para los selectores del panel SFX). */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Autoridad que validó la ficha (sin FK dura; puede ser null si nunca se verificó). */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /** Memo del soporte de verificación en BD (ver supportsVerification()). */
    protected static $verificationSupported = null;

    /**
     * ¿La BD tiene ya el delta de verificación (2026-07-16-sds-verification.sql)?
     * Memo estático: la lista itera muchas filas y cada Schema::hasColumn lanza una
     * consulta al information_schema; sin el memo pagaríamos una por fila.
     */
    public static function supportsVerification()
    {
        if (self::$verificationSupported === null) {
            self::$verificationSupported = Schema::hasColumn('consumables', 'verified_at')
                && Schema::hasColumn('consumables', 'verified_by_id');
        }
        return self::$verificationSupported;
    }

    /**
     * Ficha ya validada por una autoridad. NO-OP sin el delta aplicado: devuelve false
     * para que la UI no pinte ningún badge ("sin estado de verificación").
     */
    public function isVerified()
    {
        return self::supportsVerification() && $this->verified_at !== null;
    }

    /**
     * Ficha capturada en campo a la espera de validación. NO-OP sin el delta aplicado:
     * devuelve false (igual que isVerified) → sin columnas, no hay estado que mostrar.
     */
    public function isPendingVerification()
    {
        return self::supportsVerification() && $this->verified_at === null;
    }

    /**
     * Solo fichas pendientes de verificar. Sin el delta aplicado NADA está pendiente
     * (no existe el concepto), así que el scope corta con un predicado siempre-falso
     * en vez de tronar por columna inexistente.
     */
    public function scopePending($query)
    {
        if (!self::supportsVerification()) {
            return $query->whereRaw('1 = 0');
        }
        return $query->whereNull('verified_at');
    }

    /**
     * Tipos canónicos de consumible SFX (catálogo cerrado). El key se guarda en la
     * columna `type`; el value es la etiqueta legible (ES) para tablas y selects.
     */
    public static function typeLabels()
    {
        return [
            'smoke'    => 'Humo',
            'haze'     => 'Haze/neblina',
            'fire'     => 'Fuego',
            'blank'    => 'Salvas/municiones fogueo',
            'fuel'     => 'Combustible',
            'pyro'     => 'Pirotecnia',
            'chemical' => 'Químico',
            'cryo'     => 'Criogénico/CO₂',
            'other'    => 'Otro',
        ];
    }

    /** Etiqueta legible del tipo (cae al valor crudo si es desconocido). */
    public function getTypeLabelAttribute()
    {
        $map = self::typeLabels();
        return $this->type ? ($map[$this->type] ?? $this->type) : null;
    }

    /** Memo del soporte de la HDS extendida en BD (ver supportsExtendedSds()). */
    protected static $extendedSdsSupported = null;

    /**
     * ¿La BD tiene ya el delta de la HDS de 16 secciones (2026-07-17-consumables-sds16.sql)?
     * Memo estático por el mismo motivo que supportsVerification(): una lista itera muchas
     * filas y cada Schema::hasColumn lanza una consulta al information_schema.
     *
     * Sin el delta aplicado (p.ej. producción antes de que el owner corra el SQL) esto
     * devuelve false y todo lo de abajo es un NO-OP seguro: la ficha se comporta como
     * hasta ahora (SDS resumida), sin tronar por columna inexistente.
     *
     * ── LAS TRES VERIFICACIONES NO SON LA MISMA (no las mezcles) ────────────────
     *   1) `verified_at`/`verified_by_id` (1c) = un responsable con `sds.manage` APROBÓ
     *      la ficha aquí. Único sello que vale como acto de gobierno.
     *   2) `source_verified`  = el catálogo de ORIGEN dice que el dato está cotejado.
     *   3) `sds_url_verified` = el enlace resuelve a la HDS de ESA sustancia.
     * Y `sds_source_date` NO es ninguna de las tres: es la fecha en que se GENERÓ el
     * catálogo (vale igual en los 41 insumos, incluidos los que no están verificados).
     * NUNCA la vuelques en `verified_at`.
     */
    public static function supportsExtendedSds()
    {
        if (self::$extendedSdsSupported === null) {
            self::$extendedSdsSupported = Schema::hasColumn('consumables', 'sds_sections')
                && Schema::hasColumn('consumables', 'code');
        }
        return self::$extendedSdsSupported;
    }

    /**
     * ¿La ficha la capturó una persona A MANO en set? (vs. importada del catálogo SPFX).
     *
     * El discriminante es `code`: el importador del Paso 4c SIEMPRE siembra la clave
     * natural (INS-FIRE-01…), y el formulario de alta NUNCA la manda — `code` no está
     * entre las reglas de ConsumableController::validatedData(), así que una ficha nacida
     * del panel no puede traerla ni por accidente. Sin `code` ⇒ la capturó alguien en set.
     *
     * ── EL CASO NO OBVIO: BD SIN EL DELTA 4b ────────────────────────────────────
     * Ahí la columna `code` NI SIQUIERA EXISTE. Leerla daría null y esto respondería
     * "de campo"… por la razón equivocada. Pero la respuesta correcta ES esa, y por un
     * motivo distinto que conviene dejar escrito: el importador del 4c escribe EN las
     * columnas del 4b, o sea que en una instancia sin ese delta JAMÁS pudo correr, y por
     * tanto toda ficha que exista ahí salió del formulario de alta. Se corta con
     * supportsExtendedSds() y se devuelve true sin tocar la columna ausente.
     *
     * NO CONFUNDIR con la verificación (1c): esto es PROCEDENCIA (de dónde salió la
     * ficha), no gobierno (quién la avaló). Una ficha importada puede estar pendiente y
     * una de campo puede estar verificada; los dos ejes son independientes.
     */
    public function isFieldCaptured()
    {
        if (!self::supportsExtendedSds()) {
            return true;
        }

        return trim((string) $this->code) === '';
    }

    /**
     * Una sección de la HDS por su clave canónica (p.ej. '4_primeros_auxilios').
     * Devuelve null si no hay nada que enseñar. NULL-SAFE EN TRES NIVELES, porque los
     * tres pasan de verdad: (a) BD sin el delta 4b, (b) ficha sin `sds_sections`
     * (las 12 fichas vivas y cualquiera capturada a mano en set), y (c) clave que la
     * ficha no trae. La vista puede llamarlo a pelo y decidir con un solo `@if`.
     */
    public function sdsSection($key)
    {
        if (!self::supportsExtendedSds()) {
            return null;
        }

        $sections = $this->sds_sections;
        if (!is_array($sections)) {
            return null;
        }

        return $sections[$key] ?? null;
    }

    /**
     * Las 16 secciones de la HDS (NOM-018-STPS / GHS) en su ORDEN OFICIAL: el key es lo
     * que se guarda dentro del JSON `sds_sections`; el value, la etiqueta legible (ES).
     * Fuente única de las etiquetas — que la vista del 4d itere esto y no se las invente
     * (igual que typeLabels() con `type`). El orden del arreglo ES el orden de la ficha:
     * una HDS se lee del 1 al 16, no alfabéticamente.
     */
    public static function sdsSectionLabels()
    {
        return [
            '1_identificacion'               => 'Identificación',
            '2_peligros'                     => 'Identificación de los peligros',
            '3_composicion'                  => 'Composición / información sobre los componentes',
            '4_primeros_auxilios'            => 'Primeros auxilios',
            '5_incendios'                    => 'Medidas contra incendios',
            '6_derrames'                     => 'Medidas en caso de derrame accidental',
            '7_manejo_almacenamiento'        => 'Manejo y almacenamiento',
            '8_controles_epp_vle'            => 'Controles de exposición / protección personal',
            '9_propiedades_fisicoquimicas'   => 'Propiedades físicas y químicas',
            '10_estabilidad_reactividad'     => 'Estabilidad y reactividad',
            '11_toxicologica'                => 'Información toxicológica',
            '12_ecologica'                   => 'Información ecotoxicológica',
            '13_eliminacion'                 => 'Información relativa a la eliminación de los productos',
            '14_transporte'                  => 'Información relativa al transporte',
            '15_reglamentaria'               => 'Información reglamentaria',
            '16_otra'                        => 'Otra información',
        ];
    }

    /**
     * Tipos de efecto (Capa A) que usan este insumo — inversa N:M del Paso 4a.
     * Ojo con el eje: `type` clasifica el MATERIAL (con qué se hace); la `family`
     * del efecto clasifica el EFECTO (qué se hace). Comprobar
     * SfxEffectType::isAvailable() antes de ejecutarla (load/with/sync).
     */
    public function sfxEffectTypes()
    {
        return $this->belongsToMany(
            SfxEffectType::class,
            'consumable_sfx_effect_type',
            'consumable_id',
            'sfx_effect_type_id'
        )->withTimestamps();
    }

    /**
     * Cinturón y tirantes: al DESTRUIR físicamente un insumo se purgan sus vínculos con
     * tipos de efecto. Con la FK dura del delta 2026-07-16-sfx-effect-types.sql puesta es
     * un no-op (el motor ya hizo el CASCADE); cubre a quien haya usado la salida de
     * emergencia de ese SQL y perdería el cascade sin enterarse.
     *
     * OJO con el soft delete (Paso 1b): el evento `deleting` se dispara TAMBIÉN en un
     * retiro (soft), y ahí NO hay que purgar nada — la ficha se conserva y sus vínculos
     * deben sobrevivir para que restaurarla la devuelva intacta. Solo se purga en un
     * borrado DURO real: forceDelete() (isForceDeleting()=true) o, en una BD sin la
     * columna deleted_at, cualquier delete() (que ahí sigue siendo duro).
     */
    protected static function booted()
    {
        static::deleting(function ($consumable) {
            $isHardDelete = !self::supportsSoftDelete() || $consumable->isForceDeleting();
            if ($isHardDelete && Schema::hasTable('consumable_sfx_effect_type')) {
                DB::table('consumable_sfx_effect_type')
                    ->where('consumable_id', $consumable->id)
                    ->delete();
            }
        });
    }
}
