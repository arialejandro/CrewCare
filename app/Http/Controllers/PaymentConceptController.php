<?php

namespace App\Http\Controllers;

use App\Models\PaymentConcept;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;

/**
 * CATÁLOGO DE CONCEPTOS DE PAGO — CRUD mínimo para contabilidad (permiso periods.manage). Los GLOBALES
 * (production_id NULL, sembrados) se ven pero NO se editan/borran aquí: son el default compartido; una
 * producción agrega/edita LOS SUYOS. No valida nada del negocio: es vocabulario editable.
 */
class PaymentConceptController extends Controller
{
    public function index(Request $request)
    {
        $productionId = CurrentProduction::id();

        $concepts = PaymentConcept::query()
            ->forProduction($productionId)
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return view('periods.concepts', compact('concepts', 'productionId'));
    }

    public function store(Request $request)
    {
        $productionId = CurrentProduction::id();
        abort_unless($productionId, 409, 'No hay una producción activa.');

        $data = $this->validated($request);
        PaymentConcept::create($data + [
            'production_id' => $productionId,
            'created_by_id' => $request->user()->id,
        ]);

        return back()->with('status', __('Concepto agregado.'));
    }

    public function update(Request $request, PaymentConcept $concept)
    {
        // Los GLOBALES (production_id NULL) no se editan desde una producción: son el default compartido.
        abort_if($concept->production_id === null, 403, 'Los conceptos globales no se editan aquí.');
        $this->authorizeSameProduction($concept);

        $concept->update($this->validated($request));

        return back()->with('status', __('Concepto actualizado.'));
    }

    public function destroy(Request $request, PaymentConcept $concept)
    {
        abort_if($concept->production_id === null, 403, 'Los conceptos globales no se borran aquí.');
        $this->authorizeSameProduction($concept);

        $concept->delete();

        return back()->with('status', __('Concepto eliminado.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'code'        => 'required|string|max:40',
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:400',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'is_active'   => 'nullable|boolean',
        ]);
    }

    private function authorizeSameProduction(PaymentConcept $concept): void
    {
        abort_unless($concept->production_id === CurrentProduction::id(), 403);
    }
}
