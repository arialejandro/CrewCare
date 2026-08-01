<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\locationreport;
use Illuminate\Support\Facades\Storage;

class locationController extends Controller
{
    public function viewlocation()
    {
        return view('admin.locationreport');
    }

    /**
     * Mostrar la lista de reportes de locación.
     */
    public function index()
    {
        // PERF (2026-06-28): antes era locationreport::all() (traía TODOS los reportes sin paginar).
        // Ahora pagina de 15 en 15, ordenado por PK desc (más recientes primero). PK del modelo = id_loc.
        $reports = locationreport::orderBy('id_loc', 'desc')->paginate(15);
        return view('admin.locationcrud', compact('reports'));
    }

    /**
     * Guardar un nuevo reporte de locación en la base de datos.
     */
    public function store(Request $request)
    {
        // Validar los datos del formulario, incluyendo las imágenes.
        // SEGURIDAD (2026-06-28): antes se validaba aquí pero luego se persistía con
        // $request->all() (mass-assignment del request crudo). Ahora capturamos el ARRAY
        // VALIDADO en $data y construimos $reportData a partir de él, de modo que solo se
        // guarden columnas que pasaron validación. Se confirmó (form name= vs $fillable vs
        // estas reglas) que validate() cubre las 116 columnas de datos del modelo, así que
        // no se pierde ninguna columna al dejar de usar $request->all().
        $data = $request->validate([
            // Datos generales
            'production_name' => 'required|string|max:255',
            'scene' => 'required|string|max:255',
            'name_scene' => 'required|string|max:255',
            'name_loc' => 'required|string|max:255',
            'date_prep' => 'nullable|date',
            'date_shoot' => 'nullable|date',
            'date_wrap' => 'nullable|date',
            'hour_prep' => 'nullable|string|max:10',
            'hour_shoot' => 'nullable|string|max:10',
            'hour_wrap' => 'nullable|string|max:10',
            'loc_type' => 'required|integer|in:0,1,2',
            'shoot_time' => 'required|integer|in:0,1,2',

            // Sección 1: Preguntas para el administrador/propietario
            'owner_1' => 'required|integer|in:0,1',
            'owner_1_details' => 'nullable|string|max:255',
            'owner_2' => 'required|integer|in:0,1',
            'owner_2_details' => 'nullable|string|max:255',
            'owner_3' => 'required|integer|in:0,1',
            'owner_3_details' => 'nullable|string|max:255',
            'owner_3_1' => 'nullable|string|max:255',
            'owner_3_2' => 'required|integer|in:0,1',
            'owner_3_2_details' => 'nullable|string|max:255',
            'owner_4' => 'required|integer|in:0,1',
            'owner_4_details' => 'nullable|string|max:255',
            'owner_4_1' => 'required|integer|in:0,1',
            'owner_4_1_details' => 'nullable|string|max:255',
            'owner_5' => 'required|integer|in:0,1',
            'owner_5_details' => 'nullable|string|max:255',
            'owner_5_1' => 'nullable|string|max:255',
            'owner_6' => 'required|integer|in:0,1',
            'owner_6_details' => 'nullable|string|max:255',
            'owner_6_1' => 'required|integer|in:0,1',
            'owner_6_1_details' => 'nullable|string|max:255',
            'owner_7' => 'required|integer|in:0,1',
            'owner_7_details' => 'nullable|string|max:255',
            'owner_8' => 'required|integer|in:0,1',
            'owner_8_details' => 'nullable|string|max:255',
            'owner_9' => 'required|integer|in:0,1',
            'owner_9_details' => 'nullable|string|max:255',
            'owner_10' => 'required|integer|in:0,1',
            'owner_10_details' => 'nullable|string|max:255',
            'owner_11' => 'required|integer|in:0,1',
            'owner_11_details' => 'nullable|string|max:255',
            'owner_12' => 'required|integer|in:0,1',
            'owner_12_details' => 'nullable|string|max:255',
            'owner_13' => 'required|integer|in:0,1',
            'owner_13_details' => 'nullable|string|max:255',
            'owner_14' => 'required|integer|in:0,1',
            'owner_14_details' => 'nullable|string|max:255',
            'owner_15' => 'required|integer|in:0,1',
            'owner_15_details' => 'nullable|string|max:255',

            // Sección 2: Inspección visual - Instalaciones
            'inst_16' => 'required|integer|in:0,1',
            'inst_16_details' => 'nullable|string|max:255',
            'inst_17' => 'required|integer|in:0,1',
            'inst_17_details' => 'nullable|string|max:255',
            'inst_18_details' => 'nullable|string|max:255',
            'inst_19' => 'required|integer|in:0,1',
            'inst_19_details' => 'nullable|string|max:255',
            'inst_20' => 'required|integer|in:0,1',
            'inst_20_details' => 'nullable|string|max:255',
            'inst_21' => 'required|integer|in:0,1',
            'inst_21_details' => 'nullable|string|max:255',
            'inst_22' => 'required|integer|in:0,1',
            'inst_22_details' => 'nullable|string|max:255',
            'inst_23' => 'required|integer|in:0,1',
            'inst_23_details' => 'nullable|string|max:255',
            'inst_24' => 'required|integer|in:0,1',
            'inst_24_details' => 'nullable|string|max:255',

            // Sección 3: Ventilación
            'vent_25' => 'required|integer|in:0,1',
            'vent_25_details' => 'nullable|string|max:255',
            'vent_26' => 'required|integer|in:0,1',
            'vent_26_details' => 'nullable|string|max:255',
            'vent_27' => 'required|integer|in:0,1',
            'vent_27_details' => 'nullable|string|max:255',
            'vent_28' => 'required|integer|in:0,1',
            'vent_28_details' => 'nullable|string|max:255',
            'vent_29' => 'required|integer|in:0,1',
            'vent_29_details' => 'nullable|string|max:255',

            // Sección 4: Servicios
            'batr_30' => 'required|integer|in:0,1',
            'batr_30_details' => 'nullable|string|max:255',
            'batr_31' => 'required|integer|in:0,1',
            'batr_31_details' => 'nullable|string|max:255',

            // Sección 5: Control de tráfico
            'trafic_32' => 'required|integer|in:0,1',
            'trafic_32_details' => 'nullable|string|max:255',
            'trafic_32_1' => 'nullable|string|max:255',
            'trafic_32_2' => 'required|integer|in:0,1',
            'trafic_32_2_details' => 'nullable|string|max:255',
            'trafic_33' => 'required|integer|in:0,1',
            'trafic_33_details' => 'nullable|string|max:255',
            'trafic_34' => 'required|integer|in:0,1',
            'trafic_34_details' => 'nullable|string|max:255',

            // Sección 6: Trabajo en alturas
            'height_35' => 'required|integer|in:0,1',
            'height_35_details' => 'nullable|string|max:255',
            'height_36' => 'required|integer|in:0,1',
            'height_36_details' => 'nullable|string|max:255',
            'height_37' => 'required|integer|in:0,1',
            'height_37_details' => 'nullable|string|max:255',

            // Sección 7: Espacios confinados
            'confi_38' => 'required|integer|in:0,1',
            'confi_38_details' => 'nullable|string|max:255',

            // Sección 8: Consideraciones climáticas
            'wheat_39' => 'required|integer|in:0,1',
            'wheat_39_details' => 'nullable|string|max:255',
            'wheat_39_1' => 'nullable|string|max:255',

            // Sección 9: Avisos de seguridad
            'adv_40' => 'required|integer|in:0,1',
            'adv_40_details' => 'nullable|string|max:255',

            // Sección 10: Respuesta a emergencias
            'emer_resp_1' => 'required|boolean',
            'emer_resp_2' => 'required|boolean',
            'emer_resp_3' => 'required|boolean',
            'emer_resp_4' => 'required|boolean',
            'emer_resp_5' => 'required|boolean',
            'emer_resp_6' => 'required|boolean',
            'emer_resp_7' => 'required|boolean',
            'emer_resp_8' => 'required|boolean',
            'emer_resp_hosp' => 'nullable|string|max:255',

            // Sección 11: Riesgos adicionales
            'aditional_risk' => 'nullable|string|max:255',
            'aditional_cons' => 'nullable|string|max:255',

            // Sección 12: Firma y distribución
            'make_by' => 'required|string|max:255',
            'make_date' => 'required|date',

            // Imágenes
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Base de datos a persistir = SOLO lo validado (no $request->all()).
        $reportData = $data;

        // Los inputs de archivo no son columnas de la tabla; se quitan del array y
        // luego se setean las rutas resultantes del almacenamiento.
        unset($reportData['main_image'], $reportData['additional_images']);

        // Procesar la imagen principal
        if ($request->hasFile('main_image')) {
            $image = $request->file('main_image');
            // Nombre único: antes era time().'_main.ext' (predecible y colisionable si dos
            // usuarios suben en el mismo segundo). uniqid() lo vuelve único.
            $filename = time() . '_main_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('location_images', $filename, 'public');
            $reportData['main_image_path'] = Storage::url($path);
        }

        // Procesar las imágenes adicionales
        if ($request->hasFile('additional_images')) {
            $additionalImagePaths = [];
            foreach ($request->file('additional_images') as $image) {
                $filename = time() . '_additional_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('location_images', $filename, 'public');
                $additionalImagePaths[] = Storage::url($path);
            }
            // El modelo castea additional_images_paths a 'array' → se asigna como ARRAY
            // (sin json_encode; Eloquent lo serializa).
            $reportData['additional_images_paths'] = $additionalImagePaths;
        }

        // Conteo de claves finales persistidas (verificación de que no se pierden columnas).
        \Illuminate\Support\Facades\Log::info('locationController@store claves persistidas: ' . count($reportData));

        // Crear el reporte en la base de datos
        try {
            locationreport::create($reportData);
            return redirect()->route('locationreport.index')->with('success', 'Reporte de locación guardado correctamente.');
        } catch (\Exception $e) {
            // SEGURIDAD/UX (2026-06-27): antes hacía dd($e) — volcaba detalles internos al usuario y
            // abortaba la petición. Ahora se registra el error y se regresa con un mensaje limpio.
            \Illuminate\Support\Facades\Log::error('Error al guardar el reporte de locación: '.$e->getMessage());
            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el reporte de locación. Intenta de nuevo.');
        }
    }

    /**
     * Mostrar los detalles de un reporte de locación.
     */
    public function show($id)
    {
        $report = locationreport::findOrFail($id);
        return view('admin.location', compact('report'));
    }
}