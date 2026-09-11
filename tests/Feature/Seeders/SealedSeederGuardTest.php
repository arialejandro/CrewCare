<?php

namespace Tests\Feature\Seeders;

use Tests\QaTestCase;

/**
 * Candado de entorno de los seeders que RE-SELLAN documentos (2026-09-11). El único que reescribe
 * y re-sella tablas selladas es DemoProductionSeeder (el corpus demo): corrido en el servidor
 * re-sellaría documentos reales. Debe ABORTAR fuera de `local` (trait SoloEnLocal, primera línea de
 * run(), --force no lo salta). Este test lo prueba forzando el entorno a producción.
 */
class SealedSeederGuardTest extends QaTestCase
{
    public function test_demo_production_seeder_aborta_fuera_de_local(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $threw = false;
            try {
                (new \Database\Seeders\DemoProductionSeeder())->run();
            } catch (\RuntimeException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'DemoProductionSeeder debe abortar fuera de local: re-sella documentos.');
        } finally {
            $this->app['env'] = $original;
        }
    }
}
