<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\TransportAccess;
use App\Support\VehicleChecklist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Transportación · Editor del CATÁLOGO DE TIPOS (§5 · Capa 4).
 *
 * El catálogo `vehicle_types` es GLOBAL y EDITABLE por producción (a diferencia del de ambulancias,
 * de fondo). Cada tipo trae un PERFIL de atributos precargado (`attr_profile`) que PROPONE al crear
 * un vehículo; los atributos siguen siendo ajustables por unidad. Editar el perfil de un tipo NO
 * altera los vehículos ya dados de alta (cada uno guarda su propio `attr_values`), ni las actas
 * selladas (usan su `checklist_snapshot`). Gate: canFull (transpo). `verified_*` es estado
 * server-only (guarded), el editor no lo toca.
 */
class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $types = VehicleType::orderByDesc('is_active')->orderBy('sort_order')->orderBy('name_es')->get();

        // Conteo de vehículos por tipo → para la baja INFORMADA (§5 ⚠).
        $counts = Vehicle::where('is_active', 1)
            ->selectRaw('vehicle_type_id, COUNT(*) as c')
            ->groupBy('vehicle_type_id')
            ->pluck('c', 'vehicle_type_id');

        return view('transport.types.index', ['types' => $types, 'counts' => $counts]);
    }

    public function store(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $this->validateType($request, null);
        $type = new VehicleType($this->attributes($data));
        $type->is_active = 1;
        $type->save();

        return back()->with('ok', __('Tipo creado.'));
    }

    public function update(Request $request, VehicleType $type)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $this->validateType($request, $type);
        // Sólo campos editables; is_active/verified_* quedan como estaban (los vehículos NO cambian).
        $type->fill($this->attributes($data));
        $type->save();

        return back()->with('ok', __('Tipo actualizado. Los vehículos ya registrados conservan sus atributos.'));
    }

    /**
     * BAJA = desactivar (is_active=0). NO borrado físico. Deja de PROPONERSE en altas nuevas; los
     * vehículos existentes conservan su `attr_values` y sus actas selladas siguen intactas.
     * (Decisión REPORTADA, no impuesta: ver el reporte de §5.)
     */
    public function destroy(Request $request, VehicleType $type)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $type->is_active = 0;
        $type->save();

        return back()->with('ok', __('Tipo desactivado: deja de proponerse. Los vehículos ya registrados no cambian.'));
    }

    public function restore(Request $request, VehicleType $type)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $type->is_active = 1;
        $type->save();

        return back()->with('ok', __('Tipo reactivado.'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private function validateType(Request $request, ?VehicleType $type): array
    {
        return $request->validate([
            'code'                          => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('vehicle_types', 'code')->ignore($type?->id)],
            'name_es'                       => 'required|string|max:120',
            'name_en'                       => 'nullable|string|max:120',
            'sort_order'                    => 'nullable|integer',
            'is_special'                    => 'nullable|boolean',
            'notes'                         => 'nullable|string|max:2000',
            'powertrain'                    => 'nullable|in:combustion,electric,hybrid',
            'seats'                         => 'nullable|integer|min:0|max:200',
            'water_tank_liters'             => 'nullable|integer|min:0',
            'has_cargo_box'                 => 'nullable|boolean',
            'has_lpg_or_sanitary'           => 'nullable|boolean',
            'has_genset_or_heat_appliances' => 'nullable|boolean',
            'tows'                          => 'nullable|boolean',
            'is_towed'                      => 'nullable|boolean',
        ], [
            'code.regex' => __('El código sólo admite minúsculas, números y guion bajo.'),
        ]);
    }

    /** Campos escribibles del tipo. El 'especial' no lleva perfil (todo se declara a mano). */
    private function attributes(array $data): array
    {
        $special = ! empty($data['is_special']);

        $profile = $special ? [] : VehicleChecklist::normalizeAttributes([
            'powertrain'                    => $data['powertrain'] ?? 'combustion',
            'seats'                         => $data['seats'] ?? null,
            'water_tank_liters'             => $data['water_tank_liters'] ?? null,
            'has_cargo_box'                 => ! empty($data['has_cargo_box']),
            'has_lpg_or_sanitary'           => ! empty($data['has_lpg_or_sanitary']),
            'has_genset_or_heat_appliances' => ! empty($data['has_genset_or_heat_appliances']),
            'tows'                          => ! empty($data['tows']),
            'is_towed'                      => ! empty($data['is_towed']),
        ]);

        return [
            'code'         => $data['code'],
            'name_es'      => $data['name_es'],
            'name_en'      => $data['name_en'] ?? null,
            'sort_order'   => (int) ($data['sort_order'] ?? 0),
            'is_special'   => $special,
            'notes'        => $data['notes'] ?? null,
            'attr_profile' => $profile,
        ];
    }
}
