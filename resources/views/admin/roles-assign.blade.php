@extends('layouts.app')

@push('styles')
<style>
    /* ===== Asignar roles · "Cinematic Dark Glass" ===== */
    .adm-header { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .adm-icon {
        width:48px; height:48px; border-radius:14px; flex:none;
        display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary);
        background:color-mix(in srgb, var(--brand-primary) 16%, transparent);
        border:1px solid var(--stroke);
    }
    .adm-icon .cc-ico { width:22px; height:22px; }
    .adm-title { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.02em; margin:0; color:var(--text); font-size:1.35rem; }
    .adm-subtitle { color:var(--text-muted); font-size:.9rem; margin:.15rem 0 0; }

    .adm-note { color:var(--text-muted); font-size:.85rem; }
    .adm-note strong { color:var(--text); }
    .adm-note code {
        background:color-mix(in srgb, var(--brand-primary) 12%, transparent);
        color:var(--brand-primary); border-radius:6px; padding:.05rem .35rem; font-size:.8em;
    }

    /* Buscador tokenizado */
    .adm-search .form-control {
        background-color:var(--glass); border:1px solid var(--stroke); color:var(--text);
    }
    .adm-search .form-control::placeholder { color:var(--text-muted); opacity:.75; }
    .adm-search .form-control:focus {
        background-color:var(--bg-2); border-color:var(--brand-primary); color:var(--text);
        box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb), .22);
    }

    /* Tabla de asignación (también aplica al parcial inyectado por AJAX) */
    .roles-table { color:var(--text); margin-bottom:0; }
    .roles-table thead th {
        text-transform:uppercase; font-size:.72rem; letter-spacing:.05em;
        color:var(--text-muted); font-weight:700; background:transparent;
        border-bottom:1px solid var(--stroke);
    }
    .roles-table tbody td { border-color:var(--stroke); color:var(--text); vertical-align:middle; }
    .roles-table tbody tr:hover td { background:color-mix(in srgb, var(--brand-primary) 8%, transparent); }
    .roles-table .form-select {
        background-color:var(--bg-2); border:1px solid var(--stroke); color:var(--text);
    }
    .roles-table .form-select:focus {
        border-color:var(--brand-primary);
        box-shadow:0 0 0 .2rem rgba(var(--brand-primary-rgb), .22);
    }
    .roles-count { color:var(--text-muted); font-size:.85rem; }

    @media (max-width:767px){ .adm-title { font-size:1.15rem; } }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-4">

    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico', 'label' => null])</span>
        <div class="me-auto">
            <h4 class="adm-title">Asignar roles y departamento</h4>
            <p class="adm-subtitle">Define qué puede hacer cada usuario y a qué área pertenece.</p>
        </div>
        <a href="{{ route('roles.permissions.edit') }}" class="btn btn-outline-primary btn-sm">
            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico me-1', 'label' => null]) Permisos por rol
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    <p class="adm-note mb-3">
        Cada usuario tiene un <strong>rol</strong> (qué puede hacer en la app) y un
        <strong>departamento</strong> (su área; define qué crew ve un HOD). El
        <code>super-admin</code> no se asigna aquí a propósito. Cambia los selectores y pulsa
        <strong>Guardar</strong> en esa fila.
    </p>

    {{-- Buscador EN VIVO (mismo patrón AJAX que el Crew List): al teclear se hace fetch a
         ?partial=1 y se reemplaza #rolesResults. El submit GET es respaldo sin-JS. --}}
    <form id="roleSearchForm" method="GET" action="{{ route('roles.index') }}" class="row g-2 align-items-center mb-3 adm-search">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="input-group input-group-sm">
                <input type="text" id="roleSearch" name="q" value="{{ $q }}" class="form-control"
                       placeholder="Buscar por nombre, apellido o email…" autocomplete="off" autofocus>
                <button class="btn btn-outline-primary" type="submit" aria-label="Buscar">
                    @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                </button>
            </div>
        </div>
        <div class="col-auto">
            <span id="roleSearchSpin" class="spinner-border spinner-border-sm text-secondary d-none" role="status" aria-hidden="true"></span>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0" id="rolesResults">
            @include('admin.partials.roles-assign-table')
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var input   = document.getElementById('roleSearch');
    var form    = document.getElementById('roleSearchForm');
    var results = document.getElementById('rolesResults');
    var spin    = document.getElementById('roleSearchSpin');
    if (!input || !results) return;

    var timer = null;
    var baseUrl = "{{ route('roles.index') }}";

    function runSearch() {
        if (spin) spin.classList.remove('d-none');
        fetch(baseUrl + '?partial=1&q=' + encodeURIComponent(input.value), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.text(); })
        .then(function (html) { results.innerHTML = html; })
        .catch(function () { /* si falla la red, se queda la última tabla */ })
        .finally(function () { if (spin) spin.classList.add('d-none'); });
    }

    // Teclear → buscar (con debounce, como el Crew List).
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 250);
    });

    // Enter no recarga la página: dispara la búsqueda en vivo (sin-JS, el GET es el respaldo).
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(timer);
            runSearch();
        });
    }
})();
</script>
@endpush
