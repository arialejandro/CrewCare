<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Payee;
use App\Models\PayeeContract;
use App\Models\PayeeContractWorkDate;
use App\Models\Position;
use App\Support\CurrentProduction;
use App\Support\InfosheetSigning;
use App\Support\ProductionCalendar;
use App\Support\TaxDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * EL INFOSHEET · FASE 2 — la superficie de CAPTURA del TRATO (la mitad que la persona NO pone).
 *
 * La mitad PERSONAL es el INTAKE que ya existe (identidad/fiscal/docs/beneficiario/emergencia): se
 * MUESTRA y se edita por su propio camino — aquí NO se duplica. Esta superficie captura la mitad
 * del TRATO: puesto, departamento, actividad, créditos, vigencia, importes POR FASE (con desglose
 * fiscal), factura/recibo, caja chica, y las fechas de trabajo.
 *
 * El Infosheet ES el estado editable del contrato crew_work (UNO por persona por producción; hoy
 * no se crea en ningún lado, así que aquí se crea con firstOrCreate — no hay unique en BD, la
 * cardinalidad la impone el firstOrCreate). Al firmarse (Fase 3) se emitirá el contrato.
 *
 * GUARDA DE AUTO-EDICIÓN: nadie edita el trato de otro. Se gatea con la Policy `capture`
 * (→ Payee::isVisibleTo → scopeVisibleTo), el MISMO criterio que el intake del contratante. El
 * puesto NO otorga accesos. Guardado POR SECCIÓN (como el intake), server-side, pensado para móvil.
 */
class InfosheetController extends Controller
{
    /** Secciones del TRATO (la persona las VE; producción/HOD las edita). */
    const STEPS = ['role', 'fees', 'dates'];

    /** El contrato crew_work de esta persona en la producción vigente (único por firstOrCreate). */
    private function resolveContract(Payee $payee): PayeeContract
    {
        $contract = $payee->contracts()->firstOrCreate(
            ['concept' => PayeeContract::CONCEPT_CREW, 'production_id' => CurrentProduction::id()],
            ['is_active' => 1, 'contracted_by_user_id' => auth()->id(), 'created_by_id' => auth()->id()]
        );

        $this->seedFromIntake($contract, $payee);

        return $contract;
    }

    /**
     * PRELLENADO desde el ALTA (2026-08-25). El Infosheet salía EN BLANCO aunque el departamento y
     * el puesto ya se hubieran capturado al dar de alta a la persona: quien autoriza abría un
     * formulario vacío en vez de una hoja que dijera qué puesto es y cuánto dura.
     *
     * Siembra SOLO lo que está vacío (nunca pisa lo capturado) y solo antes de emitir:
     *  · departamento y puesto ← el pivote de la producción (`production_user`, lo del alta);
     *    si no hay pivote, el puesto en texto de `users.puestodepartamento`.
     *  · vigencia ← el calendario de la producción (arranque y wrap planeado), como estimado.
     * Los IMPORTES no se siembran: no existen en ningún lado previo, los captura producción.
     */
    private function seedFromIntake(PayeeContract $contract, Payee $payee): void
    {
        if ($contract->isEmitted()) {
            return;
        }

        $dirty = false;
        $user  = $payee->user;

        if ($user) {
            $pivot = DB::table('production_user')
                ->where('production_id', $contract->production_id)
                ->where('user_id', $user->id)
                ->first(['department_id', 'position_id']);

            if (! $contract->department_id && $pivot && $pivot->department_id) {
                $contract->department_id = (int) $pivot->department_id;
                $dirty = true;
            }
            if (trim((string) $contract->title) === '') {
                $name = ($pivot && $pivot->position_id)
                    ? optional(Position::find($pivot->position_id))->name
                    : null;
                $name = $name ?: trim((string) $user->puestodepartamento);
                if ($name !== '' && $name !== null) {
                    $contract->title = $name;
                    $dirty = true;
                }
            }
        }

        // Vigencia estimada = la de la producción. Es un punto de partida editable, no un dato duro.
        if (! $contract->effective_date && ($start = ProductionCalendar::anchorDate())) {
            $contract->effective_date = $start;
            $dirty = true;
        }
        if (! $contract->estimated_end_date && ($end = ProductionCalendar::plannedWrapDate() ?: ProductionCalendar::wrapDate())) {
            $contract->estimated_end_date = $end;
            $dirty = true;
        }

        if ($dirty) {
            $contract->save();
        }
    }

