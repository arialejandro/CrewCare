<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\PaymentPeriod;
use App\Support\CurrentProduction;
use App\Support\PeriodBoard;
use App\Support\PeriodReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VENTANA DE RECEPCIÓN POR PERIODO DE PAGO. Dos superficies:
 *  - VER (periods.view): index (lista de periodos) + show (EL TABLERO de quién falta, derivado
 *    por {@see PeriodBoard} → reusa los estados de PayeePackage; acotado por "quien contrata ve").
 *  - ADMINISTRAR (periods.manage): abrir/cerrar/reabrir un periodo y asignar la frecuencia del
 *    contrato (la fuente de a quién le toca cada periodo).
 *
 * La ventana ABRE y CIERRA pero NO RECHAZA: cerrar un periodo no impide recibir; solo define
 * qué se ESPERA. El fuera-de-ventana se MARCA al capturar, no se bloquea.
 */
class PaymentPeriodController extends Controller
{
    /** Lista de periodos de la producción actual (agrupados por frecuencia) + form de alta. */
    public function index(Request $request)
    {
        $productionId = CurrentProduction::id();

        $periods = PaymentPeriod::query()
            ->when($productionId, fn ($q) => $q->forProduction($productionId))
            ->with('payee')
            ->orderByDesc('opens_on')->orderByDesc('id')
            ->get()
            ->groupBy('frequency');

        $frequencies = PayeeContract::frequencies();
        $dayPlayerPayees = Payee::query()->active()->visibleTo($request->user())
            ->orderBy('name')->get(['id', 'name']);

        return view('periods.index', compact('periods', 'frequencies', 'dayPlayerPayees', 'productionId'));
    }

    /** EL TABLERO: por periodo, quién entregó / quién falta y quiénes. Filtros dept + tipo. */
    public function show(Request $request, PaymentPeriod $period)
    {
        $filters = [
            'department_id'    => $request->integer('department') ?: null,
            'document_type_id' => $request->integer('document_type') ?: null,
        ];

        $board = PeriodBoard::build($period, $request->user(), $filters);

        // Departamentos presentes en la producción (para el filtro).
        $departments = Department::query()
            ->whereIn('id', DB::table('production_user')
                ->where('production_id', $period->production_id)
                ->whereNotNull('department_id')->distinct()->pluck('department_id'))
            ->orderBy('name')->get(['id', 'name']);

        return view('periods.show', compact('period', 'board', 'departments', 'filters'));
    }

    /**
     * RECORDATORIO MANUAL a quienes faltan (§a). Contabilidad revisa la lista y manda UNO POR UNO
     * por WhatsApp (sabe quién está de vacaciones / dijo que lo manda mañana). Nunca a quien ya
     * entregó. El mensaje nombra la producción y dice qué le falta a cada quien.
     */
    public function reminders(Request $request, PaymentPeriod $period)
    {
        $rows = PeriodReminder::build($period, $request->user());

        return view('periods.reminders', compact('period', 'rows'));
    }

    /** ABRIR un periodo (contabilidad). production_id = la producción actual. */
    public function store(Request $request)
    {
        $productionId = CurrentProduction::id();
        abort_unless($productionId, 409, 'No hay una producción activa.');

        $data = $request->validate([
            'frequency' => 'required|in:' . implode(',', array_keys(PayeeContract::frequencies())),
            'label'     => 'nullable|string|max:160',
            'opens_on'  => 'required|date',
            'closes_on' => 'required|date|after_or_equal:opens_on',
            'worked_on' => 'nullable|date',
            'payee_id'  => 'nullable|integer|exists:payees,id',
        ]);

        // DAY PLAYER: no tiene semana, tiene el DÍA que trabajó (capturado a mano) y ES de un payee.
        if ($data['frequency'] === PayeeContract::FREQ_DAY_PLAYER) {
            $request->validate([
                'worked_on' => 'required|date',
                'payee_id'  => 'required|integer|exists:payees,id',
            ]);
        } else {
            $data['worked_on'] = null;
            $data['payee_id']  = null;
        }

        PaymentPeriod::create(array_merge($data, [
            'production_id' => $productionId,
            'status'        => PaymentPeriod::STATUS_OPEN,
            'created_by_id' => $request->user()->id,
        ]));

        return redirect()->route('periods.index')->with('status', __('Periodo abierto.'));
    }

    /** CERRAR la recepción de un periodo. No rechaza documentos: solo deja de esperarlos. */
    public function close(Request $request, PaymentPeriod $period)
    {
        if ($period->isOpen()) {
            $period->update([
                'status'       => PaymentPeriod::STATUS_CLOSED,
                'closed_at'    => now(),
                'closed_by_id' => $request->user()->id,
            ]);
        }
        return back()->with('status', __('Periodo cerrado.'));
    }

    /** REABRIR un periodo cerrado (queda rastro de quién y cuándo). */
    public function reopen(Request $request, PaymentPeriod $period)
    {
        if ($period->isClosed()) {
            $period->update([
                'status'         => PaymentPeriod::STATUS_OPEN,
                'reopened_at'    => now(),
                'reopened_by_id' => $request->user()->id,
            ]);
        }
        return back()->with('status', __('Periodo reabierto.'));
    }

    /**
     * Asignar la FRECUENCIA de un contrato (la que hereda el periodo). Es la fuente de a quién
     * le toca cada periodo. Solo sobre payees que el usuario puede ver (defensa extra).
     */
    public function setFrequency(Request $request, PayeeContract $contract)
    {
        abort_unless($contract->payee && $contract->payee->isVisibleTo($request->user()), 403);

        $data = $request->validate([
            'payment_frequency' => 'nullable|in:' . implode(',', array_keys(PayeeContract::frequencies())),
        ]);

        $contract->update(['payment_frequency' => $data['payment_frequency'] ?: null]);

        return back()->with('status', __('Frecuencia actualizada.'));
    }
}
