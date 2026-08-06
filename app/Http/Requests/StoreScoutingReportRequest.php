<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * (2026-07-09) Validación del Scouting / Location Risk Assessment con la regla
 * condicional SB-132: si se declaran ACTIVIDADES ESPECIALES (special_activities),
 * el desglose sb132_details DEBE traer activity_type, scene_number y
 * certified_personnel_required (Specific Risk Assessment obligatorio).
 *
 * Las reglas base (baseRules/baseMessages) y el chequeo SB-132 (sb132Errors) son
 * ESTÁTICOS para que update() del controlador los reutilice sin duplicar (store()
 * usa este FormRequest directamente; update() sigue con validateReport()).
 */
class StoreScoutingReportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return self::baseRules();
    }

    public function messages()
    {
        return self::baseMessages();
    }

    /**
     * Hook after: agrega los errores SB-132 al validador de store().
     */
    public function withValidator($validator)
    {
        $request = $this;
        $validator->after(function ($v) use ($request) {
            foreach (self::sb132Errors($request) as $key => $msg) {
                $v->errors()->add($key, $msg);
            }
        });
    }

    /**
     * Reglas compartidas por store() (FormRequest) y update() (validateReport).
     *
     * @return array
     */
    public static function baseRules()
    {
        return [
            'production_id'       => 'nullable|integer',
            'production_type'     => 'nullable|string|max:40',
            'manager_name'        => 'nullable|string|max:255',
            'safety_rep_name'     => 'nullable|string|max:255',
            'location_name'       => 'required|string|max:255',
            'location_address'    => 'nullable|string|max:500',
            'latitude'            => 'nullable|numeric|between:-90,90',
            'longitude'           => 'nullable|numeric|between:-180,180',
            'scene'               => 'nullable|string|max:255',
            'date_prep'           => 'nullable|date',
            'date_shoot'          => 'nullable|date',
            'date_wrap'           => 'nullable|date',
            'loc_setting'         => 'nullable|string|max:30',
            'shoot_time'          => 'nullable|string|max:30',
            'complexity'          => 'nullable|string|max:20',

            // Capa (1) Emergencia
            'nearest_hospital'    => 'nullable|string|max:255',
            'hospital_address'    => 'nullable|string|max:500',
            'hospital_eta'        => 'nullable|string|max:50',
            'hospital_distance_km'=> 'nullable|numeric|min:0|max:9999.99',
            'emergency_access'    => 'nullable|string|max:500',
            'assembly_point'      => 'nullable|string|max:255',
            'ambulance_company'   => 'nullable|string|max:255',
            'emergency_phone'     => 'nullable|string|max:50',

            // Capa (2) Peligros — tabla de riesgo 5×5 (arrays repetibles)
            // (2026-07-13) HOMOLOGACIÓN: cada fila elige un EVENTO del catálogo único
            // (hazard_events). El evento aporta la categoría y su(s) norma(s); reemplaza
            // los antiguos hz_key (13 categorías) + hz_category_name (norma manual).
            'hz_event_id'         => 'nullable|array',
            'hz_event_id.*'       => 'nullable|integer',
            'hz_hazard'           => 'nullable|array',
            'hz_likelihood'       => 'nullable|array',
            'hz_consequence'      => 'nullable|array',
            'hz_control'          => 'nullable|array',
            'hz_residual'         => 'nullable|array',
            'hz_personnel'        => 'nullable|array',
            'special_activities'  => 'nullable|boolean',

            // SB-132: desglose de actividades especiales (obligatorio si special_activities).
            'sb132_details'                              => 'nullable|array',
            'sb132_details.activity_type'                => 'nullable',
            // Lista CERRADA de tipos de actividad (incluye fuego abierto y trabajo en altura/rigging).
            'sb132_details.activity_type.*'              => 'nullable|in:armas,pirotecnia,stunts,aereo,agua,off-road,fuego,altura',
            'sb132_details.scene_number'                 => 'nullable|string|max:255',
            'sb132_details.certified_personnel_required' => 'nullable|string|max:500',

            // Capa (3) Operativa
            'exec_summary'        => 'nullable|string',
            'operational_notes'   => 'nullable|string',
            'viab_area'           => 'nullable|array',
            'viab_status'         => 'nullable|array',
            'viab_responsible'    => 'nullable|array',
            'viab_note'           => 'nullable|array',
            'agr_item'            => 'nullable|array',
            'agr_responsible'     => 'nullable|array',
            'agr_date'            => 'nullable|array',
            'agr_status'          => 'nullable|array',
            'status'              => 'nullable|string|in:draft,revision,final',

            // (2026-07-12) MÓDULO 9 (inventarios): aforo, equipo de emergencia y logística.
            // Solo CAPTURA (todo nullable): el scouting registra las condiciones de la locación.
            'max_headcount'                                    => 'nullable|integer|min:0|max:100000',
            'emergency_equipment_inventory'                    => 'nullable|array',
            'emergency_equipment_inventory.fire_extinguishers' => 'nullable|integer|min:0',
            'emergency_equipment_inventory.aed'                => 'nullable|boolean',
            'emergency_equipment_inventory.first_aid_kits'     => 'nullable|integer|min:0',
            'logistics_facilities'                             => 'nullable|array',
            'logistics_facilities.restrooms'                   => 'nullable|boolean',
            'logistics_facilities.hydration_stations'          => 'nullable|integer|min:0',
            'logistics_facilities.shade_areas'                 => 'nullable|boolean',

            // (2026-07-12) MÓDULO 8 (EPP scouting): captura del EPP requerido. En el scouting
            // NO es condicional-obligatorio (a diferencia del DSR); solo se registra.
            'required_ppe'                                     => 'nullable|array',
            'required_ppe.*'                                   => 'nullable|string|max:100',

            // Imágenes — 12 MB por archivo.
            'main_image'          => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',
            'additional_images.*' => 'nullable|mimes:jpeg,png,jpg,gif,heic,heif|heic_ok|max:12288',

            // Pies de foto + gestión de existentes al editar.
            'additional_images_captions'   => 'nullable|array',
            'additional_images_captions.*' => 'nullable|string|max:300',
            'existing_images'              => 'nullable|array',
            'existing_images.*'            => 'nullable|string|max:1000',
            'existing_images_captions'     => 'nullable|array',
            'existing_images_captions.*'   => 'nullable|string|max:300',
        ];
    }

    /**
     * Mensajes compartidos (imágenes).
     *
     * @return array
     */
    public static function baseMessages()
    {
        return [
            'location_name.required'    => 'La locación es obligatoria.',
            'main_image.image'          => 'La imagen principal debe ser un archivo de imagen válido (JPG, PNG o GIF).',
            'main_image.mimes'          => 'La imagen principal debe ser JPG, PNG o GIF.',
            'main_image.max'            => 'La imagen principal no debe pesar más de 12 MB.',
            'additional_images.*.image' => 'Cada imagen adicional debe ser un archivo de imagen válido (JPG, PNG o GIF).',
            'additional_images.*.mimes' => 'Cada imagen adicional debe ser JPG, PNG o GIF.',
            'additional_images.*.max'   => 'Cada imagen adicional no debe pesar más de 12 MB.',
        ];
    }

    /**
     * Chequeo condicional SB-132: devuelve un mapa clave→mensaje de los campos
     * faltantes cuando se declaran actividades especiales. Vacío si todo OK o si
     * no hay actividades especiales.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public static function sb132Errors($request)
    {
        if (!$request->boolean('special_activities')) {
            return [];
        }

        $details = (array) $request->input('sb132_details', []);
        $required = [
            'activity_type'                => 'Indica el TIPO de actividad especial (armas, pirotecnia, stunts, aéreo, agua, off-road, fuego abierto, altura/rigging).',
            'scene_number'                 => 'Indica el número de escena de la actividad especial.',
            'certified_personnel_required' => 'Indica el personal certificado requerido para la actividad especial.',
        ];

        $errors = [];
        foreach ($required as $key => $msg) {
            $value = isset($details[$key]) ? $details[$key] : null;
            if (is_array($value)) {
                $value = array_filter($value, function ($x) {
                    return trim((string) $x) !== '';
                });
                $empty = empty($value);
            } else {
                $empty = trim((string) $value) === '';
            }
            if ($empty) {
                $errors['sb132_details.' . $key] = $msg;
            }
        }

        return $errors;
    }
}
