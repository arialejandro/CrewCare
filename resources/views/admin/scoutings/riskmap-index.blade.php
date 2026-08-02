@extends('layouts.app')
@section('content')

@push('styles')
<style>
    /* ===== Mapeo de riesgos — índice del módulo (por locación) ===== */
    .rmx-page { max-width: 900px; }
    .rmx-head { margin-bottom: 1rem; }
    .rmx-head h1 { font-size: 1.35rem; font-weight: 700; margin: 0; }
    .rmx-sub { color: var(--text-muted,#6c757d); font-size: .9rem; margin-top: .15rem; }

    .rmx-filter { position: relative; margin-bottom: 1rem; }
    .rmx-filter input { min-height: 46px; }

    .rmx-list { display: flex; flex-direction: column; gap: .6rem; }
    .rmx-row { display: flex; align-items: center; gap: .9rem; flex-wrap: wrap;
        border: 1px solid var(--border,#dee2e6); border-radius: 12px; background: var(--surface,#fff);
        padding: .85rem 1rem; text-decoration: none; color: var(--text,#14181f);
        transition: border-color .12s ease, box-shadow .12s ease; }
    .rmx-row:hover { border-color: var(--brand-primary,#0e6f6c); box-shadow: 0 2px 10px rgba(0,0,0,.06); }
    .rmx-loc { font-weight: 700; font-size: 1rem; flex: 1 1 220px; min-width: 0; word-break: break-word; }
    .rmx-meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    .rmx-date { font-size: .82rem; color: var(--text-muted,#6c757d); }
    .rmx-badge { font-size: .72rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px;
        background: color-mix(in srgb, var(--brand-primary,#0e6f6c) 14%, transparent); color: var(--brand-primary,#0e6f6c); white-space: nowrap; }
    .rmx-badge.alt { background: var(--surface-2,#f1f3f5); color: var(--text-muted,#6c757d); }
    .rmx-badge.empty { background: var(--surface-2,#f1f3f5); color: var(--text-muted,#9aa3af); }
    .rmx-open { font-size: .82rem; font-weight: 600; color: var(--brand-primary,#0e6f6c); white-space: nowrap; }
    .rmx-empty { text-align: center; color: var(--text-muted,#6c757d); padding: 2rem 1rem; }
    .rmx-none { display: none; text-align: center; color: var(--text-muted,#6c757d); padding: 1rem; }
</style>
@endpush

<div class="container py-3 rmx-page">

    <div class="rmx-head">
        <h1>Mapeo de riesgos</h1>
        <div class="rmx-sub">Todos los mapeos por locación. Abre uno para agregar plano, satelital, dron o fotos y exportarlo a PDF.</div>
    </div>

    @if(count($rows))
        <div class="rmx-filter">
            <input type="text" id="rmx-q" class="form-control" placeholder="Filtrar por locación…" autocomplete="off" aria-label="Filtrar por locación">
        </div>

        <div class="rmx-list" id="rmx-list">
            @foreach($rows as $r)
                @php $total = $r['images'] + $r['flagged']; @endphp
                <a href="{{ route('riskmaps.show', $r['id']) }}" class="rmx-row" data-loc="{{ \Illuminate\Support\Str::lower($r['location'] ?? '') }}">
                    <span class="rmx-loc">{{ $r['location'] ?: 'Sin nombre de locación' }}</span>
                    <span class="rmx-meta">
                        @if($r['date'])<span class="rmx-date">{{ $r['date'] }}</span>@endif
                        @if($total === 0)
                            <span class="rmx-badge empty">Vacío</span>
                        @else
                            <span class="rmx-badge">{{ $r['images'] }} {{ $r['images'] === 1 ? 'imagen' : 'imágenes' }}</span>
                            @if($r['flagged'] > 0)<span class="rmx-badge alt">{{ $r['flagged'] }} del scouting</span>@endif
                        @endif
                    </span>
                    <span class="rmx-open">Abrir →</span>
                </a>
            @endforeach
        </div>
        <div class="rmx-none" id="rmx-none">Sin coincidencias.</div>
    @else
        <div class="rmx-empty">
            <p class="mb-1">Aún no hay locaciones para mapear.</p>
            <p class="rmx-sub mb-0">Crea un scouting; su locación aparecerá aquí para armar su mapeo de riesgos.</p>
        </div>
    @endif

</div>

@push('scripts')
<script>
    // Filtro por locación (acento-insensible, por code-point para no depender de regex frágil).
    (function () {
        var q = document.getElementById('rmx-q');
        var list = document.getElementById('rmx-list');
        if (!q || !list) return;
        function norm(s) {
            s = (s == null ? '' : s.toString()).toLowerCase().normalize('NFD');
            var o = ''; for (var i = 0; i < s.length; i++) { var c = s.charCodeAt(i); if (c < 0x300 || c > 0x36f) { o += s.charAt(i); } }
            return o;
        }
        var rows = Array.prototype.slice.call(list.querySelectorAll('.rmx-row'));
        var none = document.getElementById('rmx-none');
        q.addEventListener('input', function () {
            var term = norm(q.value.trim());
            var shown = 0;
            rows.forEach(function (row) {
                var hit = term === '' || norm(row.getAttribute('data-loc')).indexOf(term) !== -1;
                row.style.display = hit ? '' : 'none';
                if (hit) shown++;
            });
            if (none) none.style.display = shown === 0 ? 'block' : 'none';
        });
    })();
</script>
@endpush
@endsection
