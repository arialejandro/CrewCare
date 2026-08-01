<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * 403 con una cara propia y clara (errors/403) en lugar del 403 crudo del framework: un acceso
     * denegado legítimo debe leerse como un problema de permisos, no como que la app está rota.
     *
     * Sólo web: las peticiones JSON/API caen al manejador estándar (respuesta JSON), sin tocar.
     */
    public function render($request, Throwable $e)
    {
        if ($this->esAccesoDenegado($e) && ! $request->expectsJson() && ! $request->is('api/*')) {
            return response()->view('errors.403', [], 403);
        }

        return parent::render($request, $e);
    }

    /** ¿La excepción es un 403 (permiso denegado, autorización, o abort(403))? */
    protected function esAccesoDenegado(Throwable $e): bool
    {
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return true;
        }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
            return true;
        }
        if ($e instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            return true;
        }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getStatusCode() === 403;
        }
        return false;
    }
}
