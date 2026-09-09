<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\ImageCompressor;

/**
 * BrandingController — pantalla "Marca" (solo super-admin, permission:settings.manage).
 * Permite personalizar en vivo: logo del cliente, nombre de marca, título de la app/PWA y color
 * primario. Guarda en la tabla `settings` (clave/valor) e invalida el cache de Branding.
 */
class BrandingController extends Controller
{
    public function edit()
    {
        // $branding ya viene compartido a todas las vistas (View::share en AppServiceProvider),
        // pero lo pasamos explícito para legibilidad del formulario.
        $current = Branding::all();
        return view('admin.branding', compact('current'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_name'      => 'nullable|string|max:120',
            'app_title'       => 'nullable|string|max:120',
            'primary_color'   => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color'    => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'company_name'    => 'nullable|string|max:191',
            'office_address'  => 'nullable|string|max:191',
            // Contratante para la carátula del contrato (Paso B) — aditivo.
            'rfc'                 => 'nullable|string|max:20',
            'representante_legal' => 'nullable|string|max:160',
            'correo_contratante'  => 'nullable|string|max:160',
            'client_logo'     => 'nullable|mimes:png,jpg,jpeg,webp,svg,heic,heif|heic_ok|max:2048',
        ]);

        // Campos de texto/color → settings clave/valor.
        foreach (['brand_name', 'app_title', 'primary_color', 'secondary_color', 'accent_color', 'company_name', 'office_address', 'rfc', 'representante_legal', 'correo_contratante'] as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key)]);
            }
        }

        // Logo del cliente: si suben archivo, se guarda y se persiste su URL pública.
        // HEIC (iPhone) → JPEG donde el servidor pueda convertir (mismo pipeline que el resto de
        // subidas); si no puede, se conserva el archivo tal cual y la extensión real lo refleja.
        if ($request->hasFile('client_logo')) {
            $file = ImageCompressor::normalizeForUpload($request->file('client_logo'));
            $filename = 'client_logo_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('branding', $filename, 'public');
            Setting::updateOrCreate(['key' => 'client_logo'], ['value' => Storage::url($path)]);
        }

        // Invalida el cache para que el nuevo branding aplique de inmediato.
        Branding::forget();

        return redirect()->route('settings.branding.edit')->with('success', 'Marca actualizada.');
    }
}
