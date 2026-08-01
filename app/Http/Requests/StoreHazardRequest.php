<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * (2026-07-09) Validación ESTRICTA de la Acción Insegura / Notificación de Peligro.
 *
 * Cierra la brecha "obligatoriedad solo del lado cliente" detectada en la
 * auditoría: production_name, name_loc (locación), date_observed, time_observed y
 * la descripción pasan a required|string en el SERVIDOR. Las coordenadas GPS NO son
 * obligatorias duro (el navegador puede fallar o denegar el permiso): si faltan, la
 * justificación de ubicación con referencias exactas pasa a ser obligatoria
 * (required_without:latitude). `standards` valida contra safety_standards (N:M).
 */
class StoreHazardRequest extends FormRequest
{
    public function authorize()
    {
        // El permiso/rol se controla en la definición de la ruta.
        return true;
    }

    public function rules()
    {
        // (2026-07-14) Pilar 1 — captura en 2 fases. Reglas BASE compartidas: definen los
        // FORMATOS/tipos (que valen igual en móvil y en back-office). La OBLIGATORIEDAD es
        // lo único que cambia entre fases y se aplica más abajo según el flag.
        $rules = [
            'production_name'               => 'nullable|string|max:255',
            // (2026-07-23) Locación SIEMPRE obligatoria (decisión owner), incluso en captura ágil:
            // el GPS puede fallar, pero la ubicación no puede quedar vacía. Homologado con la Condición.
            'name_loc'                      => 'required|string|max:255',
            // (2026-07-13) GPS NO obligatorio duro: el navegador puede fallar/denegar el
            // permiso. En modo estricto, si faltan coordenadas `manual_location_justification`
            // pasa a ser obligatoria (required_without:latitude, abajo) como respaldo trazable.
            'latitude'                      => 'nullable|numeric|between:-90,90',
            'longitude'                     => 'nullable|numeric|between:-180,180',
            'gps_address'                   => 'nullable|string|max:500',
            // (2026-07-12) Módulo 11: referencias exactas de ubicación (respaldo del GPS).
            'manual_location_justification' => 'nullable|string|max:1000',
            'date_observed'                 => 'nullable|date',
            'time_observed'                 => 'nullable',
            'location_hazard_unsafe_act'    => 'nullable|string',
            // Fase 1 exige SOLO la descripción del acto inseguro (captura ágil en set).
            'description_hazard_unsafe_act' => 'required|string',
            'action_taken'                  => 'nullable|string',
            'suggestions_corrective_action' => 'nullable|string',
            'main_image'                    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:12288',
            'additional_images'             => 'nullable|array',
            'additional_images.*'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:12288',
            // (2026-07-13) Catálogo ÚNICO de eventos: sustituye a category_name y standards[].
            // 'nullable|integer' (NO exists:) a propósito, para no romper PROD antes del SQL;
            // el trait applyHazardEvent() valida contra hazard_events de forma defensiva.
            'hazard_event_id'               => 'nullable|integer',
            'risk_level'                    => 'nullable|in:Bajo,Medio,Alto,Extremo',
            'action_status'                 => 'nullable|in:Abierto,En proceso,Cerrado',
            // Matriz 5×5 (ejes; el servidor calcula risk_level). Nullable en Fase 1: se
            // completa en back-office (Fase 2 / edit()). Si falta => pending_compliance=1.
            'likelihood'                    => 'nullable|in:A,B,C,D,E',
            'consequence'                   => 'nullable|integer|between:1,5',
            // (2026-07-24) PASO 2/2. scouting_report_id NO se valida aquí: se resuelve server-side
            // desde el GPS (no es un input de usuario). El resto son opcionales.
            'involved_user_id'              => 'nullable|integer|exists:users,id',
            'related_unsafecond_id'         => 'nullable|integer|exists:unsafeconds,id',
            'human_factor'                  => 'nullable|array',
            'human_factor.*'                => 'string|in:' . implode(',', array_keys(\App\Models\hazardnotification::HUMAN_FACTORS)),
            'corrective_owner_id'           => 'nullable|integer|exists:users,id',
            'corrective_due_date'           => 'nullable|date',
        ];

        // (2026-07-14) Fase 1 (progressive_capture ON, default): reglas MÍNIMAS — solo la
        // descripción es obligatoria; TODO lo demás (incluida la matriz 5×5 y el GPS) es
        // nullable para que el llenado en set sea ágil y sin fricción.
        if (\App\Support\Features::enabled('progressive_capture')) {
            return $rules;
        }

        // Flag OFF → comportamiento ESTRICTO legado: obligatoriedad completa server-side
        // (cierra la brecha "obligatoriedad solo del lado cliente") y GPS/justificación.
        return array_merge($rules, [
            'production_name'               => 'required|string|max:255',
            'name_loc'                      => 'required|string|max:255',
            // (2026-07-23) Ya NO required_without:latitude — GPS es nullable en ambos gemelos
            // (decisión owner) y name_loc obligatorio ya garantiza que la ubicación conste.
            'manual_location_justification' => 'nullable|string|max:1000',
            'date_observed'                 => 'required|date',
            'time_observed'                 => 'required',
            'location_hazard_unsafe_act'    => 'required|string',
        ]);
    }

    public function messages()
    {
        return [
            'production_name.required'               => 'El nombre de la producción es obligatorio.',
            'name_loc.required'                      => 'La locación es obligatoria.',
            'date_observed.required'                 => 'La fecha observada es obligatoria.',
            'time_observed.required'                 => 'La hora observada es obligatoria.',
            'location_hazard_unsafe_act.required'    => 'Indica dónde se observó la acción insegura.',
            'description_hazard_unsafe_act.required' => 'La descripción del acto inseguro es obligatoria.',
        ];
    }

    /**
     * (2026-07-23) Blindaje anti-"solo espacios": `required` de Laravel acepta una cadena de
     * puros espacios. Se recortan los campos de texto ANTES de validar ("   " → "" → dispara el
     * mensaje de obligatorio) y de paso se normaliza lo guardado. Solo cubre store (que usa este
     * FormRequest); update() replica el trim en el controller.
     */
    protected function prepareForValidation()
    {
        foreach (['production_name', 'name_loc', 'location_hazard_unsafe_act', 'description_hazard_unsafe_act'] as $f) {
            if (is_string($this->input($f))) {
                $this->merge([$f => trim($this->input($f))]);
            }
        }
    }
}
