<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SessionController — VER y CERRAR sesiones activas desde el perfil (sesión larga pero REVOCABLE).
 * Es el caso del teléfono perdido: cerrar esa sesión SIN cambiar la contraseña.
 *
 * Requiere SESSION_DRIVER=database (la tabla `sessions` guarda user_id/ip/user_agent/last_activity).
 * Con el driver `file` no hay forma de enumerar sesiones → la vista lo explica y no rompe nada.
 * El id de sesión ya se rota al iniciar sesión (laravel/ui: sendLoginResponse → regenerate()).
 */
class SessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $driverOk = config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions');
        $sessions = $driverOk ? $this->userSessions($request) : collect();

        return view('perfil.sesiones', ['sessions' => $sessions, 'driverOk' => $driverOk]);
    }

    /** Cierra TODAS las sesiones del usuario salvo la actual (revocación sin cambiar contraseña). */
    public function destroyOthers(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return back()->with('error', __('Activa la sesión en base para poder cerrar otras sesiones.'));
        }
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', __('Se cerraron las demás sesiones.'));
    }

    private function userSessions(Request $request)
    {
        $current = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($current) {
                return (object) [
                    'current' => $s->id === $current,
                    'ip'      => $s->ip_address ?: '—',
                    'agent'   => $this->readAgent($s->user_agent),
                    'last'    => $s->last_activity ? \Carbon\Carbon::createFromTimestamp($s->last_activity) : null,
                ];
            });
    }

    /** Lectura simple del user-agent → "Navegador · SO". Sin dependencias. */
    private function readAgent(?string $ua): string
    {
        if (! $ua) {
            return __('Dispositivo desconocido');
        }
        $os = 'otro';
        foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Macintosh' => 'Mac', 'Linux' => 'Linux'] as $k => $v) {
            if (str_contains($ua, $k)) { $os = $v; break; }
        }
        $br = 'navegador';
        // Orden importa: Edge/Opera antes que Chrome; Chrome antes que Safari (sus UA se solapan).
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'] as $k => $v) {
            if (str_contains($ua, $k)) { $br = $v; break; }
        }
        return "{$br} · {$os}";
    }
}
