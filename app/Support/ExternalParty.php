<?php

namespace App\Support;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * EL INFOSHEET · FASE 4 — el contratado NO-CREW como USUARIO ÚNICO sin credenciales.
 *
 * UNA sola tabla `users` (la lección de las tres tablas divergentes): el no-crew NO recibe
 * contraseña y NO inicia sesión — se marca `is_external=1`, queda FUERA del listado
 * (`crewlist_visible=0`) y del crew activo (`activo=0`, que ya lo excluye de todos los listados que
 * filtran `activo=1`), y ENTRA por un enlace con `external_access_token`: un hash de UN SOLO USO que
 * no caduca hasta usarse. El token NO es mass-assignable (se asigna aquí, en servidor).
 *
 * El enlace es solo la PUERTA de entrada: una vez dentro, el contratado sigue firmando por el flujo
 * sin sesión de siempre (con su 2º factor = RFC). Belt-and-suspenders, no lo reemplaza.
 */
class ExternalParty
{
    /**
     * Provisiona (o reusa) el usuario externo lite de un payee. Idempotente: si el payee YA está
     * ligado a un usuario, se respeta (puede ser un crew real; no lo convertimos en externo). Liga
     * `payee->user_id`. La contraseña es un hash aleatorio INUSABLE (nunca inicia sesión).
     */
    public static function provisionForPayee(Payee $payee, string $email, ?string $name = null): User
    {
        if ($payee->user) {
            return $payee->user;   // ya ligado → no clobbear
        }

        // Si ese correo YA es un usuario, se reusa (puede ser crew real). Si no, se crea el externo
        // con forceFill (no por $fillable: hay que fijar `admin=0`, que a propósito NO es
        // mass-assignable, y las banderas del externo).
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = (new User())->forceFill([
                'name'             => $name ?: $payee->name,
                'email'            => $email,
                'password'         => Hash::make(Str::random(48)),   // inusable: nunca inicia sesión
                'admin'            => 0,
                'activo'           => 0,
                'is_external'      => 1,
                'crewlist_visible' => 0,
            ]);
            $user->save();
        }

        $payee->user_id = $user->id;
        $payee->save();

        return $user;
    }

    /** Genera (o regenera) el token de UN SOLO USO y devuelve la URL de acceso. Reusar invalida el previo. */
    public static function mintAccessLink(User $user): string
    {
        $user->external_access_token   = Str::random(48);   // NO fillable → asignación directa
        $user->external_access_used_at = null;              // re-armable hasta que se use
        $user->save();

        return route('external.access', ['token' => $user->external_access_token]);
    }

    /** Consume el token (un solo uso). Devuelve el usuario externo, o null si es inválido/ya usado. */
    public static function consume(string $token): ?User
    {
        $user = User::where('external_access_token', $token)
            ->where('is_external', 1)
            ->whereNull('external_access_used_at')
            ->first();
        if (! $user) {
            return null;
        }
        $user->external_access_used_at = now();
        $user->save();

        return $user;
    }

    /** El destinatario de firma PENDIENTE (contratado) del payee de este usuario externo, o null. */
    public static function pendingRecipientFor(User $user): ?ContractEnvelopeRecipient
    {
        $payee = Payee::where('user_id', $user->id)->first();
        if (! $payee) {
            return null;
        }

        return ContractEnvelopeRecipient::query()
            ->where('role', ContractEnvelopeRecipient::ROLE_CONTRACTED)
            ->where('payee_id', $payee->id)
            ->where('status', '!=', ContractEnvelopeRecipient::STATUS_SIGNED)
            ->whereHas('envelope', fn ($q) => $q->where('status', ContractEnvelope::STATUS_SENT))
            ->latest('id')
            ->first();
    }
}
