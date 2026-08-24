@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

@php $general = $data['general']; @endphp

<div class="container-fluid py-4 cs-wrap" style="max-width:1180px">
    <div class="adm-header">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Horario por persona</h1>
            <p class="adm-subtitle">La misma gente del roster, en modo edición. Vacío = hereda su departamento.</p>
        </div>
    </div>

    @include('admin.callsheet._tabs', ['active' => 'people'])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(! $general)
        <div class="cs-note mb-3">Sin llamado general no hay horas que calcular. Ponlo en <a href="{{ route('callsheet.config', ['date' => $nav['dateStr']]) }}">Configuración</a>.</div>
    @endif

    <div class="cs-filters">
        <input type="text" id="cs-psearch" class="form-control" placeholder="Buscar por nombre, cargo o depto…">
        <select id="cs-dept-filter" class="form-select" style="max-width:240px">
            <option value="">Todos los departamentos</option>
            @foreach($data['groups'] as $g)
                @if(count($g['people']))<option value="{{ \Illuminate\Support\Str::lower($g['label']) }}">{{ $g['label'] }} ({{ count($g['people']) }})</option>@endif
            @endforeach
        </select>
        <span class="text-muted small ms-auto">Ajuste masivo:</span>
        <input type="time" id="cs-mass-time" class="form-control" style="max-width:130px" {{ $general ? '' : 'disabled' }}>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="cs-mass-apply">Aplicar a lo visible</button>
    </div>

    <form action="{{ route('callsheet.people.save', ['date' => $nav['dateStr']]) }}" method="POST">
        @csrf
        <datalist id="cs-hotel-codes">@foreach($hotelCodes as $hc)<option value="{{ $hc }}">@endforeach</datalist>
        <div class="cs-scroll">
            <table class="cs-ptable">
                <thead>
                    <tr>
                        <th>Puesto</th><th>Nombre</th><th>Estado</th>
                        <th>Llamado</th><th>Pick up</th><th>Lugar</th><th>Hotel</th>
                        <th title="Tenedor encendido = entra al conteo de comida" class="text-center">@include('componentes._icon', ['name' => 'utensils', 'class' => 'cc-ico', 'label' => 'Come'])</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['groups'] as $g)
                        @if(count($g['people']))
                            <tr class="cs-deptband"><td colspan="8">{{ $g['label'] }}</td></tr>
                            @foreach($g['people'] as $p)
                                @php
                                    $sched = $p['schedule']; $pickup = $p['pickup'];
                                    $ownTime = ($sched['source'] === 'person' && $sched['time']) ? $sched['time'] : '';
                                    $ownLit  = ($sched['source'] === 'person' && $sched['literal']) ? $sched['literal'] : '';
                                    $effective = $sched['literal'] ?? $sched['time'];
                                    $uid = $p['user_id'];
                                @endphp
                                <tr class="cs-prow" data-dept="{{ \Illuminate\Support\Str::lower($g['label']) }}" data-search="{{ \Illuminate\Support\Str::lower($p['name'] . ' ' . $p['cargo'] . ' ' . $g['label']) }}">
                                    <td>{{ $p['cargo'] }}</td>
                                    <td>{{ $p['name'] }}</td>
                                    <td><span class="small text-muted">{{ __(ucfirst(str_replace('_',' ',$p['state']))) }}</span></td>
                                    <td style="min-width:140px">
                                        <input type="time" class="form-control cs-sched" name="person[{{ $uid }}][sched_time]" value="{{ $ownTime }}" placeholder="{{ $effective }}" {{ $general ? '' : 'disabled' }}>
                                        <input type="text" class="form-control mt-1" name="person[{{ $uid }}][sched_literal]" value="{{ $ownLit }}" placeholder="O/C">
                                    </td>
                                    <td style="min-width:130px">
                                        <input type="time" class="form-control" name="person[{{ $uid }}][pickup_time]" value="{{ $pickup['time'] }}" {{ $general ? '' : 'disabled' }}>
                                        <input type="text" class="form-control mt-1" name="person[{{ $uid }}][pickup_literal]" value="{{ $pickup['literal'] }}" placeholder="N/A">
                                    </td>
                                    <td>
                                        <select class="form-select" name="person[{{ $uid }}][pickup_place_id]" style="min-width:90px">
                                            <option value="">—</option>
                                            @foreach($places as $pl)
                                                <option value="{{ $pl->id }}" @selected($pickup['place'] === $pl->code)>{{ $pl->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="person[{{ $uid }}][hotel]" value="{{ $p['hotel'] ?? '' }}" list="cs-hotel-codes" maxlength="24" placeholder="—" style="min-width:70px">
                                    </td>
                                    <td class="text-center">
                                        <label class="cs-fork" title="Entra al conteo de comida">
                                            <input type="checkbox" name="person[{{ $uid }}][meal]" value="1" @checked($p['meal_mark'])>
                                            @include('componentes._icon', ['name' => 'utensils', 'class' => 'cc-ico', 'label' => 'Come'])
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Nadie en el roster de este día.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end my-4">
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar
            </button>
        </div>
    </form>

    @if(count($dayPlayers))
        <div class="card cs-card">
            <div class="card-header">Day players con llamado hoy — sugeridos para Crew Adicional</div>
            <div class="card-body">
                <p class="form-text">En el back estos van al bloque Crew Adicional (no bajo su departamento).</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($dayPlayers as $dp)
                        <span class="badge rounded-pill text-bg-secondary">{{ $dp['name'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    var search = document.getElementById('cs-psearch'), deptFilter = document.getElementById('cs-dept-filter');
    function applyFilters() {
        var q = (search ? search.value : '').trim().toLowerCase(),
            dept = deptFilter ? deptFilter.value : '';
        document.querySelectorAll('.cs-prow').forEach(function (r) {
            var okQ = !q || r.dataset.search.indexOf(q) !== -1,
                okD = !dept || r.dataset.dept === dept;
            r.style.display = (okQ && okD) ? '' : 'none';
        });
        // Oculta bandas de depto sin filas visibles.
        document.querySelectorAll('.cs-deptband').forEach(function (band) {
            var vis = false, n = band.nextElementSibling;
            while (n && !n.classList.contains('cs-deptband')) { if (n.style.display !== 'none') vis = true; n = n.nextElementSibling; }
            band.style.display = vis ? '' : 'none';
        });
    }
    search && search.addEventListener('input', applyFilters);
    deptFilter && deptFilter.addEventListener('change', applyFilters);
    var mass = document.getElementById('cs-mass-apply'), massTime = document.getElementById('cs-mass-time');
    mass && mass.addEventListener('click', function () {
        if (!massTime.value) return;
        document.querySelectorAll('.cs-prow').forEach(function (r) {
            if (r.style.display === 'none') return;
            var inp = r.querySelector('.cs-sched');
            if (inp && !inp.disabled) inp.value = massTime.value;
        });
    });
})();
</script>
@endpush
@endsection
