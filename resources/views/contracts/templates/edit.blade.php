@extends('layouts.app')
@section('content')
{{-- CONTRACT BUILDER · editor de plantilla. Izquierda: redacción + paleta de campos/anclas.
     Derecha: preview server-side (mismo render que el documento real) con datos de ejemplo. --}}
@php $isNew = ! $template->exists; @endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1180px">

        <div class="mb-3">
            <a href="{{ route('contracts.templates.index') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Plantillas') }}
            </a>
        </div>

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $isNew ? __('Nueva plantilla') : $template->name }}</h1>
                <p class="text-muted mb-0 small">{{ __('Escribe el contrato e inserta campos y firmas desde el menú. La vista previa usa datos de ejemplo.') }}</p>
            </div>
        </div>

        {{-- Descargo legal — CrewCare no redacta ni asume responsabilidad legal (contract-builder-legal-boundary). --}}
        <div class="alert alert-warning small mb-3" role="note">
            <strong>{{ __('Responsabilidad legal de la productora.') }}</strong>
            {{ __('El contenido jurídico del contrato lo define y respalda la productora (su área legal). CrewCare solo ensambla los datos del trato, numera y estampa las firmas: no redacta contratos ni brinda asesoría legal.') }}
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ $isNew ? route('contracts.templates.store') : route('contracts.templates.update', $template) }}">
            @csrf
            @unless($isNew)@method('PUT')@endunless

            <div class="row g-4">
                {{-- ── Editor ── --}}
                <div class="col-12 col-lg-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('Nombre') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold d-block">{{ __('Aplica a') }}</label>
                                @foreach($subtypes as $val => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="applies_to[]" value="{{ $val }}"
                                               id="st_{{ $val }}" @checked(in_array($val, old('applies_to', $template->applies_to ?? []), true))>
                                        <label class="form-check-label" for="st_{{ $val }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Paleta: insertar campos / firmas en el cursor. Sin `name` → no se envían. --}}
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <select class="form-select form-select-sm cc-tpl-insert" style="max-width:220px">
                                    <option value="">{{ __('+ Insertar campo…') }}</option>
                                    @foreach($fields as $key => $label)
                                        @php $tok = '{{' . $key . '}}'; @endphp
                                        <option value="{{ $tok }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select class="form-select form-select-sm cc-tpl-insert" style="max-width:220px">
                                    <option value="">{{ __('+ Insertar firma…') }}</option>
                                    @foreach($anchors as $key => $label)
                                        @php $atok = '[[firma:' . $key . ']]'; @endphp
                                        <option value="{{ $atok }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <textarea id="tplBody" name="body" class="form-control" rows="18"
                                      style="font-family:ui-monospace,Consolas,monospace;font-size:.86rem;">{{ old('body', $template->body) }}</textarea>
                            <div class="form-text">{{ __('HTML permitido. Los {{campos}} se llenan con el trato; las [[firma:...]] se estampan al firmar.') }}</div>

                            <div class="d-flex align-items-center gap-3 mt-3">
                                <button class="btn btn-crew">{{ __('Guardar') }}</button>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                           @checked(old('is_active', $template->is_active))>
                                    <label class="form-check-label" for="is_active">{{ __('Activa') }}</label>
                                </div>
                                <input type="hidden" name="language" value="{{ old('language', $template->language ?: 'es') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Preview ── --}}
                <div class="col-12 col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <span class="fw-semibold">{{ __('Vista previa') }} <span class="text-muted small">({{ __('datos de ejemplo') }})</span></span>
                            <button type="button" id="tplRefresh" class="btn btn-sm btn-crew-soft">{{ __('Actualizar') }}</button>
                        </div>
                        <div class="card-body">
                            <iframe id="tplPreview" title="{{ __('Vista previa') }}" sandbox=""
                                    style="width:100%;height:560px;border:1px solid var(--border, #d7dce4);border-radius:10px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var ta = document.getElementById('tplBody');
    function insertAtCursor(text) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        ta.value = ta.value.slice(0, s) + text + ta.value.slice(e);
        ta.selectionStart = ta.selectionEnd = s + text.length;
        ta.focus();
    }
    document.querySelectorAll('.cc-tpl-insert').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (!this.value) { return; }
            insertAtCursor(this.value);
            this.value = '';
        });
    });

    var frame = document.getElementById('tplPreview');
    var meta  = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.content : '';
    var url   = @json(route('contracts.templates.preview'));
    function refresh() {
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
            body: 'body=' + encodeURIComponent(ta.value)
        }).then(function (r) { return r.text(); }).then(function (html) { frame.srcdoc = html; });
    }
    var btn = document.getElementById('tplRefresh');
    if (btn) { btn.addEventListener('click', refresh); }
    refresh();
})();
</script>
@endpush
