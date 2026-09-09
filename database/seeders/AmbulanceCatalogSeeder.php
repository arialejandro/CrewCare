<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\AmbulanceType;
use App\Models\AmbulanceInspectionPoint;

/**
 * AmbulanceCatalogSeeder — importa el catálogo de verificación de ambulancias
 * (NOM-034-SSA3-2013): 6 tipos + 92 puntos. Idempotente (updateOrCreate por
 * `code`). Requiere el esquema de 2026-08-08-ambulance-catalog.sql (delta #51).
 *
 * FUENTE: database/seeders/data/crewcare_ambulancias_catalogo.json — copia byte a
 * byte del JSON del owner (no se modifica; las correcciones vivirían AQUÍ).
 *
 * ESTADO SE DECLARA: el seeder NUNCA marca `verified_at` ni pisa `is_active`. Un
 * punto/tipo nace con verified_at NULL (redactado desde la NOM, sin auditar por
 * responsable sanitario); si el owner ya lo auditó o lo desactivó a mano, esos
 * campos se quedan como están (no se incluyen en el update).
 */
class AmbulanceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ambulance_types', 'ambulance_inspection_points'] as $t) {
            if (! Schema::hasTable($t)) {
                throw new \RuntimeException("Falta la tabla `$t`. Aplica primero database/owner-apply/2026-08-08-ambulance-catalog.sql");
            }
        }

        $path = database_path('seeders/data/crewcare_ambulancias_catalogo.json');
        if (! is_file($path)) {
            throw new \RuntimeException("No se encontró el catálogo: $path");
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['tipos']) || empty($data['puntos_de_verificacion'])) {
            throw new \RuntimeException('El JSON del catálogo de ambulancias no tiene la forma esperada (tipos / puntos_de_verificacion).');
        }

        $types = 0;
        foreach ($data['tipos'] as $i => $t) {
            AmbulanceType::updateOrCreate(
                ['code' => $t['code']],
                [
                    'name_es'             => $t['name_es'] ?? $t['code'],
                    'name_en'             => $t['name_en'] ?? null,
                    'rama'                => $t['rama'] ?? 'terrestre',
                    'level'               => $t['level'] ?? null,
                    'apendice'            => $t['apendice'] ?? null,
                    'personal_minimo'     => $t['personal_minimo'] ?? null,
                    'dimensiones_minimas' => $t['dimensiones_minimas'] ?? null,
                    'capacidad'           => $t['capacidad'] ?? null,
                    'sort_order'          => $i,
                    // is_active / verified_at NO se tocan (defaults en create; respeto en update).
                ]
            );
            $types++;
        }

        $points = 0;
        foreach ($data['puntos_de_verificacion'] as $i => $p) {
            AmbulanceInspectionPoint::updateOrCreate(
                ['code' => $p['code']],
                [
                    'text_es'           => $p['texto_punto'] ?? '',
                    'norm_ref'          => $p['referencia_norma'] ?? null,
                    'rama'              => $p['rama'] ?? 'todas',
                    'min_level'         => $p['min_level'] ?? null,
                    'max_level'         => $p['max_level'] ?? null,
                    'is_gate'           => ! empty($p['es_compuerta']),
                    'outcome_if_fail'   => $p['outcome_if_fail'] ?? null,
                    'origen'            => $p['origen'] ?? 'normativo',
                    'exigido_por'       => $p['exigido_por'] ?? null,
                    'trigger_scopes'    => array_values((array) ($p['disparadores'] ?? [])),
                    'requires_document' => ! empty($p['pide_documento']),
                    'nota'              => $p['nota'] ?? null,
                    'norm_code'         => $p['norma'] ?? null,
                    'sort_order'        => $i,
                    // is_active / verified_at NO se tocan.
                ]
            );
            $points++;
        }

        $this->command->info("AmbulanceCatalogSeeder: {$types} tipos, {$points} puntos (idempotente; verified_at intacto).");
    }
}
