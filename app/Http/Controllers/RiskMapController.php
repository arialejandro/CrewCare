<?php

namespace App\Http\Controllers;

use App\Models\RiskMap;
use App\Models\RiskMapMarker;
use App\Models\RiskMapView;
use App\Models\ScoutingReport;
use App\Support\CurrentProduction;
use App\Support\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * MAPEO DE RIESGOS Y RECURSOS (2026-08-03 · delta #50).
 *
 * Editor APARTE (no cuelga del scouting) que produce un DOCUMENTO SELLADO, una
 * página por vista. Referencia un scouting en SOLO LECTURA (sus imágenes + sus
 * eventos ya evaluados). El scouting jamás se escribe desde aquí.
 *
 * Autorización: TODO el módulo va con permission:riskmap.issue (safety). El crew
 * lee el documento sellado (impreso / verificador QR), no entra al editor.
 *
 * Reemplaza al andamiaje anterior (galería de imágenes por tipo). El check
 * "Mapeo de riesgos" en Imágenes del scouting SE QUEDA como puente que propone
 * primero esas fotos al agregar una vista.
 */
class RiskMapController extends Controller
{
    /** Imagen aceptada (el navegador ya comprime/convierte HEIC vía CCPhoto). */
    const IMG_RULES = 'mimes:jpeg,jpg,png,webp,heic,heif|heic_ok|max:12288'; // 12 MB (heic/heif de iPhone; ver ImageCompressor)

    /* ================================================================== */
    /* Índice + alta                                                       */
    /* ================================================================== */

    /** Todos los mapeos, listados por nombre de locación. */
    public function index()
    {
        $maps = RiskMap::with('scouting:id,location_name')
            ->withCount('views')
            ->orderByDesc('id')
            ->get();

        $rows = $maps->map(function (RiskMap $m) {
            return [
                'id'       => $m->id,
                'title'    => $m->title,
                'location' => $m->locationName(),
                'views'    => (int) $m->views_count,
                'sealed'   => $m->isSealed(),
                'folio'    => $m->isSealed() ? $m->folio() : null,
                'date'     => optional($m->created_at)->format('d/m/Y'),
            ];
        })->values();

        // Scoutings disponibles para arrancar un mapeo nuevo (más recientes primero).
        $scoutings = ScoutingReport::orderByDesc('id')->get(['id', 'location_name']);

        return view('admin.riskmaps.index', ['rows' => $rows, 'scoutings' => $scoutings]);
    }

    /** Crea un borrador para un scouting y salta al editor. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'scouting_id' => 'required|integer|exists:scouting_reports,id',
            'title'       => 'nullable|string|max:160',
        ], [], ['scouting_id' => 'scouting']);

        $scouting = ScoutingReport::findOrFail($data['scouting_id']);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Mapeo — ' . $scouting->location_name;
        }

        $map = RiskMap::create([
            'scouting_id'   => $scouting->id,
            'project_id'    => $scouting->production_id ?: CurrentProduction::id(),
            'location_id'   => null, // reservado (no hay catálogo de locaciones)
            'title'         => mb_substr($title, 0, 160),
            'status'        => 'draft',
            'version'       => 1,
            'created_by_id' => auth()->id(),
        ]);

        return redirect()->route('riskmaps.edit', $map->id);
    }

    /* ================================================================== */
    /* Editor                                                              */
    /* ================================================================== */

    public function edit(Request $request, $id)
    {
        $map = RiskMap::with(['views.markers', 'scouting'])->findOrFail($id);

        // Sellado = inmutable: no hay editor, se ve el documento.
        if ($map->isSealed()) {
            return redirect()->route('riskmaps.document', $map->id);
        }

        $views = $map->views; // ya ordenadas
        $current = null;
        $wanted = (int) $request->query('view');
        if ($wanted) {
            $current = $views->firstWhere('id', $wanted);
        }
        if (! $current) {
            $current = $views->first();
        }

        return view('admin.riskmaps.edit', [
            'map'            => $map,
            'views'          => $views,
            'current'        => $current,
            'eligibleEvents' => $map->eligibleEvents(),
            'orphanMarkers'  => $map->orphanHazardMarkers(), // pines de peligro que el scouting ya no evalúa
            'scoutingPhotos' => $this->scoutingPhotos($map->scouting),
            'viewTypes'      => RiskMapView::VIEW_TYPES,
            'resourceTypes'  => RiskMapMarker::RESOURCE_TYPES,
        ]);
    }

    /** Renombra el mapeo (solo borrador). */
    public function updateMeta(Request $request, $id)
    {
        $map = $this->draftOrFail($id);
        $data = $request->validate([
            'title'     => 'nullable|string|max:160',
            'pin_scale' => 'nullable|in:sm,md,lg',
        ], [], ['title' => 'título']);

        if ($request->filled('title')) {
            $map->title = trim($data['title']);
        }
        if ($request->filled('pin_scale')) {
            $map->pin_scale = $data['pin_scale'];
        }
        $map->save();

        return $this->wantsJson($request)
            ? response()->json(['ok' => true])
            : back()->with('success', 'Mapeo actualizado.');
    }

    /* ================================================================== */
    /* Vistas (páginas)                                                    */
    /* ================================================================== */

    public function storeView(Request $request, $id)
    {
        $map = $this->draftOrFail($id);

        $data = $request->validate([
            'view_type'     => 'required|in:' . implode(',', array_keys(RiskMapView::VIEW_TYPES)),
            'label'         => 'nullable|string|max:160',
            'source'        => 'required|in:scouting_photo,upload',
            'image'         => 'required_if:source,upload|nullable|' . self::IMG_RULES,
            'scouting_path' => 'required_if:source,scouting_photo|nullable|string',
        ], [], ['view_type' => 'tipo de vista', 'image' => 'imagen']);

        // Resolver la imagen ORIGINAL (inmutable) según el origen.
        if ($data['source'] === 'upload') {
            $imagePath = $this->storeUpload($request->file('image'));
            $imageSource = 'upload';
        } else {
            $imagePath = $this->validatedScoutingPath($map->scouting, (string) $data['scouting_path']);
            if ($imagePath === null) {
                return back()->withErrors(['scouting_path' => 'Esa imagen no pertenece al scouting.'])->withInput();
            }
            $imageSource = 'scouting_photo';
        }

        $nextOrder = (int) $map->views()->max('sort_order') + 1;

        $view = RiskMapView::create([
            'risk_map_id'         => $map->id,
            'sort_order'          => $nextOrder,
            'view_type'           => $data['view_type'],
            'label'               => trim((string) ($data['label'] ?? '')) ?: null,
            'image_original_path' => $imagePath,
            'image_source'        => $imageSource,
        ]);

        return redirect()->route('riskmaps.edit', ['id' => $map->id, 'view' => $view->id])
            ->with('success', 'Vista agregada.');
    }

    /** Autosave de la vista: etiqueta, tipo y las tres narrativas. AJAX. */
    public function updateView(Request $request, $id, $viewId)
    {
        $map  = $this->draftOrFail($id);
        $view = $this->viewOrFail($map, $viewId);

        $data = $request->validate([
            'view_type'          => 'nullable|in:' . implode(',', array_keys(RiskMapView::VIEW_TYPES)),
            'label'              => 'nullable|string|max:160',
            'narrative_what'     => 'nullable|string|max:280',
            'narrative_decision' => 'nullable|string|max:280',
            'narrative_action'   => 'nullable|string|max:280',
        ]);

        foreach (['view_type', 'label', 'narrative_what', 'narrative_decision', 'narrative_action'] as $f) {
            if ($request->has($f)) {
                $val = trim((string) $data[$f]);
                $view->{$f} = ($f === 'view_type') ? ($val ?: $view->view_type) : ($val !== '' ? $val : null);
            }
        }
        $view->save();

        return response()->json(['ok' => true, 'display_label' => $view->displayLabel()]);
    }

    public function destroyView(Request $request, $id, $viewId)
    {
        $map  = $this->draftOrFail($id);
        $view = $this->viewOrFail($map, $viewId);

        // Solo se borra el archivo si LO SUBIMOS nosotros; jamás el del scouting.
        if ($view->image_source === 'upload') {
            $this->deleteUpload($view->image_original_path);
        }
        $view->delete(); // FK ON DELETE CASCADE limpia sus marcadores

        return $this->wantsJson($request)
            ? response()->json(['ok' => true])
            : back()->with('success', 'Vista eliminada.');
    }

    /** Reordena las vistas por arrastre. AJAX (order[] de ids). */
    public function reorderViews(Request $request, $id)
    {
        $map = $this->draftOrFail($id);
        $order = (array) $request->input('order', []);

        $own = $map->views()->pluck('id')->all();
        $pos = 1;
        foreach ($order as $vid) {
            $vid = (int) $vid;
            if (in_array($vid, $own, true)) {
                RiskMapView::where('id', $vid)->where('risk_map_id', $map->id)->update(['sort_order' => $pos++]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /* ================================================================== */
    /* Marcadores (gota + icono)                                           */
    /* ================================================================== */

    public function storeMarker(Request $request, $id, $viewId)
    {
        $map  = $this->draftOrFail($id);
        $view = $this->viewOrFail($map, $viewId);

        $marker = $this->applyMarkerData($request, $map, new RiskMapMarker(['view_id' => $view->id]));
        if (! $marker instanceof RiskMapMarker) {
            return $marker; // respuesta 422 de validación de negocio
        }
        $marker->sort_order = (int) $view->markers()->max('sort_order') + 1;
        $marker->save();

        return response()->json(['ok' => true, 'marker' => $this->markerDto($marker, $map)]);
    }

    public function updateMarker(Request $request, $id, $viewId, $markerId)
    {
        $map    = $this->draftOrFail($id);
        $view   = $this->viewOrFail($map, $viewId);
        $marker = $view->markers()->findOrFail($markerId);

        $marker = $this->applyMarkerData($request, $map, $marker);
        if (! $marker instanceof RiskMapMarker) {
            return $marker;
        }
        $marker->save();

        return response()->json(['ok' => true, 'marker' => $this->markerDto($marker, $map)]);
    }

    public function destroyMarker($id, $viewId, $markerId)
    {
        $map  = $this->draftOrFail($id);
        $view = $this->viewOrFail($map, $viewId);
        $view->markers()->where('id', $markerId)->delete();

        return response()->json(['ok' => true]);
    }

    /* ================================================================== */
    /* Sellado + documento                                                 */
    /* ================================================================== */

    public function seal(Request $request, $id)
    {
        $map = RiskMap::with('views.markers')->findOrFail($id);
        if ($map->isSealed()) {
            return redirect()->route('riskmaps.document', $map->id);
        }
        if ($map->views()->count() < 1) {
            return back()->withErrors(['seal' => 'Agrega al menos una vista antes de sellar.']);
        }

        // Pines de peligro COLGANTES: el scouting quitó ese peligro después de mapearlo.
        // No se sella un documento que certificaría un peligro que el scouting ya no
        // evalúa. NO se borran pines por nuestra cuenta: lo resuelve el safety (corrige
        // la evaluación del scouting o retira el pin en el editor). Ver RiskMap::orphanHazardMarkers().
        $orphans = $map->orphanHazardMarkers();
        if ($orphans->isNotEmpty()) {
            return back()->withErrors(['seal' =>
                'Hay ' . $orphans->count() . ' señal(es) de peligro que el scouting ya no evalúa. '
                . 'Corrige la evaluación del scouting o retira esos pines en el editor antes de sellar.']);
        }

        $map->status    = 'sealed';
        $map->folio     = 'RMAP-' . str_pad((string) $map->id, 4, '0', STR_PAD_LEFT);
        $map->sealed_at = now();
        $map->sealed_by = auth()->id();
        $map->save();

        $map->refresh();                                   // estado canónico en BD
        $sig = $map->signDocument(auth()->user(), $request); // firmante real (safety)
        if ($sig) {
            $map->seal_hash = $sig->document_hash;         // copia informativa (fuera del hash)
            $map->save();
        }

        return redirect()->route('riskmaps.document', $map->id)->with('success', 'Mapeo sellado.');
    }

    /** Documento imprimible (borrador = vista previa; sellado = definitivo). */
    public function document($id)
    {
        $map = RiskMap::with(['views.markers', 'scouting'])->findOrFail($id);

        // (2026-08-11) EXPORT PDF SERVER-SIDE (?pdf=1) — ADITIVO, antes del return normal. Reusa la
        // MISMA vista/datos y la pasa por Browsershot (Chrome headless) → descarga idéntica a
        // window.print(). Márgenes 0 (el @page Oficio manda). Ver [[browsershot-pdf-pipeline]].
        if (request()->boolean('pdf')) {
            $html = view('admin.riskmaps.document', [
                'map'            => $map,
                'eligibleEvents' => $map->eligibleEvents(),
            ])->render();
            return \App\Support\PdfExporter::download($html, 'RMAP-' . $map->id, [0, 0, 0, 0]);
        }

        return view('admin.riskmaps.document', [
            'map'            => $map,
            'eligibleEvents' => $map->eligibleEvents(),
            'pdfUrl'         => request()->fullUrlWithQuery(['pdf' => 1]),
        ]);
    }

    public function destroy($id)
    {
        $map = RiskMap::with('views')->findOrFail($id);
        if ($map->isSealed()) {
            abort(403, 'Un mapeo sellado no se elimina.');
        }
        // Borra archivos SUBIDOS (no los del scouting) antes de la cascada.
        foreach ($map->views as $v) {
            if ($v->image_source === 'upload') {
                $this->deleteUpload($v->image_original_path);
            }
        }
        $map->delete(); // cascada a vistas y marcadores

        return redirect()->route('riskmaps.index')->with('success', 'Mapeo eliminado.');
    }

    /* ================================================================== */
    /* Helpers                                                             */
    /* ================================================================== */

    /** Aplica y valida datos de un marcador. Devuelve el modelo o una respuesta 422. */
    private function applyMarkerData(Request $request, RiskMap $map, RiskMapMarker $marker)
    {
        $data = $request->validate([
            'kind'           => 'required|in:resource,hazard', // 'area' (polígono) fuera de alcance
            'x_pct'          => 'required|numeric|min:0|max:100',
            'y_pct'          => 'required|numeric|min:0|max:100',
            'label_x_pct'    => 'nullable|numeric|min:0|max:100',
            'label_y_pct'    => 'nullable|numeric|min:0|max:100',
            'label_side'     => 'nullable|in:left,right',
            'reference_text' => 'nullable|string|max:200',
            'resource_type'  => 'nullable|in:' . implode(',', array_keys(RiskMapMarker::RESOURCE_TYPES)),
            'event_id'       => 'nullable|integer',
        ]);

        $marker->kind           = $data['kind'];
        $marker->x_pct          = round((float) $data['x_pct'], 3);
        $marker->y_pct          = round((float) $data['y_pct'], 3);
        $marker->label_side     = $data['label_side'] ?? 'right';
        $marker->reference_text = trim((string) ($data['reference_text'] ?? '')) ?: null;

        // Posición de la etiqueta (chip). Se envía solo al reubicarla; vacío = auto junto al pin.
        if ($request->has('label_x_pct')) {
            $marker->label_x_pct = $request->filled('label_x_pct') ? round((float) $data['label_x_pct'], 3) : null;
            $marker->label_y_pct = $request->filled('label_y_pct') ? round((float) $data['label_y_pct'], 3) : null;
        }

        if ($data['kind'] === 'resource') {
            if (empty($data['resource_type'])) {
                return response()->json(['ok' => false, 'error' => 'Elige el tipo de recurso.'], 422);
            }
            $marker->resource_type = $data['resource_type'];
            $marker->event_id = null;
        } else { // hazard
            $eid = (int) ($data['event_id'] ?? 0);
            if (! $map->eligibleEvents()->has($eid)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Ese peligro no está evaluado en el scouting. Agrégalo en la evaluación y vuelve.',
                ], 422);
            }
            $marker->event_id = $eid;
            $marker->resource_type = null;
        }

        return $marker;
    }

    /** DTO de un marcador para el editor (etiqueta/icono ya resueltos). */
    private function markerDto(RiskMapMarker $marker, RiskMap $map): array
    {
        if ($marker->kind === 'resource') {
            $label = $marker->resourceLabel();
            $short = $label;
            $icon  = $marker->iconKey();
        } else {
            $ev = $map->eligibleEvents()->get((int) $marker->event_id);
            $label = $ev['name'] ?? ('#' . $marker->event_id);
            $short = $ev['short'] ?? 'Peligro';
            $icon  = $ev['icon'] ?? 'haz-warn';
        }

        return [
            'id'             => $marker->id,
            'kind'           => $marker->kind,
            'resource_type'  => $marker->resource_type,
            'event_id'       => $marker->event_id,
            'x_pct'          => (float) $marker->x_pct,
            'y_pct'          => (float) $marker->y_pct,
            'label_x'        => $marker->label_x_pct !== null ? (float) $marker->label_x_pct : null,
            'label_y'        => $marker->label_y_pct !== null ? (float) $marker->label_y_pct : null,
            'label_side'     => $marker->label_side,
            'reference_text' => $marker->reference_text,
            'icon'           => $icon,      // pictograma (recurso o categoría del peligro)
            'label'          => $label,     // nombre completo (tooltip)
            'short'          => $short,     // etiqueta corta del chip
            'color'          => $marker->color(),
            'ink'            => $marker->ink(), // color del símbolo (negro en peligros)
        ];
    }

    /** Fotos del scouting para "Agregar vista": las marcadas (puente) primero. */
    private function scoutingPhotos($scouting): array
    {
        if (! $scouting) {
            return [];
        }
        $out = [];
        // Principal del scouting primero (si existe).
        $main = trim((string) $scouting->main_image_path);
        if ($main !== '') {
            $out[] = ['path' => $main, 'caption' => 'Imagen principal', 'flagged' => false, 'main' => true];
        }
        foreach ($scouting->additionalImagesList() as $im) {
            $out[] = [
                'path'    => $im['path'],
                'caption' => $im['caption'],
                'flagged' => ! empty($im['risk_map']),
                'main'    => false,
            ];
        }
        // Puente: primero las marcadas "Mapeo de riesgos".
        usort($out, function ($a, $b) {
            return ($b['flagged'] <=> $a['flagged']);
        });
        return $out;
    }

    /** Comprueba que una ruta pertenece al scouting (evita inyección de rutas). */
    private function validatedScoutingPath($scouting, string $path): ?string
    {
        if (! $scouting) {
            return null;
        }
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $allowed = [];
        $main = trim((string) $scouting->main_image_path);
        if ($main !== '') {
            $allowed[] = $main;
        }
        foreach ($scouting->additionalImagesList() as $im) {
            $allowed[] = $im['path'];
        }
        return in_array($path, $allowed, true) ? $path : null;
    }

    /** Guarda una imagen subida en disco 'public' y devuelve su URL RELATIVA. */
    private function storeUpload($image): string
    {
        // HEIC (iPhone) → JPEG si el servidor puede convertir; si no, la validación ya lo rechazó.
        $image    = ImageCompressor::normalizeForUpload($image);
        $filename = time() . '_rmap_' . uniqid() . '.' . ImageCompressor::safeExtensionOrBin($image);
        $path = $image->storeAs('riskmaps', $filename, 'public');
        return Storage::url($path);
    }

    private function deleteUpload($url): void
    {
        if (! is_string($url) || strpos($url, '/storage/') !== 0) {
            return;
        }
        try {
            Storage::disk('public')->delete(substr($url, strlen('/storage/')));
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    private function draftOrFail($id): RiskMap
    {
        $map = RiskMap::findOrFail($id);
        if ($map->isSealed()) {
            abort(403, 'Un mapeo sellado es inmutable.');
        }
        return $map;
    }

    private function viewOrFail(RiskMap $map, $viewId): RiskMapView
    {
        return $map->views()->findOrFail($viewId);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }
}
