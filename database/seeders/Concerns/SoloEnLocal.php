<?php

namespace Database\Seeders\Concerns;

/**
 * SoloEnLocal — candado de entorno para los seeders que siembran DATOS DE PRUEBA (2026-07-24).
 *
 * ============================ POR QUÉ ============================
 * Los seeders de CATÁLOGO (normas, eventos, departamentos, permisos, EPP) tienen que llegar a
 * una instancia de cliente: son parte del producto. Los de PRUEBA no, nunca. Y el riesgo no es
 * teórico: el seeder viaja dentro del código, así que un `db:seed` corrido en el lugar
 * equivocado —o una línea encadenada en DatabaseSeeder que nadie volvió a leer— siembra datos
 * falsos en la base de un cliente.
 *
 * Documentarlo NO ALCANZA. `TestAccountsSeeder` llevaba escrito en su propio comentario "No
 * tocar en instancias de cliente" y aun así estaba encadenado en DatabaseSeeder: bastaba un
 * `php artisan db:seed` para crear cuatro cuentas con una contraseña conocida. Un aviso que
 * depende de que alguien lo lea no es un control.
 *
 * ============================ CÓMO ============================
 * Primera línea de run(): `$this->exigirEntornoLocal('qué siembra');`.
 *
 * El candado mira `app()->environment('local')`, que sale de APP_ENV. `--force` NO lo salta:
 * esa bandera existe para confirmar un seed en producción, que es justo lo que aquí se prohíbe.
 * Si APP_ENV está mal puesto, el candado aborta — falla hacia el lado seguro.
 *
 * ⚠ LANZA EXCEPCIÓN, no devuelve false. La primera versión sólo imprimía el error y salía de
 * run(); `db:seed` entonces terminaba con "Database seeding completed successfully" y código de
 * salida 0. Quien lee la última línea —o un script de despliegue que mira el exit code— concluía
 * que sí había sembrado. Un candado que reporta éxito al bloquear es peor que no tenerlo.
 */
trait SoloEnLocal
{
    /**
     * @param  string $queSiembra  qué se estaba por sembrar (sale en el mensaje)
     * @return void
     * @throws \RuntimeException  si el entorno no es local
     */
    protected function exigirEntornoLocal($queSiembra = 'datos de prueba')
    {
        if (app()->environment('local')) {
            return;
        }

        throw new \RuntimeException(
            'ABORTADO: este seeder siembra ' . $queSiembra . ' y el entorno es "' . app()->environment()
            . '", no "local". Los datos de prueba NUNCA deben llegar a una instancia de cliente. '
            . 'Si de verdad quieres correrlo aquí, cambia APP_ENV a local a conciencia — no hay bandera que lo salte.'
        );
    }
}
