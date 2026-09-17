@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

@php
    $pendingSignable = collect($signable)->filter(fn ($k) => ! (($sigByKey[$k] ?? null) && $sigByKey[$k]->signed_at))->values();
@endphp

<div class="container py-4 cs-wrap" style="max-width:900px">
    <div class="adm-header">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Firmar el llamado</h1>
            <p class="adm-subtitle">{{ ucfirst(\Carbon\Carbon::parse($day)->locale('es')->isoFormat('dddd, D MMMM')) }} · las firmas se muestran en vivo.</p>
        </div>
        <a href="{{ route('callsheet.package', ['date' => $nav['dateStr']]) }}" class="btn btn-outline-secondary btn-sm ms-auto">Volver al paquete</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mt-3">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mt-1">
        {{-- Estado en vivo --}}
        <div class="col-lg-5">
            <div class="card cs-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Estado</span>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.package.pdf', ['date' => $nav['dateStr']]) }}" target="_blank" rel="noopener">Ver paquete</a>
                </div>
                <div class="card-body">
                    <div class="pk-signers" id="pkState">
                        @foreach($signers as $sg)
                            @php $sig = $sigByKey[$sg['key']] ?? null; $signed = $sig && $sig->signed_at; @endphp
                            <div class="pk-signer {{ $signed ? 'is-signed' : '' }}" data-key="{{ $sg['key'] }}">
                                <span class="pk-signer__ico">@include('componentes._icon', ['name' => $signed ? 'check-circle' : 'pencil', 'class' => 'cc-ico', 'label' => null])</span>
                                <div class="pk-signer__body">
                                    <div class="pk-signer__role">{{ $sg['role_label'] }}</div>
                                    <div class="pk-signer__name">{{ $sg['name'] ?: '—' }}</div>
                                </div>
                                <span class="pk-signer__state">{{ $signed ? ('Firmó ' . $sig->signed_at->isoFormat('HH:mm')) : 'Pendiente' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Firma de esta persona --}}
        <div class="col-lg-7">
            <div class="card cs-card">
                <div class="card-header">Tu firma</div>
                <div class="card-body">
                    @if($pendingSignable->isEmpty())
                        <p class="cs-note">No tienes firmas pendientes aquí. Esta pantalla se actualiza sola cuando las demás firmen.</p>
                    @else
                        <form action="{{ route('callsheet.package.sign.do', ['date' => $nav['dateStr']]) }}" method="POST">
                            @csrf
                            @if($pendingSignable->count() > 1)
                                <label class="form-label">Firmas como</label>
                                <select name="signer_key" class="form-select mb-3" style="max-width:360px">
                                    @foreach($pendingSignable as $k)
                                        @php $sg = collect($signers)->firstWhere('key', $k); @endphp
                                        <option value="{{ $k }}">{{ $sg['role_label'] ?? $k }}</option>
                                    @endforeach
                                </select>
                            @else
                                @php $sg = collect($signers)->firstWhere('key', $pendingSignable->first()); @endphp
                                <input type="hidden" name="signer_key" value="{{ $pendingSignable->first() }}">
                                <p class="mb-2">Firmas como <b>{{ $sg['role_label'] ?? '' }}</b>.</p>
                            @endif

                            @include('componentes._signature-pad', ['name' => 'signature_image', 'adopted' => $adopted, 'label' => 'Dibuja o escribe tu firma'])

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary">
                                    @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) Firmar
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pk-signers { display:flex; flex-direction:column; gap:.5rem; }
    .pk-signer { display:flex; align-items:center; gap:.7rem; padding:.6rem .85rem; border:1px solid var(--stroke); border-radius:11px; background:var(--glass); }
    .pk-signer.is-signed { border-color:color-mix(in srgb, #22c55e 45%, var(--stroke)); background:color-mix(in srgb, #22c55e 8%, var(--glass)); }
    .pk-signer__ico { color:var(--text-muted); }
    .pk-signer.is-signed .pk-signer__ico { color:#22c55e; }
    .pk-signer__body { flex:1; min-width:0; }
    .pk-signer__role { font-weight:700; color:var(--text); font-size:.92rem; }
    .pk-signer__name { color:var(--text-muted); font-size:.83rem; }
    .pk-signer__state { font-size:.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.03em; }
    .pk-signer.is-signed .pk-signer__state { color:#16a34a; }
</style>

@push('scripts')
<script>
(function () {
    var URL = @json(route('callsheet.package.state', ['date' => $nav['dateStr']]));
    var PKG = @json(route('callsheet.package', ['date' => $nav['dateStr']]));
    function poll() {
        fetch(URL, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            (d.signers || []).forEach(function (s) {
                var row = document.querySelector('#pkState .pk-signer[data-key="' + s.key + '"]');
                if (!row) return;
                row.classList.toggle('is-signed', s.signed);
                var st = row.querySelector('.pk-signer__state');
                if (st) st.textContent = s.signed ? ('Firmó ' + (s.at || '')) : 'Pendiente';
            });
            if (d.all_signed || d.status === 'approved') { window.location = PKG; }
        }).catch(function () {});
    }
    setInterval(poll, 4000);
})();
</script>
@endpush
@endsection
