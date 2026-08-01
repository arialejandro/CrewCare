<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // SEGURIDAD (2026-06-27): se exige también `activo`. Antes un usuario admin DESACTIVADO
        // (activo=0) seguía pasando el gate binario. Ahora un admin dado de baja queda fuera.
        if (auth()->check() && auth()->user()->admin && auth()->user()->activo)
            return $next($request);

        return redirect('/');
    }
}