    /** El id del PUESTO del catálogo que corresponde al `title` del contrato (para preseleccionar). */
    private function positionIdFor(PayeeContract $contract, Payee $payee): ?int
    {
        if (trim((string) $contract->title) !== '') {
            $pos = Position::whereNull('production_id')->where('name', $contract->title)->first(['id']);
            if ($pos) {
                return (int) $pos->id;
            }
        }
        if ($payee->user) {
            $pivot = DB::table('production_user')
                ->where('production_id', $contract->production_id)
                ->where('user_id', $payee->user->id)
                ->first(['position_id']);
            if ($pivot && $pivot->position_id) {
                return (int) $pivot->position_id;
            }
        }

        return null;
    }

    /** La puerta: solo quien tiene alcance sobre el payee captura su trato (mismo criterio que ver). */
    private function authorizeCapture(Payee $payee): void
    {
        abort_unless(auth()->check() && auth()->user()->can('capture', $payee), 403);
    }

    public function edit(Request $request, Payee $payee, string $step = 'role')
    {
        $this->authorizeCapture($payee);
        if (! in_array($step, self::STEPS, true)) {
            $step = 'role';
        }
        $contract = $this->resolveContract($payee);

        return view('infosheet.edit', [
            'payee'       => $payee,
            'contract'    => $contract,
            'step'        => $step,
            'steps'       => self::STEPS,
            'departments' => Department::where('active', 1)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'positions'   => Position::whereNull('production_id')->where('active', 1)->orderBy('name')->get(['id', 'name', 'department_id']),
            'phaseLabels' => PayeeContractWorkDate::phases(),
            // Puesto del alta, para que el select llegue elegido y no en blanco.
            'positionId'  => $this->positionIdFor($contract, $payee),
            // Desglose fiscal que toca por régimen (se prellena; se puede ajustar).
            'taxRates'    => TaxDefaults::forContract($contract),
            // Lo que falta para poder mandarlo a autorizar (candado del pipeline).
            'missing'     => InfosheetSigning::missingToAuthorize($contract),
        ]);
    }

    public function save(Request $request, Payee $payee)
    {
        $this->authorizeCapture($payee);
        $contract = $this->resolveContract($payee);

        // FASE 5 — CANDADO post-emisión. Una vez emitido, los datos del trato quedaron CONGELADOS en la
        // carátula sellada (y en el sobre): editarlos aquí haría que la fila VIVA diverja del sello. Para
        // cambiarlos hay que anular el sobre y reemitir. Defensa en el servidor (la UI ya oculta el form).
        if ($contract->isEmitted()) {
            return back()->with('error', __('Este contrato ya fue emitido; sus datos quedaron congelados. Para cambiarlos, anula el sobre y vuelve a emitir.'));
        }

        $step = (string) $request->input('_step', 'role');
        abort_unless(in_array($step, self::STEPS, true), 422);

        DB::transaction(function () use ($request, $contract, $step) {
            $this->saveSection($request, $contract, $step);
        });

        $idx = array_search($step, self::STEPS, true);
        if ($idx !== false && $idx < count(self::STEPS) - 1) {
            return redirect()
                ->route('infosheet.edit', ['payee' => $payee->id, 'step' => self::STEPS[$idx + 1]])
                ->with('success', __('Sección guardada.'));
        }

        return redirect()
            ->route('infosheet.edit', ['payee' => $payee->id, 'step' => $step])
            ->with('success', __('Hoja de información guardada.'));
    }

    /**
     * AUTORIZACIÓN (paso 2). Un autorizador configurado (Line Producer y/o puesto del módulo de
     * firma) aprueba el trato con su FIRMA AUTÓGRAFA. Al completarse todas, se dispara la generación
     * del contrato + sobre (dentro de InfosheetSigning::authorize). Gate: tener un casillero pendiente.
     */
    public function approve(Request $request, Payee $payee)
    {
        $contract = $payee->contracts()
            ->where('concept', PayeeContract::CONCEPT_CREW)
            ->where('production_id', CurrentProduction::id())
            ->first();
        abort_unless($contract, 404);

        $user = $request->user();
        abort_unless($user && InfosheetSigning::canAuthorize($user, $contract), 403);

        // La autógrafa es OBLIGATORIA: cada paso requiere la firma (no basta el sello). Se firma
        // DENTRO de la Hoja, después de revisarla — el pad llega ya con la firma adoptada del
        // usuario si la tiene ({@see componentes._signature-pad}), pero la firma viaja igual.
        $request->validate(['signature_image' => 'required|string|min:100']);
        $image = (string) $request->input('signature_image');

        // Adopción de firma para reúso (DocuSign): UNA firma por persona, la misma en contrato,
        // llamado e Infosheet (users.adopted_signature). No hay firma por módulo.
        if ($request->boolean('save_signature')) {
            $user->adopted_signature = $image;
            $user->save();
        }

        $result = InfosheetSigning::authorize($contract, $user, $image, $request);

        if (! ($result['ok'] ?? false) || ($result['error'] ?? false)) {
            return back()->with('error', $result['message'] ?? __('No se pudo autorizar.'));
        }

        return back()->with('success', $result['message'] ?? __('Autorización registrada.'));
    }

