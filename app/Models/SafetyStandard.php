<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyStandard extends Model
{
    protected $table = 'safety_standards';

    // Mass-assignment explícito (antes $guarded=[] dejaba la tabla abierta).
    // Columnas reales del catálogo normativo (ver SafetyCatalogSeeder).
    protected $fillable = [
        'category_name',
        // Traducción EN del nombre de categoría (i18n, columna del delta 2026-07-12).
        // El SafetyCatalogSeeder la escribe por asignación DIRECTA; aquí se hace
        // mass-assignable a propósito para que la pantalla de captura (Paso 4a) pueda
        // persistir el nombre en inglés validado (nullable|max:255) vía create/update
        // sin dropearlo en silencio. El seeder sigue funcionando igual (la asignación
        // directa no depende de $fillable).
        'category_name_en',
        'regulation_badge',
        'regulation_code',
        'reference_url',
        // Vigencia (2026-07-18, Paso 4a): 1 = norma capturable, 0 = retirada del
        // catálogo de captura (pero VIVA para el histórico, ver isActive()). NO se
        // expone en el form: solo la mueven deactivate()/reactivate() del controlador.
        'is_active',
        // Verificación (2026-07-18, espejo del Paso 1c de las fichas SDS): solo
        // servidor, NUNCA por POST — ninguna regla de captura las incluye; solo
        // las escribe el flujo de verificación / el sellado inicial del SQL.
        'verified_at',
        'verified_by_id',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * (2026-07-13) Eventos posibles que mapean a esta norma (N:N inverso).
     * Ver App\Models\HazardEvent. Pivote `hazard_event_standard`.
     */
    public function hazardEvents()
    {
        return $this->belongsToMany(
            HazardEvent::class,
            'hazard_event_standard',
            'safety_standard_id',
            'hazard_event_id'
        );
    }

    /**
     * (2026-07-18, Paso 5b) Tipos de efecto SFX que citan esta norma (N:N inverso).
     * Ver App\Models\SfxEffectType. Pivote `effect_standard`. DEFENSIVO: declararla es
     * perezoso; ejecutarla (load/with) requiere el delta del puente → guard con
     * SfxEffectType::supportsStandardsBridge() en el punto de uso.
     */
    public function sfxEffectTypes()
    {
        return $this->belongsToMany(
            SfxEffectType::class,
            'effect_standard',
            'safety_standard_id',
            'sfx_effect_type_id'
        )->withTimestamps();
    }

    /**
     * (2026-07-09) Integridad de los pivotes sin FK dura: al borrar una norma, se
     * purgan sus vínculos (emula ON DELETE CASCADE). El repo usa BIGINT sin FK por
     * convención (evita choques de engine/charset).
     *   - standardables          : reportes ↔ norma (polimórfico).
     *   - hazard_event_standard   : evento ↔ norma (2026-07-13).
     */
    protected static function booted()
    {
        static::deleting(function ($standard) {
            if (\Illuminate\Support\Facades\Schema::hasTable('standardables')) {
                \Illuminate\Support\Facades\DB::table('standardables')
                    ->where('safety_standard_id', $standard->id)
                    ->delete();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('hazard_event_standard')) {
                \Illuminate\Support\Facades\DB::table('hazard_event_standard')
                    ->where('safety_standard_id', $standard->id)
                    ->delete();
            }
            // effect_standard : efecto SFX ↔ norma (2026-07-18, Paso 5b). Con la FK dura
            // del delta esto es no-op (el motor cascada); cubre la SALIDA DE EMERGENCIA.
            if (\Illuminate\Support\Facades\Schema::hasTable('effect_standard')) {
                \Illuminate\Support\Facades\DB::table('effect_standard')
                    ->where('safety_standard_id', $standard->id)
                    ->delete();
            }
        });
    }

    /**
     * (2026-07-13) MÓDULO 14 — nombre de categoría localizado.
     * Regresa category_name_en cuando el locale es 'en' y la columna existe y
     * no está vacía; si no, cae a category_name. Guard defensivo de columna:
     * PROD aún no tiene el ALTER, así que no truena si category_name_en falta.
     */
    public function getCategoryNameLocalizedAttribute()
    {
        if (app()->getLocale() === 'en'
            && \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'category_name_en')
            && !empty($this->category_name_en)) {
            return $this->category_name_en;
        }

        return $this->category_name;
    }

    /** Autoridad que validó la norma (sin FK dura; puede ser null si nunca se verificó). */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /** Memo del soporte de verificación en BD (ver supportsVerification()). */
    protected static $verificationSupported = null;

    /**
     * ¿La BD tiene ya el delta de verificación (2026-07-18-normas-eventos-verification.sql)?
     * Memo estático: una lista itera muchas filas y cada Schema::hasColumn lanza una
     * consulta al information_schema; sin el memo pagaríamos una por fila. Usa facades FQ
     * para respetar la convención de este archivo (único import = Model).
     */
    public static function supportsVerification()
    {
        if (self::$verificationSupported === null) {
            self::$verificationSupported = \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'verified_at')
                && \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'verified_by_id');
        }
        return self::$verificationSupported;
    }

    /**
     * Norma ya validada por una autoridad. NO-OP sin el delta aplicado: devuelve false
     * para que la UI no pinte ningún badge ("sin estado de verificación").
     */
    public function isVerified()
    {
        return self::supportsVerification() && $this->verified_at !== null;
    }

    /**
     * Norma capturada a la espera de validación. NO-OP sin el delta aplicado: devuelve
     * false (igual que isVerified) → sin columnas, no hay estado que mostrar.
     */
    public function isPendingVerification()
    {
        return self::supportsVerification() && $this->verified_at === null;
    }

    /**
     * Solo normas pendientes de verificar. Sin el delta aplicado NADA está pendiente
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

    /** Memo del soporte de vigencia (is_active) en BD (ver supportsActiveFlag()). */
    protected static $activeFlagSupported = null;

    /**
     * ¿La BD tiene ya el delta de vigencia (2026-07-18-safety-standards-is-active.sql)?
     * Memo estático: la lista itera muchas filas y cada Schema::hasColumn lanza una
     * consulta al information_schema; sin el memo pagaríamos una por fila. Usa facade FQ
     * para respetar la convención de este archivo (único import = Model).
     */
    public static function supportsActiveFlag()
    {
        if (self::$activeFlagSupported === null) {
            self::$activeFlagSupported = \Illuminate\Support\Facades\Schema::hasColumn('safety_standards', 'is_active');
        }
        return self::$activeFlagSupported;
    }

    /**
     * ¿Norma vigente (capturable)? DEGRADE-SAFE: sin la columna, TODO se considera
     * vigente (return true) — así ninguna vista/consulta trata como retirada una fila
     * que en realidad solo carece del delta de BD.
     */
    public function isActive()
    {
        return !self::supportsActiveFlag() || (bool) $this->is_active;
    }

    /**
     * Solo normas vigentes. Sin el delta aplicado el flag no existe, así que el scope
     * es un NO-OP (devuelve el query intacto = todas cuentan como vigentes) en vez de
     * tronar por columna inexistente. OJO: is_active NO tiene scope global — una norma
     * retirada SIGUE resolviéndose por la relación ->standards del histórico; este scope
     * solo la excluye de las consultas que explícitamente lo apliquen (p.ej. captura).
     */
    public function scopeActive($query)
    {
        if (!self::supportsActiveFlag()) {
            return $query;
        }
        return $query->where('is_active', 1);
    }
}