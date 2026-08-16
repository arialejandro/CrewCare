<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Mail;

/**
 * crew:welcome-resend {id}
 *
 * Reenvía el correo de bienvenida a un miembro de crew. Reemplaza la antigua ruta web GET
 * /welcomeresend/{id} (efecto colateral por GET, sin UI) — extraída del God Object AdminController
 * y convertida a comando Artisan (2026-06-28), consistente con `encuestas:task`.
 *
 * FIX (seguridad): antes el correo incluía `users.password` (el HASH bcrypt) — inservible para
 * el usuario y un leak del hash. Ahora se genera un TOKEN de restablecimiento con el password
 * broker y se envía el ENLACE `password.reset` (rutas de Auth::routes ya habilitadas). El usuario
 * establece su contraseña desde ahí; NO se expone ningún hash ni contraseña.
 */
class CrewWelcomeResend extends Command
{
    protected $signature = 'crew:welcome-resend {id : ID del usuario de crew}';

    protected $description = 'Reenvía el correo de bienvenida a un miembro de crew por ID.';

    public function handle()
    {
        $producto = User::find($this->argument('id'));
        if (! $producto) {
            $this->error('Usuario no encontrado: '.$this->argument('id'));
            return 1;
        }

        // FIX: en vez de mandar el hash bcrypt, generamos un enlace de restablecimiento de
        // contraseña (token del password broker) para que el usuario fije su propia contraseña.
        $token = Password::broker()->createToken($producto);
        $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $producto->email], false));

        $subject = "WELCOME AP S2"." ".$producto->name;
        $data = [
            'nombre'    => $producto->name,
            'email'     => $producto->email,
            'resetUrl'  => $resetUrl,
            // El reenvío también lleva la invitación al intake (mismo enlace firmado que el alta).
            'intakeUrl' => \App\Http\Controllers\IntakeController::invitationUrl($producto),
        ];
        $for = $producto->email;
        Mail::send('correos.welcomeuser', $data, function ($msj) use ($subject, $for) {
            $msj->from("noreply@crewcare.mx", "CrewCare");
            $msj->subject($subject);
            $msj->to($for);
        });

        $this->info('Correo de bienvenida reenviado a '.$for);
        return 0;
    }
}
