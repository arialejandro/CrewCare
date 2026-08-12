<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Vistas por ROL: inicia sesión como un usuario de cada rol y saca screenshot de su
 * dashboard (que es role-diferenciado por permisos), verificando que la vista renderiza
 * sin error 500 y sin rebotar a login. Los usuarios los crea TestAccountsSeeder
 * (7 roles, password Test1234!, admin=0 = RBAC puro) + el super-admin admin@127.0.0.1.
 *
 * Correr: php artisan dusk --filter=RoleViewsTest
 */
class RoleViewsTest extends DuskTestCase
{
    private array $roles = [
        'super-admin'    => 'admin@127.0.0.1',
        'line-producer'  => 'test.lineproducer@crewcare.test',
        'coordinator'    => 'test.coordinador@crewcare.test',
        'safety-officer' => 'test.safety@crewcare.test',
        'hod'            => 'test.hod@crewcare.test',
        'medic'          => 'test.medico@crewcare.test',
        'auditor'        => 'test.auditor@crewcare.test',
        'crew'           => 'test.crew@crewcare.test',
    ];

    private const ERROR_MARKERS = [
        'SQLSTATE[', 'QueryException', 'ErrorException', 'ParseError',
        'Call to undefined', 'Undefined variable $', 'Class &quot;App', 'Whoops, looks like',
    ];

    public function test_dashboard_por_rol_renderiza(): void
    {
        $fail = [];

        $this->browse(function (Browser $browser) use (&$fail) {
            foreach ($this->roles as $role => $email) {
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $fail[$role] = 'usuario no existe';
                    continue;
                }
                try {
                    $browser->loginAs($user)->visit('/')->pause(700)->screenshot('role-' . $role);
                    if (str_contains($browser->driver->getCurrentURL(), '/login')) {
                        $fail[$role] = 'rebotó a login';
                        continue;
                    }
                    $src = $browser->driver->getPageSource();
                    foreach (self::ERROR_MARKERS as $marker) {
                        if (str_contains($src, $marker)) {
                            $fail[$role] = 'error: ' . $marker;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $fail[$role] = get_class($e) . ': ' . substr($e->getMessage(), 0, 100);
                }
            }
        });

        fwrite(STDERR, "\n=== DASHBOARD POR ROL ===\n");
        foreach ($this->roles as $role => $email) {
            fwrite(STDERR, sprintf("%-16s %s\n", $role, $fail[$role] ?? 'OK'));
        }

        $this->assertEmpty($fail, 'Roles con problema: ' . json_encode($fail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
