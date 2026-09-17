@extends('layouts.app')
@section('content')

<div class="container py-4" style="max-width:900px">
    <div class="adm-header d-flex align-items-center gap-2">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico', 'label' => null])</span>
        <div class="flex-grow-1">
            <h1 class="adm-title">{{ $delivery->title }}</h1>
            <p class="adm-subtitle">Enviado {{ optional($delivery->created_at)->isoFormat('D MMM YYYY HH:mm') }} · marca de agua por persona</p>
        </div>
        <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="dl-chips mb-3" id="dlChips" data-pending="{{ $counts['pending'] }}">
        <span class="dl-chip dl-chip--sent"><b data-k="sent">{{ $counts['sent'] }}</b> enviados</span>
        <span class="dl-chip dl-chip--pending"><b data-k="pending">{{ $counts['pending'] }}</b> en cola</span>
        @if($counts['failed'] > 0)<span class="dl-chip dl-chip--failed"><b data-k="failed">{{ $counts['failed'] }}</b> con error</span>@endif
        <span class="dl-chip dl-chip--total"><b data-k="total">{{ $counts['total'] }}</b> en total</span>
    </div>

    <div class="card cc-card"><div class="table-responsive">
        <table class="table align-middle mb-0 cc-stack">
            <thead><tr><th>Persona</th><th>Correo</th><th class="text-center">Estado</th><th>Cuándo</th></tr></thead>
            <tbody>
            @foreach($recipients as $r)
                <tr data-id="{{ $r->id }}">
                    <td data-label="Persona">{{ $r->name ?: '—' }}</td>
                    <td data-label="Correo" class="text-muted">{{ $r->email }}</td>
                    <td data-label="Estado" class="text-center">
                        @if($r->status === 'sent')<span class="badge text-bg-success">Enviado</span>
                        @elseif($r->status === 'failed')<span class="badge text-bg-danger" title="{{ $r->error }}">Error</span>
                        @else<span class="badge text-bg-warning">En cola</span>@endif
                    </td>
                    <td data-label="Cuándo">{{ $r->sent_at ? $r->sent_at->isoFormat('D MMM HH:mm') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>

@if($counts['pending'] > 0)
<script>
(function () {
    var URL = @json(route('deliveries.state', $delivery->id));
    var box = document.getElementById('dlChips');
    var t = setInterval(function () {
        fetch(URL, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            ['sent', 'pending', 'failed', 'total'].forEach(function (k) {
                var el = box.querySelector('[data-k="' + k + '"]');
                if (el && typeof d[k] !== 'undefined') el.textContent = d[k];
            });
            if (d.pending === 0) { clearInterval(t); window.location.reload(); }
        }).catch(function () {});
    }, 5000);
})();
</script>
@endif

<style>
    .dl-chips { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .dl-chip { font-size:.85rem; padding:.3rem .7rem; border-radius:999px; border:1px solid var(--stroke); background:var(--glass); color:var(--text); }
    .dl-chip b { font-weight:800; }
    .dl-chip--sent { border-color:color-mix(in srgb, #22c55e 45%, var(--stroke)); background:color-mix(in srgb, #22c55e 10%, transparent); }
    .dl-chip--pending { border-color:color-mix(in srgb, #f59e0b 45%, var(--stroke)); background:color-mix(in srgb, #f59e0b 10%, transparent); }
    .dl-chip--failed { border-color:color-mix(in srgb, #ef4444 45%, var(--stroke)); background:color-mix(in srgb, #ef4444 10%, transparent); }
</style>
@endsection
