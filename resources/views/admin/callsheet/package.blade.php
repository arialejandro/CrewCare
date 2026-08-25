@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

@php
    use App\Models\CallPackage;
    $ds = $nav['dateStr'];
    $hasFront = $pkg->front_path;
    $statusMap = [
        CallPackage::DRAFT    => ['Borrador', 'secondary'],
        CallPackage::PENDING  => ['Pendiente de firma', 'warning'],
        CallPackage::APPROVED => ['Aprobado', 'success'],
        CallPackage::CHANGED  => ['Con cambios', 'warning'],
    ];
    [$stLabel, $stColor] = $statusMap[$pkg->status] ?? ['Borrador', 'secondary'];
@endphp

<div class="container py-4 cs-wrap">
    <div class="adm-header">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Paquete del llamado</h1>
            <p class="adm-subtitle">Une el front (del 2nd AD) con el back, fírmalo y envíalo — o solo descarga el back.</p>
        </div>
    </div>

    @include('admin.callsheet._tabs', ['active' => 'package'])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="text-muted small text-uppercase" style="letter-spacing:.05em">Estado</span>
        <span class="badge text-bg-{{ $stColor }}">{{ $stLabel }}</span>
        @if($pkg->approved_at)<span class="text-muted small">· aprobado {{ $pkg->approved_at->isoFormat('D MMM HH:mm') }}</span>@endif
    </div>

    @if($pkg->status === CallPackage::CHANGED && ($changedCount ?? 0) > 0)
        <div class="alert alert-warning">
            @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico me-1', 'label' => null])
            Cambiaron <b>{{ $changedCount }}</b> horario(s) después de aprobar — se resaltan en <span style="color:#1d4ed8;font-weight:700">azul</span> en el back.
            <b>Reabre</b> y vuelve a firmar para re-aprobar la versión nueva.
        </div>
    @endif

    <div class="pk-grid">
        {{-- 1 · FRONT --}}
        <div class="card cs-card">
            <div class="card-header"><span class="pk-step">1</span> Front del llamado <span class="text-muted fw-normal" style="font-size:.82rem">· lo arma el 2nd AD (Scenechronize)</span></div>
            <div class="card-body">
                @if($hasFront)
                    <div class="pk-file">
                        <span class="pk-file__ico">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])</span>
                        <div class="pk-file__body">
                            <div class="pk-file__name">Front subido</div>
                            <div class="pk-file__meta">{{ $pkg->front_pages ? $pkg->front_pages . ' página' . ($pkg->front_pages === 1 ? '' : 's') : 'PDF' }}</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <label class="btn btn-sm btn-outline-secondary mb-0">
                            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico me-1', 'label' => null]) Reemplazar
                            <input type="file" accept="application/pdf" class="d-none" onchange="this.form.submit()" form="pk-front-form" name="front">
                        </label>
                        <form action="{{ route('callsheet.package.front.remove', ['date' => $ds]) }}" method="POST" onsubmit="return confirm('¿Quitar el front?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Quitar</button>
                        </form>
                    </div>
                    <form id="pk-front-form" action="{{ route('callsheet.package.front', ['date' => $ds]) }}" method="POST" enctype="multipart/form-data" class="d-none">@csrf</form>
                @else
                    <p class="cs-note mb-3">Sube el PDF del front. Se unirá con el back en un solo documento.</p>
                    <form action="{{ route('callsheet.package.front', ['date' => $ds]) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                        @csrf
                        <input type="file" name="front" accept="application/pdf" class="form-control" required style="max-width:340px">
                        <button class="btn btn-primary btn-sm">
                            @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Subir front
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- 2 · BACK --}}
        <div class="card cs-card">
            <div class="card-header"><span class="pk-step">2</span> Back (crew list) <span class="text-muted fw-normal" style="font-size:.82rem">· se genera solo del roster</span></div>
            <div class="card-body">
                <p class="cs-note mb-3">El back sale del roster y el formato de este día. Se une automáticamente al front.</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.back', ['date' => $ds]) }}" target="_blank" rel="noopener">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico me-1', 'label' => null]) Ver back
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.package.back', ['date' => $ds]) }}">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico me-1', 'label' => null]) Descargar back (para Scenechronize)
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- 2.5 · DOCUMENTOS ADICIONALES (flag callsheet_extra_docs) --}}
    @if($extraEnabled)
    <div class="card cs-card">
        <div class="card-header"><span class="pk-step">+</span> Documentos adicionales <span class="text-muted fw-normal" style="font-size:.82rem">· se unen después del back</span></div>
        <div class="card-body">
            @if(count($extraDocs))
                <div class="pk-signers mb-3">
                    @foreach($extraDocs as $i => $doc)
                        <div class="pk-file">
                            <span class="pk-file__ico">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])</span>
                            <div class="pk-file__body flex-grow-1">
                                <div class="pk-file__name">{{ $doc['name'] ?? ('Adicional ' . ($i + 1)) }}</div>
                                <div class="pk-file__meta">{{ !empty($doc['pages']) ? $doc['pages'] . ' página' . ($doc['pages'] === 1 ? '' : 's') : 'PDF' }}</div>
                            </div>
                            <form action="{{ route('callsheet.package.extra.remove', ['date' => $ds]) }}" method="POST" onsubmit="return confirm('¿Quitar este adjunto?')">
                                @csrf<input type="hidden" name="i" value="{{ $i }}">
                                <button class="btn btn-sm btn-outline-danger">Quitar</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
            <form action="{{ route('callsheet.package.extra', ['date' => $ds]) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                @csrf
                <input type="file" name="extra" accept="application/pdf" class="form-control" required style="max-width:340px">
                <button class="btn btn-primary btn-sm">
                    @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Agregar PDF adicional
                </button>
            </form>
        </div>
    </div>
    @endif

    {{-- 3 · PAQUETE UNIDO --}}
    <div class="card cs-card">
        <div class="card-header"><span class="pk-step">3</span> Paquete unido <span class="text-muted fw-normal" style="font-size:.82rem">· front + back en un PDF</span></div>
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="cs-note flex-grow-1" style="border:0;background:transparent;padding:0">
                @if($hasFront)
                    El paquete son <b>2 documentos en uno</b>: primero el front, después el back.
                @else
                    Aún no hay front — por ahora el paquete es solo el back. Sube el front arriba para unirlos.
                @endif
            </div>
            <a class="btn btn-primary" href="{{ route('callsheet.package.pdf', ['date' => $ds]) }}" target="_blank" rel="noopener">
                @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico me-1', 'label' => null]) Ver paquete (PDF)
            </a>
        </div>
    </div>

    {{-- 4 · APROBACIÓN (firmas) — el firmar se habilita en la siguiente fase --}}
    <div class="card cs-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><span class="pk-step">4</span> Aprobación <span class="text-muted fw-normal" style="font-size:.82rem">· firman las 3 figuras (sobre el front)</span></span>
            <span class="d-flex gap-2 flex-wrap">
                @if($pkg->status === CallPackage::DRAFT)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.format') }}">
                        @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico me-1', 'label' => null]) Quién aprueba
                    </a>
                @endif
                @if($hasFront)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.package.layout', ['date' => $ds]) }}">
                        @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico me-1', 'label' => null]) Colocar firmas
                    </a>
                @endif
            </span>
        </div>
        <div class="card-body">
            @if($hasFront && empty($pkg->sign_field_map))
                <div class="cs-note mb-3">Primero <a href="{{ route('callsheet.package.layout', ['date' => $ds]) }}">coloca dónde firma cada figura</a> sobre el front (solo la 1ª vez; se recuerda).</div>
            @endif
            <div class="pk-signers" id="pkState">
                @foreach($signers as $sg)
                    @php $sig = $sigByKey[$sg['key']] ?? null; $signed = $sig && $sig->signed_at; @endphp
                    <div class="pk-signer {{ $signed ? 'is-signed' : '' }}" data-key="{{ $sg['key'] }}">
                        <span class="pk-signer__ico">@include('componentes._icon', ['name' => $signed ? 'check-circle' : 'pencil', 'class' => 'cc-ico', 'label' => null])</span>
                        <div class="pk-signer__body">
                            <div class="pk-signer__role">{{ $sg['role_label'] }}</div>
                            <div class="pk-signer__name">
                                @if($sg['name'])
                                    {{ $sg['name'] }}
                                @elseif($pkg->status === CallPackage::DRAFT)
                                    <a href="{{ route('callsheet.format') }}">Sin asignar — elegir</a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <span class="pk-signer__state">{{ $signed ? ('Firmó ' . $sig->signed_at->isoFormat('HH:mm')) : 'Pendiente' }}</span>
                    </div>
                @endforeach
            </div>

            <div class="d-flex gap-2 flex-wrap mt-3">
                @if($pkg->status === CallPackage::DRAFT && $hasFront && ! empty($pkg->sign_field_map))
                    <form action="{{ route('callsheet.package.send', ['date' => $ds]) }}" method="POST">
                        @csrf
                        <button class="btn btn-primary btn-sm">
                            @include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico me-1', 'label' => null]) Enviar a aprobación
                        </button>
                    </form>
                @endif
                @if(in_array($pkg->status, [CallPackage::PENDING, CallPackage::CHANGED], true))
                    <a class="btn btn-primary btn-sm" href="{{ route('callsheet.package.sign', ['date' => $ds]) }}">
                        @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico me-1', 'label' => null]) Ir a firmar
                    </a>
                @endif
                @if(in_array($pkg->status, [CallPackage::PENDING, CallPackage::CHANGED, CallPackage::APPROVED], true))
                    <form action="{{ route('callsheet.package.reopen', ['date' => $ds]) }}" method="POST" onsubmit="return confirm('¿Reabrir? Se borran las firmas puestas.')">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm">Reabrir para corregir</button>
                    </form>
                @endif
            </div>

            @unless($hasFront)
                <p class="cs-note mt-3">Sube el front para poder colocar y recoger las firmas.</p>
            @endunless
        </div>
    </div>

    {{-- 5 · ENVÍO --}}
    <div class="card cs-card">
        <div class="card-header"><span class="pk-step">5</span> Envío <span class="text-muted fw-normal" style="font-size:.82rem">· al crew llamado, con marca de agua por persona</span></div>
        <div class="card-body">
            @if($pkg->status === CallPackage::APPROVED)
                @php $c = $deliveryCounts; $alreadySent = $delivery !== null; @endphp

                {{-- Estado del envío en vivo (si ya se envió alguna vez) --}}
                @if($alreadySent)
                    <div class="pk-delivery mb-3" id="pkDelivery" data-pending="{{ $c['pending'] }}">
                        <div class="pk-delivery__row">
                            <span class="pk-chip pk-chip--sent"><b data-k="sent">{{ $c['sent'] }}</b> enviados</span>
                            <span class="pk-chip pk-chip--pending"><b data-k="pending">{{ $c['pending'] }}</b> en cola</span>
                            @if($c['failed'] > 0)<span class="pk-chip pk-chip--failed"><b data-k="failed">{{ $c['failed'] }}</b> con error</span>@endif
                            <span class="pk-chip pk-chip--total"><b data-k="total">{{ $c['total'] }}</b> en total</span>
                        </div>
                        <div class="pk-delivery__note" data-k="note">
                            @if($c['pending'] > 0)
                                Enviando en segundo plano — el resto sale solo en unos minutos.
                            @else
                                Envío completo. Cada quien recibió su copia con su nombre marcado.
                            @endif
                        </div>
                    </div>
                @endif

                <div class="cs-note mb-3" style="border:0;background:transparent;padding:0">
                    Paquete <b>aprobado y congelado</b>. Cada copia sale marcada en diagonal con el <b>nombre en créditos</b> de quien la recibe (trazable, no reenviable).
                    @if($crewCount !== null)<br><span class="text-muted small">{{ $crewCount }} persona(s) del crew llamado.</span>@endif
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <a class="btn btn-outline-secondary" href="{{ route('callsheet.package.pdf', ['date' => $ds]) }}" target="_blank" rel="noopener">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico me-1', 'label' => null]) Paquete aprobado
                    </a>
                    <form action="{{ route('callsheet.package.send.crew', ['date' => $ds]) }}" method="POST"
                          onsubmit="return confirm('{{ $alreadySent ? '¿Reenviar el llamado al crew? Se genera un nuevo envío.' : '¿Enviar el paquete a todo el crew llamado?' }}')">
                        @csrf
                        <button class="btn btn-primary">
                            @include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico me-1', 'label' => null]) {{ $alreadySent ? 'Reenviar al crew' : 'Enviar al crew' }}
                        </button>
                    </form>
                </div>
            @else
                <div class="cs-note" style="border:0;background:transparent;padding:0">El envío se habilita cuando el paquete está <b>aprobado</b> (las 3 firmas).</div>
            @endif
        </div>
    </div>
