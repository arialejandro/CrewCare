<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * (2026-07-09) Validación ESTRICTA de la Condición Insegura.
 *
 * Cierra la brecha "todo nullable" detectada en la auditoría: los campos clave
 * pasan a required|string en el SERVIDOR y las coordenadas GPS son obligatorias
 * (con captura manual de respaldo en el form).
 *
 * (2026-07-13) Coherencia (post auditoría): se corrigió la discrepancia label↔columna.
 * `production_name` vuelve a significar "Producción" (nombre del proyecto, igual que
 * Hazard) y la persona o departamento involucrado se captura ahora en la columna
 * dedicada `involved_department` (catálogo Department; valor libre 'Otro' permitido).
 *
 * (2026-07-14) Pilar 1 — Progressive Disclosure (captura en 2 fases):
 *   - Fase 1 (store/móvil, flag `progressive_capture` ON = default): sólo la
 *     descripción es obligatoria; TODO lo demás es NULLABLE (captura ágil en set).
 *     El GPS NO bloquea el submit. El controller marca pending_compliance=1 si la
 *     matriz 5×5 llega incompleta.
 *   - Fase 2 (edit/update en back-office, o flag OFF): validación ESTRICTA (el set
 *     completo de compliance) vía strictRules(). update() la usa SIEMPRE.
 * rules() elige según el flag; strictRules()/minimalRules() son públicas y estáticas
 * para que el controller reutilice la ESTRICTA en update() (Fase 2).
 */
class StoreUnsafeConditionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // Pilar 1: si la captura progresiva está encendida, store() aplica el set MÍNIMO
        // (Fase 1). Si está apagada, conserva las reglas ESTRICTAS actuales.
        return \App\Support\Features::enabled('progressive_capture')
            ? self::minimalRules()
            : self::strictRules();
    }

    /**
     * Fase 1 (captura ágil en set): sólo la descripción es obligatoria; el resto NULLABLE.
     * El GPS NO se exige aquí (nunca bloquea el submit); se captura en background.
     *
     * @return array
     */
    public static function minimalRules()
    {
        return [
            'production_name'          => 'nullable|string|max:255',
            // (2026-07-24) `involved_department` retirado (la condición se ancla al lugar).
            // (2026-07-23) Locación SIEMPRE obligatoria (decisión owner): aun en captura
            // ágil, un reporte de seguridad no puede quedar sin ubicación.
            'name_loc'                 => 'required|string|max:255',
            // GPS silencioso: opcional en Fase 1 (nunca bloquea el submit).
            'latitude'                 => 'nullable|numeric|between:-90,90',
            'longitude'                => 'nullable|numeric|between:-180,180',
            'gps_address'              => 'nullable|string|max:500',
            'manual_location_justification' => 'nullable|string|max:1000',
            'date_observed'            => 'nullable|date',
            'time_observed'            => 'nullable',
            'unsafe_act_notify'        => 'nullable|in:0,1',
            'date_notify_unsafe_act'   => 'nullable|date',
            'location_unsafe_cond'     => 'nullable|string',
            // Lo ÚNICO obligatorio en Fase 1: la descripción de la condición insegura.
            'description_unsafe_cond'  => 'required|string',
            'action_taken'             => 'nullable|string',
            'corrective_action'        => 'nullable|string',
            'main_image'               => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'additional_images'        => 'nullable|array',
            'additional_images.*'      => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'hazard_event_id'          => 'nullable|integer',
            'category_name'            => 'nullable|string',
            'risk_level'               => 'nullable|in:Bajo,Medio,Alto,Extremo',
            'action_status'            => 'nullable|in:Abierto,En proceso,Cerrado',
            'likelihood'               => 'nullable|in:A,B,C,D,E',
            'consequence'              => 'nullable|integer|between:1,5',
            'standards'                => 'nullable|array',
            'standards.*'              => 'nullable|integer',
        ] + self::step2Rules();
    }

    /**
     * Fase 2 / flag OFF: validación ESTRICTA (set completo de compliance). Es el
     * conjunto de reglas histórico; update() lo usa SIEMPRE (back-office).
     *
     * @return array
     */
    public static function strictRules()
    {
        return [
            'production_name'          => 'required|string|max:255', // Producción (nombre del proyecto), igual que Hazard
            // (2026-07-13) Coherencia: persona o departamento involucrado (catálogo Department;
            // se admite el valor libre 'Otro'). Columna VARCHAR snapshot; guardado defensivo en el controller.
            'involved_department'      => 'nullable|string|max:255',
            'name_loc'                 => 'required|string|max:255',
            // (2026-07-23) GPS NULLABLE en AMBOS gemelos (decisión owner): en sótano/foro
            // apantallado/sin señal el GPS falla, y bloquear un reporte de seguridad por eso es
            // peor que aceptarlo sin coordenadas. La LOCACIÓN (name_loc) es el campo obligatorio.
            'latitude'                 => 'nullable|numeric|between:-90,90',
            'longitude'                => 'nullable|numeric|between:-180,180',
            'gps_address'              => 'nullable|string|max:500',
            // (2026-07-12) Módulo 11: referencias exactas de ubicación (respaldo opcional del GPS).
            // (2026-07-23) Ya NO es required_without:latitude — con name_loc obligatorio, exigir
            // además una justificación cuando falta el GPS reintroduciría el bloqueo que se quitó.
            'manual_location_justification' => 'nullable|string|max:1000',
            'date_observed'            => 'required|date',
            'time_observed'            => 'required',
            'unsafe_act_notify'        => 'nullable|in:0,1',
            'date_notify_unsafe_act'   => 'nullable|date|required_if:unsafe_act_notify,1',
            'location_unsafe_cond'     => 'required|string',
            'description_unsafe_cond'  => 'required|string',
            'action_taken'             => 'nullable|string',
            'corrective_action'        => 'nullable|string',
            'main_image'               => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'additional_images'        => 'nullable|array',
            'additional_images.*'      => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            // (2026-07-13) Catálogo único de eventos posibles. 'nullable' (NO 'exists')
            // a propósito: no romper PROD antes de aplicar el SQL de hazard_events.
            'hazard_event_id'          => 'nullable|integer',
            // (2026-07-13) category_name y standards[] ya NO se envían (los reemplaza el
            // event-picker); se dejan nullable por compatibilidad si algún cliente los manda.
            'category_name'            => 'nullable|string',
            'risk_level'               => 'nullable|in:Bajo,Medio,Alto,Extremo',
            'action_status'            => 'nullable|in:Abierto,En proceso,Cerrado',
            // Matriz 5×5 (ejes; el servidor calcula risk_level).
            'likelihood'               => 'nullable|in:A,B,C,D,E',
            'consequence'              => 'nullable|integer|between:1,5',
            // Vínculo N:M a normas (legacy; ahora las adjunta applyHazardEvent desde el evento).
            'standards'                => 'nullable|array',
            'standards.*'              => 'nullable|integer',
        ] + self::step2Rules();
    }

    /**
     * (2026-07-24) PASO 2/2 — reglas de los campos NUEVOS, compartidas por minimal y strict (todas
     * opcionales). scouting_report_id NO se valida aquí: se resuelve server-side desde el GPS.
     *
     * @return array
     */
    public static function step2Rules()
    {
        // (2026-07-24) `involved_user_id` retirado: la condición se ancla al LUGAR, no a una persona.
        return [
            'related_hazard_id'   => 'nullable|integer|exists:hazardnotifications,id',
            'is_recurrent'        => 'nullable|in:0,1',
            'corrective_owner_id' => 'nullable|integer|exists:users,id',
            'corrective_due_date' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            'production_name.required'         => 'El nombre de la producción es obligatorio.',
            'name_loc.required'                => 'La locación es obligatoria.',
            'date_observed.required'           => 'La fecha observada es obligatoria.',
            'time_observed.required'           => 'La hora observada es obligatoria.',
            'date_notify_unsafe_act.required_if'=> 'Indica la fecha de la acción insegura relacionada.',
            'location_unsafe_cond.required'    => 'Indica dónde se observó la condición insegura.',
            'description_unsafe_cond.required' => 'La descripción de la condición insegura es obligatoria.',
        ];
    }

    /**
     * (2026-07-23) Blindaje anti-"solo espacios": `required` de Laravel deja pasar una cadena
     * de puros espacios. Se recortan los campos de texto ANTES de validar, así "   " → "" y
     * dispara el mensaje de obligatorio (y de paso se normaliza lo que se guarda). Solo toca
     * el path de creación (store usa este FormRequest); update() replica el trim en el controller.
     * NO recorta involved_department (regla owner: no tocar el campo involucrado en este paso).
     */
    protected function prepareForValidation()
    {
        foreach (['production_name', 'name_loc', 'location_unsafe_cond', 'description_unsafe_cond'] as $f) {
            if (is_string($this->input($f))) {
                $this->merge([$f => trim($this->input($f))]);
            }
        }
    }
}
