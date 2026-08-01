<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Avatar — FUENTE ÚNICA de la foto de perfil (2026-07-24).
 *
 * EL PROBLEMA QUE RESUELVE: la foto vive en `users.imgperfil` como NOMBRE DE ARCHIVO suelto
 * dentro de public/imagesprf/usrs/. 84 de los 92 usuarios NO tienen foto, y tres usuarios
 * semilla apuntan a un archivo que no existe. Cada vista resolvía eso a su manera —unas con
 * File::exists, otras con !empty($u->imgperfil), otras sin nada— así que la misma persona
 * salía con silueta en una pantalla y con el icono roto del navegador en otra.
 *
 * LA REGLA: si el archivo no existe EN DISCO, se devuelve nophoto.png. No basta con revisar
 * que la columna venga llena: el caso real de esta base es una columna llena que apunta a un
 * archivo borrado, y ése es justo el que pinta el icono roto.
 *
 * ⚠ asset() necesita una petición HTTP para resolver la raíz correcta. En CLI, colas o dompdf
 *   cae a APP_URL. Por eso esto NO se usa para el gafete impreso: ese flujo tiene su propio
 *   resolvedor a base64 en admin/badge/_card, que sí funciona sin servidor.
 */
class Avatar
{
    /** Carpeta pública donde el cropper deja las fotos. */
    const DIR = 'imagesprf/usrs/';

    /** Silueta genérica del proyecto (existe desde antes; el owner la dejó para esto). */
    const FALLBACK = 'img/nophoto.png';

    /**
     * URL de la foto de $user, o la silueta genérica si no hay foto utilizable.
     *
     * Acepta un User, un stdClass de un select con proyección, o el nombre de archivo suelto:
     * las listas del crew traen filas de Query Builder, no modelos.
     *
     * @param  mixed  $user
     * @return string
     */
    public static function url($user)
    {
        $file = self::filename($user);

        return $file !== null ? asset(self::DIR . $file) : asset(self::FALLBACK);
    }

    /**
     * ¿Esta persona tiene una foto REAL en disco? Las vistas que pintan iniciales en vez de
     * silueta preguntan aquí para no depender de que la columna venga llena.
     *
     * @param  mixed  $user
     * @return bool
     */
    public static function has($user)
    {
        return self::filename($user) !== null;
    }

    /**
     * Nombre de archivo utilizable, o null. Privado a propósito: fuera de esta clase nadie
     * debería volver a concatenar la ruta a mano.
     *
     * @param  mixed  $user
     * @return string|null
     */
    private static function filename($user)
    {
        if (is_string($user)) {
            $name = $user;
        } elseif (is_object($user) && isset($user->imgperfil)) {
            $name = $user->imgperfil;
        } else {
            return null;
        }

        $name = trim((string) $name);
        if ($name === '' || $name === self::basename()) {
            return null;
        }

        // basename() corta cualquier intento de subir de carpeta ("../../.env") que llegara
        // por la columna: aquí sólo puede salir un archivo de imagesprf/usrs.
        $name = basename($name);

        try {
            if (! File::exists(public_path(self::DIR . $name))) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $name;
    }

    /**
     * Nombre del archivo de silueta, para no tratar "nophoto.png" guardado en la columna
     * como si fuera una foto de verdad.
     *
     * @return string
     */
    private static function basename()
    {
        return basename(self::FALLBACK);
    }
}
