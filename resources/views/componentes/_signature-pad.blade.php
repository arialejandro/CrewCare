{{--
    _signature-pad.blade.php — FIRMA AUTÓGRAFA · captura reusable (canvas nativo, sin librería,
    offline). Se dibuja con dedo/mouse, o se escribe el nombre (fuente manuscrita), o se reúsa la
    firma ADOPTADA del usuario (tipo DocuSign). Vuelca la imagen PNG base64 en un <input hidden>.

    Props:
      - $name     (opcional) nombre del input hidden con la imagen. Default 'signature_image'.
      - $adopted  (opcional) firma adoptada del usuario (data URL base64) para reúso.
      - $label    (opcional) rótulo arriba del lienzo.

    Emite también `save_signature` (0/1): si el usuario marca "guardar para reúso", el backend
    actualiza users.adopted_signature. Va DENTRO de un <form>.
--}}
@php
    $sigName    = $name ?? 'signature_image';
    $sigId      = 'sigpad_' . \Illuminate\Support\Str::random(6);
    $adoptedSig = $adopted ?? null;
@endphp
<div class="cc-sigpad" id="{{ $sigId }}" @if($adoptedSig) data-adopted="{{ $adoptedSig }}" @endif>
    @if(!empty($label))<label class="cc-label">{{ $label }}</label>@endif
    <div class="cc-sigpad__wrap">
        <canvas class="cc-sigpad__canvas" width="640" height="200"></canvas>
        <span class="cc-sigpad__baseline"></span>
    </div>
    <div class="cc-sigpad__tools">
        <button type="button" class="btn btn-sm btn-crew-soft cc-sigpad__clear">{{ __('Limpiar') }}</button>
        @if($adoptedSig)
            <button type="button" class="btn btn-sm btn-crew-soft cc-sigpad__reuse">{{ __('Usar mi firma guardada') }}</button>
        @endif
        <input type="text" class="form-control form-control-sm cc-sigpad__typed" placeholder="{{ __('o escribe tu nombre') }}">
        <button type="button" class="btn btn-sm btn-crew-soft cc-sigpad__type">{{ __('Firmar con texto') }}</button>
        <label class="cc-sigpad__savelbl">
            <input type="checkbox" class="cc-sigpad__save" value="1"> {{ __('Guardar para reúso') }}
        </label>
    </div>
    <input type="hidden" name="{{ $sigName }}" class="cc-sigpad__data" value="">
    <input type="hidden" name="save_signature" class="cc-sigpad__saveflag" value="0">
</div>
@once
@push('styles')
<style>
.cc-sigpad__wrap { position: relative; border: 1px solid var(--border, #cbd2dd); border-radius: 10px; background: #fff; }
.cc-sigpad__canvas { width: 100%; height: auto; display: block; border-radius: 10px; cursor: crosshair; touch-action: none; }
.cc-sigpad__baseline { position: absolute; left: 6%; right: 6%; bottom: 26%; border-bottom: 1px dashed #c3c9d4; pointer-events: none; }
.cc-sigpad__tools { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-top: .5rem; }
.cc-sigpad__typed { max-width: 210px; }
.cc-sigpad__savelbl { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; margin: 0 0 0 auto; }
</style>
@endpush
@push('scripts')
<script>
window.CCSigPad = (function () {
    function init(root) {
        if (root.__ccSig) { return; }
        root.__ccSig = true;
        var canvas   = root.querySelector('.cc-sigpad__canvas');
        var ctx      = canvas.getContext('2d');
        var data     = root.querySelector('.cc-sigpad__data');
        var saveFlag = root.querySelector('.cc-sigpad__saveflag');
        var drawing = false, dirty = false;
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f1115';

        function pos(e) {
            var r = canvas.getBoundingClientRect();
            var t = (e.touches && e.touches[0]) ? e.touches[0] : e;
            return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
        }
        function sync() { data.value = dirty ? canvas.toDataURL('image/png') : ''; }
        function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); if (e.cancelable) e.preventDefault(); }
        function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); dirty = true; if (e.cancelable) e.preventDefault(); }
        function end() { if (drawing) { drawing = false; sync(); } }
        function clear() { ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false; sync(); }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);

        root.querySelector('.cc-sigpad__clear').addEventListener('click', clear);

        var typed = root.querySelector('.cc-sigpad__typed');
        var typeBtn = root.querySelector('.cc-sigpad__type');
        if (typeBtn) {
            typeBtn.addEventListener('click', function () {
                var t = (typed.value || '').trim();
                if (!t) { return; }
                clear();
                ctx.fillStyle = '#0f1115';
                ctx.font = 'italic 56px "Segoe Script","Brush Script MT","Snell Roundhand",cursive';
                ctx.textBaseline = 'middle';
                ctx.fillText(t, 26, canvas.height * 0.52);
                dirty = true; sync();
            });
        }

        var reuseBtn = root.querySelector('.cc-sigpad__reuse');
        var adopted = root.getAttribute('data-adopted') || '';
        if (reuseBtn && adopted) {
            reuseBtn.addEventListener('click', function () {
                var img = new Image();
                img.onload = function () { clear(); ctx.drawImage(img, 0, 0, canvas.width, canvas.height); dirty = true; data.value = adopted; };
                img.src = adopted;
            });
        }

        var saveCb = root.querySelector('.cc-sigpad__save');
        if (saveCb) { saveCb.addEventListener('change', function () { saveFlag.value = this.checked ? '1' : '0'; }); }

        var form = root.closest('form');
        if (form) { form.addEventListener('submit', sync); }
    }
    function initAll(scope) { (scope || document).querySelectorAll('.cc-sigpad').forEach(init); }
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
    return { init: init, initAll: initAll };
})();
</script>
@endpush
@endonce
