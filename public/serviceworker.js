var staticCacheName = "pwa-v" + new Date().getTime();
// (captura fluida · Paso A3) Cache de RUNTIME para que las FORMAS DE CAPTURA abran sin red.
// Nombre FIJO (no se borra en cada activate) → sobrevive a las actualizaciones del SW.
var runtimeCache = "pwa-runtime-v1";
var filesToCache = [
    '/offline',
    '/css/form-register.css',
    '/js/app.js',
    // (CSP fase 3) Librerías ahora servidas desde 'self' → el SW las precachea para que las
    // FORMAS DE CAPTURA offline tengan su JS/CSS sin depender de que un CDN haya cargado antes.
    // Rutas versionadas (cache-bust): al subir de versión cambia el nombre y se recachea solo.
    '/js/vendor/jquery-3.7.1.min.js',
    '/js/vendor/bootstrap-5.1.3.bundle.min.js',
    '/js/vendor/chart-4.5.1.umd.min.js',
    '/js/vendor/chartjs-plugin-trendline-3.2.12.min.js',
    '/js/vendor/cropper-1.5.6.min.js',
    '/vendor/bootstrap/css/bootstrap-5.1.3.min.css',
    '/vendor/fontawesome/css/all-6.7.2.min.css',
    '/vendor/cropper/cropper-1.5.6.min.css',
    '/css/inicio-tw.css',
    '/vendor/fontawesome/webfonts/fa-solid-900.woff2',
    '/vendor/fontawesome/webfonts/fa-regular-400.woff2',
    '/vendor/fontawesome/webfonts/fa-brands-400.woff2',
    // (CSP · autoalojar Google Fonts) La tipografía de la UI (Poppins) + el póster (Roboto Condensed)
    // servidas desde 'self' → las formas offline conservan su fuente real sin depender de la red.
    // Subsets latin + latin-ext (los acentos del español viven en 'latin'). Las fuentes de firma NO
    // se precachean: la ceremonia rara vez es offline y degradan a fuente del sistema sin romper.
    '/fonts/ui/ui-fonts.css',
    '/fonts/reports/poppins-400-normal-latin.woff2',
    '/fonts/reports/poppins-400-normal-latin-ext.woff2',
    '/fonts/ui/poppins-500-normal-latin.woff2',
    '/fonts/ui/poppins-500-normal-latin-ext.woff2',
    '/fonts/reports/poppins-600-normal-latin.woff2',
    '/fonts/reports/poppins-600-normal-latin-ext.woff2',
    '/fonts/reports/poppins-700-normal-latin.woff2',
    '/fonts/reports/poppins-700-normal-latin-ext.woff2',
    '/fonts/reports/poppins-800-normal-latin.woff2',
    '/fonts/reports/poppins-800-normal-latin-ext.woff2',
    '/fonts/ui/roboto-condensed-900-normal-latin.woff2',
    '/fonts/ui/roboto-condensed-900-normal-latin-ext.woff2',
    '/images/icons/icon-72x72.png',
    '/images/icons/icon-96x96.png',
    '/images/icons/icon-128x128.png',
    '/images/icons/icon-144x144.png',
    '/images/icons/icon-152x152.png',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-384x384.png',
    '/images/icons/icon-512x512.png',
];

// Cache on install
self.addEventListener("install", event => {
    this.skipWaiting();
    event.waitUntil(
        caches.open(staticCacheName)
            .then(cache => {
                return cache.addAll(filesToCache);
            })
    )
});

// Clear cache on activate — PERO conserva el runtimeCache (nombre fijo) además del static actual.
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => (cacheName.startsWith("pwa-")))
                    .filter(cacheName => (cacheName !== staticCacheName && cacheName !== runtimeCache))
                    .map(cacheName => caches.delete(cacheName))
            );
        })
    );
});

// (captura fluida · Paso A3) Solo las FORMAS DE CAPTURA (rutas .../create|crear) se guardan en
// runtime con estrategia NETWORK-FIRST: online siempre trae la versión fresca (token CSRF al día);
// solo si NO hay red se sirve la copia cacheada, para poder ABRIR la forma y retomar el borrador
// (que vive en IndexedDB). Se limita a estas rutas a propósito: son formas EN BLANCO (no filtran
// datos de nadie), a diferencia de un dashboard con información. Todo lo demás conserva el
// comportamiento previo (cache-first de los estáticos precacheados; fallback a /offline).
function isCaptureForm(req) {
    if (req.method !== 'GET') { return false; }
    var url;
    try { url = new URL(req.url); } catch (e) { return false; }
    if (url.origin !== self.location.origin) { return false; }
    var isNav = req.mode === 'navigate' || (req.headers.get('accept') || '').indexOf('text/html') !== -1;
    return isNav && /(create|crear)(\/|$)/i.test(url.pathname);
}

self.addEventListener("fetch", event => {
    var req = event.request;

    if (isCaptureForm(req)) {
        event.respondWith(
            fetch(req)
                .then(function (res) {
                    var copy = res.clone();
                    caches.open(runtimeCache).then(function (c) { c.put(req, copy); });
                    return res;
                })
                .catch(function () {
                    return caches.match(req).then(function (r) { return r || caches.match('offline'); });
                })
        );
        return;
    }

    // Comportamiento previo (cache-first de estáticos; red si no está en cache; /offline si falla).
    event.respondWith(
        caches.match(req)
            .then(response => {
                return response || fetch(req);
            })
            .catch(() => {
                return caches.match('offline');
            })
    );
});
