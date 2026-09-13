<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * AppSetting — clave/valor a nivel instancia (config que no vive en .env). Defensivo: si la tabla aún
 * no existe (PROD sin el SQL), get() devuelve el default y set() es no-op → nada truena.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    /** @var array<string,string|null> caché por request */
    protected static $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }
        $row = static::where('key', $key)->first();
        self::$cache[$key] = $row ? $row->value : null;

        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache[$key] = $value;
    }
}
