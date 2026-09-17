<?php

namespace App\Support;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * ACCESO AL TECH SCOUT — por DEPARTAMENTO, no por puesto (decisión del owner 2026-09-16).
 *
 * «Eso sólo lo vea quien esté en el departamento de Locaciones, no importa su puesto, sólo el
 * departamento.» Es distinto a todo lo demás de la app, que evalúa PERMISOS: aquí el criterio es
 * la pertenencia, y por una razón sencilla —el Tech Scout es la libreta de trabajo de Locaciones—.
 * Un P.A. de Locaciones tiene que poder capturar; un gerente de otro departamento, aunque tenga
 * más rango y hasta el permiso `locations.create`, no pinta nada aquí.
 *
 * El departamento vive en el pivote production_user ({@see User::ownDepartmentIds()}), que es la
 * llave estable; la etiqueta desnormalizada `users.puestodepartamento` NO lo es.
 *
 * ── LA ÚNICA PUERTA TRASERA ────────────────────────────────────────────────────────────────
 * super-admin. No es un descuido: hoy en producción el owner es el único usuario y no está en
 * ningún departamento — sin esta salvedad, este cambio lo dejaría fuera de su propio módulo el
 * mismo día que se sube. Cuando se den de alta los scouters, la regla del departamento es la que
 * gobierna para todos los demás.
 *
 * Emparenta con {@see TransportAccess}, que resuelve lo mismo en Transportación pero HÍBRIDO
 * (permiso O departamento). Aquí NO se acepta el permiso: el owner lo pidió cerrado.
 */
class LocationsAccess
{
    /** IDs del/los departamento(s) de Locaciones. Catálogo pequeño; sin caché para no ensuciar tests. */
    public static function departmentIds(): array
    {
        try {
            if (! Schema::hasTable('departments')) {
                return [];
            }

            // ES y EN: el catálogo organizacional guarda 'Locaciones' y su equivalente 'Locations'.
            return Department::whereRaw('LOWER(name) LIKE ?', ['%locacion%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%location%'])
                ->pluck('id')->map(fn ($x) => (int) $x)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** ¿Pertenece al departamento de Locaciones (en cualquier puesto)? */
    public static function isInLocations(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $dept = self::departmentIds();
        if (empty($dept)) {
            return false;
        }

        try {
            return $user->ownDepartmentIds()->intersect($dept)->isNotEmpty();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * ¿Puede entrar al Tech Scout? Departamento de Locaciones, o super-admin.
     *
     * SIN memo estático a propósito. Tentaba: el sidebar pinta el enlace dos veces (escritorio y
     * móvil) y son un par de consultas a una tabla diminuta. Pero un `static` indexado por id de
     * usuario ya nos mordió una vez —sobrevive entre pruebas del mismo proceso y responde por un
     * usuario que ya no es el mismo—, y un falso "sí" aquí es justo el aislamiento que el owner
     * pidió, roto en silencio. Dos SELECT baratos valen esa tranquilidad.
     */
    public static function allows(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return self::isInLocations($user) || $user->hasRole('super-admin');
    }
}
