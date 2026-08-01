<?php

namespace App\Http\Requests;

use App\Models\formulario;
use Illuminate\Foundation\Http\FormRequest;

/**
 * (2026-07-24 · PIEZA 3, corrida 2/2) Validación del ANEXO al expediente clínico.
 *
 * Reglas ESPEJO de las del expediente (StoreHealthRecordRequest): si el tipo de sangre se
 * captura contra una lista cerrada al declararlo, corregirlo por anexo no puede ser más laxo —
 * sería la puerta trasera de la validación. Diferencia: aquí TODO es opcional salvo el motivo y
 * la nota, porque un anexo toca un campo o dos, no los cuarenta.
 *
 * `notes` es OBLIGATORIA: un cambio sin explicación no es un anexo clínico, es una edición
 * disfrazada. La nota es lo que queda impreso al lado del dato viejo.
 */
class StoreHealthAddendumRequest extends FormRequest
{
    public function authorize()
    {
        // La autorización real (rol médico + expediente existente) la impone el controlador:
        // depende del expediente que se está anexando, no sólo de quién pide.
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'required|string|in:' . implode(',', array_keys(\App\Models\HealthRecordAddendum::MOTIVOS)),
            'notes'  => 'required|string|min:10|max:65535',

            // Mismas reglas que al declarar el expediente.
            'blod_type'   => 'nullable|string|in:' . implode(',', StoreHealthRecordRequest::BLOOD_TYPES),
            'height'      => 'nullable|numeric|between:20,400',
            'size'        => 'nullable|numeric|between:0.5,2.6',
            'alergy'      => 'nullable|string|max:65535',
            'pathology'   => 'nullable|string|max:65535',
            'cirugy'      => 'nullable|string|max:65535',
            'trauma'      => 'nullable|string|max:65535',
            'c_emer'      => 'nullable|string|max:255',
            'relation'    => 'nullable|string|max:255',
            'p_emer'      => 'nullable|string|max:255',
            'vacci1'      => 'nullable',
            'vacci2'      => 'nullable',
            'vacci3'      => 'nullable',
            'vacci4'      => 'nullable',
            'vacci5'      => 'nullable',
            'vacci2_date' => 'nullable|date|after_or_equal:1940-01-01|before_or_equal:today',
        ];
    }

    public function messages()
    {
        return [
            'notes.required' => __('health.v_addendum_notes_required'),
            'notes.min'      => __('health.v_addendum_notes_min'),
            'reason.required'=> __('health.v_addendum_reason_required'),
            'blod_type.in'   => __('health.v_blood_in'),
        ];
    }

    /**
     * Mismo trim y misma normalización del tipo de sangre que al declarar. Sin esto, " o+ "
     * pasaría por la lista cerrada al declarar pero chocaría al corregir — y el médico se
     * quedaría sin poder arreglar precisamente el dato que más importa.
     */
    protected function prepareForValidation()
    {
        $textos = ['notes', 'alergy', 'pathology', 'cirugy', 'trauma', 'c_emer', 'relation', 'p_emer'];
        foreach ($textos as $f) {
            if (is_string($this->input($f))) {
                $this->merge([$f => trim($this->input($f))]);
            }
        }

        if (is_string($this->input('blod_type'))) {
            $this->merge(['blod_type' => strtoupper(str_replace(' ', '', trim($this->input('blod_type'))))]);
        }

        foreach (['height', 'size'] as $f) {
            if ($this->input($f) === '') {
                $this->merge([$f => null]);
            }
        }
    }

    /**
     * Los cambios REALES: sólo los campos anexables que llegaron con un valor DISTINTO al
     * vigente. Comparar contra el vigente (no contra lo declarado) evita que reenviar el
     * formulario sin tocar nada siembre un anexo vacío, y evita "deshacer" un anexo anterior
     * sin querer.
     *
     * Las casillas se normalizan a 1/0 antes de comparar: el formulario manda "on", la BD
     * guarda 1, y sin normalizar todo cambio de casilla parecería distinto siempre.
     *
     * @param  \App\Models\formulario  $expediente
     * @return array  campo => valor nuevo
     */
    public function cambiosRespectoA($expediente)
    {
        $vigente = (array) $expediente->aplicados();
        $cambios = [];

        foreach (array_keys(formulario::CAMPOS_ANEXABLES) as $campo) {
            $esCasilla = in_array($campo, formulario::CHECKBOXES, true);

            if ($esCasilla) {
                // Una casilla siempre tiene respuesta (marcada o no), así que no hay "no lo
                // mandó": se compara su estado actual contra el vigente.
                $nuevo = $this->boolean($campo) ? 1 : 0;
            } else {
                if (! $this->has($campo)) {
                    continue;
                }
                $nuevo = $this->input($campo);
                if ($nuevo === '') {
                    // Vaciar un campo clínico no es corregirlo, es borrarlo: no se acepta por
                    // anexo. Para dejar constancia de que algo ya no aplica, se escribe.
                    continue;
                }
            }

            $actual = isset($vigente[$campo]) ? $vigente[$campo] : null;

            // Comparación por cadena: la BD devuelve "1"/1 y floats como "70"/70.0 según el
            // driver y el cast. Comparar con === marcaría cambios que no existen.
            if ((string) $actual !== (string) $nuevo) {
                $cambios[$campo] = $esCasilla ? (int) $nuevo : $nuevo;
            }
        }

        return $cambios;
    }
}
