/*
 * CrewCare Geo — utilidades de geolocalización compartidas.
 *
 * Se usa desde cualquier formulario incluyendo el parcial Blade
 * `componentes/_geo-capture.blade.php` (captura GPS + dirección automática)
 * y opcionalmente un bloque `[data-hospital-finder]` (hospitales cercanos + ETA).
 *
 * Servicios externos (todos gratuitos, sin API key, solo se envían coordenadas):
 *   - Nominatim (OpenStreetMap)  → dirección a partir de coordenadas (reverse geocoding)
 *   - Overpass  (OpenStreetMap)  → hospitales cercanos a un punto
 *   - OSRM (router.project-osrm) → ruta en auto y ETA locación → hospital
 *
 * Si no hay internet o el servicio falla, TODO degrada a captura manual:
 * los campos nunca dependen de estas APIs.
 */
(function () {
    'use strict';

    var NOMINATIM = 'https://nominatim.openstreetmap.org/reverse';
    var OVERPASS_SERVERS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter'
    ];
    // Servidores de ruteo OSRM (demo público + espejo FOSSGIS que usa osm.org).
    var OSRM_SERVERS = [
        'https://router.project-osrm.org/route/v1/driving/',
        'https://routing.openstreetmap.de/routed-car/route/v1/driving/'
    ];

    /* ------------------------------------------------------------------ *
     *  Utilerías básicas
     * ------------------------------------------------------------------ */

    // Distancia en línea recta (km) entre dos puntos — fórmula de Haversine.
    function haversineKm(lat1, lng1, lat2, lng2) {
        var R = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // fetch con timeout: los servicios públicos a veces se cuelgan sin responder;
    // sin esto un campo puede quedarse en "Calculando…" para siempre.
    function fetchJson(url, options, timeoutMs) {
        options = options || {};
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timer = null;
        if (controller) {
            options.signal = controller.signal;
            timer = setTimeout(function () { controller.abort(); }, timeoutMs || 12000);
        }
        return fetch(url, options).then(function (res) {
            if (timer) { clearTimeout(timer); }
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            return res.json();
        }).catch(function (err) {
            if (timer) { clearTimeout(timer); }
            throw err;
        });
    }

    /* ------------------------------------------------------------------ *
     *  1. Posición del dispositivo (navegador)
     * ------------------------------------------------------------------ */
    function locate() {
        return new Promise(function (resolve, reject) {
            if (!('geolocation' in navigator)) {
                reject(new Error('unsupported'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                },
                function (err) { reject(err); },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        });
    }

    /* ------------------------------------------------------------------ *
     *  2. Dirección a partir de coordenadas (Nominatim)
     * ------------------------------------------------------------------ */
    function reverseGeocode(lat, lng) {
        var url = NOMINATIM + '?format=jsonv2&zoom=18&accept-language=es' +
                  '&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
        return fetchJson(url).then(function (data) {
            var a = data.address || {};
            var parts = [];
            var street = a.road || a.pedestrian || a.footway || a.path || '';
            if (street) { parts.push(a.house_number ? street + ' ' + a.house_number : street); }
            var hood = a.neighbourhood || a.suburb || a.quarter || a.hamlet || '';
            if (hood && hood !== street) { parts.push(hood); }
            var city = a.city || a.town || a.village || a.municipality || '';
            if (city) { parts.push(city); }
            if (a.state) { parts.push(a.state); }
            if (a.postcode) { parts.push('C.P. ' + a.postcode); }
            return parts.length ? parts.join(', ') : (data.display_name || '');
        });
    }

    /* ------------------------------------------------------------------ *
     *  3. Hospitales cercanos (Overpass / OpenStreetMap)
     * ------------------------------------------------------------------ */

    // Clasifica un hospital como privado / público / desconocido usando las
    // etiquetas de OSM y heurísticas de nombres comunes en México.
    var RE_PUBLIC  = /(IMSS|ISSSTE|INSABI|BIENESTAR|SEDENA|SEMAR|PEMEX|CRUZ ROJA|HOSPITAL GENERAL|GENERAL DE ZONA|COMUNITARIO|HOSPITAL RURAL|HOSPITAL REGIONAL|HOSPITAL MUNICIPAL|CENTRO DE SALUD|HOSPITAL CIVIL|MATERNO|SECRETAR[IÍ]A DE SALUD|\bSSA\b|\bDIF\b)/i;
    var RE_PRIVATE = /([ÁA]NGELES|STAR M[EÉ]DICA|M[EÉ]DICA SUR|CHRISTUS|MUGUERZA|SAN JOS[EÉ]|SAN JAVIER|AMERIMED|GALENIA|\bCIMA\b|PUERTA DE HIERRO|DALINDE|ESPA[ÑN]OL|\bABC\b|BENEFICENCIA|SANATORIO|HOSPITAL PRIVADO|MAC\b|HOSPITARIA|ZAMBRANO|OCA HOSPITAL|BIT[EÉ] M[EÉ]DICA)/i;

    function classifyHospital(tags) {
        var t = ((tags['operator:type'] || '') + ' ' + (tags['ownership'] || '')).toLowerCase();
        if (t.indexOf('private') !== -1) { return 'private'; }
        if (t.indexOf('public') !== -1 || t.indexOf('government') !== -1 || t.indexOf('municipal') !== -1) { return 'public'; }
        var name = tags.name || '';
        if (RE_PRIVATE.test(name)) { return 'private'; }
        if (RE_PUBLIC.test(name)) { return 'public'; }
        return 'unknown';
    }

    function addressFromTags(tags) {
        var parts = [];
        if (tags['addr:street']) {
            parts.push(tags['addr:housenumber'] ? tags['addr:street'] + ' ' + tags['addr:housenumber'] : tags['addr:street']);
        }
        if (tags['addr:suburb']) { parts.push(tags['addr:suburb']); }
        if (tags['addr:city']) { parts.push(tags['addr:city']); }
        if (tags['addr:postcode']) { parts.push('C.P. ' + tags['addr:postcode']); }
        return parts.join(', ');
    }

    function queryOverpass(query, serverIndex) {
        serverIndex = serverIndex || 0;
        if (serverIndex >= OVERPASS_SERVERS.length) {
            return Promise.reject(new Error('overpass-unavailable'));
        }
        return fetchJson(OVERPASS_SERVERS[serverIndex], {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'data=' + encodeURIComponent(query)
        }).catch(function () {
            // Si el servidor principal falla (caído / saturado), prueba el espejo.
            return queryOverpass(query, serverIndex + 1);
        });
    }

    function hospitalsAround(lat, lng, radiusMeters) {
        var q = '[out:json][timeout:25];' +
                'nwr["amenity"="hospital"](around:' + radiusMeters + ',' + lat + ',' + lng + ');' +
                'out center tags 40;';
        return queryOverpass(q).then(function (data) {
            var list = [];
            var seen = {};
            (data.elements || []).forEach(function (el) {
                var tags = el.tags || {};
                if (!tags.name) { return; }                    // sin nombre no sirve en el reporte
                var hLat = el.lat || (el.center && el.center.lat);
                var hLng = el.lon || (el.center && el.center.lon);
                if (!hLat || !hLng) { return; }
                var key = tags.name.toUpperCase().replace(/\s+/g, ' ');
                if (seen[key]) { return; }                     // dedupe (nodo + edificio duplicados)
                seen[key] = true;
                list.push({
                    name: tags.name,
                    lat: hLat,
                    lng: hLng,
                    km: haversineKm(lat, lng, hLat, hLng),
                    kind: classifyHospital(tags),              // 'private' | 'public' | 'unknown'
                    address: addressFromTags(tags),
                    phone: tags.phone || tags['contact:phone'] || '',
                    emergency: tags.emergency === 'yes'
                });
            });
            return list;
        });
    }

    // Busca hospitales: 15 km primero; si hay pocos (zonas rurales), amplía a 45 km.
    // Orden final: PRIVADOS primero, luego sin clasificar, luego públicos; por distancia.
    function findHospitals(lat, lng) {
        return hospitalsAround(lat, lng, 15000).then(function (list) {
            if (list.length >= 3) { return list; }
            return hospitalsAround(lat, lng, 45000).then(function (wide) {
                return wide.length > list.length ? wide : list;
            });
        }).then(function (list) {
            var rank = { private: 0, unknown: 1, public: 2 };
            list.sort(function (a, b) {
                var r = rank[a.kind] - rank[b.kind];
                return r !== 0 ? r : a.km - b.km;
            });
            return list.slice(0, 12);
        });
    }

    /* ------------------------------------------------------------------ *
     *  4. ETA en auto (OSRM) — ruta real por carretera, sin tráfico en vivo
     * ------------------------------------------------------------------ */
    function osrmRoute(fromLat, fromLng, toLat, toLng, serverIndex) {
        serverIndex = serverIndex || 0;
        if (serverIndex >= OSRM_SERVERS.length) {
            return Promise.reject(new Error('osrm-unavailable'));
        }
        var url = OSRM_SERVERS[serverIndex] + fromLng + ',' + fromLat + ';' + toLng + ',' + toLat +
                  '?overview=false&alternatives=false';
        return fetchJson(url, null, 8000).then(function (data) {
            if (!data.routes || !data.routes.length) { throw new Error('no-route'); }
            var r = data.routes[0];
            return {
                minutes: Math.max(1, Math.round(r.duration / 60)),
                km: Math.round(r.distance / 100) / 10,
                estimated: false
            };
        }).catch(function () {
            return osrmRoute(fromLat, fromLng, toLat, toLng, serverIndex + 1);
        });
    }

    function driveEta(fromLat, fromLng, toLat, toLng) {
        return osrmRoute(fromLat, fromLng, toLat, toLng, 0).catch(function () {
            // Último recurso: estimación por línea recta × factor de camino (1.35)
            // a ~35 km/h promedio urbano. Se marca como estimado.
            var roadKm = haversineKm(fromLat, fromLng, toLat, toLng) * 1.35;
            return {
                minutes: Math.max(1, Math.round(roadKm / 35 * 60)),
                km: Math.round(roadKm * 10) / 10,
                estimated: true
            };
        });
    }

    /* ------------------------------------------------------------------ *
     *  5. Auto-init: bloques [data-geo-capture]
     *     (botón GPS → lat/lng → dirección automática)
     * ------------------------------------------------------------------ */
    function setStatus(el, text, cls) {
        if (!el) { return; }
        el.textContent = text;
        el.className = 'js-geo-help ' + (cls || 'text-muted') + ' d-block';
    }

    function initGeoCapture(root) {
        var latEl = root.querySelector('.js-geo-lat');
        var lngEl = root.querySelector('.js-geo-lng');
        var btn   = root.querySelector('.js-geo-btn');
        var help  = root.querySelector('.js-geo-help');
        var link  = root.querySelector('.js-geo-maps');
        if (!latEl || !lngEl || !btn) { return; }

        var addressTarget = root.getAttribute('data-address-target');
        var addressEl = addressTarget ? document.querySelector(addressTarget) : null;

        // Modo automático: al abrir el formulario intenta detectar la ubicación
        // por detrás y SUGERIR la locación (solo llena campos vacíos, nunca pisa).
        var autoMode = root.hasAttribute('data-geo-auto');
        var suggestTarget = root.getAttribute('data-suggest-target');
        var suggestEl = suggestTarget ? document.querySelector(suggestTarget) : null;

        function updateMapsLink() {
            if (!link) { return; }
            var lat = (latEl.value || '').trim();
            var lng = (lngEl.value || '').trim();
            if (lat !== '' && lng !== '') {
                link.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng);
                link.style.display = 'inline-block';
            } else {
                link.style.display = 'none';
            }
        }
        latEl.addEventListener('input', updateMapsLink);
        lngEl.addEventListener('input', updateMapsLink);

        // Llena el campo de dirección: directo si está vacío; si el usuario ya
        // escribió algo, ofrece la detectada como liga clicable (no pisa su texto).
        function offerAddress(address) {
            if (!address) { return; }
            if (addressEl && addressEl.value.trim() === '') {
                addressEl.value = address;
                setStatus(help, 'Ubicación y dirección capturadas. Ajusta lo que necesites.', 'text-success');
            } else if (addressEl) {
                setStatus(help, 'Dirección detectada: ' + address + ' — ', 'text-success');
                var use = document.createElement('a');
                use.href = '#';
                use.textContent = 'usar esta dirección';
                use.addEventListener('click', function (e) {
                    e.preventDefault();
                    addressEl.value = address;
                    setStatus(help, 'Dirección reemplazada por la detectada.', 'text-success');
                });
                help.appendChild(use);
            } else {
                setStatus(help, 'Ubicación capturada: ' + address, 'text-success');
            }
        }

        btn.addEventListener('click', function () {
            setStatus(help, 'Obteniendo ubicación…');
            btn.disabled = true;
            locate().then(function (pos) {
                latEl.value = pos.lat.toFixed(7);
                lngEl.value = pos.lng.toFixed(7);
                updateMapsLink();
                setStatus(help, 'Ubicación capturada. Buscando dirección…', 'text-success');
                btn.disabled = false;
                root.dispatchEvent(new CustomEvent('geo:captured', { bubbles: true, detail: pos }));
                return reverseGeocode(pos.lat, pos.lng).then(offerAddress).catch(function () {
                    setStatus(help, 'Ubicación capturada (sin internet para la dirección — escríbela a mano).', 'text-warning');
                });
            }).catch(function (err) {
                var msg = 'No se pudo obtener la ubicación.';
                if (err && err.message === 'unsupported') { msg = 'Tu navegador no soporta geolocalización. Escribe las coordenadas a mano.'; }
                else if (err && err.code === 1) { msg = 'Permiso de ubicación denegado. Actívalo en el navegador o escribe las coordenadas a mano.'; }
                else if (err && err.code === 2) { msg = 'Ubicación no disponible. Intenta de nuevo o escríbela a mano.'; }
                else if (err && err.code === 3) { msg = 'Se agotó el tiempo de espera. Intenta de nuevo.'; }
                setStatus(help, msg, 'text-danger');
                btn.disabled = false;
            });
        });

        // ---- Detección automática al cargar (silenciosa) ----
        // Solo actúa sobre campos vacíos: si el form viene de old() tras un
        // error de validación, o el usuario ya escribió, no se pisa nada.
        function autoDetect() {
            if (latEl.value.trim() !== '' || lngEl.value.trim() !== '') { return; }
            setStatus(help, 'Detectando tu ubicación para sugerir la locación…');
            locate().then(function (pos) {
                if (latEl.value.trim() !== '' || lngEl.value.trim() !== '') { return; } // el usuario se adelantó
                latEl.value = pos.lat.toFixed(7);
                lngEl.value = pos.lng.toFixed(7);
                updateMapsLink();
                root.dispatchEvent(new CustomEvent('geo:captured', { bubbles: true, detail: pos }));
                return reverseGeocode(pos.lat, pos.lng).then(function (address) {
                    if (!address) { return; }
                    if (addressEl && addressEl.value.trim() === '') { addressEl.value = address; }
                    if (suggestEl && suggestEl.value.trim() === '') {
                        suggestEl.value = address;
                        suggestEl.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    setStatus(help, 'Locación detectada por GPS y sugerida. Ajusta el nombre si el sitio tiene uno propio (ej. "Foro 5").', 'text-success');
                }).catch(function () {
                    setStatus(help, 'Ubicación detectada (sin internet para la dirección — escríbela a mano).', 'text-warning');
                });
            }).catch(function () {
                // Silencioso a propósito: sin permiso o sin señal, el form sigue 100% manual.
                setStatus(help, 'Usa el botón para capturar tu ubicación y llenar la dirección automáticamente, o escribe todo a mano.');
            });
        }

        if (autoMode) {
            // Si el permiso está denegado no intentes (evita ruido); si está
            // concedido o por preguntar, adelante — en PWA ya suele estar concedido.
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' }).then(function (st) {
                    if (st.state !== 'denied') { autoDetect(); }
                }).catch(autoDetect);
            } else {
                autoDetect();
            }
        }

        updateMapsLink();
    }

    /* ------------------------------------------------------------------ *
     *  6. Auto-init: bloques [data-hospital-finder]
     *     (lista de hospitales cercanos → selección → dirección + ETA)
     * ------------------------------------------------------------------ */
    function kindBadge(kind) {
        if (kind === 'private') { return '<span class="badge bg-success ms-2">Privado</span>'; }
        if (kind === 'public') { return '<span class="badge bg-secondary ms-2">Público</span>'; }
        return '';
    }

    function initHospitalFinder(root) {
        var btn     = root.querySelector('.js-hosp-search');
        var help    = root.querySelector('.js-hosp-help');
        var results = root.querySelector('.js-hosp-results');
        if (!btn || !results) { return; }

        var latEl  = document.querySelector(root.getAttribute('data-lat') || '[name=latitude]');
        var lngEl  = document.querySelector(root.getAttribute('data-lng') || '[name=longitude]');
        var nameEl = document.querySelector(root.getAttribute('data-name-target') || '[name=nearest_hospital]');
        var addrEl = document.querySelector(root.getAttribute('data-address-target') || '[name=hospital_address]');
        var etaEl  = document.querySelector(root.getAttribute('data-eta-target') || '[name=hospital_eta]');
        var phoneEl = root.getAttribute('data-phone-target') ? document.querySelector(root.getAttribute('data-phone-target')) : null;
        // Distancia numérica en km (opt-in): la franja "LOCACIÓN · DISTANCIA · TIEMPO" del póster
        // MEDEVAC necesita el número limpio, no el texto del ETA. Solo se llena con la ruta REAL de
        // OSRM; si cae al estimado por línea recta se deja vacío (no se guarda un km inventado).
        var distEl = root.getAttribute('data-distance-target') ? document.querySelector(root.getAttribute('data-distance-target')) : null;

        function hospStatus(text, cls) {
            if (!help) { return; }
            help.textContent = text;
            help.className = 'js-hosp-help small ' + (cls || 'text-muted') + ' d-block mt-1';
        }

        function selectHospital(h, item, origin) {
            // Marca visualmente la selección.
            var actives = results.querySelectorAll('.active');
            for (var i = 0; i < actives.length; i++) { actives[i].classList.remove('active'); }
            item.classList.add('active');

            if (nameEl) { nameEl.value = h.name; }
            if (phoneEl && h.phone) { phoneEl.value = h.phone; }

            // Dirección: la de OSM si existe; si no, reverse geocoding del hospital.
            if (addrEl) {
                if (h.address) {
                    addrEl.value = h.address;
                } else {
                    addrEl.value = '';
                    reverseGeocode(h.lat, h.lng).then(function (addr) {
                        if (addr) { addrEl.value = addr; }
                    }).catch(function () { /* queda manual */ });
                }
            }

            // ETA real por carretera (sin tráfico en vivo).
            if (etaEl && origin) {
                etaEl.value = 'Calculando…';
                driveEta(origin.lat, origin.lng, h.lat, h.lng).then(function (eta) {
                    var suffix = eta.estimated ? ' aprox.' : '';
                    etaEl.value = '~' + eta.minutes + ' min (' + eta.km + ' km)' + suffix;
                    // Solo la ruta REAL alimenta la columna de km; el estimado por línea recta no.
                    if (distEl) { distEl.value = eta.estimated ? '' : eta.km; }
                    if (eta.estimated) {
                        hospStatus('Hospital seleccionado: ' + h.name + ' — ETA aproximado (no se pudo calcular la ruta exacta). Verifícalo.', 'text-warning');
                    } else {
                        hospStatus('Hospital seleccionado: ' + h.name + ' — ETA ~' + eta.minutes + ' min en auto (sin tráfico).', 'text-success');
                    }
                }).catch(function () {
                    etaEl.value = '';
                    if (distEl) { distEl.value = ''; }
                    hospStatus('Hospital seleccionado. No se pudo calcular el ETA automático — escríbelo a mano.', 'text-warning');
                });
            } else {
                hospStatus('Hospital seleccionado: ' + h.name, 'text-success');
            }
        }

        btn.addEventListener('click', function () {
            var lat = parseFloat(latEl && latEl.value);
            var lng = parseFloat(lngEl && lngEl.value);
            if (isNaN(lat) || isNaN(lng)) {
                hospStatus('Primero captura la ubicación GPS (arriba) para poder buscar hospitales.', 'text-danger');
                return;
            }
            var origin = { lat: lat, lng: lng };
            btn.disabled = true;
            hospStatus('Buscando hospitales cercanos…');
            results.style.display = 'none';
            results.innerHTML = '';

            findHospitals(lat, lng).then(function (list) {
                btn.disabled = false;
                if (!list.length) {
                    hospStatus('No se encontraron hospitales en un radio de 45 km. Captura los datos a mano.', 'text-warning');
                    return;
                }
                hospStatus(list.length + ' hospitales encontrados — privados primero. Selecciona uno para llenar los campos y calcular el ETA.', 'text-success');
                list.forEach(function (h) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                    item.innerHTML =
                        '<span><strong>' + h.name.replace(/</g, '&lt;') + '</strong>' + kindBadge(h.kind) +
                        (h.address ? '<br><small class="text-muted">' + h.address.replace(/</g, '&lt;') + '</small>' : '') +
                        '</span>' +
                        '<span class="badge bg-light text-dark border ms-2">' + h.km.toFixed(1) + ' km</span>';
                    item.addEventListener('click', function () { selectHospital(h, item, origin); });
                    results.appendChild(item);
                });
                results.style.display = 'block';
            }).catch(function () {
                btn.disabled = false;
                hospStatus('No se pudo consultar el servicio de hospitales (¿sin internet?). Captura los datos a mano.', 'text-danger');
            });
        });
    }

    /* ------------------------------------------------------------------ *
     *  7. Scoutings cercanos (API interna): la "magia" de los reportes de
     *     seguridad. Dado un punto, pregunta al servidor qué LOCACIONES
     *     scouteadas hay a ≤500 m y devuelve sus NOMBRES (no direcciones),
     *     ya rankeadas por fechas de filmación + distancia.
     * ------------------------------------------------------------------ */
    function nearbyScoutings(lat, lng, radius) {
        var url = '/geo/scoutings-nearby?lat=' + encodeURIComponent(lat) +
                  '&lng=' + encodeURIComponent(lng) +
                  '&radius=' + encodeURIComponent(radius || 500);
        return fetchJson(url, { headers: { 'Accept': 'application/json' } }, 10000)
            .then(function (data) { return (data && data.matches) ? data.matches : []; });
    }

    function permissionAllows(cb) {
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function (st) {
                if (st.state !== 'denied') { cb(); }
            }).catch(cb);
        } else {
            cb();
        }
    }

    /* ------------------------------------------------------------------ *
     *  8. Modo SILENCIOSO [data-geo-silent] — reportes de seguridad.
     *     Sin botones ni coordenadas visibles: al abrir el form detecta la
     *     posición, la guarda en inputs hidden, y si hay un scouting a
     *     ≤500 m sugiere su NOMBRE en el campo Locación.
     *     Estructura esperada dentro del wrapper: inputs hidden
     *     [name=latitude], [name=longitude], [name=gps_address].
     *     Atributos: data-suggest-target (campo Locación),
     *                data-note-target (small donde se explica la sugerencia).
     * ------------------------------------------------------------------ */
    function initGeoSilent(root) {
        var latEl  = root.querySelector('input[name=latitude]');
        var lngEl  = root.querySelector('input[name=longitude]');
        var addrEl = root.querySelector('input[name=gps_address]');
        if (!latEl || !lngEl) { return; }

        var suggestEl = document.querySelector(root.getAttribute('data-suggest-target') || '');
        var noteEl    = document.querySelector(root.getAttribute('data-note-target') || '');

        function note(text, cls) {
            if (!noteEl) { return; }
            noteEl.textContent = text;
            noteEl.className = (cls || 'text-muted') + ' d-block';
        }

        function run() {
            if (latEl.value.trim() !== '') { return; } // ya hay coords (old() tras error de validación)
            locate().then(function (pos) {
                latEl.value = pos.lat.toFixed(7);
                lngEl.value = pos.lng.toFixed(7);
                root.dispatchEvent(new CustomEvent('geo:captured', { bubbles: true, detail: pos }));

                // Dirección para el registro (columna gps_address) — invisible para el usuario.
                if (addrEl && addrEl.value.trim() === '') {
                    reverseGeocode(pos.lat, pos.lng).then(function (a) {
                        if (addrEl.value.trim() === '') { addrEl.value = a; }
                    }).catch(function () { /* sin red: queda vacío, no pasa nada */ });
                }

                // La magia: ¿en qué locación scouteada estamos parados?
                nearbyScoutings(pos.lat, pos.lng, 500).then(function (matches) {
                    if (!matches.length) { return; } // sin coincidencias: silencio total
                    var best = matches[0];
                    if (suggestEl && suggestEl.value.trim() === '') {
                        suggestEl.value = best.location_name;
                        suggestEl.dispatchEvent(new Event('input', { bubbles: true }));
                        note('📍 Detectamos que estás en: ' + best.location_name +
                             ' (a ' + best.distance_m + ' m del punto scouteado). Edítalo si no es correcto.', 'text-success');
                    } else if (suggestEl && noteEl) {
                        // El usuario ya escribió algo: solo informa, con opción de usar la detectada.
                        note('📍 Según tu ubicación podrías estar en: ' + best.location_name +
                             ' (a ' + best.distance_m + ' m) — ', 'text-muted');
                        var use = document.createElement('a');
                        use.href = '#';
                        use.textContent = 'usar este nombre';
                        use.addEventListener('click', function (e) {
                            e.preventDefault();
                            suggestEl.value = best.location_name;
                            note('Locación actualizada a: ' + best.location_name, 'text-success');
                        });
                        noteEl.appendChild(use);
                    }
                }).catch(function () { /* sin red o sin sesión: silencio */ });
            }).catch(function () { /* sin permiso o sin señal: el form sigue 100% manual */ });
        }

        permissionAllows(run);
    }

    /* ------------------------------------------------------------------ *
     *  9. Modo DIRECCIÓN [data-geo-address] — formulario de Scouting.
     *     El campo Dirección manda: botón discreto 📍 lo llena desde el GPS,
     *     y al escribir una dirección a mano se geocodifica hacia adelante
     *     (Nominatim /search) guardando lat/lng en hidden por detrás.
     *     Estructura esperada: input de dirección (.js-geo-addr), botón
     *     (.js-geo-locate), status (.js-geo-status), hidden lat/lng.
     * ------------------------------------------------------------------ */
    function forwardGeocode(query) {
        var url = NOMINATIM.replace('/reverse', '/search') +
                  '?format=jsonv2&limit=1&accept-language=es&q=' + encodeURIComponent(query);
        return fetchJson(url, null, 10000).then(function (list) {
            if (!list || !list.length) { throw new Error('no-results'); }
            return { lat: parseFloat(list[0].lat), lng: parseFloat(list[0].lon), label: list[0].display_name };
        });
    }

    function initGeoAddress(root) {
        var addrEl = root.querySelector('.js-geo-addr');
        var latEl  = root.querySelector('input[name=latitude]');
        var lngEl  = root.querySelector('input[name=longitude]');
        var btn    = root.querySelector('.js-geo-locate');
        var status = root.querySelector('.js-geo-status');
        if (!addrEl || !latEl || !lngEl) { return; }

        function say(text, cls) {
            if (!status) { return; }
            status.textContent = text;
            status.className = 'js-geo-status small ' + (cls || 'text-muted') + ' d-block mt-1';
        }

        function setCoords(lat, lng) {
            latEl.value = lat.toFixed(7);
            lngEl.value = lng.toFixed(7);
            root.dispatchEvent(new CustomEvent('geo:captured', { bubbles: true, detail: { lat: lat, lng: lng } }));
        }

        // 📍 Botón: mi ubicación → coordenadas ocultas + dirección al campo.
        if (btn) {
            btn.addEventListener('click', function () {
                say('Obteniendo tu ubicación…');
                btn.disabled = true;
                locate().then(function (pos) {
                    setCoords(pos.lat, pos.lng);
                    btn.disabled = false;
                    say('Ubicación guardada. Buscando dirección…', 'text-success');
                    return reverseGeocode(pos.lat, pos.lng).then(function (a) {
                        if (addrEl.value.trim() === '') {
                            addrEl.value = a;
                            say('Dirección y coordenadas capturadas ✓', 'text-success');
                        } else {
                            say('Coordenadas guardadas ✓ — dirección detectada: ' + a, 'text-success');
                        }
                    }).catch(function () {
                        say('Coordenadas guardadas ✓ (sin internet para la dirección — escríbela a mano).', 'text-warning');
                    });
                }).catch(function (err) {
                    btn.disabled = false;
                    var msg = (err && err.code === 1)
                        ? 'Permiso de ubicación denegado. Escribe la dirección: las coordenadas se buscarán solas.'
                        : 'No se pudo obtener tu ubicación. Escribe la dirección: las coordenadas se buscarán solas.';
                    say(msg, 'text-warning');
                });
            });
        }

        // A la inversa: dirección escrita a mano → coordenadas por detrás (al salir del campo).
        var lastGeocoded = addrEl.value.trim();
        function geocodeTyped() {
            var q = addrEl.value.trim();
            if (q === '' || q === lastGeocoded || q.length < 8) { return; }
            lastGeocoded = q;
            say('Buscando coordenadas de la dirección…');
            forwardGeocode(q).then(function (r) {
                setCoords(r.lat, r.lng);
                say('Coordenadas guardadas por detrás ✓ (' + r.lat.toFixed(5) + ', ' + r.lng.toFixed(5) + ')', 'text-success');
            }).catch(function () {
                say('No se encontraron coordenadas para esa dirección. Puedes guardar así y completarlo después desde Editar.', 'text-warning');
            });
        }
        addrEl.addEventListener('change', geocodeTyped);
        addrEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); geocodeTyped(); }
        });

        // Auto-captura al cargar (GPS en segundo plano, como el modo silencioso): si aún no
        // hay dirección ni coordenadas, intenta llenarlas solo. Silencioso si el permiso está
        // denegado, no hay señal o no hay red → el campo se queda vacío y el form sigue manual.
        function autoFill() {
            if (addrEl.value.trim() !== '' || latEl.value.trim() !== '') { return; } // ya hay algo (old()/edición)
            say('Ubicándote…');
            locate().then(function (pos) {
                if (latEl.value.trim() === '') { setCoords(pos.lat, pos.lng); }
                reverseGeocode(pos.lat, pos.lng).then(function (a) {
                    if (a && addrEl.value.trim() === '') {
                        addrEl.value = a;
                        lastGeocoded = a.trim();
                        say('Dirección tomada de tu ubicación ✓ — edítala si no es correcta.', 'text-success');
                    } else {
                        say('Escribe la dirección (las coordenadas se guardan solas) o toca 📍 para usar tu ubicación.');
                    }
                }).catch(function () {
                    // Coordenadas sí, dirección no (sin red): que la escriba a mano.
                    say('Escribe la dirección (las coordenadas se guardan solas) o toca 📍 para usar tu ubicación.');
                });
            }).catch(function () {
                // Sin permiso / sin señal: se queda vacío, form 100% manual.
                say('Escribe la dirección (las coordenadas se guardan solas) o toca 📍 para usar tu ubicación.');
            });
        }
        if (root.hasAttribute('data-geo-auto')) { permissionAllows(autoFill); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var caps = document.querySelectorAll('[data-geo-capture]');
        for (var i = 0; i < caps.length; i++) { initGeoCapture(caps[i]); }
        var finders = document.querySelectorAll('[data-hospital-finder]');
        for (var j = 0; j < finders.length; j++) { initHospitalFinder(finders[j]); }
        var silents = document.querySelectorAll('[data-geo-silent]');
        for (var k = 0; k < silents.length; k++) { initGeoSilent(silents[k]); }
        var addrs = document.querySelectorAll('[data-geo-address]');
        for (var m = 0; m < addrs.length; m++) { initGeoAddress(addrs[m]); }
    });

    // API pública por si otra vista necesita usar las funciones directamente.
    window.CrewGeo = {
        locate: locate,
        reverseGeocode: reverseGeocode,
        findHospitals: findHospitals,
        driveEta: driveEta
    };
})();
