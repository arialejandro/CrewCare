@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

<div class="container py-4 cs-wrap">
    <div class="adm-header">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'layers', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Horario por departamento</h1>
            <p class="adm-subtitle">El valor sugerido es el general; ajusta el offset donde haya precall.</p>
        </div>
    </div>

    @include('admin.callsheet._tabs', ['active' => 'departments'])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(! $general)
        <div class="cs-note mb-3">Aún no hay llamado general para este día. Configúralo en <a href="{{ route('callsheet.config', ['date' => $nav['dateStr']]) }}">Configuración</a> para editar horas; mientras, sólo puedes fijar literales (O/C, D/C).</div>
    @endif

    <div class="cs-filters">
        <input type="text" id="cs-dept-search" class="form-control" placeholder="Buscar departamento…">
    </div>

    <form action="{{ route('callsheet.departments.save', ['date' => $nav['dateStr']]) }}" method="POST">
        @csrf
        @if(! count($depts))
            <div class="cs-note mb-3">Nadie llamado este día todavía; no hay departamentos que ajustar.</div>
        @endif
        <div class="cs-depts">
            @foreach($depts as $d)
                <div class="cs-dept" data-search="{{ \Illuminate\Support\Str::lower($d->name) }}">
                    <div class="cs-dept__name">
                        <span>{{ $d->name }}</span>
                        <span class="cs-dept__count">{{ $d->count }}</span>
                    </div>
                    <div class="cs-dept__radio">
                        <label for="cs-radio-{{ $d->id }}">Canal</label>
                        <input type="text" id="cs-radio-{{ $d->id }}" name="dept[{{ $d->id }}][radio]" value="{{ $d->radio }}" maxlength="80" placeholder="—">
                    </div>
                    <div class="cs-dept__row">
                        <input type="time" class="form-control cs-dept-time" name="dept[{{ $d->id }}][time]" value="{{ $d->time }}" {{ $general ? '' : 'disabled' }}>
                        <input type="text" class="form-control cs-dept-lit" name="dept[{{ $d->id }}][literal]" value="{{ $d->literal }}" placeholder="O/C" style="max-width:90px">
                    </div>
                    @if($general)
                        <div class="cs-quick">
                            <button type="button" data-set="{{ $general }}">= General ({{ $general }})</button>
                            <button type="button" data-clear>Limpiar</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end my-4">
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    // Buscador.
    var search = document.getElementById('cs-dept-search');
    search && search.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.cs-dept').forEach(function (c) {
            c.style.display = (!q || c.dataset.search.indexOf(q) !== -1) ? '' : 'none';
        });
    });
    // Accesos rápidos.
    document.querySelectorAll('.cs-dept').forEach(function (card) {
        var time = card.querySelector('.cs-dept-time'), lit = card.querySelector('.cs-dept-lit');
        card.querySelectorAll('.cs-quick button').forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.hasAttribute('data-clear')) { time.value = ''; lit.value = ''; }
                else { time.value = b.getAttribute('data-set'); lit.value = ''; }
            });
        });
    });
})();
</script>
@endpush
@endsection
