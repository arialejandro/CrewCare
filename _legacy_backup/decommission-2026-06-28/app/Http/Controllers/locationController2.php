<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\locationreport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class locationController2 extends Controller
{
    // Muestra el formulario de auditoría
    public function create()
    {
        return view('admin.locationReport2');
    }

    // Guarda la auditoría en la BD
    public function store(Request $request)
    {
        // Validación de campos.
        // SEGURIDAD (2026-06-28):
        //  - Las imágenes ahora exigen mimes/max (antes 'image|max:2048' aceptaba cualquier
        //    archivo que pasara como imagen sin restringir extensión; se añade mimes).
        //  - El form V2 también envía estos campos de inspección (radios 0/1 y textareas).
        //    Antes se persistían vía $request->all(); ahora que basamos el guardado en el
        //    array VALIDADO, deben declararse aquí como nullable para no perder esas columnas.
        $data = $request->validate([
            'production_name' => 'required|string|max:255',
            'name_loc' => 'required|string|max:255',
            'make_by' => 'required|string',
            'make_date' => 'required|date',

            // Campos de inspección presentes en el form V2 (locationReport2.blade.php).
            'owner_4' => 'nullable|integer|in:0,1',
            'owner_4_details' => 'nullable|string|max:255',
            'owner_6' => 'nullable|integer|in:0,1',
            'owner_6_details' => 'nullable|string|max:255',
            'owner_11' => 'nullable|integer|in:0,1',
            'owner_11_details' => 'nullable|string|max:255',
            'height_35' => 'nullable|integer|in:0,1',
            'height_35_details' => 'nullable|string|max:255',

            // Imágenes
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Base a persistir = SOLO lo validado (no $request->all()).
        $reportData = $data;

        // Los inputs de archivo no son columnas; se quitan y se setean las rutas.
        unset($reportData['main_image'], $reportData['additional_images']);

        // Imagen Principal (Header del Reporte)
        if ($request->hasFile('main_image')) {
            $path = $request->file('main_image')->store('locations_v2/main', 'public');
            $reportData['main_image_path'] = Storage::url($path);
        }

        // Imágenes de Evidencia (Múltiples)
        if ($request->hasFile('additional_images')) {
            $evidencePaths = [];
            foreach ($request->file('additional_images') as $image) {
                $path = $image->store('locations_v2/evidence', 'public');
                $evidencePaths[] = Storage::url($path);
            }
            // Cast 'array' en el modelo → se asigna como ARRAY (sin json_encode).
            $reportData['additional_images_paths'] = $evidencePaths;
        }

        // SEGURIDAD/UX (2026-06-28): create() ahora va en try/catch. Antes, una excepción
        // (p.ej. fallo de BD) reventaba con la página de error de Laravel. Ahora se registra
        // y se regresa al form con los datos y un mensaje limpio.
        try {
            $report = locationreport::create($reportData);
        } catch (\Exception $e) {
            Log::error('Error al guardar la auditoría de locación (V2): ' . $e->getMessage());
            return back()->withInput()->with('error', 'No se pudo guardar la auditoría. Intenta de nuevo.');
        }

        return redirect()->route('location2.show', $report->id)
                         ->with('success', 'Auditoría Técnica completada con éxito.');
    }

    // Vista de revisión y descarga del reporte
    public function show($id)
    {
        $report = locationreport::findOrFail($id);
        return view('admin.location2', compact('report'));
    }
}