{{-- COTIZACIÓN · formulario compartido (crear/editar). Dos formas, mismo objeto: PDF subido
     byte-intact o partidas capturadas. Los importes de partidas se calculan en el servidor
     (recomputeTotals); aquí solo se muestran en vivo. --}}
@php
    $v = $version ?? null;
    $isPdf = old('source_kind', $v ? $v->source_kind : 'items') === 'pdf';
    $val = fn ($f, $d = null) => old($f, $d);
    $rows = old('items');
    if (! $rows) {
        $rows = ($v && $v->source_kind === 'items' && $v->items->count())
            ? $v->items->map(fn ($i) => [
                'description' => $i->description, 'detail' => $i->detail,
                'quantity' => rtrim(rtrim((string) $i->quantity, '0'), '.'),
                'days' => $i->days !== null ? rtrim(rtrim((string) $i->days, '0'), '.') : '',
                'unit_price' => rtrim(rtrim((string) $i->unit_price, '0'), '.'),
            ])->all()
            : [['description' => '', 'detail' => '', 'quantity' => '1', 'days' => '', 'unit_price' => '']];
    }
@endphp

@include('componentes._typeahead')

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="quotationForm">
    @csrf
    @if(($method ?? 'POST') === 'PUT')@method('PUT')@endif

    {{-- Emisor --}}
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6 mb-3">{{ __('Emisor') }}</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('Nombre del emisor') }} <span class="text-danger">*</span></label>
                <input name="emitter_name" class="form-control" required value="{{ $val('emitter_name', optional($quotation ?? null)->emitter_name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('Correo del emisor') }}</label>
                <input name="emitter_email" type="email" class="form-control" value="{{ $val('emitter_email', optional($quotation ?? null)->emitter_email) }}">
                <div class="form-text">{{ __('Puede diferir del correo de alta (personal, de la empresa o del contador). Al aceptar se busca coincidencia.') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('Departamento') }}</label>
                <select name="department_id" class="form-select js-typeahead">
                    <option value="">{{ __('— Selecciona —') }}</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected((string) $val('department_id', optional($quotation ?? null)->department_id) === (string) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('Locación') }} <span class="text-muted small">({{ __('opcional') }})</span></label>
                <input name="location_name" class="form-control" value="{{ $val('location_name', optional($quotation ?? null)->location_name) }}">
            </div>
        </div>
    </div></div>

    {{-- Fuente: PDF o partidas --}}
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6 mb-3">{{ __('¿Cómo llegó la cotización?') }}</h2>
        <div class="btn-group mb-3" role="group">
            <input type="radio" class="btn-check" name="source_kind" id="src_items" value="items" @checked(! $isPdf)>
            <label class="btn btn-outline-secondary" for="src_items">{{ __('Capturar partidas') }}</label>
            <input type="radio" class="btn-check" name="source_kind" id="src_pdf" value="pdf" @checked($isPdf)>
            <label class="btn btn-outline-secondary" for="src_pdf">{{ __('Subir PDF') }}</label>
        </div>

        {{-- PDF --}}
        <div data-source="pdf" class="{{ $isPdf ? '' : 'd-none' }}">
            @if($v && $v->pdf_path)
                <p class="small mb-2">{{ __('PDF actual') }}: <a href="{{ route('quotations.version_pdf', [$quotation, $v]) }}" target="_blank">{{ $v->pdf_original_name }}</a>
                    <span class="text-muted">· SHA-256 {{ \Illuminate\Support\Str::limit($v->pdf_sha256, 16, '…') }}</span></p>
            @endif
            <label class="form-label">{{ __('Archivo PDF') }}</label>
            <input type="file" name="pdf" class="form-control" accept="application/pdf">
            <div class="form-text">{{ __('Se conserva idéntico (byte-intact). No se estampa nada encima; la firma va en una hoja de aceptación aparte.') }}</div>
            <div class="row g-3 mt-1">
                <div class="col-md-4"><label class="form-label">{{ __('Subtotal') }}</label><input name="subtotal" type="number" step="0.01" class="form-control" value="{{ $val('subtotal', $v ? rtrim(rtrim((string) $v->subtotal,'0'),'.') : '') }}"></div>
                <div class="col-md-4"><label class="form-label">{{ __('IVA') }}</label><input name="iva_amount" type="number" step="0.01" class="form-control" value="{{ $val('iva_amount', $v ? rtrim(rtrim((string) $v->iva_amount,'0'),'.') : '') }}"></div>
                <div class="col-md-4"><label class="form-label">{{ __('Total') }}</label><input name="total" type="number" step="0.01" class="form-control" value="{{ $val('total', $v ? rtrim(rtrim((string) $v->total,'0'),'.') : '') }}"></div>
            </div>
        </div>

        {{-- Partidas --}}
        <div data-source="items" class="{{ $isPdf ? 'd-none' : '' }}">
            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="iva_included" id="iva_included" value="1" @checked($val('iva_included', $v ? $v->iva_included : false))>
                <label class="form-check-label" for="iva_included">{{ __('Los precios YA incluyen IVA') }}</label>
            </div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <label class="form-label mb-0 small">{{ __('Tasa IVA %') }}</label>
                <input name="iva_rate" type="number" step="0.01" class="form-control form-control-sm" style="max-width:100px" value="{{ $val('iva_rate', $v ? rtrim(rtrim((string) $v->iva_rate,'0'),'.') : '16') }}">
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="itemsTable">
                    <thead><tr>
                        <th style="min-width:200px">{{ __('Descripción') }}</th>
                        <th style="width:90px">{{ __('Cant.') }}</th>
                        <th style="width:90px">{{ __('Días') }}</th>
                        <th style="width:120px">{{ __('P. unitario') }}</th>
                        <th style="width:120px" class="text-end">{{ __('Importe') }}</th>
                        <th style="width:40px"></th>
                    </tr></thead>
                    <tbody data-repeat="items">
                        @foreach($rows as $i => $r)
                        <tr class="repeat-row">
                            <td>
                                <input name="items[{{ $i }}][description]" class="form-control form-control-sm" placeholder="{{ __('Ej. Renta de cámara') }}" value="{{ $r['description'] }}">
                                <input name="items[{{ $i }}][detail]" class="form-control form-control-sm mt-1" placeholder="{{ __('Detalle (opcional)') }}" value="{{ $r['detail'] }}">
                            </td>
                            <td><input name="items[{{ $i }}][quantity]" class="form-control form-control-sm js-amt" type="number" step="0.01" min="0" value="{{ $r['quantity'] }}"></td>
                            <td><input name="items[{{ $i }}][days]" class="form-control form-control-sm js-amt" type="number" step="0.01" min="0" placeholder="—" value="{{ $r['days'] }}"></td>
                            <td><input name="items[{{ $i }}][unit_price]" class="form-control form-control-sm js-amt" type="number" step="0.01" min="0" value="{{ $r['unit_price'] }}"></td>
                            <td class="text-end"><span class="js-line">0.00</span></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-crew-soft" id="addItem">+ {{ __('Agregar partida') }}</button>
            <div class="text-end mt-2 small">
                <div>{{ __('Subtotal') }}: <span id="sumSubtotal">0.00</span></div>
                <div>{{ __('IVA') }}: <span id="sumIva">0.00</span></div>
                <div class="fw-bold">{{ __('Total') }}: <span id="sumTotal">0.00</span></div>
            </div>
        </div>
    </div></div>

    {{-- Datos de la cotización --}}
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6 mb-3">{{ __('Datos de la cotización') }}</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">{{ __('Número') }}</label>
                <input name="quotation_number" class="form-control" placeholder="2.1 · DB-2002 · 3010252" value="{{ $val('quotation_number', optional($v)->quotation_number) }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Fecha de emisión') }}</label>
                <input name="issued_at" type="date" class="form-control" value="{{ $val('issued_at', optional(optional($v)->issued_at)->format('Y-m-d')) }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Vigencia (vence)') }}</label>
                <input name="valid_until" type="date" class="form-control" id="valid_until" value="{{ $val('valid_until', optional(optional($v)->valid_until)->format('Y-m-d')) }}">
                <div class="mt-1"><button type="button" class="btn btn-sm btn-link p-0 me-2" data-days="15">+15 días</button><button type="button" class="btn btn-sm btn-link p-0" data-days="30">+30 días</button></div></div>
            <div class="col-md-6"><label class="form-label">{{ __('Condiciones de pago') }}</label>
                <input name="payment_terms" class="form-control" placeholder="Anticipo del 50% · Pago total al corte" value="{{ $val('payment_terms', optional($v)->payment_terms) }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Datos bancarios del emisor') }}</label>
                <input name="bank_details" class="form-control" value="{{ $val('bank_details', optional($v)->bank_details) }}"></div>
        </div>
    </div></div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-crew">{{ __('Guardar cotización') }}</button>
        <a href="{{ isset($quotation) && $quotation ? route('quotations.show', $quotation) : route('quotations.index') }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
    </div>
