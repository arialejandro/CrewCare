<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstrap del PRIMER super-admin para una instalacion NUEVA de cliente.
 *
 * Sin este seeder, `migrate && db:seed` deja la app sin ninguna cuenta con la
 * que iniciar sesion: super-admin es el unico rol inmune (Gate::before), y hasta
 * ahora solo lo asignaba MapExistingUsersSeeder sobre usuarios PREEXISTENTES
 * (admin=1) — en una base vacia eso es 0 cuentas y el panel queda inaccesible.
 *
 * IDEMPOTENTE: no hace nada si ya existe algun super-admin.
 *
 * Credenciales por variables de entorno (NUNCA hardcodeadas). En el .env del
 * servidor nuevo, antes de correr db:seed:
 *   INSTALL_ADMIN_EMAIL=...   (default: admin@<host de APP_URL>)
 *   INSTALL_ADMIN_NAME=...    (default: Administrador)
 *   INSTALL_ADMIN_PASSWORD=...(si falta, se genera uno aleatorio y se imprime UNA vez)
 */
class InstallAdminSeeder extends Seeder
{
    public function run()
    {
        if (User::role('super-admin')->exists()) {
            $this->command->info('InstallAdminSeeder: ya existe un super-admin; nada que hacer.');
            return;
        }

        $host  = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crewcare.local';
        $email = env('INSTALL_ADMIN_EMAIL', 'admin@' . $host);
        $name  = env('INSTALL_ADMIN_NAME', 'Administrador');

        $plain     = env('INSTALL_ADMIN_PASSWORD');
        $generated = false;
        if (empty($plain)) {
            $plain     = Str::random(20);
            $generated = true;
        }

        // firstOrNew + asignacion directa: `admin` esta FUERA de $fillable (fix de
        // seguridad), asi que un updateOrCreate en masa lo descartaria en silencio.
        $user = User::firstOrNew(['email' => $email]);
        $user->name     = $name;
        $user->password = Hash::make($plain);
        $user->admin    = 1;
        $user->activo   = 1;
        $user->save();

        $user->syncRoles(['super-admin']);

        // Adjuntar a la produccion vigente si existe (best-effort; el super-admin ya
        // pasa via Gate::before aunque no tenga membresia en el pivote).
        if (class_exists(\App\Support\CurrentProduction::class) && ($prod = \App\Support\CurrentProduction::get())) {
            DB::table('production_user')->updateOrInsert(
                ['production_id' => $prod->id, 'user_id' => $user->id],
                ['role' => 'super-admin', 'is_lead' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->command->warn('========================================================');
        $this->command->warn(' SUPER-ADMIN creado: ' . $email);
        if ($generated) {
            $this->command->warn(' PASSWORD (se muestra UNA sola vez): ' . $plain);
            $this->command->warn(' Cambialo en cuanto inicies sesion.');
        }
        $this->command->warn('========================================================');
    }
}
