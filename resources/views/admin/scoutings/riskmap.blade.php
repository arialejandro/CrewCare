@extends('layouts.app')
@section('content')

@push('styles')
<style>
    /* ===== Mapeo de riesgos — sección aparte y editable del scouting ===== */
    .rm-page { max-width: 1000px; }
    .rm-head { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
    .rm-head .rm-grow { flex:1 1 auto; min-width:0; }
    .rm-head h1 { font-size:1.25rem; font-weight:700; margin:0; }
    .rm-sub { color:var(--text-muted,#6c757d); font-size:.9rem; }

    .rm-panel { background:var(--surface,#fff); border:1px solid var(--border,#dee2e6); border-radius:14px; padding:1rem; margin-bottom:1.25rem; }
    .rm-panel h2 { font-size:1rem; font-weight:700; margin:0 0 .6rem; display:flex; align-items:center; gap:.5rem; }

    .rm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(230px,1fr)); gap:1rem; margin-bottom:1.25rem; }
    .rm-card { border:1px solid var(--border,#dee2e6); border-radius:12px; overflow:hidden; background:var(--surface,#fff); display:flex; flex-direction:column; }
    .rm-thumb { aspect-ratio:4/3; background:var(--surface-2,#f3f4f6); overflow:hidden; }
    .rm-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .rm-body { padding:.6rem .7rem; display:flex; flex-direction:column; gap:.35rem; }
    .rm-badge { align-self:flex-start; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
        background:color-mix(in srgb, var(--brand-primary,#0e6f6c) 15%, transparent); color:var(--brand-primary,#0e6f6c);
        padding:.15rem .5rem; border-radius:20px; }
    .rm-name { font-weight:600; font-size:.92rem; word-break:break-word; }
    .rm-actions { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.2rem; }
    .rm-actions .btn { --bs-btn-padding-y:.25rem; --bs-btn-padding-x:.5rem; font-size:.78rem; }
    .rm-edit { margin-top:.4rem; }
    .rm-edit > summary { cursor:pointer; font-size:.8rem; color:var(--brand-primary,#0e6f6c); list-style:none; }
    .rm-edit > summary::-webkit-details-marker { display:none; }
    .rm-edit .row-g { display:flex; flex-direction:column; gap:.4rem; margin-top:.5rem; }
    .rm-min { min-height:44px; }

    .rm-marked { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:.6rem; }
    .rm-marked figure { margin:0; border:1px solid var(--border,#dee2e6); border-radius:10px; overflow:hidden; background:var(--surface,#fff); }
    .rm-marked img { width:100%; aspect-ratio:4/3; object-fit:cover; display:block; }
    .rm-marked figcaption { font-size:.75rem; color:var(--text-muted,#6c757d); padding:.3rem .45rem; }

    .rm-print-title { display:none; }

    @media print {
        .no-print { display:none !important; }
        .rm-print-title { display:block; margin-bottom:14px; }
        .rm-print-title h1 { font-size:1.3rem; margin:0; }
        .rm-page { max-width:none; }
        .rm-grid, .rm-marked { grid-template-columns:repeat(2,1fr); gap:12px; }
        .rm-card, .rm-marked figure { break-inside:avoid; border-color:#ccc; }
    }
</style>
@endpush

<div class="container py-3 rm-page">

    <div class="rm-head no-print">
        <a href="{{ route('riskmaps.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
            @include('componentes._icon', ['name' => 'arrow-left', 'class' => 'cc-ico'])
            <span>Todos los mapeos</span>
        </a>
        <div class="rm-grow">
            <h1>Mapeo de riesgos</h1>
            <div class="rm-sub">{{ $scouting->location_name ?: 'Locación' }}</div>
        </div>
        <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" onclick="window.print()">
            @include('componentes._icon', ['name' => 'printer', 'class' => 'cc-ico'])
            <span>Imprimir / PDF</span>
        </button>
    </div>

    {{-- Encabezado sólo para la impresión (el PDF sale limpio, sin la barra de acciones). --}}
    <div class="rm-print-title">
        <h1>Mapeo de riesgos</h1>
        <div class="rm-sub">{{ $scouting->location_name ?: 'Locación' }}</div>
    </div>

    @if(session('success'))<div class="alert alert-success py-2 no-print">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-2 no-print">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger py-2 no-print"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- ---- Agregar imagen ---- --}}
    <div class="rm-panel no-print">
        <h2>@include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico']) <span>Agregar imagen</span></h2>
        <form method="POST" action="{{ route('riskmaps.images.store', $scouting->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label class="form-label mb-1">Tipo <span class="text-danger">*</span></label>
                    <select name="type" class="form-select rm-min" required>
                        @foreach($types as $val => $label)
                            <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label mb-1">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control rm-min" maxlength="120" required placeholder="Ej. Planta baja, Acceso norte" value="{{ old('name') }}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label mb-1">Imagen <span class="text-danger">*</span></label>
                    <input type="file" name="image" class="form-control rm-min" accept="image/*" data-cc-photo required>
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary w-100 rm-min">Agregar</button>
                </div>
            </div>
        </form>
    </div>

    {{-- ---- Galería del mapeo ---- --}}
    @if($images->count())
        <div class="rm-grid">
            @foreach($images as $im)
                <div class="rm-card">
                    <div class="rm-thumb">
                        @if($im->imageUrl() !== '')<img src="{{ $im->imageUrl() }}" alt="{{ $im->name }}">@endif
                    </div>
                    <div class="rm-body">
                        <span class="rm-badge">{{ $im->typeLabel() }}</span>
                        <span class="rm-name">{{ $im->name }}</span>

                        <div class="rm-actions no-print">
                            <form method="POST" action="{{ route('riskmaps.images.destroy', [$scouting->id, $im->id]) }}"
                                  onsubmit="return confirm('¿Quitar esta imagen del mapeo?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-1">
                                    @include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico'])
                                    <span>Quitar</span>
                                </button>
                            </form>
                        </div>

                        <details class="rm-edit no-print">
                            <summary>Editar</summary>
                            <form method="POST" action="{{ route('riskmaps.images.update', [$scouting->id, $im->id]) }}" enctype="multipart/form-data">
                                @csrf @method('PUT')
                                <div class="row-g">
                                    <div>
                                        <label class="form-label mb-1 small">Tipo</label>
                                        <select name="type" class="form-select form-select-sm">
                                            @foreach($types as $val => $label)
                                                <option value="{{ $val }}" {{ $im->type === $val ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1 small">Nombre</label>
                                        <input type="text" name="name" class="form-control form-control-sm" maxlength="120" value="{{ $im->name }}">
                                    </div>
                                    <div>
                                        <label class="form-label mb-1 small">Reemplazar imagen (opcional)</label>
                                        <input type="file" name="image" class="form-control form-control-sm" accept="image/*" data-cc-photo>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                                </div>
                            </form>
                        </details>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rm-panel no-print text-center">
            <p class="mb-1">Aún no agregas imágenes al mapeo.</p>
            <p class="rm-sub mb-0">Sube un plano, una satelital, un dron shot o una foto. Si no tienes más, el mapeo se arma con las fotos del scouting marcadas abajo.</p>
        </div>
    @endif

    {{-- ---- Puente: fotos del scouting marcadas como "Mapeo de riesgos" ---- --}}
    @if(count($scoutingMarked))
        <div class="rm-panel">
            <h2>@include('componentes._icon', ['name' => 'image', 'class' => 'cc-ico']) <span>Del scouting (marcadas)</span></h2>
            <p class="rm-sub no-print mb-2">Estas vienen del scouting (check "Mapeo de riesgos" en Imágenes). Se editan allá.</p>
            <div class="rm-marked">
                @foreach($scoutingMarked as $mi)
                    <figure>
                        <img src="{{ $mi['path'] }}" loading="lazy" alt="{{ $mi['caption'] ?: 'Imagen del scouting' }}">
                        @if(!empty($mi['caption']))<figcaption>{{ $mi['caption'] }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
        </div>
    @endif

</div>

@push('scripts')
<script src="{{ asset('js/cc-photo.js') }}?v=1"></script>
<script>
    // Comprime/convierte HEIC en el navegador antes de subir (modelo único CCPhoto),
    // swap del archivo vía DataTransfer. Si no se puede, sube el original.
    (function () {
        var canDT = false; try { new DataTransfer(); canDT = true; } catch (e) { canDT = false; }
        if (!canDT || !window.CCPhoto) return;
        document.querySelectorAll('input[type="file"][data-cc-photo]').forEach(function (inp) {
            var busy = false;
            inp.addEventListener('change', function () {
                if (busy) return;
                var f = inp.files && inp.files[0];
                if (!f) return;
                window.CCPhoto.process(f).then(function (out) {
                    if (out === f) return;
                    try { var dt = new DataTransfer(); dt.items.add(out); busy = true; inp.files = dt.files; busy = false; } catch (e) {}
                });
            });
        });
    })();
</script>
@endpush
@endsection
