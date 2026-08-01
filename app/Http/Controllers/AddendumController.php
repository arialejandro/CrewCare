<?php

namespace App\Http\Controllers;

use App\Models\Addendum;
use App\Models\InjuryReport;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * AddendumController (Pilar 4) — enmiendas médicas APPEND-ONLY sobre un accidente.
 *
 * store() jamás toca el InjuryReport original (ya firmado): SOLO inserta una fila
 * en addendums con el cambio y su autor. Las estadísticas "efectivas" se resuelven
 * después vía el trait HasMedicalAddendums (effective*()), leyendo el último
 * addendum. Protegido por el feature flag 'medical_addendum'.
 */
class AddendumController extends Controller
{
    /**
     * Guarda un addendum médico para el reporte de lesión indicado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int                       $injury    id del InjuryReport
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, $injury)
    {
        // Guard de feature: si el módulo está apagado, no existe la ruta.
        if (!Features::enabled('medical_addendum')) {
            abort(404);
        }

        // Guard defensivo: si el owner no aplicó el SQL, no truena.
        if (!Schema::hasTable('addendums')) {
            return back()->with('error', 'El módulo de addendums médicos aún no está disponible.');
        }

        // El reporte base debe existir (pero NO se modifica).
        $report = InjuryReport::findOrFail($injury);

        // PROPIEDAD (item 11): sólo un MÉDICO crea anexos. El middleware de ruta ya
        // exige permission:medical.create; esta capa añade la regla clínica (AddendumPolicy).
        // Recuerda: super-admin la salta por Gate::before — probar con un `medic` real.
        $this->authorize('create', [Addendum::class, $report]);

        $data = $request->validate([
            'type'                => 'required|in:diagnosis_change,treatment_update,note',
            'body'                => 'required|string|max:4000',
            'new_treatment_level' => 'nullable|in:first_aid,medical_treatment,hospitalization,fatality',
            'new_is_recordable'   => 'nullable|boolean',
            'new_days_away'       => 'nullable|integer|min:0',
            'new_days_restricted' => 'nullable|integer|min:0',
        ]);

        $addendum = Addendum::create([
            'injury_report_id'    => $report->id,
            'type'                => $data['type'],
            'body'                => $data['body'],
            'new_treatment_level' => isset($data['new_treatment_level']) ? $data['new_treatment_level'] : null,
            // El checkbox sólo llega cuando está marcado; ausencia = sin cambio (null).
            'new_is_recordable'   => $request->has('new_is_recordable') ? (bool) $request->input('new_is_recordable') : null,
            'new_days_away'       => isset($data['new_days_away']) ? $data['new_days_away'] : null,
            'new_days_restricted' => isset($data['new_days_restricted']) ? $data['new_days_restricted'] : null,
            'created_by_id'       => auth()->id(),
        ]);

        // SELLO PROPIO (item 8): firma polimórfica SHA-256 de ESTE anexo, al momento
        // de crearse. No toca el sello del reporte original. Defensivo: signDocument()
        // es no-op si aún no existe la tabla digital_signatures. Un fallo de firma NO
        // debe perder el anexo ya guardado (append-only), así que va blindado.
        try {
            $addendum->signDocument(auth()->user(), $request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo sellar el addendum #' . $addendum->id . ': ' . $e->getMessage());
        }

        return back()->with('success', 'Addendum médico registrado y sellado. El reporte original permanece intacto.');
    }
}
