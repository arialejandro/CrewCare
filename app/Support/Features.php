<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Features — resolución de feature flags (Pilar 5).
 *
 * Precedencia: BD (tabla `feature_flags`) SOBRESCRIBE los defaults de
 * config/features.php. Si la tabla aún no existe (PROD sin el SQL), cae a config
 * → no truena. Se usa vía la directiva Blade @feature('x')...@endfeature
 * (registrada en AppServiceProvider) y en código con Features::enabled('x').
 */
class Features
{
    /** @var array|null cache de overrides de BD por request */
    protected static $overrides = null;

    /**
     * ¿Está encendida la feature? BD gana sobre config; default false.
     *
     * @param  string  $key
     * @return bool
     */
    public static function enabled($key)
    {
        $overrides = self::dbOverrides();
        if (array_key_exists($key, $overrides)) {
            return (bool) $overrides[$key];
        }

        return (bool) config('features.' . $key, false);
    }

    /**
     * Mapa completo key => bool (config fusionado con overrides de BD).
     * Para el panel de administración.
     *
     * @return array
     */
    public static function all()
    {
        $defaults  = (array) config('features', []);
        $overrides = self::dbOverrides();
        $keys = array_unique(array_merge(array_keys($defaults), array_keys($overrides)));

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::enabled($k);
        }
        ksort($out);

        return $out;
    }

    /**
     * Overrides de la tabla feature_flags (cacheado por request). Guard defensivo:
     * si la tabla no existe, regresa [] y todo cae a config.
     *
     * @return array
     */
    protected static function dbOverrides()
    {
        if (self::$overrides !== null) {
            return self::$overrides;
        }

        self::$overrides = [];
        if (Schema::hasTable('feature_flags')) {
            foreach (DB::table('feature_flags')->get() as $row) {
                self::$overrides[$row->key] = (bool) $row->enabled;
            }
        }

        return self::$overrides;
    }

    /** Limpia el cache (tras conmutar un flag en el panel admin). */
    public static function flush()
    {
        self::$overrides = null;
    }
}
