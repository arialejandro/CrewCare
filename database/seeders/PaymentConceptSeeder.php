<?php

namespace Database\Seeders;

use App\Models\PaymentConcept;
use Illuminate\Database\Seeder;

/**
 * Siembra los CONCEPTOS DE PAGO conocidos como default GLOBAL (production_id NULL → visibles a todas
 * las producciones). Editable después: cada producción agrega/oculta los suyos. Idempotente por `code`
 * global (updateOrCreate) → correr de nuevo no duplica.
 */
class PaymentConceptSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = [
            ['code' => 'SEM', 'name' => 'Honorario', 'description' => 'Honorario semanal (sueldo/servicio de la semana).', 'sort_order' => 10],
            ['code' => 'CA',  'name' => 'Car Allowance', 'description' => 'Renta de vehículo personal usado como contraprestación.', 'sort_order' => 20],
            ['code' => 'BOX', 'name' => 'Box Rental', 'description' => 'Renta de equipo, medicamentos y herramientas propias.', 'sort_order' => 30],
        ];

        foreach ($concepts as $c) {
            PaymentConcept::updateOrCreate(
                ['production_id' => null, 'code' => $c['code']],
                ['name' => $c['name'], 'description' => $c['description'], 'sort_order' => $c['sort_order'], 'is_active' => 1]
            );
        }
    }
}
