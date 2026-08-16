<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base de las pruebas de QA que tocan la BD.
 *
 * - RefreshDatabase + $seed: en el primer test corre `migrate:fresh` + el chain de
 *   FABRICA (DatabaseSeeder) UNA vez; luego cada test va en una transaccion que se
 *   revierte (rapido, aislado). Asi cada prueba ve los catalogos sembrados pero sus
 *   escrituras no persisten.
 * - GUARD DURO: aborta ANTES de migrar si la conexion no apunta a `crewcare_test`.
 *   RefreshDatabase hace DROP de todas las tablas; jamas debe correr contra la BD real.
 */
abstract class QaTestCase extends TestCase
{
    use RefreshDatabase;

    /** Corre el DatabaseSeeder (chain de fabrica) tras migrate:fresh. */
    protected $seed = true;

    protected function setUp(): void
    {
        // Candado de seguridad ANTES de que RefreshDatabase pueda dropear nada.
        // Se acepta 'crewcare_test' o cualquier 'crewcare_test_*' (para correr verticales
        // en paralelo, cada uno con su propia BD). JAMAS la real 'crewcare' ni 'crewcare_migrate'.
        $target = $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;
        if (substr((string) $target, 0, 13) !== 'crewcare_test') {
            throw new \RuntimeException(
                "QaTestCase ABORTA: DB_DATABASE='{$target}', se esperaba 'crewcare_test[_*]'. " .
                "La suite NO corre para no arriesgar la BD real. Revisa phpunit.xml."
            );
        }

        parent::setUp();

        // Doble verificacion ya con la app booteada.
        $db = DB::connection()->getDatabaseName();
        if (substr((string) $db, 0, 13) !== 'crewcare_test') {
            $this->fail("QaTestCase: la conexion activa es '{$db}', no 'crewcare_test[_*]'.");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // FASE 3 — el render del contrato firmado corre al completar un sobre (ContractSigning::sign).
        // En pruebas NUNCA levantamos Chrome/Browsershot: sustituimos el motor PDF por un doble que
        // devuelve bytes deterministas. Así el flujo (guardar + sellar + evento) se ejerce sin Chrome
        // y sin colgar la suite con timeouts. Los tests que necesiten bytes concretos lo re-sustituyen.
        \App\Support\ContractSignedRenderer::$pdfEngine = fn (string $html) => 'TESTPDF:' . strlen($html);
    }

    /**
     * Crea un usuario con el rol dado (bypassa $fillable via forceCreate para poder
     * fijar `admin`). Password 'secret' por si algun flujo lo revalida.
     */
    protected function makeUser(string $role, array $attrs = []): User
    {
        $user = User::forceCreate(array_merge([
            'name'     => ucfirst($role) . ' QA',
            'email'    => $role . '-' . Str::random(8) . '@qa.test',
            'password' => Hash::make('secret'),
            'admin'    => $role === 'super-admin' ? 1 : 0,
            'activo'   => 1,
        ], $attrs));

        $user->assignRole($role);

        return $user;
    }

    /** Crea y autentica (sin password) un usuario del rol dado. */
    protected function actingAsRole(string $role, array $attrs = []): User
    {
        $user = $this->makeUser($role, $attrs);
        $this->actingAs($user);

        return $user;
    }
}