</form>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('quotationForm');

    // Toggle PDF / partidas.
    form.querySelectorAll('input[name="source_kind"]').forEach(function (r) {
        r.addEventListener('change', function () {
            form.querySelector('[data-source="pdf"]').classList.toggle('d-none', this.value !== 'pdf');
            form.querySelector('[data-source="items"]').classList.toggle('d-none', this.value !== 'items');
        });
    });

    // Repetidor de partidas con índices explícitos (evita el bug de PHP con "[]").
    var box = form.querySelector('[data-repeat="items"]');
    function renumber() {
        box.querySelectorAll('.repeat-row').forEach(function (row, idx) {
            row.querySelectorAll('input').forEach(function (el) {
                if (el.name) el.name = el.name.replace(/^items\[\d*\]/, 'items[' + idx + ']');
            });
        });
    }
    document.getElementById('addItem').addEventListener('click', function () {
        var rows = box.querySelectorAll('.repeat-row');
        var clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        clone.querySelector('.js-line').textContent = '0.00';
        box.appendChild(clone); renumber(); recompute();
    });
    box.addEventListener('click', function (e) {
        if (e.target.classList.contains('js-del')) {
            if (box.querySelectorAll('.repeat-row').length > 1) { e.target.closest('.repeat-row').remove(); }
            else { e.target.closest('.repeat-row').querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
            renumber(); recompute();
        }
    });

    function money(n) { return (Math.round(n * 100) / 100).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function recompute() {
        var sum = 0;
        box.querySelectorAll('.repeat-row').forEach(function (row) {
            var q = parseFloat(row.querySelector('[name$="[quantity]"]').value || 0) || 0;
            var d = parseFloat(row.querySelector('[name$="[days]"]').value || 0) || 0;
            var p = parseFloat(row.querySelector('[name$="[unit_price]"]').value || 0) || 0;
            var line = q * (d > 0 ? d : 1) * p;
            row.querySelector('.js-line').textContent = money(line);
            sum += line;
        });
        var rate = (parseFloat(form.querySelector('[name="iva_rate"]').value || 0) || 0) / 100;
        var incl = form.querySelector('[name="iva_included"]').checked;
        var subtotal, iva, total;
        if (incl) { total = sum; subtotal = rate > 0 ? total / (1 + rate) : total; iva = total - subtotal; }
        else { subtotal = sum; iva = subtotal * rate; total = subtotal + iva; }
        document.getElementById('sumSubtotal').textContent = money(subtotal);
        document.getElementById('sumIva').textContent = money(iva);
        document.getElementById('sumTotal').textContent = money(total);
    }
    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('js-amt') || e.target.name === 'iva_rate' || e.target.name === 'iva_included') recompute();
    });
    form.querySelector('[name="iva_included"]').addEventListener('change', recompute);
    recompute();

    // Vigencia rápida (+15 / +30 días desde emisión o desde hoy).
    form.querySelectorAll('[data-days]').forEach(function (b) {
        b.addEventListener('click', function () {
            var base = form.querySelector('[name="issued_at"]').value;
            var d = base ? new Date(base + 'T00:00:00') : new Date();
            d.setDate(d.getDate() + parseInt(this.dataset.days, 10));
            document.getElementById('valid_until').value = d.toISOString().slice(0, 10);
        });
    });
})();
</script>
@endpush
