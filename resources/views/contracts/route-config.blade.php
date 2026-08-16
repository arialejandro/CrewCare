@extends('layouts.app')
@section('content')
@include('contracts._route-styles')
{{-- MÓDULO DE FIRMA · la RUTA de la producción como una cadena ordenada (estilo Signus/Logical
     Contracts). Dos fases: (1) APROBACIÓN del trato (Infosheet) → al aprobarse se genera el contrato;
     (2) FIRMA del contrato, en el orden que se defina. El PUESTO define quién; el sobre CONGELA a la
     persona al crear. El token "HOD del departamento" se resuelve por el departamento de cada
     contrato. Se guarda en dos listas ordenadas de puestos (no cambia el backend). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:840px">
        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Roles de firma') }}</h1>
                <p class="text-muted mb-0 small">{{ __('La ruta de firma de la producción, en orden. Se define por PUESTO una sola vez; cada firmante es un usuario con perfil que firma autenticado.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('contracts.route.config.update') }}">
            @csrf

            <div class="cc-route">
                {{-- ── FASE 1 · Aprobación (Infosheet) ─────────────────────────────── --}}
                <section class="cc-route__phase">
                    <div class="cc-route__phead">
                        <span class="cc-route__pnum">1</span>
                        <div>
                            <div class="cc-route__ptitle">{{ __('Aprobación del trato') }}</div>
                            <div class="cc-route__psub">{{ __('Quién autoriza el Infosheet antes de generar el contrato.') }}</div>
                        </div>
                        <span class="cc-route__pcount" data-count="authorizers"></span>
                    </div>

                    <div class="cc-route__list" data-phase="authorizers"></div>

                    <div class="cc-route__add">
                        <div></div>
                        <div class="cc-route__add-inner">
                            <select class="form-select form-select-sm cc-add-select" data-phase="authorizers" aria-label="{{ __('Agregar autorizador') }}">
                                <option value="">{{ __('— Agregar puesto —') }}</option>
                                <option value="dept_hod">{{ __('HOD del departamento del contrato (dinámico)') }}</option>
                                @foreach($eligible as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-crew-soft cc-add-btn" data-phase="authorizers">{{ __('Agregar') }}</button>
                        </div>
                    </div>

                    {{-- B3 · escalera de autorización: aprobar por niveles (en orden) vs paralelo (default). --}}
                    <div class="form-check mt-2 ms-1">
                        <input type="hidden" name="auth_sequential" value="0">
                        <input type="checkbox" class="form-check-input" id="authSeq" name="auth_sequential" value="1" @checked($authSequential ?? false)>
                        <label class="form-check-label small" for="authSeq">
                            {{ __('Aprobar por niveles: cada autorizador aprueba solo cuando el anterior ya lo hizo. Si lo dejas apagado, cualquiera aprueba en cualquier orden.') }}
                        </label>
                    </div>
                </section>

                {{-- ── FASE 2 · Firma del contrato ─────────────────────────────────── --}}
                <section class="cc-route__phase">
                    <div class="cc-route__phead">
                        <span class="cc-route__pnum">2</span>
                        <div>
                            <div class="cc-route__ptitle">{{ __('Firma del contrato') }}</div>
                            <div class="cc-route__psub">{{ __('Quiénes firman, en orden. El contratado firma siempre, primero.') }}</div>
                        </div>
                        <span class="cc-route__pcount" data-count="signers"></span>
                    </div>

                    <div class="cc-route__list" data-phase="signers"></div>

                    <div class="cc-route__add">
                        <div></div>
                        <div class="cc-route__add-inner">
                            <select class="form-select form-select-sm cc-add-select" data-phase="signers" aria-label="{{ __('Agregar firmante') }}">
                                <option value="">{{ __('— Agregar puesto —') }}</option>
                                <option value="dept_hod">{{ __('HOD del departamento del contrato (dinámico)') }}</option>
                                @foreach($eligible as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-crew-soft cc-add-btn" data-phase="signers">{{ __('Agregar') }}</button>
                        </div>
                    </div>
                    <div class="form-text mt-2">{{ __('Si dejas la fase de firma vacía, se usa la ruta clásica (preparador / obliga).') }}</div>

                    {{-- B4 · ruteo paralelo: los firmantes firman en cualquier orden vs secuencial (default). --}}
                    <div class="form-check mt-2 ms-1">
                        <input type="hidden" name="sign_parallel" value="0">
                        <input type="checkbox" class="form-check-input" id="signPar" name="sign_parallel" value="1" @checked($signParallel ?? false)>
                        <label class="form-check-label small" for="signPar">
                            {{ __('Firmar en cualquier orden: todos los firmantes reciben a la vez y firman cuando quieran. Si lo dejas apagado, firman en el orden de arriba, uno tras otro.') }}
                        </label>
                    </div>
                </section>
            </div>

            {{-- B5 · FIRMANTES CONDICIONALES (por importe): agrega una firma extra si los honorarios
                 alcanzan un monto. Se resuelve al crear el sobre y se deduplica por puesto. --}}
            <section class="cc-route__phase mt-3">
                <div class="cc-route__phead">
                    <span class="cc-route__pnum">3</span>
                    <div>
                        <div class="cc-route__ptitle">{{ __('Firmantes condicionales (por importe)') }}</div>
                        <div class="cc-route__psub">{{ __('Agrega una firma extra cuando los honorarios del contrato alcanzan un monto. Ej.: arriba de $50,000, firma también el Line Producer.') }}</div>
                    </div>
                </div>
                @php $condRows = array_values($conditionalRules ?? []); @endphp
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-1">
                        <thead>
                            <tr>
                                <th style="width:220px">{{ __('Si honorarios ≥ (MXN)') }}</th>
                                <th>{{ __('Agrega como firmante') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_pad($condRows, count($condRows) + 2, null) as $rule)
                                <tr>
                                    <td><input type="number" min="0" step="1" name="cond_min[]" class="form-control form-control-sm" value="{{ $rule['min'] ?? '' }}" placeholder="0"></td>
                                    <td>
                                        <select name="cond_entry[]" class="form-select form-select-sm">
                                            <option value="">{{ __('— Ninguno —') }}</option>
                                            <option value="dept_hod" @selected(($rule['entry'] ?? null) === 'dept_hod')>{{ __('HOD del departamento del contrato') }}</option>
                                            @foreach($eligible as $p)
                                                <option value="{{ $p->id }}" @selected((string) ($rule['entry'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-text">{{ __('Para quitar una regla, deja su importe en blanco y guarda. Si el puesto ya firma en la ruta, no se duplica.') }}</div>
            </section>

            <div class="d-flex align-items-center gap-2 mt-4">
                <button class="btn btn-crew">{{ __('Guardar ruta') }}</button>
                <span class="text-muted small">{{ __('El ocupante se congela al crear cada sobre; aquí solo se previsualiza.') }}</span>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var STATE = {
        authorizers: @json($authorizers ?? []),
        signers:     @json($signers ?? [])
    };
    var OCC = @json($occupants ?? []);   // { "<position_id>": {state:'ok'|'vacant'|'duplicate', name} }
    var FIELD = { authorizers: 'authorizer_position_ids', signers: 'signer_position_ids' };

    var T = {
        approve:  @json(__('Aprobación')),
        sign:     @json(__('Firma')),
        contracted: @json(__('Contratado')),
        contractedTitle: @json(__('El contratado')),
        contractedWho:   @json(__('La persona o proveedor del contrato — firma siempre.')),
        defaultAuth:     @json(__('Productor en Línea')),
        defaultAuthWho:  @json(__('Por defecto, si no agregas autorizadores.')),
        ocupa:    @json(__('Ocupa:')),
        vacant:   @json(__('Puesto sin titular en esta producción')),
        dup:      @json(__('Dos personas ocupan este puesto (ambiguo)')),
        dynamic:  @json(__('Se resuelve por el departamento de cada contrato')),
        check:    @json(__('Se verifica al crear el sobre')),
        up:       @json(__('Subir')),
        down:     @json(__('Bajar')),
        remove:   @json(__('Quitar'))
    };

    function svg(inner) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    }
    var ICON = {
        user: svg('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'),
        warn: svg('<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'),
        dyn:  svg('<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>'),
        contract: svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'),
        up:   svg('<polyline points="18 15 12 9 6 15"/>'),
        down: svg('<polyline points="6 9 12 15 18 9"/>'),
        x:    svg('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>')
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Línea de "quién ocupa" el puesto (ok / vacante / duplicado / dinámico / por verificar).
    function whoHtml(item) {
        if (String(item.id) === 'dept_hod') {
            return { cls: '', html: ICON.dyn + '<span>' + esc(T.dynamic) + '</span>' };
        }
        var o = OCC[String(item.id)];
        if (!o) {
            return { cls: '', html: ICON.user + '<span>' + esc(T.check) + '</span>' };
        }
        if (o.state === 'ok') {
            return { cls: 'cc-step__who--ok', html: ICON.user + '<span>' + esc(T.ocupa) + ' <strong>' + esc(o.name || '—') + '</strong></span>' };
        }
        if (o.state === 'duplicate') {
            return { cls: 'cc-step__who--warn', html: ICON.warn + '<span>' + esc(T.dup) + '</span>' };
        }
        return { cls: 'cc-step__who--warn', html: ICON.warn + '<span>' + esc(T.vacant) + '</span>' };
    }

    function stepShell(num, extraClass) {
        var wrap = document.createElement('div');
        wrap.className = 'cc-step' + (extraClass ? ' ' + extraClass : '');
        wrap.innerHTML =
            '<div class="cc-step__rail"><span class="cc-step__num">' + num + '</span></div>' +
            '<div class="cc-step__card"><div class="cc-step__main"></div></div>';
        return wrap;
    }

    // Paso EDITABLE (un puesto configurado): pill de fase + título + ocupante + acciones.
    function itemStep(phase, item, i, count, num) {
        var wrap = stepShell(num);
        var main = wrap.querySelector('.cc-step__main');
        var card = wrap.querySelector('.cc-step__card');
        var who = whoHtml(item);
        var pillClass = phase === 'authorizers' ? 'cc-step__pill--approve' : 'cc-step__pill--sign';
        var pillText  = phase === 'authorizers' ? T.approve : T.sign;
        main.innerHTML =
            '<span class="cc-step__pill ' + pillClass + '">' + esc(pillText) + '</span>' +
            '<div class="cc-step__title">' + esc(item.name) + '</div>' +
            '<div class="cc-step__who ' + who.cls + '">' + who.html + '</div>';

        var acts = document.createElement('div');
        acts.className = 'cc-step__acts';
        var up = mkAct(ICON.up, T.up, i === 0);
        var down = mkAct(ICON.down, T.down, i === count - 1);
        var del = mkAct(ICON.x, T.remove, false, 'cc-step__act--del');
        up.addEventListener('click', function () { swap(phase, i, i - 1); });
        down.addEventListener('click', function () { swap(phase, i, i + 1); });
        del.addEventListener('click', function () { STATE[phase].splice(i, 1); render(phase); });
        acts.appendChild(up); acts.appendChild(down); acts.appendChild(del);
        card.appendChild(acts);

        var hid = document.createElement('input');
        hid.type = 'hidden'; hid.name = FIELD[phase] + '[]'; hid.value = item.id;
        wrap.appendChild(hid);
        return wrap;
    }

    function mkAct(icon, label, disabled, extra) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'cc-step__act' + (extra ? ' ' + extra : '');
        b.innerHTML = icon;
        b.setAttribute('aria-label', label);
        b.title = label;
        if (disabled) { b.disabled = true; }
        return b;
    }

    // Ancla FIJA (Contratado) al inicio de la fase de firma — no editable, no se envía.
    function anchorStep(num) {
        var wrap = stepShell(num, 'cc-step--anchor');
        wrap.querySelector('.cc-step__main').innerHTML =
            '<span class="cc-step__pill cc-step__pill--lead">' + esc(T.contracted) + '</span>' +
            '<div class="cc-step__title">' + esc(T.contractedTitle) + '</div>' +
            '<div class="cc-step__who">' + ICON.contract + '<span>' + esc(T.contractedWho) + '</span></div>';
        return wrap;
    }

    // Paso "por defecto" cuando la fase de aprobación está vacía (fiel al backend: LP por defecto).
    function ghostAuthStep(num) {
        var wrap = stepShell(num, 'cc-step--ghost');
        wrap.querySelector('.cc-step__main').innerHTML =
            '<span class="cc-step__pill cc-step__pill--approve">' + esc(T.approve) + '</span>' +
            '<div class="cc-step__title">' + esc(T.defaultAuth) + '</div>' +
            '<div class="cc-step__who">' + ICON.user + '<span>' + esc(T.defaultAuthWho) + '</span></div>';
        return wrap;
    }

    function swap(phase, a, b) {
        var arr = STATE[phase];
        if (b < 0 || b >= arr.length) { return; }
        var t = arr[a]; arr[a] = arr[b]; arr[b] = t;
        render(phase);
    }

    function render(phase) {
        var host = document.querySelector('.cc-route__list[data-phase="' + phase + '"]');
        host.innerHTML = '';
        var items = STATE[phase];
        var num = 1;
        if (phase === 'signers') { host.appendChild(anchorStep(num++)); }
        if (phase === 'authorizers' && items.length === 0) { host.appendChild(ghostAuthStep(num++)); }
        items.forEach(function (it, i) { host.appendChild(itemStep(phase, it, i, items.length, num++)); });

        var badge = document.querySelector('.cc-route__pcount[data-count="' + phase + '"]');
        if (badge) {
            var total = items.length + (phase === 'signers' ? 1 : 0);
            badge.textContent = total + ' ' + (total === 1 ? 'paso' : 'pasos');
        }
    }

    // Agregar un puesto a una fase (dedup por id).
    document.querySelectorAll('.cc-add-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var phase = btn.getAttribute('data-phase');
            var sel = document.querySelector('.cc-add-select[data-phase="' + phase + '"]');
            var raw = sel.value;
            if (!raw) { return; }
            var id = (raw === 'dept_hod') ? 'dept_hod' : parseInt(raw, 10);
            if (!id) { return; }
            if (STATE[phase].some(function (it) { return String(it.id) === String(id); })) { sel.value = ''; return; }
            STATE[phase].push({ id: id, name: sel.options[sel.selectedIndex].textContent.trim() });
            sel.value = '';
            render(phase);
        });
    });

    render('authorizers');
    render('signers');
})();
</script>
@endpush
