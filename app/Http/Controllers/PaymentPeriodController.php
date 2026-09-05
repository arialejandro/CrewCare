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
        $labelTemplate = \App\Models\ProductionDocumentSetting::periodTemplateFor($productionId);

        return view('periods.index', compact('periods', 'frequencies', 'dayPlayerPayees', 'productionId', 'labelTemplate'));
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
     * EXPORT CSV del tablero de "quién falta" (contabilidad). Reusa el patrón de export de CrewList
     * ({@see ExportController}: response()->stream + fputcsv). Respeta el periodo y los filtros
     * dept+tipo. Segmentado por TIPO (crew/proveedor/renta/day player) + por periodo.
     *
     * 🔑 RECIBIR NO ES VALIDAR: la columna estado dice RECIBIDO o FALTA, nunca "vigente"/"aprobado";
     * la fecha es la de CAPTURA (recepción), no la de validación.
     */
    public function export(Request $request, PaymentPeriod $period)
    {
        $filters = [
            'department_id'    => $request->integer('department') ?: null,
            'document_type_id' => $request->integer('document_type') ?: null,
        ];

        $board    = PeriodBoard::build($period, $request->user(), $filters);
        $typeById = collect($board['columns'])->keyBy('id');
        $tname    = fn ($t) => $t ? ($t->name_es ?? $t->name ?? $t->code ?? ('#' . $t->id)) : '';

        $rows = collect($board['rows'])->map(function ($r) use ($period, $typeById, $tname) {
            $c        = $r['contract'];
            $faltan   = [];
            $recibido = [];
            foreach ($r['cells'] as $typeId => $status) {
                $nm = $tname($typeById->get($typeId));
                if ($nm === '') {
                    continue;
                }
                if ($status === \App\Support\PayeePackage::ST_RECEIVED) {
                    $recibido[] = $nm;
                } else {
                    $faltan[] = $nm;   // missing / not_positive / expired → NO recibido
                }
            }

            return [
                'nombre'   => $r['payee']->name,
                'tipo'     => $this->periodRowType($c, $period),
                'regimen'  => optional($c->fiscalRegime)->name
                              ?? optional($c->fiscalRegime)->description
                              ?? optional($c->fiscalRegime)->code ?? '',
                'estado'   => $r['delivered'] ? 'RECIBIDO' : 'FALTA',
                'faltan'   => implode('; ', $faltan),
                'recibido' => implode('; ', $recibido),
                'fecha'    => $r['received_at'] ? $r['received_at']->format('d/m/Y') : '',
                'marca'    => $r['out_of_window'] ? 'fuera de ventana' : '',
            ];
        })->sortBy([['tipo', 'asc'], ['nombre', 'asc']])->values();

        $label    = $period->label ?: ('periodo-' . $period->id);
        $fileName = 'quien-falta-' . \Illuminate\Support\Str::slug($label) . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Cache-Control'       => 'no-cache, must-revalidate',
        ];
        $cols = ['PERSONA / RAZÓN SOCIAL', 'TIPO', 'RÉGIMEN', 'ESTADO', 'DOCUMENTOS FALTANTES', 'DOCUMENTOS RECIBIDOS', 'FECHA DE RECEPCIÓN', 'MARCA'];

        $callback = function () use ($rows, $cols) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");   // BOM: Excel abre los acentos (RÉGIMEN, RAZÓN) bien
            fputcsv($file, $cols);
            foreach ($rows as $r) {
                fputcsv($file, [$r['nombre'], $r['tipo'], $r['regimen'], $r['estado'], $r['faltan'], $r['recibido'], $r['fecha'], $r['marca']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** TIPO del renglón para la segmentación: day player gana sobre el concepto del contrato. */
    private function periodRowType(PayeeContract $c, PaymentPeriod $period): string
    {
        if ($period->frequency === PayeeContract::FREQ_DAY_PLAYER || $c->payment_frequency === PayeeContract::FREQ_DAY_PLAYER) {
            return 'Day player';
        }
        return match ($c->concept) {
            PayeeContract::CONCEPT_CREW   => 'Crew',
            PayeeContract::CONCEPT_RENTAL => 'Renta',
            default                       => 'Proveedor',
        };
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

        $data = $this->periodData($request);

        PaymentPeriod::create(array_merge($data, [
            'production_id' => $productionId,
            'status'        => PaymentPeriod::STATUS_OPEN,
            'created_by_id' => $request->user()->id,
        ]));

        return redirect()->route('periods.index')->with('status', __('Periodo abierto.'));
    }

    /**
     * GENERACIÓN EN LOTE — crea N semanas de una, en vez de teclear veinte. Cada periodo compone su
     * NOMENCLATURA desde la PLANTILLA configurable (o la de la producción) con la FECHA FINAL de la
     * semana. Solo weekly/biweekly (el day-player es a mano, por su naturaleza). Idempotente por
     * ventana: si ya existe un periodo de la misma frecuencia que CIERRA el mismo día, se SALTA (no
     * duplica). No dispara correos: el aviso lo manda el comando diario `periods:announce` cuando la
     * ventana ABRE de verdad (opens_on = hoy), no al crearla.
     */
    public function storeBatch(Request $request)
    {
        $productionId = CurrentProduction::id();
        abort_unless($productionId, 409, 'No hay una producción activa.');

        $weekly   = PayeeContract::FREQ_WEEKLY;
        $biweekly = PayeeContract::FREQ_BIWEEKLY;

        $data = $request->validate([
            'frequency'  => 'required|in:' . $weekly . ',' . $biweekly,
            'start_date' => 'required|date',
            'count'      => 'required|integer|min:1|max:52',
            'template'   => 'nullable|string|max:160',
        ]);

        $step     = $data['frequency'] === $biweekly ? 14 : 7;
        $template = trim((string) ($data['template'] ?? '')) !== ''
            ? $data['template']
            : \App\Models\ProductionDocumentSetting::periodTemplateFor($productionId);

        $start   = \Illuminate\Support\Carbon::parse($data['start_date'])->startOfDay();
        $created = 0; $skipped = 0;

        for ($i = 0; $i < (int) $data['count']; $i++) {
            $opens  = $start->copy()->addDays($i * $step);
            $closes = $opens->copy()->addDays($step - 1);

            $exists = PaymentPeriod::query()->forProduction($productionId)
                ->where('frequency', $data['frequency'])
                ->whereDate('closes_on', $closes->toDateString())->exists();
            if ($exists) { $skipped++; continue; }

            PaymentPeriod::create([
                'production_id' => $productionId,
                'frequency'     => $data['frequency'],
                'label'         => PaymentPeriod::composeLabel($template, $closes, $opens),
                'opens_on'      => $opens->toDateString(),
                'closes_on'     => $closes->toDateString(),
                'status'        => PaymentPeriod::STATUS_OPEN,
                'created_by_id' => $request->user()->id,
            ]);
            $created++;
        }

        $msg = trans_choice('{0}No se creó ningún periodo.|{1}Se creó 1 periodo.|[2,*]Se crearon :count periodos.', $created, ['count' => $created]);
        if ($skipped > 0) {
            $msg .= ' ' . trans_choice('{1}(1 ya existía y se saltó.)|[2,*](:n ya existían y se saltaron.)', $skipped, ['n' => $skipped]);
        }

        return redirect()->route('periods.index')->with($created > 0 ? 'status' : 'error', $msg);
    }

    /** EDITAR un periodo (corregir fecha/etiqueta/frecuencia/día). Solo periods.manage. */
    public function edit(Request $request, PaymentPeriod $period)
    {
        $productionId    = CurrentProduction::id();
        $frequencies     = PayeeContract::frequencies();
        $dayPlayerPayees = Payee::query()->active()->visibleTo($request->user())
            ->orderBy('name')->get(['id', 'name']);

        return view('periods.edit', compact('period', 'frequencies', 'dayPlayerPayees', 'productionId'));
    }

    /** ACTUALIZAR un periodo abierto por error (o con la fecha mal). No toca su estado. */
    public function update(Request $request, PaymentPeriod $period)
    {
        $period->update($this->periodData($request));

        return redirect()->route('periods.index')->with('status', __('Periodo actualizado.'));
    }

    /**
     * BORRAR un periodo abierto por error. GUARDA: si ya tiene documentos colgados
     * (external_authorizations.payment_period_id), NO se borra —se orfanaría la recepción—; en su
     * lugar el emisor lo CIERRA. Solo se borra el periodo vacío (sin recepción alguna).
     */
    public function destroy(Request $request, PaymentPeriod $period)
    {
        $hasDocs = \App\Models\ExternalAuthorization::where('payment_period_id', $period->id)->exists();
        if ($hasDocs) {
            return back()->with('error', __('Este periodo ya tiene documentos recibidos; ciérralo en vez de borrarlo.'));
        }

        $period->delete();

        return redirect()->route('periods.index')->with('status', __('Periodo eliminado.'));
    }

    /**
     * Validación COMPARTIDA por store() y update(), con la derivación del DAY PLAYER: no tiene
     * semana, su VENTANA ES el día trabajado → se piden worked_on + persona y opens_on/closes_on
     * se DERIVAN de worked_on (ya no se inventa una semana). Los demás sí piden la ventana.
     */
    protected function periodData(Request $request): array
    {
        $dayPlayer = PayeeContract::FREQ_DAY_PLAYER;

        $data = $request->validate([
            'frequency' => 'required|in:' . implode(',', array_keys(PayeeContract::frequencies())),
            'label'     => 'nullable|string|max:160',
            'opens_on'  => 'nullable|date|required_unless:frequency,' . $dayPlayer,
            'closes_on' => 'nullable|date|after_or_equal:opens_on|required_unless:frequency,' . $dayPlayer,
            'worked_on' => 'nullable|date',
            'payee_id'  => 'nullable|integer|exists:payees,id',
        ]);

        if ($data['frequency'] === $dayPlayer) {
            $request->validate([
                'worked_on' => 'required|date',
                'payee_id'  => 'required|integer|exists:payees,id',
            ]);
            // La ventana del day-player ES el día trabajado (no una semana inventada).
            $data['opens_on']  = $data['worked_on'];
            $data['closes_on'] = $data['worked_on'];
        } else {
            $data['worked_on'] = null;
            $data['payee_id']  = null;
        }

        return $data;
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
