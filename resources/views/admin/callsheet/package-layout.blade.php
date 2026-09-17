@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush
{{-- Colocar las 3 firmas sobre el FRONT (pdf.js, autoalojado). Se arrastran y se guardan como
     field_map = [{page,x_pct,y_pct,w_pct,type:'sign',key}]; se recuerda por producción. --}}
<style>
    .pl-wrap { display:grid; grid-template-columns:260px minmax(0,1fr); gap:18px; align-items:start; }
    .pl-side { position:sticky; top:12px; }
    .pl-chip { display:flex; align-items:center; gap:.5rem; width:100%; text-align:left; border:1.5px solid var(--stroke);
        background:var(--glass); color:var(--text); border-radius:10px; padding:.5rem .6rem; margin-bottom:.4rem; font-size:.86rem; cursor:pointer; }
    .pl-chip:hover { border-color:color-mix(in srgb, var(--brand-primary) 45%, var(--stroke)); }
    .pl-chip[data-armed="1"] { border-color:var(--brand-primary); background:color-mix(in srgb, var(--brand-primary) 12%, var(--glass)); font-weight:600; }
    .pl-chip[data-placed="1"] { opacity:.55; }
    .pl-chip .pl-dot { width:9px; height:9px; border-radius:50%; background:var(--brand-primary); flex:0 0 auto; }
    .pl-chip .pl-role { flex:1; min-width:0; }
    .pl-chip .pl-name { font-size:.72rem; color:var(--text-muted); }
    .pl-hint { font-size:.82rem; color:var(--text-muted); min-height:1.3rem; margin-bottom:.6rem; }
    .pl-doc { background:#525659; border-radius:10px; padding:16px; overflow:auto; max-height:78vh; }
    .pl-page { position:relative; margin:0 auto 16px; box-shadow:0 2px 10px rgba(0,0,0,.4); background:#fff; }
    .pl-page:last-child { margin-bottom:0; }
    .pl-ov { position:absolute; inset:0; cursor:crosshair; }
    .pl-ov[data-armed="1"] { background:rgba(37,99,235,.05); }
    .pl-mk { position:absolute; box-sizing:border-box; border:1.5px dashed #7c3aed; background:rgba(124,58,237,.14); color:#4c1d95;
        border-radius:6px; font-size:11px; padding:3px 16px 3px 6px; cursor:move; user-select:none; min-height:30px; display:flex; align-items:center;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pl-mk .pl-x { position:absolute; top:1px; right:2px; width:14px; height:14px; line-height:13px; text-align:center; border-radius:3px; background:rgba(0,0,0,.3); color:#fff; cursor:pointer; }
    .pl-mk .pl-x:hover { background:#dc2626; }
    .pl-loading { color:#e5e7eb; text-align:center; padding:40px 0; }
    @media (max-width:860px){ .pl-wrap { grid-template-columns:1fr; } .pl-side { position:static; } }
</style>

<div class="container-fluid py-4 cs-wrap" style="max-width:1200px">
    <div class="adm-header">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Colocar las firmas</h1>
            <p class="adm-subtitle">Arrastra cada firma a donde va en el front. Se recuerda para los siguientes días de la producción.</p>
        </div>
        <a href="{{ route('callsheet.package', ['date' => $nav['dateStr']]) }}" class="btn btn-outline-secondary btn-sm ms-auto">Volver al paquete</a>
    </div>

    @if($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('callsheet.package.layout.save', ['date' => $nav['dateStr']]) }}" id="plForm" class="mt-3">
        @csrf
        <input type="hidden" name="field_map" id="plFieldMap" value="[]">
        <div class="pl-wrap">
            <div class="pl-side">
                <div class="card cs-card">
                    <div class="card-body">
                        <div class="pl-hint" id="plHint">Elige una firma y haz clic sobre el front para colocarla.</div>
                        @foreach($signers as $sg)
                            <button type="button" class="pl-chip" data-type="sign" data-key="{{ $sg['key'] }}" data-label="{{ $sg['role_label'] }}">
                                <span class="pl-dot"></span>
                                <span class="pl-role">{{ $sg['role_label'] }}<br><span class="pl-name">{{ $sg['name'] ?: '—' }}</span></span>
                            </button>
                        @endforeach
                        <hr class="my-3">
                        <div class="small text-muted mb-2" id="plCount">—</div>
                        <button type="submit" class="btn btn-primary w-100">
                            @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar posición
                        </button>
                        <div class="form-text mt-1">Arrastra para mover · × para quitar. Se recuerda por producción.</div>
                    </div>
                </div>
            </div>
            <div class="pl-doc" id="plDoc"><div class="pl-loading" id="plLoading">Cargando el front…</div></div>
        </div>
    </form>
</div>

@push('scripts')
<script src="{{ asset('js/vendor/pdfjs/pdf.min.js') }}"></script>
<script>
(function () {
    var PDF_URL  = @json(route('callsheet.package.front.file', ['date' => $nav['dateStr']]));
    var WORKER   = @json(asset('js/vendor/pdfjs/pdf.worker.min.js'));
    var EXISTING = @json($pkg->sign_field_map ?? []);
    var LABELS = {};
    document.querySelectorAll('.pl-chip').forEach(function (c) { LABELS[c.getAttribute('data-key')] = c.getAttribute('data-label'); });

    if (!window.pdfjsLib) { document.getElementById('plLoading').textContent = 'No se pudo dibujar el PDF.'; return; }
    pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER;

    var FIELDS = [], uidSeq = 1, armed = null, overlays = {};

    function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

    function syncMap() {
        var clean = FIELDS.map(function (f) { return { page:f.page, x_pct:f.x_pct, y_pct:f.y_pct, w_pct:f.w_pct, type:'sign', key:f.key }; });
        document.getElementById('plFieldMap').value = JSON.stringify(clean);
        document.getElementById('plCount').textContent = FIELDS.length + ' firma(s) colocada(s)';
        // marca los chips ya colocados
        var placed = {}; FIELDS.forEach(function (f) { placed[f.key] = true; });
        document.querySelectorAll('.pl-chip').forEach(function (c) {
            if (placed[c.getAttribute('data-key')]) c.setAttribute('data-placed','1'); else c.removeAttribute('data-placed');
        });
    }

    function setArmed(chip) {
        document.querySelectorAll('.pl-chip').forEach(function (c) { c.removeAttribute('data-armed'); });
        Object.keys(overlays).forEach(function (p) { overlays[p].removeAttribute('data-armed'); });
        if (!chip) { armed = null; document.getElementById('plHint').textContent = 'Elige una firma y haz clic sobre el front para colocarla.'; return; }
        armed = { key: chip.getAttribute('data-key'), label: chip.getAttribute('data-label') };
        chip.setAttribute('data-armed','1');
        Object.keys(overlays).forEach(function (p) { overlays[p].setAttribute('data-armed','1'); });
        document.getElementById('plHint').textContent = 'Colocando: ' + armed.label + ' — haz clic en el front (Esc para cancelar).';
    }

    document.querySelectorAll('.pl-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            if (armed && armed.key === chip.getAttribute('data-key')) setArmed(null); else setArmed(chip);
        });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setArmed(null); });

    function renderMarker(f) {
        var ov = overlays[f.page]; if (!ov) return;
        var mk = document.createElement('div');
        mk.className = 'pl-mk';
        mk.style.left = f.x_pct + '%'; mk.style.top = f.y_pct + '%'; mk.style.width = f.w_pct + '%';
        mk.innerHTML = '<span>' + esc(LABELS[f.key] || f.key) + '</span><span class="pl-x" title="Quitar">&times;</span>';
        ov.appendChild(mk);
        mk.querySelector('.pl-x').addEventListener('click', function (e) { e.stopPropagation(); FIELDS = FIELDS.filter(function (x){return x.uid!==f.uid;}); mk.remove(); syncMap(); });
        mk.addEventListener('pointerdown', function (e) {
            if (e.target.classList.contains('pl-x')) return;
            e.preventDefault(); e.stopPropagation();
            var rect = ov.getBoundingClientRect(), startX = e.clientX, startY = e.clientY, baseL = f.x_pct, baseT = f.y_pct;
            mk.setPointerCapture(e.pointerId);
            function move(ev){ var dx=(ev.clientX-startX)/rect.width*100, dy=(ev.clientY-startY)/rect.height*100;
                f.x_pct=Math.max(0,Math.min(100,baseL+dx)); f.y_pct=Math.max(0,Math.min(100,baseT+dy)); mk.style.left=f.x_pct+'%'; mk.style.top=f.y_pct+'%'; }
            function up(){ mk.releasePointerCapture(e.pointerId); mk.removeEventListener('pointermove',move); mk.removeEventListener('pointerup',up);
                f.x_pct=Math.round(f.x_pct*1000)/1000; f.y_pct=Math.round(f.y_pct*1000)/1000; syncMap(); }
            mk.addEventListener('pointermove', move); mk.addEventListener('pointerup', up);
        });
    }

    function onOverlayClick(page, e, ov) {
        if (!armed || e.target !== ov) return;
        // una firma por figura: si ya estaba, la reubica
        FIELDS = FIELDS.filter(function (x) { return x.key !== armed.key; });
        var rect = ov.getBoundingClientRect();
        var f = { uid: uidSeq++, page: page,
            x_pct: Math.round((e.clientX-rect.left)/rect.width*1000)/10,
            y_pct: Math.round((e.clientY-rect.top)/rect.height*1000)/10,
            w_pct: 22, key: armed.key };
        // limpia el marcador viejo del mismo key
        Array.prototype.slice.call(ov.querySelectorAll('.pl-mk')).forEach(function(m){}); // (los viejos se quitan al re-render)
        FIELDS.push(f); renderMarker(f); syncMap(); setArmed(null);
    }

    function drawPage(pdf, n, done) {
        pdf.getPage(n).then(function (page) {
            var host = document.getElementById('plDoc');
            var maxW = Math.min(host.clientWidth - 32, 900);
            var base = page.getViewport({ scale: 1 });
            var viewport = page.getViewport({ scale: maxW / base.width });
            var dpr = window.devicePixelRatio || 1;
            var wrap = document.createElement('div'); wrap.className = 'pl-page';
            wrap.style.width = viewport.width + 'px'; wrap.style.height = viewport.height + 'px';
            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width*dpr); canvas.height = Math.floor(viewport.height*dpr);
            canvas.style.width = viewport.width+'px'; canvas.style.height = viewport.height+'px';
            wrap.appendChild(canvas);
            var ov = document.createElement('div'); ov.className = 'pl-ov'; wrap.appendChild(ov);
            overlays[n] = ov; if (armed) ov.setAttribute('data-armed','1');
            ov.addEventListener('click', function (e) { onOverlayClick(n, e, ov); });
            host.appendChild(wrap);
            page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport, transform: dpr!==1?[dpr,0,0,dpr,0,0]:null }).promise.then(function () { done(); });
        });
    }

    pdfjsLib.getDocument(PDF_URL).promise.then(function (pdf) {
        var l = document.getElementById('plLoading'); if (l) l.remove();
        var n = 1;
        (function next(){
            if (n > pdf.numPages) {
                (EXISTING||[]).forEach(function (f) {
                    var item = { uid:uidSeq++, page:parseInt(f.page,10)||1, x_pct:+f.x_pct||0, y_pct:+f.y_pct||0, w_pct:+f.w_pct||22, key:String(f.key||'') };
                    if (overlays[item.page] && LABELS[item.key]) { FIELDS.push(item); renderMarker(item); }
                });
                syncMap(); return;
            }
            drawPage(pdf, n, function () { n++; next(); });
        })();
    }).catch(function () { var l=document.getElementById('plLoading'); if (l) l.textContent = 'No se pudo dibujar el front (¿protegido con contraseña?).'; });

    document.getElementById('plForm').addEventListener('submit', syncMap);
})();
</script>
@endpush
@endsection
