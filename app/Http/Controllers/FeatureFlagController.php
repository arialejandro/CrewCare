<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Features;

/**
 * FeatureFlagController — panel de Feature Flags (Pilar 5).
 *
 * Muestra el mapa completo de features (config fusionado con overrides de BD, vía
 * App\Support\Features::all()) y permite encender/apagar cada módulo por proyecto.
 * Los cambios se persisten en la tabla `feature_flags` (upsert) y se refleja el
 * cache in-request con Features::flush(). DEFENSIVO: la escritura va detrás de
 * Schema::hasTable('feature_flags') → en PROD sin el SQL simplemente no truena.
 */
class FeatureFlagController extends Controller
{
    /**
     * Etiquetas legibles por key. Las de 'futuro' vienen apagadas por defecto.
     *
     * @return array
     */
    protected function labels()
    {
        return [
            'progressive_capture' => 'Captura ágil en 2 fases',
            'magic_links'         => 'Magic Links (WhatsApp)',
            'dsr_injection'       => 'Inyección de eventos al DSR',
            'sds_sfx'             => 'Módulo SDS / Efectos Especiales',
            'medical_addendum'    => 'Addendums médicos',
            'location_handover'   => 'Handover de locaciones — futuro',
            'contracts_queue_email'   => 'Contratos · correos de firma en segundo plano (cola)',
            'contracts_batch_signing' => 'Contratos · firma en lote (varios a la vez)',
            'contracts_queue_render'  => 'Contratos · render del contrato firmado en segundo plano (cola)',
        ];
    }

    /**
     * Pantalla del panel.
     */
    public function index()
    {
        $flags  = Features::all();     // mapa key => bool (config + overrides BD)
        $labels = $this->labels();     // etiquetas legibles

        return view('admin.features.index', compact('flags', 'labels'));
    }

    /**
     * Guarda el estado de cada flag conocido.
     */
    public function update(Request $request)
    {
        // Guard defensivo: sin la tabla, no hay nada que persistir.
        if (! Schema::hasTable('feature_flags')) {
            return redirect()->route('features.index')
                ->with('warning', 'La tabla de feature flags no existe todavía en esta instancia.');
        }

        // Sólo tocamos las keys que Features conoce (config + overrides ya existentes).
        foreach (array_keys(Features::all()) as $key) {
            $val = $request->boolean('flag_' . $key);

            DB::table('feature_flags')->updateOrInsert(
                ['key' => $key],
                ['enabled' => $val ? 1 : 0, 'updated_at' => now()]
            );
        }

        // Limpia el cache por-request para que el panel refleje el nuevo estado ya.
        Features::flush();

        return redirect()->route('features.index')
            ->with('success', 'Módulos actualizados.');
    }
}
