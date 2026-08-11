<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * (2026-07-12) Validación del ENCABEZADO del Daily Safety Report (DSR).
 *
 * Formaliza las reglas que antes vivían inline en DailyReportController@store y
 * suma los campos de los cimientos módulos 6-14:
 *   - medic_name: formaliza el campo huérfano (antes validado pero sin UI).
 *   - Clima extendido: humidity, wind_speed, heat_index.
 *   - EPP transversal (required_ppe) y factores de riesgo del día
 *     (day_risk_factors), ambos arrays → JSON en el modelo.
 *
 * MÓDULO 8 (EPP condicional): si el día implica CLIMA EXTREMO/LLUVIA o TRABAJO EN
 * ALTURA, el EPP requerido NO puede ir vacío (regla server-side en withValidator).
 *
 * OJO PROD: las columnas nuevas aún no existen en prod. La VALIDACIÓN es inofensiva
 * (solo revisa el input); la PERSISTENCIA de estas columnas va con guard
 * Schema::hasColumn en el controlador.
 */
class StoreDailyReportRequest extends FormRequest
{
    public function authorize()
    {
        // El permiso/rol se controla en la definición de la ruta.
        return true;
    }

    /**
     * (2026-07-25) CAMPO ÚNICO DE LOCACIÓN — el SERVIDOR reconoce la locación y amarra el scouting.
     *
     * Antes el formulario tenía DOS campos: un <select> de scouting ("Locación scouteada") y un texto
     * libre "Locación". Elegir el scouting no es decisión del safety —el sistema RECONOCE la locación—,
     * así que se fusionaron en un solo campo `location_name` (con datalist de las locaciones ya
     * scouteadas). Aquí, ANTES de validar, se reconoce esa locación por NOMBRE y se amarra
     * scouting_report_id, igual que los gemelos amarran el vínculo server-side (ellos por GPS; el DSR
     * no tiene GPS, así que por nombre — ScoutingLocator::idByLocationName, hermano de nearestId).
     *
     *   • Locación reconocida  → ata el vínculo y HEREDA hospital/ambulancia EN SILENCIO, pero SÓLO en
     *                            los campos vacíos: lo que el safety escriba MANDA.
     *   • Texto libre / nueva  → sin vínculo (scouting_report_id = null); el hospital se captura a mano.
     *
     * Corre antes de la validación para que el hospital heredado satisfaga la regla 'required' aun sin
     * JS (el prellenado del cliente es sólo UX). Todo con guards de esquema para no romper prod sin el
     * SQL, y sin lanzar: si algo falla, simplemente no ata el vínculo.
     */
    protected function prepareForValidation()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('scouting_reports')) {
            return;
        }

        $name = trim((string) $this->input('location_name', ''));
        if ($name === '') {
            return;
        }

        $pid     = \App\Support\CurrentProduction::id();
        $scoutId = \App\Support\ScoutingLocator::idByLocationName($name, $pid);

        // Locación no reconocida (texto libre / nueva): sin vínculo, hospital a mano. Se fija null
        // explícito para que un id inyectado en el POST no sobreviva (el cliente ya no lo envía).
        if ($scoutId === null) {
            $this->merge(['scouting_report_id' => null]);
            return;
        }

        $scouting = \App\Models\ScoutingReport::find($scoutId);
        if (!$scouting) {
            return;
        }

        $merge = ['scouting_report_id' => $scoutId];

        // Herencia SILENCIOSA sólo en vacíos ("lo escrito es lo que se guarda").
        if (trim((string) $this->input('nearest_hospital', '')) === '' && !empty($scouting->nearest_hospital)) {
            $hosp = $scouting->nearest_hospital;
            if (!empty($scouting->hospital_eta)) {
                $hosp .= ' - ' . $scouting->hospital_eta;
            }
            $merge['nearest_hospital'] = $hosp;
        }
        if (trim((string) $this->input('ambulance_company', '')) === '' && !empty($scouting->ambulance_company)) {
            $merge['ambulance_company'] = $scouting->ambulance_company;
        }

        $this->merge($merge);
    }

    public function rules()
    {
        return [
            // --- Reglas base (copiadas del store inline original) ---
            'report_date'          => 'required|date',
            // (2026-07-24) `shoot_day` YA NO SE TECLEA. Era 'required|integer' y los datos de
            // esta base probaron a qué llevaba: 0, 9, 10, 11, 8, 1, 1, 1 — un DSR con 150 de crew
            // marcado como día 0. Ahora lo DERIVA ProductionCalendar en el store a partir de la
            // fecha del reporte. Se deja aceptado y nullable —no removido— porque el formulario
            // manda un campo oculto de sólo lectura y una instancia vieja podría seguir
            // enviándolo; en cualquier caso el controlador PISA lo que llegue.
            'shoot_day'            => 'nullable|integer',
            'location_name'        => 'required|string',
            'slug_setting'         => 'required|string',
            'slug_time'            => 'required|string',
            'call_time'            => 'nullable|date_format:H:i',
            'weather_condition'    => 'required|string',
            'weather_min_temp'     => 'nullable|integer',
            'weather_max_temp'     => 'nullable|integer',
            'safety_meeting_time'  => 'nullable|date_format:H:i',
            // (2026-07-21) ¿Se realizó la junta? nullable a propósito: si el formulario de
            // una instancia vieja no manda el campo, el reporte queda "sin declarar" (null)
            // en vez de mentir marcándolo como realizado.
            'safety_meeting_held'  => 'nullable|boolean',
            // Foto en gran angular del crew reunido (evidencia de la junta).
            'safety_meeting_photo' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,svg,webp,heic,heif|heic_ok|max:12288',
            // (2026-07-13) safety_meeting_topics pasó de texto libre a checkboxes (reusa el catálogo
            // de factores de riesgo + temas comunes). Llega como array; el controlador lo colapsa a
            // CSV para la columna string existente (sin cambio de esquema).
            // (2026-07-22) Los temas ahora son CLAVES del catálogo de eventos y se guardan
            // como CSV en un varchar(255) con la conexión en modo ESTRICTO: pasarse de largo
            // no trunca en silencio, lanza el error 1406 y tumba la petición. Con claves
            // (~13 chars) caben ~17; el tope de 15 deja margen y además es realista — una
            // junta de seguridad no cubre veinte temas.
            'safety_meeting_topics'   => 'nullable|array|max:15',
            'safety_meeting_topics.*' => 'string|max:100',
            // (2026-07-25) HOSPITAL DESIGNADO OBLIGATORIO EN LA CREACIÓN. Es el campo que alguien
            // lee corriendo cuando hay un herido: no puede quedar vacío al abrir el día. La cadena
            // de origen lo prellena (scouting → buscador geo → manual) y queda editable, pero el
            // safety DEBE confirmar que hay uno. OJO: esto es SOLO en el alta (este FormRequest);
            // el cierre de día (DailyReportController@update) NO valida este campo, así que los DSR
            // viejos con hospital null se siguen editando y guardando sin tropezar con esta regla.
            'nearest_hospital'     => 'required|string',
            // Vínculo con el scouting de origen del hospital. nullable|integer (sin 'exists:' para no
            // acoplar a prod antes del SQL); el controlador PISA lo que llegue con guard de columna.
            'scouting_report_id'   => 'nullable|integer',
            'ambulance_company'    => 'nullable|string',
            // medic_name: formaliza el campo huérfano (nullable, ahora con límite de longitud).
            'medic_name'           => 'nullable|string|max:255',
            'crew_count'           => 'required|integer',
            'executive_summary'    => 'nullable|string',
            // author_name NO se valida: es AUTOFIRMA (server-side con el usuario logueado).
            'hero_image'           => 'nullable|mimes:jpg,jpeg,png,gif,bmp,svg,webp,heic,heif|heic_ok|max:12288', // 12 MB (foto de celular)

            // --- Cimientos módulos 6-14 ---
            'humidity'             => 'nullable|integer|min:0|max:100',
            'wind_speed'           => 'nullable|numeric|min:0|max:400',
            'heat_index'           => 'nullable|numeric',
            'required_ppe'         => 'nullable|array',
            'required_ppe.*'       => 'string|max:100',
            'day_risk_factors'     => 'nullable|array',
            'day_risk_factors.*'   => 'string|max:100',
            // 'uuid' NO se valida: el identificador público lo genera el trait GeneratesUuidKey
            // en el evento `creating`. Aceptarlo desde el form permitiría que un cliente inyecte
            // el UUID → se eliminó la regla huérfana (nunca hubo campo uuid en el formulario).
        ];
    }

    /**
     * Hook after: MÓDULO 8 — EPP obligatorio por clima extremo/lluvia o altura.
     */
    public function withValidator($validator)
    {
        $request = $this;
        $validator->after(function ($v) use ($request) {
            if ($request->requiresPpe() && !$request->hasPpeSelected()) {
                $v->errors()->add(
                    'required_ppe',
                    'El EPP es obligatorio por clima extremo/lluvia o trabajo en altura.'
                );
            }
        });
    }

    /**
     * ¿El día obliga a declarar EPP? (clima extremo/lluvia O trabajo en altura).
     *
     * @return bool
     */
    public function requiresPpe()
    {
        return $this->isExtremeWeather() || $this->involvesHeights();
    }

    /**
     * Clima extremo: la condición contiene una palabra clave de riesgo, O el índice
     * de calor >= 39, O la temperatura máxima >= 38, O el viento >= 40 km/h. Las
     * palabras clave casan con los VALUES del <select> vigente (rainy/storm/hail/snow/
     * windy/extreme_heat) — antes buscaba términos ES que el form nunca emitía → regla
     * muerta. sunny/cloudy NO disparan (correcto).
     *
     * @return bool
     */
    protected function isExtremeWeather()
    {
        $condition = strtolower((string) $this->input('weather_condition', ''));
        // 'extreme' cubre extreme_heat; 'windy' el viento fuerte; se dejan los términos ES
        // por robustez si el valor llegara como texto libre.
        $keywords = ['lluvia', 'lluvioso', 'tormenta', 'granizo', 'nieve', 'extremo', 'viento', 'extreme', 'windy', 'rain', 'rainy', 'storm', 'hail', 'snow'];
        foreach ($keywords as $word) {
            if ($condition !== '' && strpos($condition, $word) !== false) {
                return true;
            }
        }

        $heatIndex = $this->input('heat_index');
        if (is_numeric($heatIndex) && (float) $heatIndex >= 39) {
            return true;
        }

        $maxTemp = $this->input('weather_max_temp');
        if (is_numeric($maxTemp) && (float) $maxTemp >= 38) {
            return true;
        }

        // Viento fuerte (>= 40 km/h): activa la obligatoriedad de EPP igual que el índice de
        // calor y la temp. máx. Antes wind_speed sólo se guardaba (dato inerte); ahora pesa
        // en la regla server-side.
        $windSpeed = $this->input('wind_speed');
        if (is_numeric($windSpeed) && (float) $windSpeed >= 40) {
            return true;
        }

        return false;
    }

    /**
     * Trabajo en altura: algún factor de riesgo contiene 'altura' o 'heights'.
     *
     * @return bool
     */
    protected function involvesHeights()
    {
        $factors = $this->input('day_risk_factors', []);
        if (!is_array($factors)) {
            return false;
        }
        foreach ($factors as $factor) {
            $value = strtolower((string) $factor);
            if (strpos($value, 'altura') !== false || strpos($value, 'heights') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿Se seleccionó al menos un EPP no vacío?
     *
     * @return bool
     */
    public function hasPpeSelected()
    {
        $ppe = $this->input('required_ppe', []);
        if (!is_array($ppe)) {
            return false;
        }
        $ppe = array_filter($ppe, function ($value) {
            return trim((string) $value) !== '';
        });
        return count($ppe) > 0;
    }

    public function messages()
    {
        return [
            'report_date.required'    => 'La fecha del reporte es obligatoria.',
            'report_date.date'        => 'La fecha del reporte no es válida.',
            'shoot_day.integer'       => 'El shoot day debe ser un número entero.',
            'location_name.required'  => 'La locación es obligatoria.',
            'nearest_hospital.required' => 'El hospital designado es obligatorio: escríbelo a mano.',
            'slug_setting.required'   => 'El entorno (INT./EXT.) es obligatorio.',
            'slug_time.required'      => 'La luz (día/noche) es obligatoria.',
            'weather_condition.required' => 'El clima es obligatorio.',
            'weather_min_temp.integer' => 'La temperatura mínima debe ser un número entero.',
            'weather_max_temp.integer' => 'La temperatura máxima debe ser un número entero.',
            'crew_count.required'     => 'El total de crew es obligatorio.',
            'crew_count.integer'      => 'El total de crew debe ser un número entero.',
            'safety_meeting_topics.array' => 'Los temas del safety meeting tienen un formato inválido.',
            'safety_meeting_topics.*.string' => 'Cada tema del safety meeting debe ser texto.',
            'safety_meeting_topics.*.max' => 'Cada tema del safety meeting no puede superar 100 caracteres.',
            'hero_image.image'        => 'La portada debe ser un archivo de imagen válido.',
            'hero_image.max'          => 'La imagen de portada no debe pesar más de 12 MB.',
            'medic_name.max'          => 'El nombre del médico no puede superar 255 caracteres.',
            'humidity.integer'        => 'La humedad debe ser un número entero.',
            'humidity.min'            => 'La humedad no puede ser menor a 0%.',
            'humidity.max'            => 'La humedad no puede ser mayor a 100%.',
            'wind_speed.numeric'      => 'La velocidad del viento debe ser numérica.',
            'wind_speed.min'          => 'La velocidad del viento no puede ser negativa.',
            'wind_speed.max'          => 'La velocidad del viento no puede superar 400.',
            'heat_index.numeric'      => 'El índice de calor debe ser numérico.',
            'required_ppe.array'      => 'El EPP requerido tiene un formato inválido.',
            'required_ppe.*.string'   => 'Cada elemento de EPP debe ser texto.',
            'required_ppe.*.max'      => 'Cada elemento de EPP no puede superar 100 caracteres.',
            'day_risk_factors.array'  => 'Los factores de riesgo tienen un formato inválido.',
            'day_risk_factors.*.string' => 'Cada factor de riesgo debe ser texto.',
            'day_risk_factors.*.max'  => 'Cada factor de riesgo no puede superar 100 caracteres.',
        ];
    }
}
