<?php

namespace App\Http\Controllers;

use App\Models\ScoutingReport;
use App\Models\TransportPickupPoint;
use App\Models\TransportTravelTime;
use App\Support\CurrentProduction;
use App\Support\TransportAccess;
use Illuminate\Http\Request;

/**
 * Transportación · Pick up derivado — FASE 1: PUNTOS DE PICKUP + MATRIZ DE TRASLADO.
 *
 * Catálogo de orígenes (con coordenadas, capturado una vez) + el tiempo del PAR origen→destino
 * (la locación del día = un `scouting_reports` geolocalizado). `CrewGeo.driveEta` propone en el
 * navegador; transpo corrige; lo corregido persiste por par (unique). Gate: canFull (transpo).
 * 100% aditivo — no toca la corrida ni el pick up literal (eso es Fase 2).
 */
class TransportMatrixController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $pid = CurrentProduction::id();

        $points = TransportPickupPoint::where('production_id', $pid)
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // Destinos = locaciones scouteadas CON coordenadas (heredan lat/lng al par).
        $destinations = ScoutingReport::where('production_id', $pid)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'latitude', 'longitude']);

        // Tiempos guardados, indexados por "punto-scouting" para el lookup del grid.
        $times = TransportTravelTime::where('production_id', $pid)
            ->get()
            ->keyBy(fn (TransportTravelTime $t) => $t->pickup_point_id . '-' . $t->scouting_id);

        return view('transport.matrix.index', [
            'points'       => $points,
            'destinations' => $destinations,
            'times'        => $times,
        ]);
    }

    // ── Puntos de pickup ─────────────────────────────────────────────────────
    public function storePoint(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $this->validatePoint($request);
        TransportPickupPoint::create($data + [
            'production_id' => CurrentProduction::id(),
            'created_by_id' => $request->user()->id,
            'is_active'     => 1,
        ]);

        return back()->with('ok', __('Punto de pickup guardado.'));
    }

    public function updatePoint(Request $request, TransportPickupPoint $point)
    {
        $this->authorizePoint($request, $point);

        $point->fill($this->validatePoint($request));
        $point->save();

        return back()->with('ok', __('Punto actualizado.'));
    }

    public function destroyPoint(Request $request, TransportPickupPoint $point)
    {
        $this->authorizePoint($request, $point);

        $point->is_active = 0;
        $point->save();

        return back()->with('ok', __('Punto dado de baja.'));
    }

    // ── Tiempo del par (matriz) ──────────────────────────────────────────────
    /**
     * Upsert del tiempo de un par origen→destino. `CrewGeo` propone en el cliente; aquí sólo se
     * PERSISTE lo que transpo guarda. source='corrected' si transpo lo ajustó a mano; 'proposed'
     * si guardó la propuesta tal cual. Lo corregido manda y persiste para ese par.
     */
    public function saveTime(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $request->validate([
            'pickup_point_id' => 'required|integer|exists:transport_pickup_points,id',
            'scouting_id'     => 'required|integer|exists:scouting_reports,id',
            'minutes'         => 'required|integer|min:1|max:1440',
            'source'          => 'nullable|in:proposed,corrected',
        ]);

        $corrected = ($data['source'] ?? 'corrected') === TransportTravelTime::SOURCE_CORRECTED;

        TransportTravelTime::updateOrCreate(
            ['pickup_point_id' => $data['pickup_point_id'], 'scouting_id' => $data['scouting_id']],
            [
                'production_id'   => CurrentProduction::id(),
                'minutes'         => $data['minutes'],
                'source'          => $corrected ? TransportTravelTime::SOURCE_CORRECTED : TransportTravelTime::SOURCE_PROPOSED,
                'corrected_by_id' => $corrected ? $request->user()->id : null,
            ]
        );

        return back()->with('ok', __('Tiempo de traslado guardado.'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private function authorizePoint(Request $request, TransportPickupPoint $point): void
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);
        abort_unless((int) $point->production_id === (int) CurrentProduction::id(), 404);
    }

    private function validatePoint(Request $request): array
    {
        return $request->validate([
            'name'       => 'required|string|max:120',
            'address'    => 'nullable|string|max:255',
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer',
        ]);
    }
}
