/* ============================================================================================
   TECH SCOUT — CAPTURA DE NOTAS QUE NO PIERDE FOTOS.

   El 16-sep, camino a un penal: «cualquier imagen perdida es un problema grave… si algo dentro
   del penal falla, como la red». Esta es la respuesta a eso.

   ── LA REGLA ────────────────────────────────────────────────────────────────────────────────
   La nota se GUARDA EN EL DISPOSITIVO ANTES DE TOCAR LA RED. Siempre. Con o sin señal.
   Después se envía — enseguida si hay red, o sola en cuanto vuelva.

   Es distinto de lo que hacen los otros cinco formularios con envío diferido, que sólo encolan
   cuando `navigator.onLine` dice que no hay red. Ese "sí hay red" miente a diario: el wifi de
   cortesía que no deja pasar un byte, el sótano con una barra, la red del penal. Cuando miente,
   el POST se queda colgado — y si en ese momento se cierra la pestaña, la foto **no existía en
   ningún sitio**. Encolando siempre, existe desde el segundo cero.

   La llave de idempotencia (id del borrador) impide que un reintento duplique la nota; la
   respalda `App\Http\Middleware\IdempotentReplay` en el servidor.

   ── LO QUE VE QUIEN CAPTURA ─────────────────────────────────────────────────────────────────
   Un renglón de estado que dice la verdad en cada momento: guardada · enviando · agregada, o
   "se enviará sola al recuperar la red" con el número de pendientes. Sin ese renglón, el
   mecanismo es invisible y nadie le cree — y un scouter que no le cree vuelve a la libreta.

   ── Y EL SEGURO AL CERRAR ───────────────────────────────────────────────────────────────────
   Si hay una foto elegida o texto escrito SIN pulsar el botón, cerrar la pestaña pregunta
   primero. Es la única ventana en la que algo puede perderse, y dura segundos.
============================================================================================ */
(function (w, d) {
    'use strict';

    // ── EL ALTA DEL RECORRIDO ───────────────────────────────────────────────────────────────
    // La pantalla de crear lleva su PRIMERA NOTA con foto. Si se llega a la locación sin señal
    // y se crea ahí, esa foto viajaría por el envío normal del navegador y se perdería. Se le
    // engancha la misma cola: sin red, el alta entera (portada + primera nota) queda guardada y
    // sube al recuperar señal. Con red, el envío es el de siempre.
    var alta = d.querySelector('[data-ts-create-form]');
    if (alta && w.CCDrafts && w.CCDrafts.available) {
        var hAlta = w.CCDrafts.attach(alta, {
            formType: 'techscout-create',
            deferOffline: true,
            title: function () {
                var l = alta.querySelector('[name=location_name]');
                return (l && l.value) ? l.value : 'Tech Scout nuevo';
            },
            onStatus: function (st) {
                if (st.state !== 'queued') { return; }
                // 🪤 Id NUEVO en cuanto queda encolada. Si no, el autoguardado de lo que se siga
                // escribiendo reescribiría ESTE borrador como 'device' y **sin las fotos** — el
                // alta encolada se perdería sin que nadie lo notara.
                if (hAlta && typeof hAlta.reset === 'function') { hAlta.reset(); }

                var caja = d.querySelector('[data-ts-create-status]');
                if (!caja) { return; }
                caja.textContent = 'Sin red: el Tech Scout y su primera nota quedaron guardados en '
                    + 'este dispositivo. Se crearán solos al recuperar señal — no vuelvas a capturarlo.';
                caja.className = 'ts-note-status is-warn';
            }
        });
    }

    var form = d.querySelector('[data-ts-note-form]');
    if (!form) { return; }

    var estado = d.querySelector('[data-ts-note-status]');
    var boton  = form.querySelector('button[type=submit]');

    /** Sin IndexedDB no hay red de seguridad: se deja el envío normal del navegador y se avisa. */
    if (!w.CCDrafts || !w.CCDrafts.available) {
        decir('Este navegador no puede guardar notas sin conexión. Captura con señal.', 'warn');
        return;
    }

    // 🪤 ENCOLADO MANUAL (`data-cc-defer-manual`). Sigue siendo un formulario con envío diferido
    // —el registro necesita destino, método y llave de idempotencia—, pero el submit lo maneja
    // ESTE archivo, con red y sin ella. Si dejáramos que cc-drafts lo interceptara offline,
    // cortaría la propagación, el código de abajo no correría, el formulario se quedaría con el
    // texto de la nota YA encolada y el autoguardado reescribiría ESE MISMO borrador como
    // 'device'… **sin las fotos**: capturar una segunda nota sin señal se llevaba por delante la
    // primera. Un solo camino, el de abajo.
    form.setAttribute('data-cc-defer-manual', '1');

    var handle = w.CCDrafts.attach(form, {
        formType: 'techscout-note',
        deferOffline: true,
        title: function () {
            var t = (form.querySelector('[name=note]') || {}).value || '';
            return t.slice(0, 80) || 'Nota con foto';
        }
    });
    if (!handle) { return; }

    function decir(txt, clase) {
        if (!estado) { return; }
        estado.textContent = txt;
        estado.className = 'ts-note-status is-' + (clase || 'info');
    }

    function hayFoto() {
        var f = form.querySelector('input[type=file]');
        return !!(f && f.files && f.files.length);
    }
    function hayTexto() {
        var t = form.querySelector('[name=note]');
        return !!(t && t.value.trim() !== '');
    }

    /** Vacía el formulario para la siguiente nota, conservando la etiqueta de la historia. */
    function limpiar() {
        var f = form.querySelector('input[type=file]');
        var t = form.querySelector('[name=note]');
        if (f) { f.value = ''; }
        if (t) { t.value = ''; }
        handle.reset();          // la siguiente nota es OTRO borrador, con su propia llave
        if (boton) { boton.disabled = false; }
    }

    // ── EL ENVÍO ────────────────────────────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        // CON RED O SIN ELLA, el mismo camino: primero al dispositivo, después a la red.
        e.preventDefault();

        // Misma regla que el servidor: una nota vacía no dice nada. Se comprueba aquí para no
        // encolar basura que luego rebote en validación desde la cola, donde nadie la ve.
        if (!hayTexto() && !hayFoto()) {
            decir('Escribe la nota o adjunta una foto — una nota vacía no dice nada.', 'warn');
            return;
        }

        if (boton) { boton.disabled = true; }
        decir('Guardando en el dispositivo…', 'info');

        // 🪤 CADA NOTA, UN BORRADOR NUEVO — y no es cosmético. `attach` registra su propio
        // listener de submit ANTES que éste, y en línea BORRA el borrador en curso (su
        // comportamiento normal: el envío del navegador ya se lo llevó). Si encoláramos con ESE
        // mismo id, su borrado —asíncrono— podría llegar DESPUÉS de nuestro guardado y llevarse
        // por delante la nota recién encolada, con su foto. Pidiendo id nuevo, lo que él borra es
        // el borrador de texto viejo y lo nuestro nace intacto.
        // Y el borrador de texto que venía autoguardándose se retira a mano: sin red, el listener
        // de `attach` no lo hace, y quedaría suelto en el dispositivo sin destino ni fotos.
        var viejo = handle.id();
        handle.reset();
        if (viejo && typeof w.CCDrafts.remove === 'function') { w.CCDrafts.remove(viejo); }

        handle.enqueueNow().then(function () {
            // A partir de aquí la foto YA está en el dispositivo: pase lo que pase con la red,
            // no se pierde. Se limpia el formulario para poder seguir caminando y capturando.
            var conRed = !(w.navigator && w.navigator.onLine === false);
            decir(conRed
                ? 'Guardada. Enviando…'
                : 'Guardada en el dispositivo. Se enviará al recuperar la red — sigue capturando.',
                conRed ? 'ok' : 'warn');
            limpiar();
            if (!conRed) { setTimeout(contarPendientes, 400); }
        }).catch(function () {
            if (boton) { boton.disabled = false; }
            decir('No se pudo guardar en el dispositivo. No cierres la pestaña y vuelve a intentar.', 'error');
        });
    });

    // ── QUÉ PASÓ CON LO ENCOLADO ────────────────────────────────────────────────────────────
    w.addEventListener('cc-drafts:sent', function () {
        decir('Nota agregada.', 'ok');
        // Que se vea abajo en la rejilla: para quien captura, ver la nota aparecer ES la
        // confirmación de que llegó. Reusa el refresco que ya trae la pantalla.
        if (w.CCTechScout && typeof w.CCTechScout.refrescar === 'function') { w.CCTechScout.refrescar(); }
        setTimeout(contarPendientes, 300);
    });

    w.addEventListener('cc-drafts:error', function (ev) {
        decir((ev.detail && ev.detail.error) || 'La nota necesita corrección.', 'error');
    });

    w.addEventListener('cc-drafts:auth', function () {
        decir('Se cerró la sesión: la nota está guardada y se enviará al volver a entrar.', 'warn');
    });

    /** Cuántas notas esperan turno. Es el número que da tranquilidad al salir del penal. */
    function contarPendientes() {
        if (typeof w.CCDrafts.pending !== 'function') { return; }
        w.CCDrafts.pending().then(function (lista) {
            var n = (lista || []).filter(function (r) { return r && r.form === 'techscout-note'; }).length;
            if (!n) { return; }
            decir(n === 1
                ? '1 nota guardada en el dispositivo, pendiente de enviar. Se subirá sola al recuperar la red.'
                : n + ' notas guardadas en el dispositivo, pendientes de enviar. Se subirán solas al recuperar la red.',
                'warn');
        }).catch(function () { /* sin ruido */ });
    }

    contarPendientes();
    w.addEventListener('online', function () {
        decir('Red recuperada: enviando lo pendiente…', 'info');
        setTimeout(contarPendientes, 4000);
    });
    w.addEventListener('offline', function () {
        decir('Sin red. Sigue capturando: las notas se guardan en el dispositivo.', 'warn');
    });

    // ── EL SEGURO AL CERRAR ─────────────────────────────────────────────────────────────────
    // Sólo cuando hay algo escrito o una foto elegida SIN enviar. Una vez pulsado el botón la
    // nota ya está en el dispositivo y cerrar no cuesta nada — avisar entonces sería el aviso
    // que la gente aprende a ignorar.
    w.addEventListener('beforeunload', function (e) {
        if (!hayTexto() && !hayFoto()) { return; }
        e.preventDefault();
        e.returnValue = '';   // el navegador pone su propio texto; no se puede personalizar
        return '';
    });
})(window, document);
