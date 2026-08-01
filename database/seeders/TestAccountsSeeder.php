<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas de PRUEBA para verificar los roles en incógnito.
 *
 * Crea 4 usuarios, uno por rol representativo, con `admin = 0` (a propósito: así se
 * prueba el RBAC real por permisos, NO el bypass del flag `admin`). Cada uno recibe
 * su rol spatie. Idempotente (updateOrCreate por email + syncRoles) → re-ejecutable.
 *
 * Contraseña de todas: Test1234!
 *
 * Re-ejecutar:  php artisan db:seed --class=TestAccountsSeeder
 *
 * ⚠ (2026-07-24) ESTE SEEDER ESTABA ENCADENADO EN DatabaseSeeder.
 * Es decir: un `php artisan db:seed` a secas —lo primero que se corre al levantar una
 * instancia— creaba estas CUATRO CUENTAS CON CONTRASEÑA CONOCIDA Y PÚBLICA, entre ellas
 * una con rol `hod` y otra con rol `medic` (que abre el expediente clínico). En la base de
 * un cliente eso no es "data de prueba": es una puerta trasera documentada en el repo.
 *
 * El archivo YA decía "No tocar en instancias de cliente" y aun así estaba encadenado —
 * prueba de que un comentario no es un control. Ahora hay dos:
 *   1) se sacó de la cadena de DatabaseSeeder → sólo se invoca a mano;
 *   2) el candado SoloEnLocal lo hace ABORTAR fuera del entorno local.
 */
class TestAccountsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\SoloEnLocal;

    public function run()
    {
        $this->exigirEntornoLocal('CUATRO CUENTAS DE PRUEBA con contraseña conocida (una con rol hod y otra con rol medic)');

        $password = 'Test1234!';

        $accounts = [
            ['email' => 'test.coordinador@crewcare.test', 'role' => 'coordinator',   'name' => 'Test', 'lname' => 'Coordinador',    'puesto' => 'Producción-Coordinador de Producción'],
            ['email' => 'test.hod@crewcare.test',         'role' => 'hod',           'name' => 'Test', 'lname' => 'HOD',            'puesto' => 'Arte-Director de Arte'],
            ['email' => 'test.medico@crewcare.test',      'role' => 'medic',         'name' => 'Test', 'lname' => 'Médico',         'puesto' => 'Salud y Seguridad-Médico'],
            ['email' => 'test.crew@crewcare.test',        'role' => 'crew',          'name' => 'Test', 'lname' => 'Crew',           'puesto' => 'Eléctrico-Eléctrico'],
        ];

        foreach ($accounts as $a) {
            $user = User::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name'               => $a['name'],
                    'lname'              => $a['lname'],
                    'lname2'             => '(prueba)',
                    'sex'                => 'M',
                    'borndate'           => '1990-01-01',
                    'phone'              => '0000000000',
                    'zone'               => 'Demo',
                    'puestodepartamento' => $a['puesto'],
                    'ncreditos'          => 0,
                    'age'                => 0,   // legacy: "tiene foto" (0 = no)
                    'admin'              => 0,   // RBAC puro: sin bypass de admin
                    'daytest'            => 0,   // legacy: ya no decide el menú
                    'encuestadiaria'     => 0,
                    'activo'             => 1,
                    'imgperfil'          => 'avatar.png',
                    // COVID desacoplado (2026-07-07): enfermo/inline/tested/resultpcr/lastpcr retiradas
                    // (columnas eliminadas del schema). labn/lastwr se conservan.
                    'labn'               => 0,
                    'lastwr'             => Carbon::now(),
                    'password'           => Hash::make($password),
                ]
            );

            // syncRoles deja al usuario EXACTAMENTE con este rol (re-ejecutable).
            $user->syncRoles([$a['role']]);

            $this->command->info("  · {$a['email']}  →  rol {$a['role']}");
        }

        $this->command->info('Cuentas de prueba listas (4). Password de todas: '.$password);
    }
}
