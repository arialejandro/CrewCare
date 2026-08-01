<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * CONSENTIMIENTO DEL AVISO DE PRIVACIDAD (2026-07-24 · PIEZA 3, corrida 2/2).
 *
 * Hasta hoy la app no tenía aviso, ni términos, ni consentimiento, ni tabla — y captura
 * tabaquismo, alcoholismo, toxicomanías y antecedentes familiares de cáncer. En México los
 * datos de salud son datos personales SENSIBLES: requieren consentimiento EXPRESO del titular
 * ANTES de recabarlos.
 *
 * LA VERSIÓN ES EL CAMPO QUE IMPORTA. "Aceptó" sin decir QUÉ aceptó no prueba nada: el texto
 * cambia y el registro tiene que seguir señalando el que la persona leyó. Por eso esto es una
 * tabla y no un booleano en `users` — un booleano se sobrescribiría y perdería el histórico.
 *
 * ⚠ ADEMÁS ES EL DETECTOR DE "PRIMER LOGIN". La app no tiene ninguno: `users` no tiene
 * `first_login` ni `password_changed_at`, y `email_verified_at` está muerto (User importa
 * MustVerifyEmail y no lo implementa; 0 de 92 usuarios lo tienen). No hizo falta inventar una
 * columna: NO TENER FILA VIGENTE *ES* estar pendiente. Funciona igual para los 92 usuarios que
 * ya existen que para quien se dé de alta mañana, y publicar una versión nueva del aviso vuelve
 * a preguntar a todos, solo, sin tocar código ni correr un UPDATE.
 */
class PrivacyConsent extends Model
{
    use HasFactory;

    protected $table = 'privacy_consents';

    protected $fillable = ['user_id', 'version', 'accepted_at', 'ip_address', 'user_agent'];

    protected $dates = ['accepted_at'];

    /**
     * ¿Existe la tabla? Sin ella el módulo se apaga solo y la app sigue funcionando como antes
     * (sin pedir consentimiento), en vez de dejar a todo el mundo fuera del cuestionario porque
     * el owner no corrió un SQL. Memo estático: Schema::hasTable no está cacheado en Laravel 8.
     *
     * @return bool
     */
    public static function supported()
    {
        static $existe = null;

        if ($existe === null) {
            try {
                $existe = Schema::hasTable('privacy_consents');
            } catch (\Throwable $e) {
                $existe = false;
            }
        }

        return $existe;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * ¿Esta persona ya aceptó la versión VIGENTE del aviso?
     *
     * @param  \App\Models\User|null  $usuario
     * @return bool
     */
    public static function aceptadoPor($usuario)
    {
        if (! $usuario || ! self::supported()) {
            // Sin tabla no se bloquea a nadie: ver supported().
            return true;
        }

        try {
            return self::where('user_id', $usuario->id)
                ->where('version', \App\Support\PrivacyNotice::VERSION)
                ->whereNotNull('accepted_at')
                ->exists();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Registra la aceptación. Idempotente por (user_id, version) — la tabla tiene UNIQUE, así
     * que aceptar dos veces no siembra dos filas ni truena.
     *
     * @return \App\Models\PrivacyConsent|null
     */
    public static function registrar($usuario, $request = null)
    {
        if (! $usuario || ! self::supported()) {
            return null;
        }

        try {
            return self::updateOrCreate(
                ['user_id' => $usuario->id, 'version' => \App\Support\PrivacyNotice::VERSION],
                [
                    'accepted_at' => now(),
                    'ip_address'  => $request ? $request->ip() : null,
                    'user_agent'  => $request ? substr((string) $request->userAgent(), 0, 500) : null,
                ]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
}
