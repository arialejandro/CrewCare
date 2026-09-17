/* ============================================================================================
   TECH SCOUT — REFRESCO PERIÓDICO DE LA REJILLA DE NOTAS.

   Dos scouters recorren la misma locación a la vez (a veces en cuartos distintos de la misma
   casa) y cada uno tiene que ver aparecer lo del otro sin recargar a mano. Se descartó el tiempo
   real con websockets: para esto basta preguntar cada tanto, y no añade infraestructura que haya
   que mantener viva en un servidor compartido.

   Archivo EXTERNO, no <script> en la vista: así no depende del nonce y la CSP no tiene nada que
   perdonar (ver App\Http\Middleware\SecurityHeaders).

   ── LO QUE NO HACE, Y ES LO IMPORTANTE ──────────────────────────────────────────────────────
   No reemplaza nada mientras alguien está trabajando dentro de la rejilla: si hay un editor
   abierto o el cursor está ahí dentro, salta el ciclo. Cambiar el HTML bajo los dedos de alguien
   le borra lo escrito — el pecado que este módulo lleva toda la semana evitando.

   Tampoco pregunta cuando la pestaña está oculta ni sin red: en campo, con datos móviles, una
   petición cada veinte segundos de una pantalla que nadie está mirando es batería y megas de
   alguien que está trabajando fuera.
============================================================================================ */
(function () {
    'use strict';

    var root = document.querySelector('[data-ts-refresh]');
    if (!root || typeof fetch !== 'function') { return; }

    var url  = root.getAttribute('data-ts-refresh');
    var host = document.getElementById('ts-notes');
    if (!url || !host) { return; }

    var CADA = 20000;   // 20 s: suficiente para sentirlo vivo, poco para no pesar en la red.
    var vivo = document.querySelector('[data-ts-live]');

    /** ¿Hay alguien con las manos en la rejilla? Entonces no se toca. */
    function ocupado() {
        if (host.querySelector('details[open]')) { return true; }
        var act = document.activeElement;
        return !!(act && act !== document.body && host.contains(act));
    }

    /** El contador del cintillo: sin esto, la cabecera diría 3 notas con 5 en pantalla. */
    function pintarConteo(n) {
        var slot = document.querySelector('[data-ts-count-slot]');
        if (!slot) { return; }
        slot.textContent = n;
        var lbl = document.querySelector('[data-ts-count-label]');
        if (lbl) { lbl.textContent = (Number(n) === 1 ? 'nota' : 'notas'); }
    }

    /** Aviso discreto de que algo entró. Se apaga solo: no es una alerta, es una señal de vida. */
    function avisar() {
        if (!vivo) { return; }
        vivo.textContent = 'Actualizado';
        vivo.classList.add('is-on');
        setTimeout(function () { vivo.classList.remove('is-on'); }, 2600);
    }

    function preguntar() {
        if (document.hidden || navigator.onLine === false || ocupado()) { return; }

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (html) {
                if (!html) { return; }

                var doc    = new DOMParser().parseFromString(html, 'text/html');
                var fresco = doc.getElementById('ts-notes');
                if (!fresco) { return; }

                // Misma firma = mismas notas y mismo último cambio. No se toca el DOM: reemplazar
                // por lo mismo haría parpadear la pantalla cada veinte segundos sin motivo.
                if (fresco.getAttribute('data-ts-sig') === host.getAttribute('data-ts-sig')) { return; }

                // Se vuelve a comprobar: entre la pregunta y la respuesta alguien pudo abrir un
                // editor, y ese viaje dura lo que dura la red de campo.
                if (ocupado()) { return; }

                host.replaceWith(document.adoptNode(fresco));
                host = fresco;
                pintarConteo(fresco.getAttribute('data-ts-count'));
                avisar();
            })
            .catch(function () { /* sin red o servidor caído: se reintenta al siguiente ciclo */ });
    }

    // Expuesto para que la captura de notas pida el refresco EN CUANTO el servidor confirma una,
    // sin esperar al siguiente ciclo: al agregar una nota, verla aparecer es la confirmación.
    window.CCTechScout = window.CCTechScout || {};
    window.CCTechScout.refrescar = preguntar;

    setInterval(preguntar, CADA);

    // Al volver a la app (el móvil se bloqueó, se cambió de pestaña) se pregunta YA: es justo el
    // momento en que uno quiere ver lo que anotó el otro mientras no estabas mirando.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { preguntar(); }
    });
    window.addEventListener('online', preguntar);
})();
