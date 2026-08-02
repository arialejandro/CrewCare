<?php

namespace App\Http\Controllers;

use App\Models\ScoutingReport;
use App\Models\RiskMapImage;
use App\Support\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mapeo de riesgos (2026-08-01) — sección APARTE y editable del scouting.
 *
 * Reemplaza al mapeo por PINES (delta #48, retirado): aquí NO se colocan pines. Es una
 * galería editable de imágenes valiosas de la locación por TIPO (plano | satelital |
 * dron/aéreo | foto): se sube, se edita y se quita en cualquier momento (incluido después
 * del scouting). El mapeo también muestra las fotos del scouting marcadas con el check
 * "Mapeo de riesgos" (puente), de modo que, sin más imágenes, con esas se genera el mapeo
 * (imprimible a PDF con window.print()).
 *
 * Autorización: rutas con permission:locations.create (igual que editar un scouting).
 * NO toca sellos ni el reporte sellado del scouting: es su propia superficie editable.
 */
class ScoutingRiskMapController extends Controller
{
    /** Imagen aceptada (igual criterio que el resto de subidas de la app). */
    const IMG_RULES = 'image|mimes:jpeg,jpg,png,webp|max:12288'; // 12 MB

    /** Índice del MÓDULO: todos los mapeos, listados por nombre de locación. */
    public function index()
    {
        $scoutings = ScoutingReport::orderBy('location_name')->orderByDesc('id')->get();

        // Conteo de imágenes del mapeo por scouting (1 query) + fotos marcadas (JSON en memoria).
        $imgCounts = RiskMapImage::selectRaw('scouting_report_id, COUNT(*) as c')
            ->groupBy('scouting_report_id')->pluck('c', 'scouting_report_id');

        $rows = $scoutings->map(function ($s) use ($imgCounts) {
            $flagged = 0;
            foreach ($s->additionalImagesList() as $im) {
                if (!empty($im['risk_map'])) { $flagged++; }
            }
            return [
                'id'       => $s->id,
                'location' => $s->location_name,
                'date'     => optional($s->date_shoot)->format('d/m/Y') ?: (optional($s->make_date)->format('d/m/Y') ?: ''),
                'images'   => (int) ($imgCounts[$s->id] ?? 0),
                'flagged'  => $flagged,
            ];
        })->values();

        return view('admin.scoutings.riskmap-index', ['rows' => $rows]);
    }

    /** Detalle de un mapeo: galería editable + fotos del scouting marcadas + imprimir/PDF. */
    public function show($id)
    {
        $scouting = ScoutingReport::findOrFail($id);

        $images = RiskMapImage::where('scouting_report_id', $scouting->id)
            ->orderBy('id')
            ->get();

        // Puente: fotos del scouting marcadas como "Mapeo de riesgos" (check en Imágenes).
        $scoutingMarked = array_values(array_filter(
            $scouting->additionalImagesList(),
            function ($img) { return !empty($img['risk_map']); }
        ));

        return view('admin.scoutings.riskmap', [
            'scouting'       => $scouting,
            'images'         => $images,
            'scoutingMarked' => $scoutingMarked,
            'types'          => RiskMapImage::TYPES,
        ]);
    }

    /** Alta de una imagen del mapeo (tipo + nombre + imagen). Recarga la página. */
    public function store(Request $request, $id)
    {
        $scouting = ScoutingReport::findOrFail($id);

        $data = $request->validate([
            'type'  => 'required|in:plano,satelital,aereo,foto',
            'name'  => 'required|string|max:120',
            'image' => 'required|' . self::IMG_RULES,
        ], [], [
            'type'  => 'tipo',
            'name'  => 'nombre',
            'image' => 'imagen',
        ]);

        RiskMapImage::create([
            'scouting_report_id' => $scouting->id,
            'type'               => $data['type'],
            'name'               => trim($data['name']),
            'image_path'         => $this->storeImage($request->file('image')),
        ]);

        return redirect()->route('riskmaps.show',$scouting->id)->with('success', 'Imagen agregada al mapeo.');
    }

    /** Edita nombre/tipo y, opcionalmente, reemplaza la imagen. */
    public function update(Request $request, $id, $imgId)
    {
        $img = $this->findImage($id, $imgId);

        $data = $request->validate([
            'type'  => 'required|in:plano,satelital,aereo,foto',
            'name'  => 'required|string|max:120',
            'image' => 'nullable|' . self::IMG_RULES,
        ], [], [
            'type'  => 'tipo',
            'name'  => 'nombre',
            'image' => 'imagen',
        ]);

        $img->type = $data['type'];
        $img->name = trim($data['name']);
        if ($request->hasFile('image')) {
            $new = $this->storeImage($request->file('image'));
            $this->deleteImage($img->image_path);
            $img->image_path = $new;
        }
        $img->save();

        return redirect()->route('riskmaps.show',$id)->with('success', 'Imagen actualizada.');
    }

    /** Quita una imagen del mapeo (y su archivo en disco). */
    public function destroy($id, $imgId)
    {
        $img = $this->findImage($id, $imgId);
        $this->deleteImage($img->image_path);
        $img->delete();

        return redirect()->route('riskmaps.show',$id)->with('success', 'Imagen eliminada.');
    }

    // ---------------------------------------------------------------------

    /** Garantiza que la imagen pertenece a ESTE scouting (evita cross-tampering). */
    private function findImage($id, $imgId): RiskMapImage
    {
        return RiskMapImage::where('scouting_report_id', $id)->findOrFail($imgId);
    }

    /**
     * Guarda la imagen en el disco 'public' y devuelve su URL RELATIVA (/storage/...),
     * igual que las imágenes del scouting que SÍ se muestran en cualquier dirección
     * (el módulo viejo guardaba absoluta http://127.0.0.1/... y por eso no cargaba).
     * El navegador ya la comprime/convierte HEIC (CCPhoto); aquí solo se persiste.
     */
    private function storeImage($image): string
    {
        $filename = time() . '_riskmap_' . uniqid() . '.' . ImageCompressor::safeExtensionOrBin($image);
        $path = $image->storeAs('scouting_riskmap', $filename, 'public');
        return Storage::url($path);
    }

    /** Borra del disco 'public' un archivo guardado como URL /storage/... (silencioso). */
    private function deleteImage($url): void
    {
        if (!is_string($url) || strpos($url, '/storage/') !== 0) {
            return;
        }
        try {
            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
