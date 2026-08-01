<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * (2026-07-24 · PIEZA 3) Validación del EXPEDIENTE CLÍNICO (`formularios`).
 *
 * ANTES NO HABÍA NINGUNA. `newformulario()` hacía `request()->all()` → `create($datos)`, y los
 * cuatro campos "obligatorios" (quirúrgicas, patologías, alergias, traumáticos) sólo llevaban
 * `required` en el HTML: un POST con curl —o el navegador con la validación deshabilitada—
 * entraba con el expediente vacío. La única defensa era `$fillable`.
 *
 * QUÉ CUBRE, ADEMÁS DE LO OBVIO. Tres reglas nacen de LÍMITES REALES DE LA TABLA, no de gusto:
 * en MySQL estricto, pasarse de largo no trunca, TIRA la petición (500 en blanco).
 *   · `hsp1..hsp10` son VARCHAR(50) y el placeholder invita a narrar ("¿Hace cuánto? Motivo…").
 *     Sin `max:50`, describir bien una hospitalización tumbaba el guardado (error 1406).
 *   · `blod_type` es VARCHAR(10); `c_emer`/`relation`/`p_emer`/`rythm`/`pregnant` VARCHAR(255).
 *   · `height`/`size` son FLOAT: '' no es un float. Ese era el bug de peso/talla — ya son
 *     NULLABLE en BD (owner-apply 2026-07-24-health-record-fixes.sql) y aquí `nullable|numeric`.
 *
 * TIPO DE SANGRE: se cierra a los 8 grupos reales. Es el dato que se consulta en una urgencia
 * y era texto libre — un "0+" (cero) en vez de "O+" (letra) se ve idéntico y no significa nada.
 * `prepareForValidation()` normaliza mayúsculas/espacios antes de comparar.
 *
 * GINECO-OBSTÉTRICOS: sólo se exigen si la persona es F. La condición se lee de la SESIÓN
 * (`auth()->user()->sex`), NO de un campo posteado: la vista pinta esa sección con el mismo
 * criterio, y si dependiera del request cualquiera podría activar o esquivar las reglas.
 *
 * Las casillas (24 checkboxes) llegan como "on" o no llegan; se validan `nullable` y el
 * controlador las castea a 1/0. Aquí no se listan una por una a propósito: la fuente única de
 * qué casillas existen es `formulario::CHECKBOXES`.
 */
class StoreHealthRecordRequest extends FormRequest
{
    /** Los 8 grupos sanguíneos reales. Cualquier otra cosa es un error de captura. */
    const BLOOD_TYPES = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    public function authorize()
    {
        // El expediente es SIEMPRE el de quien está en sesión (el controlador fija id_user
        // desde auth()); la ruta ya exige `auth`. No hay nada más que autorizar aquí.
        return true;
    }

    public function rules()
    {
        $reglas = [
            // ---- Datos generales ----
            'blod_type' => 'required|string|in:' . implode(',', self::BLOOD_TYPES),
            // Peso (Kg) y talla (Mts): OPCIONALES de verdad, en el formulario y en la BD.
            // Los rangos son topes de sensatez para atrapar el dedazo (7 en vez de 70,
            // 170 en vez de 1.70), no juicios clínicos.
            'height'    => 'nullable|numeric|between:20,400',
            'size'      => 'nullable|numeric|between:0.5,2.6',
            'c_emer'    => 'required|string|max:255',
            'relation'  => 'required|string|max:255',
            'p_emer'    => 'required|string|max:255',

            // ---- Personales patológicas ----
            'hospitals' => 'required|integer|between:0,10',
            'cirugy'    => 'required|string|max:65535',
            'pathology' => 'required|string|max:65535',
            'alergy'    => 'required|string|max:65535',
            'trauma'    => 'required|string|max:65535',

            // ---- Vacunación ----
            // La etiqueta de la influenza afirma vigencia ("no mayor a un año"), así que si se
            // marca, la fecha deja de ser opcional: sin ella la afirmación no se puede sostener.
            'vacci2_date' => 'nullable|date|required_with:vacci2|after_or_equal:1940-01-01|before_or_equal:today',
        ];

        // Hospitalizaciones descritas: VARCHAR(50) en BD (ver cabecera).
        for ($i = 1; $i <= 10; $i++) {
            $reglas['hsp' . $i] = 'nullable|string|max:50';
        }

        // Las 24 casillas: presentes o ausentes, nada más. El casteo vive en el modelo.
        foreach (\App\Models\formulario::CHECKBOXES as $casilla) {
            $reglas[$casilla] = 'nullable';
        }

        // Gineco-obstétricos: mismo criterio que usa la vista para pintarlos.
        $u = $this->user();
        if ($u && $u->sex === 'F') {
            $reglas['rythm']    = 'required|string|max:255';
            $reglas['pregnant'] = 'required|string|max:255';
        } else {
            $reglas['rythm']    = 'nullable|string|max:255';
            $reglas['pregnant'] = 'nullable|string|max:255';
        }

        return $reglas;
    }

