<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\TransportPositionConfig;
use App\Models\TransportVehicleAssignment;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentProduction;
use App\Support\TransportAccess;
use Illuminate\Http\Request;

/**
 * Transportación · Config de crew (Fase 2), toda POR PRODUCCIÓN y NADA en código:
 *   - Puestos: "jefatura" (habilita discreto; default = is_hod) y "lleva pick up siempre" (modo LIGERO).
 *   - Asignación FIJA de vehículo: puesto→vehículo (el pasajero cambia, el puesto no) o persona→vehículo
 *     como excepción. El DRIVER NO se toca (es atributo del vehículo). La PRECARGA en la orden = Fase 3.
 * Gate: canFull.
 */
class TransportConfigController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $pid = CurrentProduction::id();

        $positions = Position::where('active', 1)
            ->where(fn ($q) => $q->whereNull('production_id')->orWhere('production_id', $pid))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'is_hod']);

        $cfg = TransportPositionConfig::where('production_id', $pid)->get()->keyBy('position_id');

        $assignments = TransportVehicleAssignment::where('production_id', $pid)
            ->where('is_active', 1)
            ->with(['vehicle', 'position', 'user'])
            ->orderByDesc('id')
            ->get();

        $vehicles = Vehicle::where('is_active', 1)->orderBy('make')->orderBy('model')->get()
            ->map(fn (Vehicle $v) => ['id' => $v->id, 'label' => trim(($v->make ?: '') . ' ' . ($v->model ?: '')) ?: ('#' . $v->id), 'plate' => $v->plate]);

        // Crew de la producción para la excepción persona→vehículo.
        $prod = CurrentProduction::get();
        $people = $prod ? $prod->members()->orderBy('name')->get()->map(fn (User $u) => ['id' => $u->id, 'name' => User::displayName($u)]) : collect();

        return view('transport.config.index', [
            'positions'   => $positions,
            'cfg'         => $cfg,
            'assignments' => $assignments,
            'vehicles'    => $vehicles,
            'people'      => $people,
        ]);
    }

    /** Guarda las marcas por puesto (jefatura / lleva-pick-up-siempre) en bloque. */
    public function savePositions(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $request->validate([
            'pos_ids'      => 'array',
            'pos_ids.*'    => 'integer',
            'leadership'   => 'array',
            'leadership.*' => 'integer',
            'always'       => 'array',
            'always.*'     => 'integer',
        ]);

        $pid    = CurrentProduction::id();
        $lead   = array_flip($data['leadership'] ?? []);
        $always = array_flip($data['always'] ?? []);

        foreach (($data['pos_ids'] ?? []) as $posId) {
            TransportPositionConfig::updateOrCreate(
                ['production_id' => $pid, 'position_id' => (int) $posId],
                ['is_leadership' => isset($lead[$posId]), 'always_pickup' => isset($always[$posId]), 'is_active' => 1]
            );
        }

        return back()->with('ok', __('Puestos actualizados.'));
    }

    public function storeAssignment(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $request->validate([
            'subject_kind' => 'required|in:position,person',
            'position_id'  => 'nullable|integer|exists:positions,id',
            'user_id'      => 'nullable|integer|exists:users,id',
            'vehicle_id'   => 'required|integer|exists:vehicles,id',
        ]);

        $isPos = $data['subject_kind'] === 'position';
        if ($isPos && empty($data['position_id'])) {
            return back()->withErrors(['position_id' => __('Elige un puesto.')]);
        }
        if (! $isPos && empty($data['user_id'])) {
            return back()->withErrors(['user_id' => __('Elige una persona.')]);
        }

        TransportVehicleAssignment::create([
            'production_id' => CurrentProduction::id(),
            'position_id'   => $isPos ? $data['position_id'] : null,
            'user_id'       => $isPos ? null : $data['user_id'],
            'vehicle_id'    => $data['vehicle_id'],
            'is_active'     => 1,
            'created_by_id' => $request->user()->id,
        ]);

        return back()->with('ok', __('Asignación guardada.'));
    }

    public function destroyAssignment(Request $request, TransportVehicleAssignment $assignment)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);
        abort_unless((int) $assignment->production_id === (int) CurrentProduction::id(), 404);

        $assignment->is_active = 0;
        $assignment->save();

        return back()->with('ok', __('Asignación dada de baja.'));
    }
}
