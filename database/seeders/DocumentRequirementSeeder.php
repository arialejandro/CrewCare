<?php

namespace Database\Seeders;

use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\ProductionDocumentSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PASO 2 — siembra el PAQUETE estándar de la producción de la instancia + sus settings.
 * ADITIVO/idempotente (firstOrCreate): NO pisa lo que el owner ya editó por producción
 * (toggles is_required / fecha de corte). Solo crea las filas que falten.
 *
 * El "paquete" = todos los tipos BILLING quedan requeridos por defecto (fiscal + REPSE +
 * facturas de contrato); la producción togglea lo que no pida. REPSE entra al REQUISITO
 * de un contrato solo si ese contrato es REPSE (lo resuelve PayeePackage, no el seeder).
 */
class DocumentRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $productionId = DB::table('productions')->min('id');
        if (! $productionId) {
            return; // sin producción no hay nada que configurar (fresh sin ProductionDemoSeeder)
        }

        // Fecha de corte de la 32-D: default 1 SOLO al crear; nunca pisa el valor del owner.
        ProductionDocumentSetting::firstOrCreate(
            ['production_id' => $productionId],
            ['csf_cut_day' => 1]
        );

        $types = DocumentType::where('family', DocumentType::FAMILY_BILLING)
            ->orderBy('sort_order')->get();

        foreach ($types as $t) {
            DocumentRequirement::firstOrCreate(
                ['production_id' => $productionId, 'document_type_id' => $t->id],
                [
                    'applies_to'  => $t->legal_nature ?: DocumentRequirement::APPLIES_AMBAS,
                    'is_required' => 1,
                    'sort_order'  => $t->sort_order,
                ]
            );
        }
    }
}
