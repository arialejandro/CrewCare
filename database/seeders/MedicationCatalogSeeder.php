<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medication;

/**
 * MedicationCatalogSeeder — siembra inicial del catálogo de medicamentos (variantes).
 * Basado en medicamentos comunes de set (ver bitácora médica de referencia). Idempotente:
 * firstOrCreate por (name,dosage,presentation), no duplica. El catálogo luego crece solo
 * cuando el médico registra variantes nuevas desde la consulta (modo híbrido).
 *
 * Correr:  php artisan db:seed --class=MedicationCatalogSeeder
 */
class MedicationCatalogSeeder extends Seeder
{
    public function run()
    {
        // [name, dosage, presentation]
        $items = [
            ['Paracetamol', '250mg', 'Tableta'],
            ['Paracetamol', '500mg', 'Tableta'],
            ['Paracetamol', '1000mg', 'Tableta'],
            ['Paracetamol', '500mg', 'Efervescente'],
            ['Ibuprofeno', '400mg', 'Tableta'],
            ['Ibuprofeno', '600mg', 'Tableta'],
            ['Ibuprofeno', '600mg', 'Cápsula'],
            ['Meloxicam/Metocarbamol', '', 'Tableta'],
            ['Metoclopramida', '', 'Ampolleta'],
            ['Difenidol', '', 'Ampolleta'],
            ['Trimebutina/Simeticona', '', 'Tableta'],
            ['Magaldrato/Dimeticona', '80mg/10mg', 'Suspensión'],
            ['Clorfenamina', '', 'Tableta'],
            ['Cloropiramina', '', 'Tableta'],
            ['Ketorolaco', '30mg', 'Sublingual'],
            ['Ketorolaco', '30mg', 'Ampolleta'],
            ['Diclofenaco', '', 'Tópico'],
            ['Hipromelosa', '', 'Gotas'],
            ['Nitrofural (Furacín)', '', 'Tópico'],
            ['Sulfadiazina de plata', '', 'Tópico'],
            ['Microdacyn', '', 'Solución'],
            ['Agrifen', '', 'Tableta'],
            ['Riopan', '', 'Gel'],
            ['Andantol', '', 'Gel'],
            ['Benzonatato', '100mg', 'Cápsula'],
        ];

        $nuevos = 0;
        foreach ($items as [$name, $dosage, $presentation]) {
            $m = Medication::firstOrCreate(
                ['name' => $name, 'dosage' => $dosage, 'presentation' => $presentation],
                ['active' => 1]
            );
            if ($m->wasRecentlyCreated) {
                $nuevos++;
            }
        }

        $this->command->info("MedicationCatalogSeeder: catálogo sembrado ({$nuevos} nuevos, ".Medication::count()." variantes totales).");
    }
}
