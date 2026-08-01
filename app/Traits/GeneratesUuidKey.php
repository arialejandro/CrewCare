<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

/**
 * Trait GeneratesUuidKey — asigna un UUID público (columna `uuid`) al crear.
 *
 * Da a cada reporte un identificador estable y no adivinable, independiente del
 * id autoincremental, útil para URLs públicas / QR de firma. NO reemplaza la PK.
 *
 * DEFENSIVO: si la tabla/columna `uuid` aún no existe (owner no aplicó el SQL),
 * el hook es no-op → nada truena. Solo asigna cuando la columna existe y el
 * modelo aún no trae uuid (respeta un uuid explícito).
 */
trait GeneratesUuidKey
{
    /**
     * Bootea el trait: registra el hook `creating`.
     *
     * @return void
     */
    public static function bootGeneratesUuidKey()
    {
        static::creating(function ($model) {
            try {
                if (empty($model->uuid)
                    && Schema::hasColumn($model->getTable(), 'uuid')) {
                    $model->uuid = (string) Str::uuid();
                }
            } catch (\Throwable $e) {
                // Tabla/columna inexistente o driver sin introspección: no romper.
            }
        });
    }
}