</div>

@if(in_array($pkg->status, [CallPackage::PENDING, CallPackage::CHANGED], true))
<script>
(function () {
    var URL = @json(route('callsheet.package.state', ['date' => $ds]));
    setInterval(function () {
        fetch(URL, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            (d.signers || []).forEach(function (s) {
                var row = document.querySelector('#pkState .pk-signer[data-key="' + s.key + '"]');
                if (!row) return;
                row.classList.toggle('is-signed', s.signed);
                var st = row.querySelector('.pk-signer__state');
                if (st) st.textContent = s.signed ? ('Firmó ' + (s.at || '')) : 'Pendiente';
            });
            if (d.status === 'approved') { window.location.reload(); }
        }).catch(function () {});
    }, 5000);
})();
</script>
@endif

@if($delivery && $deliveryCounts && $deliveryCounts['pending'] > 0)
<script>
(function () {
    var URL = @json(route('callsheet.package.delivery.state', ['date' => $ds]));
    var box = document.getElementById('pkDelivery');
    if (!box) return;
    var t = setInterval(function () {
        fetch(URL, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.exists) return;
            ['sent', 'pending', 'failed', 'total'].forEach(function (k) {
                var el = box.querySelector('[data-k="' + k + '"]');
                if (el && typeof d[k] !== 'undefined') el.textContent = d[k];
            });
            var note = box.querySelector('[data-k="note"]');
            if (d.pending === 0) {
                if (note) note.textContent = 'Envío completo. Cada quien recibió su copia con su nombre marcado.';
                clearInterval(t);
            }
        }).catch(function () {});
    }, 5000);
})();
</script>
@endif