    public function messages()
    {
        return [
            'blod_type.required'   => __('health.v_blood_required'),
            'blod_type.in'         => __('health.v_blood_in'),
            'height.numeric'       => __('health.v_weight_numeric'),
            'height.between'       => __('health.v_weight_between'),
            'size.numeric'         => __('health.v_size_numeric'),
            'size.between'         => __('health.v_size_between'),
            'c_emer.required'      => __('health.v_contact_required'),
            'relation.required'    => __('health.v_relation_required'),
            'p_emer.required'      => __('health.v_phone_required'),
            'cirugy.required'      => __('health.v_surgery_required'),
            'pathology.required'   => __('health.v_pathology_required'),
            'alergy.required'      => __('health.v_allergy_required'),
            'trauma.required'      => __('health.v_trauma_required'),
            'rythm.required'       => __('health.v_rythm_required'),
            'pregnant.required'    => __('health.v_pregnant_required'),
            'vacci2_date.required_with'    => __('health.v_flu_date_required'),
            'vacci2_date.before_or_equal'  => __('health.v_flu_date_future'),
        ];
    }

    public function attributes()
    {
        $attrs = [
            'blod_type' => __('health.f_blood'), 'height' => __('health.f_weight'),
            'size' => __('health.f_size'), 'c_emer' => __('health.f_contact'),
            'relation' => __('health.f_relation'), 'p_emer' => __('health.f_phone'),
            'hospitals' => __('health.f_hospitalizations'), 'cirugy' => __('health.f_surgery'),
            'pathology' => __('health.f_pathology'), 'alergy' => __('health.f_allergy'),
            'trauma' => __('health.f_trauma'), 'rythm' => __('health.f_rythm'),
            'pregnant' => __('health.f_pregnant'), 'vacci2_date' => __('health.f_flu_date'),
        ];
        for ($i = 1; $i <= 10; $i++) {
            $attrs['hsp' . $i] = __('health.f_hospitalization_n', ['n' => $i]);
        }
        return $attrs;
    }

    /**
     * Normalización PREVIA a validar.
     *
     * · Trim de los campos de texto: `required` de Laravel deja pasar "   ", y en un expediente
     *   clínico una cadena de espacios se lee igual que un dato ausente pero pasa el filtro.
     *   Mismo blindaje que StoreUnsafeConditionRequest (2026-07-23).
     * · Tipo de sangre: mayúsculas y sin espacios ("o +" → "O+"), para que el `in:` juzgue el
     *   dato y no el formato con el que se tecleó.
     * · Peso/talla: '' → null. El input SIEMPRE viaja (aunque vacío) y '' no es numérico; sin
     *   esto, dejar el campo en blanco dispararía "debe ser un número" en vez de aceptarse
     *   como lo que es: un dato que la persona optó por no dar.
     */
    protected function prepareForValidation()
    {
        $textos = ['c_emer', 'relation', 'p_emer', 'cirugy', 'pathology', 'alergy', 'trauma', 'rythm', 'pregnant'];
        for ($i = 1; $i <= 10; $i++) {
            $textos[] = 'hsp' . $i;
        }
        foreach ($textos as $f) {
            if (is_string($this->input($f))) {
                $this->merge([$f => trim($this->input($f))]);
            }
        }

        if (is_string($this->input('blod_type'))) {
            $this->merge(['blod_type' => strtoupper(str_replace(' ', '', trim($this->input('blod_type'))))]);
        }

        foreach (['height', 'size'] as $f) {
            $v = $this->input($f);
            if ($v === '' || $v === null) {
                $this->merge([$f => null]);
            }
        }
    }
}
