@extends('layouts.app')
@section('content')

{{--
    Gafetes — homologado al lenguaje visual de Crew List (superficie "Listas").
    Comparte estilos con Crew List vía componentes/_crew-list-styles (envolviendo en .crew-page).
    La búsqueda AJAX reemplaza #usertable con componentes.search-results-idcard (misma fila
    compartida _idcard-row) para que el swap sea invisible. Los chips-filtro filtran del lado
    del cliente las filas de la página actual usando data-has-photo / data-printed.
--}}

<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        {{-- Encabezado + toolbar --}}
        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('listas.gafetes') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('listas.miembros_activos', ['n' => $counts['total']]) }}</p>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                @can('badge.design')
                    <a href="{{ route('badge.designer') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'settings', 'class' => 'cc-ico', 'label' => null])
                        <span>{{ __('listas.disenar') }}</span>
                    </a>
                @endcan
                <a href="{{ route('badge.bulk.pdf') }}" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1 {{ $counts['ready'] ? '' : 'disabled' }}">
                    @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
                    <span>{{ __('listas.pdf_listos', ['n' => $counts['ready']]) }}</span>
                </a>
                <a href="{{ route('badge.bulk.jpg') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 {{ $counts['ready'] ? '' : 'disabled' }}">
                    @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico', 'label' => null])
                    <span>{{ __('listas.zip_listos', ['n' => $counts['ready']]) }}</span>
                </a>
            </div>
        </div>

        {{-- Chips-filtro clicables (usan los counts que ya calcula el controlador) --}}
        <div class="crew-chips mb-3" id="badgeFilters" role="group" aria-label="{{ __('listas.estado') }}">
            <button type="button" class="crew-chip" data-filter="all" aria-pressed="true">
                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico', 'label' => null])
                <span>{{ __('listas.filtro_todos') }}</span> <span class="crew-chip-count">{{ $counts['total'] }}</span>
            </button>
            <button type="button" class="crew-chip" data-filter="withPhoto" aria-pressed="false">
                @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null])
                <span>{{ __('listas.filtro_con_foto') }}</span> <span class="crew-chip-count">{{ $counts['withPhoto'] }}</span>
            </button>
            <button type="button" class="crew-chip" data-filter="ready" aria-pressed="false">
                @include('componentes._icon', ['name' => 'printer', 'class' => 'cc-ico', 'label' => null])
                <span>{{ __('listas.filtro_listos') }}</span> <span class="crew-chip-count">{{ $counts['ready'] }}</span>
            </button>
        </div>

        @if (session('error'))
            <div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico', 'label' => null])
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null])
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="alert alert-light border small text-muted d-flex align-items-center gap-2 rounded-3">
            @include('componentes._icon', ['name' => 'info', 'class' => 'cc-ico', 'label' => null])
            <span>{{ __('listas.lote_info') }}</span>
        </div>

        {{-- Buscador + tabla --}}
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header d-flex align-items-center gap-2 py-2 border-0">
                <div class="crew-search flex-grow-1">
                    <label for="search" class="visually-hidden">{{ __('listas.buscar_label') }}</label>
                    <form data-search-noop>
                        <div class="input-group">
                            <span class="input-group-text">
                                @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                            </span>
                            <input class="form-control" id="search" type="search" autocomplete="off" placeholder="{{ __('listas.buscar') }}">
                        </div>
                    </form>
                </div>
            </div>
            <div id="usertable" class="table-responsive" aria-live="polite">
                <table class="table table-hover align-middle mb-0 crew-table cc-stack">
                    <thead>
                        <tr>
                            <th scope="col" class="ps-4">{{ __('listas.integrante') }}</th>
                            <th scope="col">{{ __('listas.depto') }}</th>
                            <th scope="col">{{ __('listas.estado') }}</th>
                            <th scope="col" class="text-end pe-4">{{ __('listas.acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usuarios as $user)
                            @include('componentes._idcard-row', ['user' => $user])
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="crew-empty text-center py-5">
                                        <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                                            @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico', 'label' => null])
                                        </div>
                                        <h5 class="mb-1">{{ __('listas.empty_title') }}</h5>
                                        <p class="text-muted mb-0">{{ __('listas.sin_integrantes') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($usuarios->hasPages())
                <div class="card-footer border-0 py-3">{!! $usuarios->links() !!}</div>
            @endif
        </div>

    </div>
</div>

<script>
    (function () {
        var input = document.getElementById('search');
        var table = document.getElementById('usertable');
        var chips = Array.prototype.slice.call(document.querySelectorAll('#badgeFilters .crew-chip'));
        var activeFilter = 'all';

        // Filtro cliente sobre las filas de la página actual (data-has-photo / data-printed).
        function applyFilter() {
            var rows = table.querySelectorAll('tbody tr[data-has-photo]');
            rows.forEach(function (tr) {
                var hasPhoto = tr.getAttribute('data-has-photo') === '1';
                var printed = tr.getAttribute('data-printed') === '1';
                var show = activeFilter === 'all'
                    || (activeFilter === 'withPhoto' && hasPhoto)
                    || (activeFilter === 'ready' && hasPhoto && !printed);
                tr.style.display = show ? '' : 'none';
            });
        }
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                activeFilter = chip.getAttribute('data-filter');
                chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
                applyFilter();
            });
        });

        // Búsqueda AJAX con debounce (~250ms) — antes hacía 1 fetch por tecla.
        var t = null;
        function runSearch() {
            var valor = input.value || '';
            if (valor === '') { valor = 'vacio'; }
            fetch('/searchidcard/' + encodeURIComponent(valor) + '/?page=1', { method: 'get' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    table.innerHTML = html === ''
                        ? '<div class="crew-empty text-center py-5"><h5 class="mb-1">{{ __('listas.no_result') }}</h5></div>'
                        : html;
                    applyFilter(); // re-aplica el filtro activo tras el swap
                })
                .catch(function (err) { console.log(err); });
        }
        if (input) {
            input.addEventListener('keyup', function () {
                clearTimeout(t);
                t = setTimeout(runSearch, 250);
            });
        }
        // El buscador NO envía el form (búsqueda por keyup/AJAX): veta el submit sin on* (CSP).
        document.addEventListener('submit', function (e) {
            if (e.target.closest('[data-search-noop]')) { e.preventDefault(); }
        });
    })();
</script>

@push('styles')
    @include('componentes._crew-list-styles')
@endpush

@endsection
