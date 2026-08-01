<?php

namespace App\Http\Controllers;

use App\Models\ScoutingReport;
use App\Models\ScoutingCanvas;
use App\Models\CanvasPin;
use App\Support\ImageCompressor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mapeo de la locación (delta #48): pines sobre lienzos. Insumo del PAE.
 *
 * Toda la autorización la imponen las rutas (permission:locations.create, igual
 * que editar un scouting). Este módulo NO toca sellos, firmas ni rutas públicas:
 * su salida (imagen con pines quemados) la compone el navegador con html2canvas.
 */
class ScoutingCanvasController extends Controller
{
    /** Extensiones de imagen aceptadas para lienzos y fotos de pin. */
    const IMG_RULES = 'image|mimes:jpeg,jpg,png,webp|max:12288'; // 12 MB

    /**
     * Página de mapeo (editor + revisión). Lista los lienzos con sus pines y la
     * lista de peligros YA evaluados en el scouting para el selector del pin de
     * peligro. Un scouting sin lienzos es válido y se muestra vacío.
     */
    public function index($id)
    {
        $scouting = ScoutingReport::findOrFail($id);

        $canvases = ScoutingCanvas::where('scouting_report_id', $scouting->id)
            ->orderBy('id')
            ->with('pins')
            ->get();

        $hazards  = $this->evaluatedHazards($scouting);   // para el selector de pin de peligro
        $hazMap   = $hazards->keyBy('event_id');          // event_id => datos, para etiquetar pines

        // Payload JSON que consume el JS de la página: un solo camino de render para
        // los pines (mismo punto en pantalla chica, grande y en la imagen compuesta).
        $payload = $canvases->map(function (ScoutingCanvas $c) use ($hazMap) {
            return [
                'id'        => $c->id,
                'type'      => $c->type,
                'type_label'=> $c->typeLabel(),
                'name'      => $c->name,
                'image_url' => Storage::disk('public')->url($c->image_path),
                'uses_geo'  => $c->usesGeo(),
                'pins'      => $c->pins->map(function (CanvasPin $p) use ($hazMap) {
                    return $this->pinPayload($p, $hazMap);
                })->values(),
            ];
        })->values();

        return view('admin.scoutings.mapping', [
            'scouting'  => $scouting,
            'payload'   => $payload,
            'hazards'   => $hazards->values(),
            'resources' => CanvasPin::RESOURCES,
            'types'     => ScoutingCanvas::TYPES,
        ]);
    }

    /** Alta de un lienzo (imagen + nombre + tipo). Recarga la página. */
    public function storeCanvas(Request $request, $id)
    {
        $scouting = ScoutingReport::findOrFail($id);

        $data = $request->validate([
            'type'  => 'required|in:satelital,foto,plano,aereo',
            'name'  => 'required|string|max:120',
            'image' => 'required|' . self::IMG_RULES,
        ], [], [
            'type'  => 'tipo de lienzo',
            'name'  => 'nombre',
            'image' => 'imagen',
        ]);

        $path = ImageCompressor::store($request->file('image'), 'scouting_canvases');
        if ($path === null) {
            return back()->with('error', 'No se pudo guardar la imagen del lienzo (¿es una imagen válida?).');
        }

        ScoutingCanvas::create([
            'scouting_report_id' => $scouting->id,
            'type'               => $data['type'],
            'name'               => trim($data['name']),
            'image_path'         => $path,
        ]);

        return back()->with('success', 'Lienzo agregado.');
    }

    /** Borra un lienzo con TODOS sus pines (y las imágenes en disco). */
    public function destroyCanvas($id, $canvasId)
    {
        $canvas = $this->findCanvas($id, $canvasId);

        foreach ($canvas->pins as $pin) {
            $this->deleteFile($pin->photo_path);
        }
        $canvas->pins()->delete();
        $this->deleteFile($canvas->image_path);
        $canvas->delete();

        return back()->with('success', 'Lienzo eliminado.');
    }

    /** Coloca un pin (JSON/AJAX). El pin de peligro DEBE referenciar un peligro ya evaluado. */
    public function storePin(Request $request, $id, $canvasId)
    {
        $scouting = ScoutingReport::findOrFail($id);
        $canvas   = $this->findCanvas($id, $canvasId);

        $data = $request->validate([
            'family'          => 'required|in:recurso,peligro',
            'resource_type'   => 'nullable|string|max:40',
            'hazard_event_id' => 'nullable|integer',
            'x_pct'           => 'required|numeric|min:0|max:100',
            'y_pct'           => 'required|numeric|min:0|max:100',
            'note'            => 'nullable|string|max:300',
            'photo'           => 'nullable|' . self::IMG_RULES,
            'geo_lat'         => 'nullable|numeric|between:-90,90',
            'geo_lng'         => 'nullable|numeric|between:-180,180',
        ]);

        [$ok, $msg, $resourceType, $hazardId] = $this->resolveFamily($scouting, $data);
        if (!$ok) {
            return response()->json(['message' => $msg], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = ImageCompressor::store($request->file('photo'), 'canvas_pins');
        }

        $pin = CanvasPin::create([
            'scouting_canvas_id' => $canvas->id,
            'family'             => $data['family'],
            'resource_type'      => $resourceType,
            'hazard_event_id'    => $hazardId,
            'x_pct'              => round((float) $data['x_pct'], 3),
            'y_pct'              => round((float) $data['y_pct'], 3),
            'note'               => isset($data['note']) ? trim($data['note']) : null,
            'photo_path'         => $photoPath,
            'geo_lat'            => $data['geo_lat'] ?? null,
            'geo_lng'            => $data['geo_lng'] ?? null,
        ]);

        $hazMap = $this->evaluatedHazards($scouting)->keyBy('event_id');
        return response()->json(['pin' => $this->pinPayload($pin, $hazMap)]);
    }

    /** Mueve un pin (x/y), edita su nota o su foto (JSON/AJAX). */
    public function updatePin(Request $request, $id, $canvasId, $pinId)
    {
        $scouting = ScoutingReport::findOrFail($id);
        $canvas   = $this->findCanvas($id, $canvasId);
        $pin      = CanvasPin::where('scouting_canvas_id', $canvas->id)->findOrFail($pinId);

        $data = $request->validate([
            'x_pct' => 'nullable|numeric|min:0|max:100',
            'y_pct' => 'nullable|numeric|min:0|max:100',
            'note'  => 'nullable|string|max:300',
            'photo' => 'nullable|' . self::IMG_RULES,
        ]);

        if (array_key_exists('x_pct', $data) && $data['x_pct'] !== null) {
            $pin->x_pct = round((float) $data['x_pct'], 3);
        }
        if (array_key_exists('y_pct', $data) && $data['y_pct'] !== null) {
            $pin->y_pct = round((float) $data['y_pct'], 3);
        }
        if ($request->has('note')) {
            $pin->note = $data['note'] !== null ? trim($data['note']) : null;
        }
        if ($request->hasFile('photo')) {
            $new = ImageCompressor::store($request->file('photo'), 'canvas_pins');
            if ($new !== null) {
                $this->deleteFile($pin->photo_path);
                $pin->photo_path = $new;
            }
        }
        $pin->save();

        $hazMap = $this->evaluatedHazards($scouting)->keyBy('event_id');
        return response()->json(['pin' => $this->pinPayload($pin, $hazMap)]);
    }

    /** Borra un pin (JSON/AJAX). */
    public function destroyPin($id, $canvasId, $pinId)
    {
        $canvas = $this->findCanvas($id, $canvasId);
        $pin    = CanvasPin::where('scouting_canvas_id', $canvas->id)->findOrFail($pinId);

        $this->deleteFile($pin->photo_path);
        $pin->delete();

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** Carga un lienzo garantizando que pertenece a ESTE scouting (evita cross-tampering). */
    private function findCanvas($id, $canvasId): ScoutingCanvas
    {
        return ScoutingCanvas::where('scouting_report_id', $id)->findOrFail($canvasId);
    }

    /**
     * Valida la familia del pin y devuelve [ok, mensaje, resource_type, hazard_event_id].
     * - recurso: resource_type ∈ lista cerrada; hazard_event_id se ignora.
     * - peligro: hazard_event_id ∈ peligros YA evaluados del scouting; resource_type se ignora.
     *   Así el mapa NO se vuelve una vía paralela para registrar peligros sin evaluar.
     */
    private function resolveFamily(ScoutingReport $scouting, array $data): array
    {
        if ($data['family'] === 'recurso') {
            $rt = $data['resource_type'] ?? null;
            if (!is_string($rt) || !array_key_exists($rt, CanvasPin::RESOURCES)) {
                return [false, 'Tipo de recurso no válido.', null, null];
            }
            return [true, '', $rt, null];
        }

        // peligro
        $hid = isset($data['hazard_event_id']) ? (int) $data['hazard_event_id'] : 0;
        if ($hid <= 0) {
            return [false, 'Falta el peligro a referenciar.', null, null];
        }
        if (!in_array($hid, $this->evaluatedEventIds($scouting), true)) {
            return [false, 'Ese peligro no está evaluado en esta locación. Agrégalo por la evaluación de riesgos y luego ubícalo.', null, null];
        }
        return [true, '', null, $hid];
    }

    /**
     * IDs de eventos del catálogo YA evaluados (y clasificados) en el risk_assessment
     * del scouting. Un peligro sin evento (unclassified) no es referenciable por el
     * mapa: primero debe clasificarse en la evaluación.
     */
    private function evaluatedEventIds(ScoutingReport $scouting): array
    {
        return $this->evaluatedHazards($scouting)->pluck('event_id')->all();
    }

    /**
     * Peligros evaluados del scouting como colección de {event_id, label, rating},
     * deduplicados por event_id. Fuente: la columna JSON risk_assessment (no hay
     * tabla por-peligro; ver diagnóstico). Un mismo event_id puede tener varios
     * pines (aparece en varios sitios), por eso el pin referencia por event_id.
     */
    private function evaluatedHazards(ScoutingReport $scouting)
    {
        $rows = $scouting->risk_assessment;
        if (!is_array($rows)) {
            return collect();
        }

        return collect($rows)
            ->filter(function ($r) {
                return is_array($r) && !empty($r['event_id']) && empty($r['unclassified']);
            })
            ->map(function ($r) {
                $label = $r['hazard'] ?? ($r['event_name'] ?? ('Evento ' . $r['event_id']));
                return [
                    'event_id' => (int) $r['event_id'],
                    'label'    => (string) $label,
                    'rating'   => (string) ($r['rating'] ?? ($r['risk'] ?? '')),
                ];
            })
            ->unique('event_id')
            ->values();
    }

    /** Representación de un pin para el JSON de la página (un solo camino de render). */
    private function pinPayload(CanvasPin $pin, $hazMap): array
    {
        if ($pin->family === 'recurso') {
            $label = CanvasPin::RESOURCES[$pin->resource_type] ?? 'Recurso';
            $rating = '';
        } else {
            $haz = $hazMap->get($pin->hazard_event_id);
            $label = $haz['label'] ?? '(peligro ya no evaluado)';
            $rating = $haz['rating'] ?? '';
        }

        return [
            'id'            => $pin->id,
            'family'        => $pin->family,
            'resource_type' => $pin->resource_type,
            'hazard_event_id' => $pin->hazard_event_id,
            'x_pct'         => (float) $pin->x_pct,
            'y_pct'         => (float) $pin->y_pct,
            'note'          => $pin->note,
            'photo_url'     => $pin->photo_path ? Storage::disk('public')->url($pin->photo_path) : null,
            'geo_lat'       => $pin->geo_lat,
            'geo_lng'       => $pin->geo_lng,
            'label'         => $label,
            'rating'        => $rating,
        ];
    }

    private function deleteFile($path): void
    {
        if (is_string($path) && $path !== '' && strpos($path, 'data:') !== 0) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                // best-effort: no romper el flujo si el archivo ya no está.
            }
        }
    }
}