    /**
     * BANDEJA "POR AUTORIZAR" — el DISPARADOR de descubrimiento: los tratos crew_work que este usuario
     * puede autorizar ahora. Sin permiso especial (se auto-limita por `canAuthorize`, como firmas-pendientes).
     */
    public function pending(Request $request)
    {
        return view('infosheet.pending', [
            'contracts' => InfosheetSigning::pendingForUser($request->user()),
        ]);
    }

    /**
     * ENVIAR A AUTORIZACIÓN (paso 2 · disparo) — el capturista marca el trato listo y AVISA por correo
     * a los autorizadores (best-effort; el aviso NUNCA bloquea). Aunque el correo falle, el trato ya
     * aparece en la bandeja "Por autorizar" del autorizador (el disparador confiable).
     */
    public function submit(Request $request, Payee $payee)
    {
        $this->authorizeCapture($payee);
        $contract = $this->resolveContract($payee);

        if ($contract->isEmitted()) {
            return back()->with('error', __('Este contrato ya fue emitido.'));
        }
        // Candado: se manda a autorizar lo que está listo para firmarse. Autorizar EMITE el contrato
        // y lo congela; un trato a medias produciría un contrato vacío e inmutable.
        if (! InfosheetSigning::isReadyToAuthorize($contract)) {
            return back()->with('error', InfosheetSigning::missingLabel($contract) . ' ' . __('Complétalo antes de enviarlo a autorización.'));
        }

        $recipients = InfosheetSigning::authorizerRecipients($contract);
        if ($recipients->isEmpty()) {
            return back()->with('error', __('No hay un autorizador con correo configurado (revisa Producción → módulo de firma). El trato ya aparece en la bandeja "Por autorizar" del autorizador.'));
        }

        $subject = __('Infosheet por autorizar') . ' — ' . ($payee->name ?: 'CrewCare');
        $sent = 0;
        foreach ($recipients as $u) {
            try {
                Mail::send('correos.infosheet-authorize', [
                    'toName'    => trim($u->name . ' ' . $u->lname),
                    'payeeName' => $payee->name,
                    'puesto'    => $contract->title ?: $contract->crew_activity,
                    'url'       => route('infosheet.pending'),
                ], function ($m) use ($u, $subject) {
                    $m->from('noreply@crewcare.mx', 'CrewCare');
                    $m->to($u->email, trim($u->name . ' ' . $u->lname) ?: null);
                    $m->subject($subject);
                });
                $sent++;
            } catch (\Throwable $e) {
                // best-effort: el aviso nunca bloquea el flujo (queda la bandeja).
            }
        }

        $msg = $sent > 0
            ? __('Enviado a autorización: se avisó a :n autorizador(es) y aparece en su bandeja "Por autorizar".', ['n' => $sent])
            : __('El trato ya aparece en la bandeja "Por autorizar" del autorizador. (No se pudo enviar el correo; revisa la configuración de correo.)');

        return back()->with('success', $msg);
    }

