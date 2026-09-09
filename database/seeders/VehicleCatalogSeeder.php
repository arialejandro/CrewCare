<?php

namespace Database\Seeders;

use App\Models\VehicleCheckPoint;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * VehicleCatalogSeeder — importa el catálogo de Transportación: 13 tipos + 52 puntos
 * (Bloque 1 = 51; +REM-005 calzas del remolcado, Bloque 2 §0).
 * Idempotente (updateOrCreate por `code`). Requiere el esquema de
 * 2026-08-24-transport-catalog.sql (o las migraciones create_vehicle_types / _check_points).
 *
 * FUENTE: database/seeders/data/crewcare_transporte_catalogo.json.
 *
 * ESTADO SE DECLARA: el seeder NUNCA marca `verified_at` ni pisa `is_active`. Un punto/tipo nace
 * con verified_at NULL (de oficio, sin auditar); si el owner ya lo editó o desactivó a mano, esos
 * campos se respetan (no van en el update).
 */
class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['vehicle_types', 'vehicle_check_points'] as $t) {
            if (! Schema::hasTable($t)) {
                throw new \RuntimeException("Falta la tabla `$t`. Aplica primero database/owner-apply/2026-08-24-transport-catalog.sql");
            }
        }

        $path = database_path('seeders/data/crewcare_transporte_catalogo.json');
        if (! is_file($path)) {
            throw new \RuntimeException("No se encontró el catálogo: $path");
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['tipos']) || empty($data['puntos'])) {
            throw new \RuntimeException('El JSON del catálogo de transporte no tiene la forma esperada (tipos / puntos).');
        }

        $types = 0;
        foreach ($data['tipos'] as $i => $t) {
            VehicleType::updateOrCreate(
                ['code' => $t['code']],
                [
                    'name_es'      => $t['name_es'] ?? $t['code'],
                    'name_en'      => $t['name_en'] ?? null,
                    'is_special'   => ! empty($t['is_special']),
                    'attr_profile' => (array) ($t['attr_profile'] ?? []),
                    'sort_order'   => $i,
                    // is_active / verified_at NO se tocan.
                ]
            );
            $types++;
        }

        // `supersedes` (sustitución, mismo mecanismo que herramientas) puede no existir aún si el
        // owner no aplicó 2026-08-24-transport-supersedes.sql: se incluye sólo si la columna está.
        $hasSupersedes = Schema::hasColumn('vehicle_check_points', 'supersedes');

        $points = 0;
        foreach ($data['puntos'] as $i => $p) {
            $attrs = [
                'grupo'          => $p['grupo'] ?? null,
                'module'         => $p['module'] ?? 'nucleo',
                'text_es'        => $p['text_es'] ?? '',
                'text_en'        => $p['text_en'] ?? null,
                'class'          => in_array(($p['class'] ?? 'minor'), VehicleCheckPoint::CLASSES, true) ? $p['class'] : 'minor',
                'requires_photo' => ! empty($p['requires_photo']),
                'applies_when'   => (isset($p['applies_when']) && trim((string) $p['applies_when']) !== '') ? $p['applies_when'] : null,
                'norm_id'        => null, // de oficio: nunca una norma
                'sort_order'     => $i,
                // is_active / verified_at NO se tocan.
            ];
            if ($hasSupersedes) {
                $attrs['supersedes'] = (isset($p['sustituye']) && trim((string) $p['sustituye']) !== '') ? $p['sustituye'] : null;
            }

            VehicleCheckPoint::updateOrCreate(['code' => $p['code']], $attrs);
            $points++;
        }

        $this->command->info("VehicleCatalogSeeder: {$types} tipos, {$points} puntos (idempotente; verified_at intacto).");
    }
}
