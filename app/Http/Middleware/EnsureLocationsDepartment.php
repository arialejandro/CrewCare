<?php

namespace App\Http\Middleware;

use App\Support\LocationsAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * El Tech Scout es de LOCACIONES — y de nadie más.
 *
 * Como `privacidad` o `idempotent`, NO va en ningún grupo global: se cuelga sólo de las rutas del
 * módulo. El porqué de la regla (y por qué super-admin sí pasa) está en {@see LocationsAccess}.
 */
class EnsureLocationsDepartment
{
    public function handle(Request $request, Closure $next)
    {
        // 403 y no 404: quien llega aquí suele ser alguien del equipo que siguió un enlace, y
        // decirle "no existe" lo manda a buscar un error que no está. Que sepa que existe y que
        // no es suyo.
        abort_unless(LocationsAccess::allows($request->user()), 403,
            'El Tech Scout es del departamento de Locaciones.');

        return $next($request);
    }
}
