<?php

namespace App\Support;

use App\Models\Production;
use Illuminate\Support\Facades\Schema;

/**
 * CurrentProduction — FUENTE ÚNICA de "¿en qué producción estamos?".
 *
 * (2026-07-24) Antes de esta clase, seis lugares distintos resolvían la producción con
 * `Production::where('name', 'Producción Demo')->first()` — el nombre literal, escrito a mano,
 * en CrewController (×3), HazardNotificationController, unsafecondNotificationController,
 * RoleAssignmentController e InvolvedResolver. El día que el owner renombre esa fila —o dé de
 * alta la producción real y jubile la demo— esos seis lugares devuelven null EN SILENCIO: el
 * crew deja de asignarse, el involucrado deja de resolverse y nadie ve un error. Es la clase de
 * bug que sólo se descubre cuando ya perdiste datos.
 *
 * REGLA DE SELECCIÓN (en orden):
 *   1) la producción marcada `active = 1` con `start_date` más reciente (si hay varias activas,
 *      manda la que arrancó después: es la que se está rodando);
 *   2) si ninguna está activa, la primera por id (instalación de una sola producción);
 *   3) null — y quien llame debe tolerarlo, como ya lo toleraban los seis lugares viejos.
 *
 * El nombre 'Producción Demo' YA NO decide nada. Sobrevive como dato, no como llave.
 */
class CurrentProduction
{
    /** Caché por petición. `false` = todavía no se resolvió (null SÍ es una respuesta válida). */
    private static $cache = false;

    /**
     * @return \App\Models\Production|null
     */
    public static function get()
    {
        if (self::$cache !== false) {
            return self::$cache;
        }

        if (! Schema::hasTable('productions')) {
            return self::$cache = null;
        }

        try {
            $p = Production::where('active', 1)
                // start_date NULL al final: una producción sin fecha no le gana a una que sí la tiene.
                ->orderByRaw('start_date IS NULL, start_date DESC')
                ->orderBy('id', 'desc')
                ->first();

            if (! $p) {
                $p = Production::orderBy('id')->first();
            }
        } catch (\Throwable $e) {
            $p = null;
        }

        return self::$cache = $p;
    }

    /**
     * Id de la producción vigente, o null.
     *
     * @return int|null
     */
    public static function id()
    {
        $p = self::get();

        return $p ? (int) $p->getKey() : null;
    }

    /**
     * Olvida la caché. Sólo lo necesitan los seeders y el arnés de verificación, que cambian
     * fechas de la producción dentro del mismo proceso.
     *
     * @return void
     */
    public static function forget()
    {
        self::$cache = false;
    }
}
