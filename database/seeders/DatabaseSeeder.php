<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Cimientos RBAC (idempotentes). El mapeo de usuarios va al final porque depende de que
        // la producción exista; BackfillUserPositions corre después del mapeo para enriquecer
        // department_id/position_id del pivote a partir de los títulos planos del legacy.
        //
        // ⚠ AQUÍ SÓLO VA LO QUE DEBE LLEGAR A UNA INSTANCIA DE CLIENTE.
        // `db:seed` a secas es lo primero que se corre al levantar una instancia, así que esta
        // lista es, de hecho, la definición de "lo que el producto trae de fábrica".
        //
        // (2026-07-24) Se sacó `TestAccountsSeeder`: creaba cuatro cuentas con una contraseña
        // conocida y pública —una con rol `hod`, otra con rol `medic`, que abre el expediente
        // clínico— y estaba en esta lista, o sea que se sembraban solas. Ahora se invoca a mano
        // y además aborta fuera del entorno local:
        //     php artisan db:seed --class=TestAccountsSeeder
        //
        // Por la misma razón NUNCA debe encadenarse aquí `DemoProductionSeeder` (el corpus de
        // demostración). Se invoca explícito y también tiene candado de entorno.
        $this->call([
            RolesAndPermissionsSeeder::class,
            OrgCatalogSeeder::class,
            ProductionDemoSeeder::class,
            MapExistingUsersSeeder::class,
            BackfillUserPositionsSeeder::class,
        ]);
    }
}