<style>
    .pk-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
    @media (max-width:820px){ .pk-grid { grid-template-columns:1fr; } }
    .pk-step { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%;
        background:var(--brand-primary); color:var(--brand-on-primary); font-size:.72rem; font-weight:700; margin-right:.35rem; }
    .pk-file { display:flex; align-items:center; gap:.7rem; padding:.7rem .9rem; border:1px solid var(--stroke); border-radius:12px; background:var(--glass); }
    .pk-file__ico { color:var(--brand-primary); }
    .pk-file__name { font-weight:700; color:var(--text); }
    .pk-file__meta { color:var(--text-muted); font-size:.82rem; }
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
    .pk-delivery { padding:.8rem 1rem; border:1px solid var(--stroke); border-radius:12px; background:var(--glass); }
    .pk-delivery__row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .pk-delivery__note { color:var(--text-muted); font-size:.83rem; margin-top:.5rem; }
    .pk-chip { font-size:.82rem; padding:.28rem .6rem; border-radius:999px; border:1px solid var(--stroke); background:var(--surface-2, var(--glass)); color:var(--text); }
    .pk-chip b { font-weight:800; }
    .pk-chip--sent { border-color:color-mix(in srgb, #22c55e 45%, var(--stroke)); background:color-mix(in srgb, #22c55e 10%, transparent); }
    .pk-chip--pending { border-color:color-mix(in srgb, #f59e0b 45%, var(--stroke)); background:color-mix(in srgb, #f59e0b 10%, transparent); }
    .pk-chip--failed { border-color:color-mix(in srgb, #ef4444 45%, var(--stroke)); background:color-mix(in srgb, #ef4444 10%, transparent); }
</style>
@endsection
