<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\HazardEvent;
use App\Models\SafetyStandard;

/**
 * HazardEventSeeder — siembra el catálogo ÚNICO de "eventos posibles" (2026-07-13).
 *
 * Lee los archivos de datos por contexto en database/seeders/data/:
 *   hazard_events_location.php, _film_set.php, _construction.php,
 *   _set_build.php, _transversal.php
 * cada uno retorna un array de filas:
 *   ['code','context','category','name_es','name_en','description_es',
 *    'description_en','default_likelihood','default_consequence','standard_codes'=>[...]]
 *
 * IDEMPOTENTE: updateOrCreate por `code`; el N:N se re-sincroniza con sync() (no
 * duplica). Re-correr no cambia nada. Requiere:
 *   1) el ALTER de 2026-07-13-hazard-events-catalog.sql (tabla hazard_events + pivote)
 *   2) SafetyCatalogSeeder corrido antes (para resolver standard_codes → id)
 * DEFENSIVO: si la tabla aún no existe (PROD sin SQL), avisa y NO truena.
 *
 * Correr:  php artisan db:seed --class=HazardEventSeeder
 */
class HazardEventSeeder extends Seeder
{
    public function run()
    {
        if (!Schema::hasTable('hazard_events')) {
            $this->command->warn('HazardEventSeeder: la tabla `hazard_events` NO existe aún → aplica 2026-07-13-hazard-events-catalog.sql y vuelve a correr. Omitido.');
            return;
        }

        $dir = database_path('seeders/data');
        $files = [
            'hazard_events_location.php',
            'hazard_events_film_set.php',
            'hazard_events_construction.php',
            'hazard_events_set_build.php',
            'hazard_events_transversal.php',
        ];

        // 1) Cargar y fusionar todas las filas de eventos.
        $rows = [];
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (!is_file($path)) {
                $this->command->warn("HazardEventSeeder: archivo de datos faltante → {$file} (omitido).");
                continue;
            }
            $data = require $path;
            if (!is_array($data)) {
                $this->command->warn("HazardEventSeeder: {$file} no retornó un array (omitido).");
                continue;
            }
            foreach ($data as $row) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            $this->command->warn('HazardEventSeeder: no se cargó ningún evento (¿faltan los archivos de datos?).');
            return;
        }

        // 2) Resolver TODOS los códigos normativos a id en una sola consulta.
        $allCodes = [];
        foreach ($rows as $row) {
            foreach (($row['standard_codes'] ?? []) as $code) {
                $allCodes[$code] = true;
            }
        }
        $codeToId = SafetyStandard::whereIn('regulation_code', array_keys($allCodes))
            ->pluck('id', 'regulation_code')
            ->toArray();

        $hasPivot = Schema::hasTable('hazard_event_standard');
        $unresolved = [];
        $order = 0;
        $linksTotal = 0;

        foreach ($rows as $row) {
            $order++;
            $event = HazardEvent::updateOrCreate(
                ['code' => $row['code']],
                [
                    'context'             => $row['context'] ?? 'transversal',
                    'category'            => $row['category'] ?? null,
                    'name_es'             => $row['name_es'] ?? $row['code'],
                    'name_en'             => $row['name_en'] ?? null,
                    'description_es'      => $row['description_es'] ?? null,
                    'description_en'      => $row['description_en'] ?? null,
                    'default_likelihood'  => $row['default_likelihood'] ?? null,
                    'default_consequence' => $row['default_consequence'] ?? null,
                    'sort_order'          => $order,
                    'is_active'           => 1,
                ]
            );

            // 3) Sincronizar el pivote N:N evento ↔ norma.
            if ($hasPivot) {
                $ids = [];
                foreach (($row['standard_codes'] ?? []) as $code) {
                    if (isset($codeToId[$code])) {
                        $ids[] = $codeToId[$code];
                    } else {
                        $unresolved[$code] = true;
                    }
                }
                $event->standards()->sync(array_unique($ids));
                $linksTotal += count(array_unique($ids));
            }
        }

        $this->command->info('HazardEventSeeder: '.HazardEvent::count().' eventos sincronizados ('.count($rows).' filas procesadas).');
        if ($hasPivot) {
            $this->command->info("HazardEventSeeder: {$linksTotal} vínculos evento↔norma en hazard_event_standard.");
        } else {
            $this->command->warn('HazardEventSeeder: la tabla `hazard_event_standard` NO existe → N:N OMITIDO.');
        }
        if (!empty($unresolved)) {
            $this->command->warn('HazardEventSeeder: códigos normativos NO encontrados en safety_standards (revisa que SafetyCatalogSeeder corrió): '.implode(', ', array_keys($unresolved)));
        }
    }
}
