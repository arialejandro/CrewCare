@extends('layouts.app')
@section('content')

{{-- Paso 1 · ENCONTRAR LA HERRAMIENTA. Rejilla de cards con el lenguaje de crew.
     La operación está DETENIDA: el buscador (alias primero) es lo primero que se ve. --}}
<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'wrench', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Inspección de herramienta') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Busca por apodo, nombre o código. Elige INSPECCIONAR.') }}</p>
                </div>
            </div>

            <div class="crew-search flex-grow-1 flex-lg-grow-0" style="min-width: 280px;">
                <label for="toolsearch" class="visually-hidden">{{ __('Buscar herramienta') }}</label>
                <form onsubmit="return false;">
                    <div class="input-group">
                        <span class="input-group-text border-end-0">
                            @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                        </span>
                        <input class="form-control border-start-0 ps-0" id="toolsearch" type="search" autocomplete="off"
                               placeholder="{{ __('sawzall, la 4½, el impacto…') }}">
                    </div>
                </form>
            </div>
        </div>

        {{-- Consulta del histórico + admin de imágenes de referencia (delta #47). --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('tools.records') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'clipboard-list', 'label' => null]) {{ __('Actas de inspección') }}
            </a>
            <a href="{{ route('tools.images') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'camera', 'label' => null]) {{ __('Imágenes de referencia') }}
            </a>
        </div>

        {{-- Puerta (A4): inspección ligada a un reporte de origen. --}}
        @if (! empty($launch['origin']))
            <div class="alert alert-info d-flex align-items-center gap-2 py-2">
                @include('componentes._icon', ['name' => 'git-compare', 'label' => null])
                <span>{{ __('Esta inspección quedará ligada al reporte de origen.') }}</span>
            </div>
        @endif

        {{-- LA LISTA DEL DÍA: la DEUDA. Corta por diseño (solo por_jornada sin acta vigente hoy). --}}
        @if ($dayList->isNotEmpty())
            <div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left:4px solid #b45309 !important;">
                <div class="p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico', 'label' => null])
                        <strong>{{ __('Pendiente de inspección hoy') }}</strong>
                        <span class="insp-tag">{{ $dayList->count() }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($dayList as $t)
                            <a href="{{ route('tools.inspect.form', array_merge([$t->id], $launch)) }}"
                               class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1">
                                @include('componentes._icon', ['name' => 'wrench', 'label' => null])
                                {{ $t->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div id="toolgrid" aria-live="polite" data-launch="{{ http_build_query($launch) }}">
            @if ($tools->count() === 0)
                @include('inspection._wildcard-cta', ['wildcard' => $wildcard])
            @else
                <div class="tool-cards">
                    @foreach ($tools as $tool)
                        @include('inspection._tool-card', ['tool' => $tool, 'launch' => $launch])
                    @endforeach
                </div>
                @if ($tools->hasPages())
                    <div class="mt-3">{!! $tools->links() !!}</div>
                @endif
            @endif
        </div>

    </div>
</div>

<script>
    $(document).ready(function () {
        var t = null;
        function run() {
            var v = document.getElementById('toolsearch').value;
            if (v === '') { v = 'vacio'; }
            // Arrastra el origen/momento de la puerta (A4) a la búsqueda AJAX.
            var launch = document.getElementById('toolgrid').getAttribute('data-launch') || '';
            var url = '{{ url('/inspeccion/buscar') }}/' + encodeURIComponent(v) + (launch ? ('?' + launch) : '');
            fetch(url, { method: 'get' })
                .then(function (r) { return r.text(); })
                .then(function (html) { document.getElementById('toolgrid').innerHTML = html; })
                .catch(function (e) { console.log(e); });
        }
        $('#toolsearch').keyup(function () { clearTimeout(t); t = setTimeout(run, 250); });
    });
</script>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
