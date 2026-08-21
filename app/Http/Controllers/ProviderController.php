<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Support\ContractVisibility;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ALTA DE PROVEEDOR (carril "proveedor puro": el que NO está en el llamado). Crea la IDENTIDAD del
 * proveedor (Payee, física o moral, SIN perfil de crew) junto con su PRIMER contrato de renta/servicio.
 *
 * Por qué payee + contrato en el mismo acto: la visibilidad de "quién cobra" se deriva del CONTRATANTE
 * del contrato ({@see Payee::scopeVisibleTo} eje A) — un proveedor sin usuario y sin contrato quedaría
 * huérfano/invisible. Al firmar su primer contrato el HOD que lo da de alta, el proveedor queda scoped
 * a SU departamento (y {@see ContractVisibility} lo alinea para el panel de contratos).
 *
 * REGLA DEL CREWLIST: si la persona ESTARÁ en el llamado (susceptible a safety/medic/logística), NO va
 * por aquí — es crew y se da de alta con perfil. Este carril es SOLO para proveedores fuera del llamado
 * (jardineros, empresas que solo facturan/entregan). Los documentos se recogen después, en la ficha.
 */
class ProviderController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $depts = $this->assignableDepartments($user);
        abort_if($depts->isEmpty(), 403, __('No tienes un departamento donde dar de alta proveedores.'));

        return view('payee.provider-create', [
            'departments' => $depts,
            'frequencies' => PayeeContract::frequencies(),
        ]);
    }

    public function store(Request $request)
    {
        $user  = $request->user();
        $depts = $this->assignableDepartments($user);
        abort_if($depts->isEmpty(), 403);

        // Regla del CrewList: si está en el llamado, es CREW (perfil) — no un proveedor puro. Aquí se
        // corta con una guía clara; el alta como crew (con perfil + intake médico) es otro camino.
        if ($request->boolean('in_crewlist')) {
            return back()->withInput()->with('error', __('Si estará en el llamado es CREW (necesita perfil por safety/médico/logística). Dalo de alta como crew, no como proveedor.'));
        }

        $data = $request->validate([
            'legal_nature'        => ['required', 'in:fisica,moral'],
            'name'                => ['required', 'string', 'max:191'],
            'rfc'                 => ['nullable', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:191'],
            'phone'               => ['nullable', 'string', 'max:40'],
            'legal_representative' => ['nullable', 'string', 'max:191', 'required_if:legal_nature,moral'],
            'department_id'       => ['required', 'integer'],
            'concept'             => ['required', 'in:equipment_rental,service'],
            'title'               => ['nullable', 'string', 'max:191'],
            'fee_amount'          => ['nullable', 'numeric', 'min:0'],
            'fee_currency'        => ['nullable', 'in:MXN,USD'],
            'payment_frequency'   => ['nullable', 'in:weekly,biweekly,day_player'],
        ]);

        // El departamento elegido debe estar en el alcance del usuario (HOD su depto; ve-todo cualquiera).
        abort_unless($depts->pluck('id')->contains((int) $data['department_id']), 403);

        $envelope = DB::transaction(function () use ($data, $user) {
            $payee = Payee::create([
                'legal_nature' => $data['legal_nature'],
                'name'         => $data['name'],
                'rfc'          => $data['rfc'] ?? null,
                'email'        => $data['email'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'legal_representative' => $data['legal_nature'] === Payee::NATURE_MORAL ? ($data['legal_representative'] ?? null) : null,
                'is_active'    => 1,
                'created_by_id' => $user->id,
            ]);

            $payee->contracts()->create([
                'production_id'         => CurrentProduction::id(),
                'concept'               => $data['concept'],
                'title'                 => $data['title'] ?? null,
                'department_id'         => (int) $data['department_id'],
                'contracted_by_user_id' => $user->id,   // ← el HOD que lo da de alta = contratante (visibilidad)
                'fee_amount'            => $data['fee_amount'] ?? null,
                'fee_currency'          => $data['fee_currency'] ?? 'MXN',
                'payment_frequency'     => $data['payment_frequency'] ?? null,
                'is_active'             => 1,
            ]);

            return $payee;
        });

        return redirect()->route('payees.show', $envelope)
            ->with('status', __('Proveedor dado de alta. Completa sus documentos desde su ficha.'));
    }

    /**
     * CARRIL 2 — agrega un contrato de RENTA/SERVICIO a una identidad YA existente (el driver que ya es
     * crew y renta su auto, o un proveedor con un segundo trato). REUSA el payee (no duplica). Si es
     * renta, captura la REFERENCIA MÍNIMA del vehículo (gancho para el módulo de Transportación). El
     * contrato hereda el departamento de sus otros contratos (o el del que da de alta) y su propia
     * frecuencia de pago — distinta del contrato de trabajo.
     */
    public function addContract(Request $request, Payee $payee)
    {
        $this->authorize('capture', $payee);

        $data = $request->validate([
            'concept'           => ['required', 'in:equipment_rental,service'],
            'title'             => ['nullable', 'string', 'max:191'],
            'fee_amount'        => ['nullable', 'numeric', 'min:0'],
            'fee_currency'      => ['nullable', 'in:MXN,USD'],
            'payment_frequency' => ['nullable', 'in:weekly,biweekly,day_player'],
            'asset_make'        => ['nullable', 'string', 'max:60'],
            'asset_model'       => ['nullable', 'string', 'max:60'],
            'asset_plate'       => ['nullable', 'string', 'max:20'],
            'asset_year'        => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        // Ficha mínima del vehículo/equipo SOLO en renta (gancho a Transportación).
        $assetRef = null;
        if ($data['concept'] === PayeeContract::CONCEPT_RENTAL) {
            $ref = array_filter([
                'make'  => $data['asset_make'] ?? null,
                'model' => $data['asset_model'] ?? null,
                'plate' => $data['asset_plate'] ?? null,
                'year'  => $data['asset_year'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            $assetRef = $ref ?: null;
        }

        $deptId = $payee->contracts()->whereNotNull('department_id')->value('department_id')
            ?: $request->user()->ownDepartmentIds()->first();

        $payee->contracts()->create([
            'production_id'         => CurrentProduction::id(),
            'concept'               => $data['concept'],
            'title'                 => $data['title'] ?? null,
            'asset_ref'             => $assetRef,
            'department_id'         => $deptId,
            'contracted_by_user_id' => $request->user()->id,
            'fee_amount'            => $data['fee_amount'] ?? null,
            'fee_currency'          => $data['fee_currency'] ?? 'MXN',
            'payment_frequency'     => $data['payment_frequency'] ?? null,
            'is_active'             => 1,
        ]);

        return back()->with('status', __('Contrato agregado.'));
    }

    /**
     * Departamentos donde ESTE usuario puede dar de alta proveedores: ve-todo (producción / oficina de
     * producción / contabilidad / super-admin / bypass) → todos; el resto → su(s) departamento(s).
     */
    private function assignableDepartments($user)
    {
        if (ContractVisibility::seesAll($user)) {
            return Department::query()->where('active', 1)->orderBy('name')->get(['id', 'name']);
        }
        $ids = $user->ownDepartmentIds();

        return $ids->isEmpty()
            ? collect()
            : Department::query()->whereIn('id', $ids->all())->orderBy('name')->get(['id', 'name']);
    }
}
