{{-- Transportación · Fase 5 — POLL LIVIANO + TOAST del contador de atención.
     Sólo se rinde a transpo/producción ($__truckShow del composer). Cada minuto consulta el conteo;
     si SUBE, muestra un toast y actualiza el badge del topbar (#cc-truck-badge). Avisa, no bloquea.
     El push real (service worker + VAPID) queda fuera: proyecto aparte. --}}
@if (($__truckShow ?? false))
<style>
    .cc-truck-toast {
        position: fixed; right: 16px; bottom: 16px; z-index: 1080; max-width: 320px;
        background: var(--brand-secondary, #1f2937); color: #fff;
        border-radius: 10px; padding: .7rem .9rem; box-shadow: 0 10px 30px -8px rgba(0,0,0,.5);
        display: flex; align-items: center; gap: .55rem; font-size: .9rem;
        opacity: 0; transform: translateY(8px); transition: opacity .2s ease-out, transform .2s ease-out;
    }
    .cc-truck-toast.show { opacity: 1; transform: translateY(0); }
    .cc-truck-toast a { color: #fff; text-decoration: underline; }
</style>
<script>
(function () {
    var url   = @json(route('transport.attention.count'));
    var link  = @json(route('transport.order.index'));
    var badge = document.getElementById('cc-truck-badge');
    var last  = {{ (int) ($__truckCount ?? 0) }};

    function setBadge(n) {
        if (!badge) { return; }
        badge.textContent = n;
        badge.style.display = n > 0 ? '' : 'none';
    }

    function toast(n) {
        var box = document.createElement('div');
        box.className = 'cc-truck-toast';
        box.innerHTML = '🚚 <span>{{ __('Novedades de transportación') }} (' + n + '). <a href="' + link + '">{{ __('Ver') }}</a></span>';
        document.body.appendChild(box);
        requestAnimationFrame(function () { box.classList.add('show'); });
        setTimeout(function () { box.classList.remove('show'); setTimeout(function () { box.remove(); }, 250); }, 6000);
    }

    function poll() {
        if (document.hidden) { return; }
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) { return; }
                var n = parseInt(d.count, 10) || 0;
                if (n > last) { toast(n); }
                last = n;
                setBadge(n);
            })
            .catch(function () { /* silencioso: avisa, no bloquea */ });
    }

    setInterval(poll, 60000);
})();
</script>
@endif
