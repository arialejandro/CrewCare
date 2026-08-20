@extends('layouts.app')

@section('content')
{{-- EL INFOSHEET · FASE 2 — captura del TRATO (producción/HOD). La mitad PERSONAL es el intake
     (se reusa por su propio camino). Guardado POR SECCIÓN; la persona VE esto porque lo firmará. --}}
@include('componentes._form-kit')

@php
    $labels = ['role' => __('Puesto y actividad'), 'fees' => __('Importes'), 'dates' => __('Fechas de trabajo')];
    $idx    = array_search($step, $steps, true);
    $isLast = $idx === count($steps) - 1;
    $val     = fn ($f, $d = null) => old($f, $contract->$f ?? $d);
    $dateVal = fn ($f) => old($f, optional($contract->$f)->format('Y-m-d'));
    $phaseKeys = ['soft_prep', 'prep', 'shoot', 'wrap'];
@endphp

<div class="container-fluid py-4" style="max-width: 900px;">
    @include('componentes._form-feedback')

    {{-- Encabezado --}}
    <div class="mb-3">
        <a href="{{ route('payees.show', $payee->id) }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
            @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Volver a la ficha') }}
        </a>
    </div>
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="cc-form-ico">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-20', 'label' => null])</span>
        <div>
            <h1 class="h4 fw-bold mb-0">{{ __('Hoja de información') }}</h1>
            <div class="cc-muted small">{{ $payee->name ?: __('(sin nombre)') }}</div>
        </div>
    </div>

    {{-- ENVIAR A AUTORIZACIÓN — el disparador del flujo: avisa al autorizador (Line Producer) y el
         trato entra a su bandeja "Por autorizar". Sin esto, el Infosheet no llega a nadie. --}}
    @unless($contract->isEmitted())
        @if(trim((string) $contract->title) !== '' || trim((string) $contract->crew_activity) !== '')
            <form method="POST" action="{{ route('infosheet.submit', $payee->id) }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-crew cc-cta d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('Enviar a autorización') }}
                </button>
                <span class="cc-help d-block mt-1">{{ __('Avisa al autorizador (Line Producer) y el trato aparece en su bandeja "Por autorizar".') }}</span>
            </form>
        @endif
    @endunless

    {{-- La mitad PERSONAL vive en el intake: enlace, no se duplica aquí. --}}
    <div class="cc-help mb-3">
        @include('componentes._icon', ['name' => 'info', 'class' => 'cc-ico-14', 'label' => null])
        {{ __('Los datos personales, fiscales y documentos se capturan en el registro de la persona.') }}
        <a href="{{ route('payee.intake.form', $payee->id) }}">{{ __('Abrir registro') }}</a>
    </div>

    {{-- Pasos del TRATO --}}
    <nav class="d-flex flex-wrap gap-2 mb-3">
        @foreach($steps as $i => $s)
            <a href="{{ route('infosheet.edit', ['payee' => $payee->id, 'step' => $s]) }}"
               class="cc-chip {{ $s === $step ? 'cc-chip--brand' : '' }}">{{ $i + 1 }}. {{ $labels[$s] }}</a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('infosheet.save', $payee->id) }}" id="infosheetForm">
        @csrf
        <input type="hidden" name="_step" value="{{ $step }}">

        @switch($step)

        {{-- ============================= 1 · PUESTO Y ACTIVIDAD ============================= --}}
        @case('role')
            <div class="cc-form-card">
                <div class="cc-form-card__head">
                    <span class="cc-form-ico">@include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-20', 'label' => null])</span>
                    <div class="cc-form-card__titles">
                        <h2 class="cc-form-card__title">{{ __('Puesto y actividad') }}</h2>
                        <p class="cc-form-card__sub">{{ __('Departamento, puesto, actividad y vigencia del contrato.') }}</p>
                    </div>
                </div>
                <div class="cc-form-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="cc-field">
                                <label class="cc-label" for="department_id">{{ __('Departamento') }}</label>
                                <select name="department_id" id="department_id" class="form-select cc-select">
                                    <option value="">{{ __('— Selecciona —') }}</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->id }}" @selected((string) $val('department_id') === (string) $d->id)>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="cc-field">
                                <label class="cc-label" for="position_id">{{ __('Puesto') }}</label>
                                <select name="position_id" id="position_id" class="form-select cc-select">
                                    <option value="">{{ __('— Puesto —') }}</option>
                                    {{-- Lo llena el JS según el departamento. --}}
                                </select>
                                @if($contract->title)
                                    <span class="cc-help">{{ __('Puesto actual') }}: <strong>{{ $contract->title }}</strong></span>
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="cc-field">
                                <label class="cc-label" for="crew_activity">{{ __('Descripción de la actividad o entregable') }}</label>
                                <textarea name="crew_activity" id="crew_activity" rows="2" class="form-control cc-control" maxlength="255">{{ $val('crew_activity') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="cc-field">
                                <label class="cc-label" for="credit_name">{{ __('Nombre en créditos') }}</label>
                                <input type="text" name="credit_name" id="credit_name" class="form-control cc-control" maxlength="191" value="{{ $val('credit_name') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cc-field">
                                <label class="cc-label" for="effective_date">{{ __('Inicia') }}</label>
                                <input type="date" name="effective_date" id="effective_date" class="form-control cc-control" value="{{ $dateVal('effective_date') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cc-field">
                                <label class="cc-label" for="estimated_end_date">{{ __('Termina (estimado)') }}</label>
                                <input type="date" name="estimated_end_date" id="estimated_end_date" class="form-control cc-control" value="{{ $dateVal('estimated_end_date') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cc-field">
                                <label class="cc-label" for="definitive_end_date">{{ __('Termina (definitivo)') }}</label>
                                <input type="date" name="definitive_end_date" id="definitive_end_date" class="form-control cc-control" value="{{ $dateVal('definitive_end_date') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        {{-- ================================== 2 · IMPORTES ================================== --}}
        @case('fees')
            <div class="cc-form-card">
                <div class="cc-form-card__head">
                    <span class="cc-form-ico">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-20', 'label' => null])</span>
                    <div class="cc-form-card__titles">
                        <h2 class="cc-form-card__title">{{ __('Importes por fase') }}</h2>
                        <p class="cc-form-card__sub">{{ __('Semanas, tarifa por semana e importe de cada fase. Al llenar la primera, las siguientes se copian; ajústalas si cambian.') }}</p>
                    </div>
                </div>
                <div class="cc-form-card__body">
                    <div class="table-responsive">
                        <table class="table align-middle mb-2" id="feeTable">
                            <thead><tr>
                                <th>{{ __('Fase') }}</th><th style="width:22%">{{ __('Semanas') }}</th>
                                <th style="width:26%">{{ __('Tarifa / semana') }}</th><th style="width:26%">{{ __('Importe') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach($phaseKeys as $pk)
                                    <tr>
                                        <td class="fw-semibold">{{ $phaseLabels[$pk] ?? $pk }}</td>
                                        <td><input type="number" step="0.01" min="0" class="form-control cc-control fee-weeks" data-phase="{{ $pk }}" name="fee_{{ $pk }}_weeks" value="{{ $val('fee_'.$pk.'_weeks') }}"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control cc-control fee-rate"  data-phase="{{ $pk }}" name="fee_{{ $pk }}_rate"  value="{{ $val('fee_'.$pk.'_rate') }}"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control cc-control fee-amount" data-phase="{{ $pk }}" name="fee_{{ $pk }}_amount" value="{{ $val('fee_'.$pk.'_amount') }}"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr>
                                <th colspan="3" class="text-end">{{ __('Total honorarios') }}</th>
                                <th><span id="feeTotal">{{ number_format((float) $contract->fee_amount, 2) }}</span></th>
                            </tr></tfoot>
                        </table>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <div class="cc-field">
                                <label class="cc-label" for="fee_currency">{{ __('Moneda') }}</label>
                                <input type="text" name="fee_currency" id="fee_currency" class="form-control cc-control" maxlength="3" value="{{ $val('fee_currency', 'MXN') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="cc-field">
                                <label class="cc-label" for="payment_document_type">{{ __('Comprobante') }}</label>
                                <select name="payment_document_type" id="payment_document_type" class="form-select cc-select">
                                    <option value="">{{ __('—') }}</option>
                                    <option value="factura" @selected($val('payment_document_type') === 'factura')>{{ __('Factura') }}</option>
                                    <option value="recibo"  @selected($val('payment_document_type') === 'recibo')>{{ __('Recibo') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="cc-field">
                                <label class="cc-label" for="budget_account">{{ __('Partida del presupuesto') }}</label>
                                <input type="text" name="budget_account" id="budget_account" class="form-control cc-control" maxlength="80" value="{{ $val('budget_account') }}">
                            </div>
                        </div>
                    </div>

                    <div class="text-uppercase text-muted small fw-semibold mt-3 mb-2" style="letter-spacing:.06em">{{ __('Desglose fiscal') }}</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="cc-field">
                                <label class="cc-label" for="tax_iva">{{ __('IVA') }}</label>
                                <input type="number" step="0.01" min="0" name="tax_iva" id="tax_iva" class="form-control cc-control" value="{{ $val('tax_iva') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cc-field">
                                <label class="cc-label" for="tax_isr_retention">{{ __('Retención ISR') }}</label>
                                <input type="number" step="0.01" min="0" name="tax_isr_retention" id="tax_isr_retention" class="form-control cc-control" value="{{ $val('tax_isr_retention') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="cc-field">
                                <label class="cc-label" for="tax_iva_retention">{{ __('Retención IVA') }}</label>
                                <input type="number" step="0.01" min="0" name="tax_iva_retention" id="tax_iva_retention" class="form-control cc-control" value="{{ $val('tax_iva_retention') }}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="cc-field">
                                <label class="cc-label d-inline-flex align-items-center gap-2">
                                    <input type="hidden" name="manages_petty_cash" value="0">
                                    <input type="checkbox" name="manages_petty_cash" value="1" @checked((bool) $val('manages_petty_cash'))>
                                    {{ __('Administra caja chica') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @break

        {{-- ================================ 3 · FECHAS ================================ --}}
        @case('dates')
            <div class="cc-form-card">
                <div class="cc-form-card__head">
                    <span class="cc-form-ico">@include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico-20', 'label' => null])</span>
                    <div class="cc-form-card__titles">
                        <h2 class="cc-form-card__title">{{ __('Fechas de trabajo') }}</h2>
                        <p class="cc-form-card__sub">{{ __('Captura por rango (p.ej. seis semanas de prep) o por día suelto. Los días se pueden quitar uno por uno.') }}</p>
                    </div>
                </div>
                <div class="cc-form-card__body">
                    {{-- Rango --}}
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-3">
                            <label class="cc-label">{{ __('Fase') }}</label>
                            <select id="rangePhase" class="form-select cc-select">
                                @foreach($phaseLabels as $pv => $pl)<option value="{{ $pv }}">{{ $pl }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><label class="cc-label">{{ __('Del') }}</label><input type="date" id="rangeStart" class="form-control cc-control"></div>
                        <div class="col-md-3"><label class="cc-label">{{ __('Al') }}</label><input type="date" id="rangeEnd" class="form-control cc-control"></div>
                        <div class="col-md-3">
                            <button type="button" id="addRange" class="btn btn-primary cc-cta w-100">
                                @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18', 'label' => null]) {{ __('Agregar rango') }}
                            </button>
                        </div>
                    </div>
                    {{-- Día suelto (day player) --}}
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <label class="cc-label">{{ __('Fase') }}</label>
                            <select id="dayPhase" class="form-select cc-select">
                                @foreach($phaseLabels as $pv => $pl)<option value="{{ $pv }}">{{ $pl }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><label class="cc-label">{{ __('Día') }}</label><input type="date" id="daySingle" class="form-control cc-control"></div>
                        <div class="col-md-3">
                            <button type="button" id="addDay" class="btn btn-crew-soft w-100">
                                @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18', 'label' => null]) {{ __('Agregar día') }}
                            </button>
                        </div>
                    </div>

                    <div class="text-uppercase text-muted small fw-semibold mb-2" style="letter-spacing:.06em">
                        {{ __('Días capturados') }} (<span id="dayCount">0</span>)
                    </div>
                    <div id="dayChips" class="d-flex flex-wrap gap-2 mb-1"></div>
                    <div id="dayHidden"></div>
                    <p class="cc-help mb-0">{{ __('Toca la × de un día para quitarlo sin rehacer el rango.') }}</p>
                </div>
            </div>
            @break

        @endswitch

        {{-- Navegación --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                @if($idx > 0)
                    <a class="btn btn-crew-soft" href="{{ route('infosheet.edit', ['payee' => $payee->id, 'step' => $steps[$idx - 1]]) }}">
                        @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-18', 'label' => null]) {{ __('Anterior') }}
                    </a>
                @endif
            </div>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico-18', 'label' => null])
                {{ $isLast ? __('Guardar') : __('Guardar y seguir') }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // ── 1 · PUESTO: puestos filtrados por departamento (mismo patrón que el alta) ──
    var POSITIONS = @json($positions ?? []);
    var CURRENT_POS = @json((int) old('position_id', 0));
    var deptSel = document.getElementById('department_id');
    var posSel  = document.getElementById('position_id');
    function fillPositions(deptId, keep) {
        if (!posSel) return;
        posSel.innerHTML = '<option value="">— Puesto —</option>';
        POSITIONS.filter(function (p) { return String(p.department_id) === String(deptId); })
            .forEach(function (p) {
                var o = document.createElement('option');
                o.value = p.id; o.textContent = p.name;
                if (keep && String(keep) === String(p.id)) { o.selected = true; }
                posSel.appendChild(o);
            });
    }
    if (deptSel && posSel) {
        deptSel.addEventListener('change', function () { fillPositions(this.value, null); });
        if (deptSel.value) { fillPositions(deptSel.value, CURRENT_POS); }
    }

    // ── 2 · IMPORTES: cascada + cálculo de importe y total ──
    var PHASES = ['soft_prep', 'prep', 'shoot', 'wrap'];
    function q(name) { return document.querySelector('[name="' + name + '"]'); }
    function num(el) { var v = el ? parseFloat(el.value) : NaN; return isNaN(v) ? 0 : v; }
    function recompute() {
        var total = 0;
        PHASES.forEach(function (p) {
            var w = q('fee_' + p + '_weeks'), r = q('fee_' + p + '_rate'), a = q('fee_' + p + '_amount');
            if (a && !a.dataset.dirty) {
                var amt = num(w) * num(r);
                a.value = amt ? amt.toFixed(2) : '';
            }
            total += num(a);
        });
        var t = document.getElementById('feeTotal');
        if (t) { t.textContent = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    }
    function cascade(field, fromPhase) {
        var i = PHASES.indexOf(fromPhase);
        var src = q('fee_' + fromPhase + '_' + field);
        if (!src) return;
        for (var j = i + 1; j < PHASES.length; j++) {
            var el = q('fee_' + PHASES[j] + '_' + field);
            if (el && !el.value) { el.value = src.value; }
        }
    }
    PHASES.forEach(function (p) {
        ['weeks', 'rate'].forEach(function (f) {
            var el = q('fee_' + p + '_' + f);
            if (el) { el.addEventListener('input', function () { cascade(f, p); recompute(); }); }
        });
        var a = q('fee_' + p + '_amount');
        if (a) { a.addEventListener('input', function () { a.dataset.dirty = '1'; recompute(); }); }
    });
    if (q('fee_soft_prep_weeks')) { recompute(); }

    // ── 3 · FECHAS: rango → días (removibles) + día suelto ──
    var PHASE_LABELS = @json($phaseLabels ?? []);
    var INITIAL = @json($contract->workDates->map(fn ($w) => ['date' => optional($w->work_date)->format('Y-m-d'), 'phase' => $w->phase])->values());
    var days = Array.isArray(INITIAL) ? INITIAL.slice() : [];
    var chipsEl = document.getElementById('dayChips');
    var hiddenEl = document.getElementById('dayHidden');
    var countEl = document.getElementById('dayCount');
    function isoOf(dt) {
        var m = ('0' + (dt.getMonth() + 1)).slice(-2), d = ('0' + dt.getDate()).slice(-2);
        return dt.getFullYear() + '-' + m + '-' + d;
    }
    function render() {
        if (!chipsEl) return;
        chipsEl.innerHTML = ''; hiddenEl.innerHTML = '';
        days.sort(function (a, b) { return a.date < b.date ? -1 : (a.date > b.date ? 1 : 0); });
        days.forEach(function (d, i) {
            var chip = document.createElement('span');
            chip.className = 'cc-chip cc-chip--brand d-inline-flex align-items-center gap-1';
            chip.appendChild(document.createTextNode(d.date + ' · ' + (PHASE_LABELS[d.phase] || d.phase)));
            var btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn btn-sm p-0 border-0 bg-transparent'; btn.setAttribute('aria-label', 'Quitar');
            btn.textContent = '×'; btn.style.fontWeight = '700'; btn.style.lineHeight = '1';
            btn.addEventListener('click', function () { days.splice(i, 1); render(); });
            chip.appendChild(btn);
            chipsEl.appendChild(chip);
            hiddenEl.insertAdjacentHTML('beforeend',
                '<input type="hidden" name="dates[' + i + '][date]" value="' + d.date + '">' +
                '<input type="hidden" name="dates[' + i + '][phase]" value="' + d.phase + '">');
        });
        if (countEl) { countEl.textContent = days.length; }
    }
    function has(date) { return days.some(function (d) { return d.date === date; }); }
    var addRangeBtn = document.getElementById('addRange');
    if (addRangeBtn) {
        addRangeBtn.addEventListener('click', function () {
            var phase = document.getElementById('rangePhase').value;
            var s = document.getElementById('rangeStart').value, e = document.getElementById('rangeEnd').value;
            if (!phase || !s || !e) { return; }
            var cur = new Date(s + 'T00:00:00'), end = new Date(e + 'T00:00:00');
            if (isNaN(cur) || isNaN(end) || end < cur) { return; }
            var guard = 0;
            while (cur <= end && guard < 1000) {
                var iso = isoOf(cur);
                if (!has(iso)) { days.push({ date: iso, phase: phase }); }
                cur.setDate(cur.getDate() + 1); guard++;
            }
            render();
        });
    }
    var addDayBtn = document.getElementById('addDay');
    if (addDayBtn) {
        addDayBtn.addEventListener('click', function () {
            var phase = document.getElementById('dayPhase').value, d = document.getElementById('daySingle').value;
            if (!phase || !d || has(d)) { return; }
            days.push({ date: d, phase: phase });
            render();
        });
    }
    render();
})();
</script>
@endpush