    private function saveSection(Request $request, PayeeContract $contract, string $section): void
    {
        switch ($section) {
            case 'role':
                $request->validate([
                    'department_id'       => 'nullable|integer|exists:departments,id',
                    'position_id'         => 'nullable|integer|exists:positions,id',
                    'crew_activity'       => 'nullable|string|max:255',
                    'effective_date'      => 'nullable|date',
                    'estimated_end_date'  => 'nullable|date',
                ]);
                $contract->fill($request->only([
                    'department_id', 'crew_activity',
                    'effective_date', 'estimated_end_date',
                ]));
                // El PUESTO (position) no es columna del contrato: se guarda su nombre legible en
                // `title` (título del contrato crew_work). La ACTIVIDAD/entregable va en crew_activity.
                if ($request->filled('position_id')) {
                    $pos = Position::find($request->input('position_id'));
                    if ($pos) {
                        $contract->title = $pos->name;
                    }
                }
                // El NOMBRE EN CRÉDITOS no se captura aquí (no tiene sentido en el trato): se deriva del
                // nombre de la persona para que el contrato/créditos igual lo tengan. Editable a futuro
                // por otro camino si se ocupa un nombre artístico.
                if (trim((string) $contract->credit_name) === '') {
                    $contract->credit_name = optional($contract->payee)->name ?: null;
                }
                $contract->save();
                break;

            case 'fees':
                $request->validate([
                    'fee_currency'          => 'nullable|string|max:3',
                    'payment_document_type' => 'nullable|in:factura,recibo',
                    'payment_frequency'     => 'nullable|in:weekly,biweekly,day_player',
                    'budget_account'        => 'nullable|string|max:80',
                    'tax_iva'               => 'nullable|numeric',
                    'tax_isr_retention'     => 'nullable|numeric',
                    'tax_iva_retention'     => 'nullable|numeric',
                    'fee_soft_prep_weeks'   => 'nullable|numeric', 'fee_soft_prep_rate' => 'nullable|numeric', 'fee_soft_prep_amount' => 'nullable|numeric',
                    'fee_prep_weeks'        => 'nullable|numeric', 'fee_prep_rate'      => 'nullable|numeric', 'fee_prep_amount'      => 'nullable|numeric',
                    'fee_shoot_weeks'       => 'nullable|numeric', 'fee_shoot_rate'     => 'nullable|numeric', 'fee_shoot_amount'     => 'nullable|numeric',
                    'fee_wrap_weeks'        => 'nullable|numeric', 'fee_wrap_rate'      => 'nullable|numeric', 'fee_wrap_amount'      => 'nullable|numeric',
                ]);
                $contract->fill($request->only([
                    'fee_currency', 'payment_document_type', 'payment_frequency', 'budget_account',
                    'tax_iva', 'tax_isr_retention', 'tax_iva_retention',
                    'fee_soft_prep_weeks', 'fee_soft_prep_rate', 'fee_soft_prep_amount',
                    'fee_prep_weeks', 'fee_prep_rate', 'fee_prep_amount',
                    'fee_shoot_weeks', 'fee_shoot_rate', 'fee_shoot_amount',
                    'fee_wrap_weeks', 'fee_wrap_rate', 'fee_wrap_amount',
                ]));
                $contract->manages_petty_cash = $request->boolean('manages_petty_cash');
                // El TOTAL (fee_amount) es la suma de los importes por fase capturados.
                $sum = 0.0;
                foreach (['fee_soft_prep_amount', 'fee_prep_amount', 'fee_shoot_amount', 'fee_wrap_amount'] as $f) {
                    $sum += (float) $request->input($f, 0);
                }
                if ($sum > 0) {
                    $contract->fee_amount = round($sum, 2);
                }
                // DESGLOSE FISCAL: lo que no se capturó se deriva del RÉGIMEN (el mismo cálculo que
                // muestra el formulario). Un importe escrito a mano manda —incluido el 0 explícito—;
                // esto solo cubre los que llegan en blanco. Sin régimen conocido, las tasas son 0 y
                // no se inventa nada.
                $suggested = TaxDefaults::amountsFor($contract, (float) $contract->fee_amount);
                foreach ($suggested as $field => $amount) {
                    if (! $request->filled($field) && $amount > 0) {
                        $contract->$field = $amount;
                    }
                }
                $contract->save();
                break;

            case 'dates':
                $request->validate([
                    'dates'         => 'nullable|array',
                    'dates.*.date'  => 'required_with:dates|date',
                    'dates.*.phase' => 'required_with:dates|in:soft_prep,prep,shoot,wrap',
                ]);
                // El rango es atajo de captura; aquí llega ya EXPANDIDO a días (con los quitados
                // fuera). Reemplazamos el conjunto (delete+recreate, como el intake con sus hijos)
                // respetando unique(payee_contract_id, work_date).
                $contract->workDates()->delete();
                $seen = [];
                foreach ((array) $request->input('dates', []) as $row) {
                    $d = $row['date'] ?? null;
                    $p = $row['phase'] ?? null;
                    if (! $d || ! $p || isset($seen[$d])) {
                        continue;
                    }
                    $seen[$d] = true;
                    $contract->workDates()->create(['work_date' => $d, 'phase' => $p]);
                }
                break;
        }
    }
}
