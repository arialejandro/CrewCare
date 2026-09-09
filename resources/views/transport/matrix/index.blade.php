@extends('layouts.app')
@include('componentes._confirm-submit')
@section('content')
{{-- FASE 1 · Puntos de pickup + matriz de traslado. Orígenes estables con coordenadas + el tiempo del
     PAR origen→destino (locación scouteada). CrewGeo (OSRM en el navegador) PROPONE; transpo CORRIGE;
     lo corregido PERSISTE por par. 100% aditivo: no toca la corrida ni el pick up existentes. --}}
@php
    $pointById = $points->keyBy('id');
    $destById  = $destinations->keyBy('id');
@endphp
<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Puntos y traslados') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El sistema propone el tiempo por la ruta; tú lo corriges y se queda para ese par.') }}</p>
            </div>
        </div>

        @if (session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        {{-- ===== A · PUNTOS DE PICKUP ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2">{{ __('Puntos de pickup') }}</h2>
        <div class="border rounded-3 p-3 bg-body-tertiary mb-3">
            <form method="POST" action="{{ route('transport.point.store') }}" class="row g-2 cc-point-form">
                @csrf
                <div class="col-md-4"><label class="form-label small mb-0">{{ __('Nombre') }}</label><input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" maxlength="120" placeholder="Churubusco"></div>
                <div class="col-md-5"><label class="form-label small mb-0">{{ __('Dirección') }}</label><input type="text" name="address" value="{{ old('address') }}" class="form-control form-control-sm" maxlength="255"></div>
                <div class="col-md-3"><label class="form-label small mb-0">{{ __('Orden') }}</label><input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" class="form-control form-control-sm"></div>
                <div class="col-md-3"><label class="form-label small mb-0">{{ __('Latitud') }}</label><input type="text" name="lat" value="{{ old('lat') }}" class="form-control form-control-sm" placeholder="19.3567"></div>
                <div class="col-md-3"><label class="form-label small mb-0">{{ __('Longitud') }}</label><input type="text" name="lng" value="{{ old('lng') }}" class="form-control form-control-sm" placeholder="-99.1620"></div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary js-geo-fill" title="{{ __('Usar mi ubicación') }}">📍 {{ __('Aquí') }}</button>
                    <button class="btn btn-sm btn-primary">{{ __('Guardar punto') }}</button>
                    <span class="small text-muted">{{ __('Pega lat/lng de un mapa, o toca 📍 si estás en el punto.') }}</span>
                </div>
            </form>
        </div>

        @forelse ($points as $p)
            <div class="border rounded-3 p-2 mb-2 d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                    <span class="fw-semibold">{{ $p->name }}</span>
                    @if ($p->hasGeo())<span class="badge bg-success-subtle text-dark border ms-1">{{ __('con coordenadas') }}</span>@else<span class="badge bg-warning text-dark ms-1">{{ __('sin coordenadas') }}</span>@endif
                    <div class="small text-muted">{{ $p->address ?: '—' }}@if($p->hasGeo()) · <span class="font-monospace">{{ $p->lat }}, {{ $p->lng }}</span>@endif</div>
                </div>
                <div class="d-flex gap-2">
                    <details>
                        <summary class="btn btn-sm btn-outline-secondary" style="list-style:none">{{ __('Editar') }}</summary>
                        <form method="POST" action="{{ route('transport.point.update', $p) }}" class="row g-1 mt-1 cc-point-form" style="min-width:280px">
                            @csrf
                            <div class="col-12"><input type="text" name="name" value="{{ $p->name }}" class="form-control form-control-sm" maxlength="120"></div>
                            <div class="col-12"><input type="text" name="address" value="{{ $p->address }}" class="form-control form-control-sm" maxlength="255" placeholder="{{ __('Dirección') }}"></div>
                            <div class="col-5"><input type="text" name="lat" value="{{ $p->lat }}" class="form-control form-control-sm" placeholder="lat"></div>
                            <div class="col-5"><input type="text" name="lng" value="{{ $p->lng }}" class="form-control form-control-sm" placeholder="lng"></div>
                            <div class="col-2"><input type="number" name="sort_order" value="{{ $p->sort_order }}" class="form-control form-control-sm" title="{{ __('Orden') }}"></div>
                            <div class="col-12 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary js-geo-fill">📍</button>
                                <button class="btn btn-sm btn-primary">{{ __('Guardar') }}</button>
                            </div>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('transport.point.destroy', $p) }}" data-confirm="{{ __('¿Dar de baja este punto?') }}">
                        @csrf<button class="btn btn-sm btn-outline-danger">{{ __('Baja') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-muted small">{{ __('Sin puntos todavía.') }}</p>
        @endforelse

        {{-- ===== B · MATRIZ DE TRASLADO ===== --}}
        <h2 class="h6 text-uppercase text-muted mb-2 mt-4">{{ __('Tiempos de traslado') }}</h2>
        @if ($points->isEmpty())
            <p class="text-muted small">{{ __('Primero captura al menos un punto de pickup.') }}</p>
        @elseif ($destinations->isEmpty())
            <p class="text-muted small">{{ __('No hay locaciones con coordenadas todavía (vienen del scouting).') }}</p>
        @else
            <div class="border rounded-3 p-3 mb-3">
                <form method="POST" action="{{ route('transport.time.save') }}" id="cc-time-form" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small mb-0">{{ __('Punto de origen') }}</label>
                        <select name="pickup_point_id" class="form-select form-select-sm cc-point" required>
                            @foreach ($points as $p)
                                <option value="{{ $p->id }}" data-lat="{{ $p->lat }}" data-lng="{{ $p->lng }}">{{ $p->name }}@unless($p->hasGeo()) ({{ __('sin coords') }})@endunless</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">{{ __('Destino (locación)') }}</label>
                        <select name="scouting_id" class="form-select form-select-sm cc-dest" required>
                            @foreach ($destinations as $d)
                                <option value="{{ $d->id }}" data-lat="{{ $d->latitude }}" data-lng="{{ $d->longitude }}">{{ $d->location_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">{{ __('Minutos') }}</label>
                        <input type="number" name="minutes" class="form-control form-control-sm cc-minutes" min="1" max="1440" required>
                        <input type="hidden" name="source" value="corrected" class="cc-source">
                    </div>
                    <div class="col-md-2 d-flex flex-column gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary cc-propose">{{ __('Proponer') }}</button>
                        <button class="btn btn-sm btn-primary">{{ __('Guardar') }}</button>
                    </div>
                    <div class="col-12"><span class="small text-muted cc-eta-hint"></span></div>
                </form>
            </div>

            @if ($times->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr class="text-muted small text-uppercase"><th>{{ __('Origen') }}</th><th>{{ __('Destino') }}</th><th>{{ __('Minutos') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($times as $t)
                                <tr>
                                    <td>{{ optional($pointById->get($t->pickup_point_id))->name ?? '—' }}</td>
                                    <td>{{ optional($destById->get($t->scouting_id))->location_name ?? ('#' . $t->scouting_id) }}</td>
                                    <td class="fw-semibold">{{ $t->minutes }}</td>
                                    <td>@if($t->isCorrected())<span class="badge bg-primary-subtle text-dark border">{{ __('corregido') }}</span>@else<span class="badge bg-light text-dark border">{{ __('propuesto') }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
    <style>details.d-inline summary::-webkit-details-marker{display:none}</style>
@endpush
@push('scripts')
    <script src="{{ asset('js/crewcare-geo.js') }}"></script>
    <script>
    window.__ccTimes = {!! json_encode($times->mapWithKeys(fn ($t, $k) => [$k => ['minutes' => $t->minutes, 'source' => $t->source]])) !!};
    (function () {
        // 📍 — llena lat/lng (y dirección si está vacía) desde el dispositivo, reusando CrewGeo.
        document.querySelectorAll('.js-geo-fill').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!window.CrewGeo) return;
                var form = btn.closest('form');
                btn.disabled = true;
                CrewGeo.locate().then(function (p) {
                    var la = form.querySelector('[name=lat]'), lo = form.querySelector('[name=lng]');
                    if (la) la.value = p.lat.toFixed(7);
                    if (lo) lo.value = p.lng.toFixed(7);
                    return CrewGeo.reverseGeocode(p.lat, p.lng);
                }).then(function (addr) {
                    var a = form.querySelector('[name=address]');
                    if (a && !a.value) a.value = addr;
                }).catch(function () {}).then(function () { btn.disabled = false; });
            });
        });

        // Matriz: proponer con la ruta real (OSRM); si transpo edita, pasa a corregido.
        var f = document.getElementById('cc-time-form');
        if (f) {
            var ps = f.querySelector('.cc-point'), ds = f.querySelector('.cc-dest'),
                mi = f.querySelector('.cc-minutes'), src = f.querySelector('.cc-source'),
                pr = f.querySelector('.cc-propose'), hint = f.querySelector('.cc-eta-hint');
            function ll(sel) { var o = sel.options[sel.selectedIndex]; return o ? { lat: parseFloat(o.getAttribute('data-lat')), lng: parseFloat(o.getAttribute('data-lng')) } : null; }
            function prefill() {
                var key = ps.value + '-' + ds.value, row = window.__ccTimes[key];
                if (row) { mi.value = row.minutes; src.value = row.source; hint.textContent = '{{ __('Guardado para este par.') }}'; }
                else { mi.value = ''; src.value = 'corrected'; hint.textContent = ''; }
            }
            ps.addEventListener('change', prefill);
            ds.addEventListener('change', prefill);
            prefill();
            if (pr) pr.addEventListener('click', function () {
                var a = ll(ps), b = ll(ds);
                if (!a || !b || isNaN(a.lat) || isNaN(b.lat)) { hint.textContent = '{{ __('Elige un punto con coordenadas y un destino.') }}'; return; }
                if (!window.CrewGeo) return;
                pr.disabled = true; hint.textContent = '{{ __('Calculando…') }}';
                CrewGeo.driveEta(a.lat, a.lng, b.lat, b.lng).then(function (r) {
                    mi.value = r.minutes; src.value = 'proposed';
                    hint.textContent = (r.estimated ? '{{ __('estimado') }}' : '{{ __('ruta real') }}') + ' · ' + r.km + ' km';
                    pr.disabled = false;
                }).catch(function () { hint.textContent = '{{ __('No se pudo calcular; captura el tiempo a mano.') }}'; pr.disabled = false; });
            });
            if (mi) mi.addEventListener('input', function () { src.value = 'corrected'; });
        }
    })();
    </script>
@endpush
@endsection
