@extends('layouts.app')
@section('content')
{{-- MÓDULO DE FIRMA (config global, al iniciar el proyecto). Dos listas de PUESTOS:
     (a) AUTORIZADORES del Infosheet (paso 2) · (b) FIRMANTES del contrato (paso 4). El puesto
     DEFINE quién firma; el sobre CONGELA a la persona al crear. No lo cambia ningún departamento. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:760px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Firmas Prod.') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Quiénes autorizan y quiénes firman los contratos. Se define por PUESTO, una vez, para toda la producción. Cada firmante es un usuario con perfil (firma autenticado).') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('contracts.route.config.update') }}">
            @csrf

            {{-- (a) Autorizadores del Infosheet (paso 2) --}}
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label fw-semibold mb-1">{{ __('Autorizadores del Infosheet') }}</label>
                    <div class="form-text mb-2">{{ __('Quién aprueba el trato antes de generar el contrato (por defecto el Line Producer; se puede añadir HOD u otros).') }}</div>
                    <div class="cc-picker" data-name="authorizer_position_ids">
                        <div class="d-flex gap-2">
                            <select class="form-select cc-picker__select">
                                <option value="">{{ __('— Puesto —') }}</option>
                                <option value="dept_hod">{{ __('HOD del departamento del contrato (dinámico)') }}</option>
                                @foreach($eligible as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-crew-soft cc-picker__add">{{ __('Agregar') }}</button>
                        </div>
                        <div class="cc-picker__chips d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                </div>
            </div>

            {{-- (b) Firmantes del contrato (paso 4) --}}
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label fw-semibold mb-1">{{ __('Firmantes del contrato') }}</label>
                    <div class="form-text mb-2">{{ __('Quiénes firman el contrato y los documentos, en orden (HOD, Gerente de Producción, Line Producer, Fiscales, Legal…). El contratado firma siempre.') }}</div>
                    <div class="cc-picker" data-name="signer_position_ids">
                        <div class="d-flex gap-2">
                            <select class="form-select cc-picker__select">
                                <option value="">{{ __('— Puesto —') }}</option>
                                <option value="dept_hod">{{ __('HOD del departamento del contrato (dinámico)') }}</option>
                                @foreach($eligible as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-crew-soft cc-picker__add">{{ __('Agregar') }}</button>
                        </div>
                        <div class="cc-picker__chips d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                    <div class="form-text mt-1">{{ __('Si dejas esta lista vacía, se usa la ruta clásica de abajo (preparador / obliga).') }}</div>
                </div>
            </div>

            <button class="btn btn-crew">{{ __('Guardar') }}</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var INIT = {
        authorizer_position_ids: @json($authorizers ?? []),
        signer_position_ids:     @json($signers ?? [])
    };
    document.querySelectorAll('.cc-picker').forEach(function (root) {
        var name   = root.getAttribute('data-name');
        var select = root.querySelector('.cc-picker__select');
        var addBtn = root.querySelector('.cc-picker__add');
        var chips  = root.querySelector('.cc-picker__chips');
        var items  = (INIT[name] || []).slice();

        function render() {
            chips.innerHTML = '';
            items.forEach(function (it, i) {
                var chip = document.createElement('span');
                chip.className = 'cc-chip cc-chip--brand d-inline-flex align-items-center gap-1';
                chip.appendChild(document.createTextNode((i + 1) + '. ' + it.name));
                var x = document.createElement('button');
                x.type = 'button'; x.className = 'btn btn-sm p-0 border-0 bg-transparent';
                x.textContent = '×'; x.style.fontWeight = '700'; x.style.lineHeight = '1';
                x.addEventListener('click', function () { items.splice(i, 1); render(); });
                chip.appendChild(x);
                chips.appendChild(chip);
                var hid = document.createElement('input');
                hid.type = 'hidden'; hid.name = name + '[]'; hid.value = it.id;
                chips.appendChild(hid);
            });
        }
        addBtn.addEventListener('click', function () {
            var raw = select.value;
            if (!raw) { return; }
            var id = (raw === 'dept_hod') ? 'dept_hod' : parseInt(raw, 10);
            if (!id || items.some(function (it) { return String(it.id) === String(id); })) { return; }
            items.push({ id: id, name: select.options[select.selectedIndex].textContent.trim() });
            select.value = '';
            render();
        });
        render();
    });
})();
</script>
@endpush
